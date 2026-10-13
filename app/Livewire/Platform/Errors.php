<?php

namespace App\Livewire\Platform;

use App\Models\ErrorEvent;
use Livewire\Component;

/**
 * 平台错误监控面板（平台超管专用）：
 * 5xx 异常按签名聚合的列表与统计，数据来自 ErrorReporter 写入的
 * error_events 表（与群机器人告警相互独立，见 docs/monitoring.md）。
 */
class Errors extends Component
{
    public function render()
    {
        return view('livewire.platform.errors', [
            'events' => ErrorEvent::query()
                ->orderByDesc('last_seen_at')
                ->limit(100)
                ->get(),
            'totalSignatures' => ErrorEvent::query()->count(),
            'totalOccurrences' => (int) ErrorEvent::query()->sum('count'),
            'recentCount' => ErrorEvent::query()
                ->where('last_seen_at', '>=', now()->subDay())
                ->count(),
        ])->layout('layouts.app', ['title' => __('platform_errors.title')]);
    }
}
