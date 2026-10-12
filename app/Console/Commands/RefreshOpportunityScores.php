<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OpportunityScoreDropNotification;
use App\Services\OpportunityScoringService;
use App\Services\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshOpportunityScores extends Command
{
    protected $signature = 'crm:refresh-scores {--tenant= : 仅刷新指定租户} {--all : 刷新所有租户（跳过非租户记录）}';

    protected $description = '批量刷新商机智能评分（score + scored_at）';

    public function handle(OpportunityScoringService $scoring): int
    {
        $tenantSlug = $this->option('tenant');
        $all = $this->option('all');

        if ($tenantSlug) {
            $tenants = Tenant::where('slug', $tenantSlug)->get();
        } elseif ($all) {
            $tenants = Tenant::all();
        } else {
            $this->error('请指定 --tenant=xxx 或 --all');

            return self::FAILURE;
        }

        $total = 0;
        $drops = 0;
        $threshold = (int) config('crm.scoring.drop_threshold', 15);
        $tenancy = app(Tenancy::class);

        foreach ($tenants as $tenant) {
            $tenancy->setTenant($tenant);

            $ids = \App\Models\Opportunity::query()->pluck('id');
            if ($ids->isEmpty()) {
                $this->info("  {$tenant->name}: 无商机");
                continue;
            }

            $scores = $scoring->scoreBatch(\App\Models\Opportunity::query()->whereIn('id', $ids)->get());

            foreach ($scores as $id => $score) {
                $old = DB::table('opportunities')->where('id', $id)->value('score');

                DB::table('opportunities')->where('id', $id)->update([
                    'score' => $score,
                    'scored_at' => now(),
                ]);

                // 评分骤降提醒：有历史评分且降幅超阈值（冷却期去重 + 用户可静音）
                if ($old !== null && $score <= $old - $threshold) {
                    $opportunity = \App\Models\Opportunity::with('owner')->find($id);

                    if ($opportunity?->owner
                        && $opportunity->owner->wantsNotification('score_drop')
                        && ! $this->alreadyNotified($opportunity->owner, $id)) {
                        $opportunity->owner->notify(new OpportunityScoreDropNotification(
                            $id,
                            $opportunity->title,
                            (int) $old,
                            (int) $score,
                        ));
                        $drops++;
                    }
                }
            }

            $this->info("  {$tenant->name}: 已刷新 {$ids->count()} 条商机" . ($drops > 0 ? "，{$drops} 条评分骤降已通知负责人" : ''));
            $total += $ids->count();
        }

        $this->info("总计刷新 {$total} 条商机评分");

        return self::SUCCESS;
    }

    /**
     * 同一商机骤降提醒的冷却期去重（与 ReminderService 口径一致，默认 24h）。
     */
    private function alreadyNotified(User $user, int $opportunityId): bool
    {
        return $user->notifications()
            ->where('type', OpportunityScoreDropNotification::class)
            ->where('created_at', '>=', now()->subHours((int) config('crm.reminder.cooldown_hours', 24)))
            ->where('data->opportunity_id', $opportunityId)
            ->exists();
    }
}
