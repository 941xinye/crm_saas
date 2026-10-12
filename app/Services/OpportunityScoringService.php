<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\Stage;

/**
 * 智能商机评分：基于多维度加权打分，帮助销售团队识别高/低优先级商机。
 *
 * 评分维度（权重可配置）：
 * - value_score：金额分，越高越高（value / max(所有商机value) × 100）
 * - stage_score：阶段分，越靠后越高（won/lost 阶段设基准分）
 * - velocity_score：活跃度分（更新频率 + 活动数量）
 * - urgency_score：紧迫感分（expected_close_date 临近）
 * - stall_score：停滞惩罚分（超过 14 天无活动扣分）
 */
class OpportunityScoringService
{
    /** 权重配置（可在 config/crm.php 覆盖） */
    private array $weights;

    public function __construct()
    {
        $this->weights = config('crm.scoring', [
            'value'    => 25,
            'stage'    => 25,
            'velocity' => 20,
            'urgency'  => 15,
            'stall'    => 15,
        ]);
    }

    /**
     * 计算单个商机评分（0-100）。
     */
    public function score(Opportunity $opportunity): int
    {
        // 租户内归一化（TenantScope 自动过滤），与 scoreBatch 保持一致
        $maxValue = Opportunity::query()->max('value') ?: 1;
        $stageScore = $this->stageScore($opportunity);
        $velocityScore = $this->velocityScore($opportunity);
        $urgencyScore = $this->urgencyScore($opportunity);
        $stallScore = $this->stallScore($opportunity);
        $valueScore = (min(100, $opportunity->value / $maxValue * 100)) * ($this->weights['value'] / 100);

        $total = $valueScore + $stageScore + $velocityScore + $urgencyScore + $stallScore;

        return (int) max(0, min(100, round($total)));
    }

    /**
     * 批量评分（带租户内金额归一化）。
     *
     * @return array<int, int>  [opportunity_id => score]
     */
    public function scoreBatch(iterable $opportunities): array
    {
        $maxValue = Opportunity::query()->max('value') ?: 1;
        $tenantWonStageIds = Stage::won()->pluck('id')->toArray();
        $tenantLostStageIds = Stage::lost()->pluck('id')->toArray();
        $result = [];

        foreach ($opportunities as $opp) {
            $valueScore = min(100, $opp->value / $maxValue * 100) * ($this->weights['value'] / 100);
            $stageScore = $this->stageScoreRaw($opp->stage, $tenantWonStageIds, $tenantLostStageIds);
            $velocityScore = $this->velocityScore($opp);
            $urgencyScore = $this->urgencyScore($opp);
            $stallScore = $this->stallScore($opp);

            $result[$opp->id] = (int) max(0, min(100, round($valueScore + $stageScore + $velocityScore + $urgencyScore + $stallScore)));
        }

        return $result;
    }

    private function stageScore(Opportunity $opp): float
    {
        return $this->stageScoreRaw($opp->stage, [], []);
    }

    private function stageScoreRaw(?Stage $stage, array $wonIds, array $lostIds): float
    {
        if (! $stage) {
            return 0;
        }

        if (in_array($stage->id, $wonIds, true) || $stage->is_won) {
            return 100 * ($this->weights['stage'] / 100);
        }
        if (in_array($stage->id, $lostIds, true) || $stage->is_lost) {
            return 20 * ($this->weights['stage'] / 100);
        }

        // 排序位置归一化：0=最早 → 60 分，最后阶段 → 90 分
        $stages = Stage::ordered()->pluck('id')->toArray();
        $pos = array_search($stage->id, $stages, true);
        if ($pos === false || count($stages) <= 1) {
            $normalized = 50;
        } else {
            $normalized = 50 + ($pos / (count($stages) - 1)) * 40;
        }

        return $normalized * ($this->weights['stage'] / 100);
    }

    private function velocityScore(Opportunity $opp): float
    {
        // 活动数量 + 最近更新新鲜度
        $activityCount = $opp->activities()->count();
        $activityBonus = min(30, $activityCount * 5); // 5 分/活动，封顶 30

        $daysSinceUpdate = $opp->updated_at?->diffInDays(now()) ?? 30;
        $freshnessBonus = max(0, 30 - $daysSinceUpdate); // 每天扣 1 分

        $score = ($activityBonus + $freshnessBonus) / 60 * 100;

        return min(100, $score) * ($this->weights['velocity'] / 100);
    }

    private function urgencyScore(Opportunity $opp): float
    {
        if (! $opp->expected_close_date) {
            return 50 * ($this->weights['urgency'] / 100);
        }

        $daysLeft = $opp->expected_close_date->diffInDays(now(), false);

        return match (true) {
            $daysLeft < 0 => 100,     // 已过期，最紧急
            $daysLeft <= 3 => 90,
            $daysLeft <= 7 => 75,
            $daysLeft <= 30 => 50,
            default => 25,
        } * ($this->weights['urgency'] / 100);
    }

    private function stallScore(Opportunity $opp): float
    {
        $daysSince = max(
            $opp->updated_at?->diffInDays(now()) ?? 0,
            $opp->activities()->latest('created_at')->value('created_at')?->diffInDays(now()) ?? 0,
        );

        if ($daysSince >= 30) {
            return 0;  // 严重停滞
        }
        if ($daysSince >= 14) {
            return 30;
        }
        if ($daysSince >= 7) {
            return 60;
        }
        if ($daysSince >= 3) {
            return 85;
        }

        return 100 * ($this->weights['stall'] / 100);
    }
}
