<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Services\LLM\LLMProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * AI 活动时间线摘要（Phase 6 / P2）：
 * 为公司 / 联系人 / 商机详情页的时间线生成 ≤5 条要点的中文摘要。
 *
 * 保障（对齐 FollowUpSuggestionService）：
 * - LLMProvider::configured() 为 null 时静默降级返回 null
 * - RateLimiter 限流（默认 10 次/分钟 per-user）
 * - prompt 截断（最近 20 条活动、单条 150 字符）
 * - 缓存：按「实体 + 最新活动 ID」作 key，新活动产生即自然失效（1 小时兜底 TTL）；
 *   缓存命中不消耗限流额度
 */
class ActivitySummaryService
{
    /** 支持摘要的实体白名单 */
    public const WHITELIST = [
        Company::class,
        Contact::class,
        Opportunity::class,
    ];

    public function __construct(private readonly LLMProvider $llm)
    {
    }

    /**
     * 生成摘要文本；未配置 / 无活动 / 限流 / 生成失败返回 null。
     */
    public function summarize(Model $model, ?int $userId = null): ?string
    {
        if (! $this->llm->configured() || ! in_array($model::class, self::WHITELIST, true)) {
            return null;
        }

        $activities = $model->activities()->orderBy('created_at', 'desc')->limit(20)->get();
        if ($activities->isEmpty()) {
            return null;
        }

        $cacheKey = $this->cacheKey($model, $activities);

        // 缓存命中直接返回（不占限流额度）
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $userId = $userId ?? auth()->id();
        $rateKey = 'ai-summary-' . ($userId ?: 'anon');
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            logger()->warning('AI 活动摘要触发限流', ['user' => $userId, 'entity' => $model::class . ':' . $model->id]);

            return null;
        }
        RateLimiter::hit($rateKey, 60);

        $response = $this->llm->generate(
            $this->buildPrompt($model, $activities),
            systemRole: 'You are a helpful CRM assistant that summarizes customer follow-up history. Reply in Chinese, plain text only (no markdown). Output 3-5 short bullet lines, each starting with "- ".',
            maxTokens: 400,
        );

        if ($response === null) {
            return null;
        }

        // 失效兜底：缓存 1 小时；key 含最新活动 ID，新增活动自动换 key
        Cache::put($cacheKey, trim($response), now()->addHour());

        return trim($response);
    }

    private function cacheKey(Model $model, Collection $activities): string
    {
        $signature = (string) ($activities->max('id') ?? 0);

        return sprintf('crm:ai-summary:%s:%d:%s', class_basename($model), $model->id, $signature);
    }

    private function buildPrompt(Model $model, Collection $activities): string
    {
        $lines = $activities
            ->map(fn ($a) => sprintf('- [%s] %s: %s',
                $a->created_at?->format('Y-m-d'),
                $a->type ?? 'note',
                Str::limit($a->subject . ' — ' . ($a->notes ?? ''), 150),
            ))
            ->implode("\n");

        $entityName = $model->name ?? $model->title ?? ('#' . $model->id);

        return <<<PROMPT
请用不超过 5 条要点总结以下 CRM 记录「{$entityName}」的跟进历史，突出关键进展、风险点与建议的下一步行动：

最近活动（最多 20 条，新→旧）：
{$lines}
PROMPT;
    }
}
