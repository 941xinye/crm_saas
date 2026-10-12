<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Services\LLM\LLMProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * AI 跟进邮件建议：根据商机上下文生成邮件草稿，帮助销售快速响应。
 *
 * 安全保障：
 * - LLMProvider::configured() 返回 null 时静默降级
 * - RateLimiter 限流（默认 10 次/分钟 per-user）
 * - prompt 截断避免超长 token（商机 title/activity 列表取前 5 条）
 */
class FollowUpSuggestionService
{
    public function __construct(
        private readonly LLMProvider $llm,
    ) {
    }

    /**
     * 为商机生成跟进邮件建议。
     *
     * @return array{subject: string, body: string, fallback: bool}|null  失败返回 null
     */
    public function suggest(Opportunity $opp, ?int $userId = null): ?array
    {
        if (! $this->llm->configured()) {
            return null;
        }

        $userId = $userId ?? auth()->id();
        $rateKey = 'ai-suggest-' . ($userId ?: 'anon');
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            logger()->warning('AI 跟进建议触发限流', ['user' => $userId]);

            return null;
        }
        RateLimiter::hit($rateKey, 60);

        $prompt = $this->buildPrompt($opp);
        $response = $this->llm->generate($prompt, systemRole: 'You are a helpful CRM assistant that writes concise, professional sales follow-up emails in plain text. Output ONLY the email subject line on the first line, a blank line, then the email body. Do not use markdown.', maxTokens: 500);

        if (! $response) {
            return null;
        }

        return $this->parseResponse($response);
    }

    private function buildPrompt(Opportunity $opp): string
    {
        $activityList = $opp->activities()->latest('created_at')->limit(5)->get()
            ->map(fn ($a) => sprintf("- [%s] %s: %s", $a->created_at?->format('Y-m-d'), $a->type ?? 'note', Str::limit($a->subject . ' — ' . ($a->notes ?? ''), 120)))
            ->implode("\n") ?: '- 无历史活动';

        $contactName = $opp->contact?->full_name ?? '未指定联系人';
        $companyName = $opp->company?->name ?? '未指定公司';
        $stageName = $opp->stage?->name ?? '未指定阶段';
        $value = $opp->value ? ('$' . number_format($opp->value, 2)) : '未指定金额';
        $closeDate = $opp->expected_close_date?->format('Y-m-d') ?? '未指定';

        return <<<PROMPT
为以下商机生成一封跟进邮件：

商机标题：{$opp->title}
公司：{$companyName}
联系人：{$contactName}
当前阶段：{$stageName}
金额：{$value}
预计成交日：{$closeDate}

最近活动：
{$activityList}

邮件要求：
- 语气专业友好，200-300 字
- 根据商机阶段选择合适的跟进角度（如已到末尾阶段强调紧迫感，早期阶段安排初步沟通）
- 包含明确的下一步行动建议
PROMPT;
    }

    /**
     * 解析模型输出：第一行 → subject，其余 → body。
     */
    private function parseResponse(string $response): array
    {
        $response = trim($response);
        $lines = explode("\n", $response, 2);

        return [
            'subject' => trim($lines[0]),
            'body' => trim($lines[1] ?? ''),
            'fallback' => false,
        ];
    }
}
