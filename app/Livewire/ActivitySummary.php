<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Services\ActivitySummaryService;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * 详情页 AI 活动摘要卡片（Phase 6 / P2）。
 *
 * 点击「生成摘要」调 ActivitySummaryService（LLM 未配置时静默提示不可用）。
 * 实体类型白名单校验，find 受 TenantScope 保护（租户外 ID 查不到）。
 */
class ActivitySummary extends Component
{
    public string $entityType = '';

    public int $entityId = 0;

    public ?string $summary = null;

    public bool $unavailable = false;

    public function mount(string $entityType, int $entityId): void
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
    }

    public function generate(): void
    {
        $model = $this->resolveModel();

        if ($model === null) {
            $this->unavailable = true;
            return;
        }

        $this->summary = app(ActivitySummaryService::class)->summarize($model);
        $this->unavailable = ($this->summary === null || $this->summary === '');
    }

    private function resolveModel(): ?Model
    {
        if (! in_array($this->entityType, ActivitySummaryService::WHITELIST, true)) {
            return null;
        }

        return $this->entityType::find($this->entityId);
    }

    public function render()
    {
        return view('livewire.activity-summary');
    }
}
