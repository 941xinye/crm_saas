<?php

namespace App\Livewire\Opportunities;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Services\FollowUpSuggestionService;
use App\Services\LLM\LLMProvider;
use App\Services\PlanService;
use App\Services\Tenancy;
use Livewire\Component;

class Show extends Component
{
    public Opportunity $opportunity;

    /** AI 生成的跟进邮件草稿 */
    public ?array $suggestion = null;

    /** AI 不可用/失败时的提示（null = 无错误） */
    public ?string $aiMessage = null;

    public bool $generating = false;

    public function mount(Opportunity $opportunity): void
    {
        // sales 仅可查看自己负责的商机
        $this->authorize('view', $opportunity);
        $this->opportunity = $opportunity;
    }

    /**
     * AI 生成跟进邮件建议（未配置 LLM Key 或无 feature 时优雅降级提示）。
     */
    public function generateFollowUp(FollowUpSuggestionService $service, LLMProvider $llm, PlanService $plans, Tenancy $tenancy): void
    {
        $this->authorize('view', $this->opportunity);

        if (! $plans->hasFeature($tenancy->current(), 'ai_followup')) {
            $this->aiMessage = $plans->upgradeMessage('ai_followup');

            return;
        }

        if (! $llm->configured()) {
            $this->aiMessage = 'AI 功能未启用：请在 .env 中配置 LLM_API_KEY 后重试。';

            return;
        }

        $this->generating = true;
        $this->aiMessage = null;

        try {
            $this->suggestion = $service->suggest($this->opportunity);

            if ($this->suggestion === null) {
                $this->aiMessage = 'AI 生成失败：请稍后重试（可能触发频率限制或服务暂时不可用）。';
            }
        } finally {
            $this->generating = false;
        }
    }

    /**
     * 将 AI 草稿保存为跟进活动（type=email note）。
     */
    public function saveSuggestionAsActivity(): void
    {
        $this->authorize('view', $this->opportunity);

        if (empty($this->suggestion['body'])) {
            return;
        }

        Activity::create([
            'activitable_type' => Opportunity::class,
            'activitable_id' => $this->opportunity->id,
            'user_id' => auth()->id(),
            'type' => 'email',
            'subject' => $this->suggestion['subject'] ?: 'AI 跟进邮件草稿',
            'notes' => $this->suggestion['body'],
        ]);

        // 保存后清空草稿并刷新时间线
        $this->suggestion = null;
        $this->aiMessage = '草稿已保存到跟进时间线。';
    }

    public function render()
    {
        $activities = $this->opportunity->activities()->with('user')->latest()->get();
        return view('livewire.opportunities.show', [
            'activities' => $activities,
        ])->layout('layouts.app', ['title' => $this->opportunity->title]);
    }
}
