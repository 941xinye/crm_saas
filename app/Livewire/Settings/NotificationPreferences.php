<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class NotificationPreferences extends Component
{
    /** 可开关的通知类型（键与 ReminderService / Notification data.type 对应） */
    public const TYPES = [
        'activity_due' => '活动到期提醒',
        'opp_close' => '商机成交日提醒',
        'opp_no_activity' => '商机长期无活动提醒',
        'opp_stalled' => '商机停滞提醒',
        'trial_ending' => '试用期到期提醒',
        'score_drop' => '商机评分骤降提醒',
    ];

    /** @var array<string, bool> */
    public array $prefs = [];

    public function mount(): void
    {
        $saved = auth()->user()->notification_prefs ?? [];

        foreach (self::TYPES as $key => $label) {
            $this->prefs[$key] = (bool) ($saved[$key] ?? true);
        }
    }

    public function save(): void
    {
        $this->validate([
            'prefs' => ['array'],
            'prefs.*' => ['boolean'],
        ]);

        auth()->user()->update(['notification_prefs' => $this->prefs]);

        session()->flash('success', '通知偏好已更新');
    }

    public function render()
    {
        return view('livewire.settings.notification-preferences', [
            'types' => self::TYPES,
        ])->layout('layouts.app', ['title' => '通知偏好']);
    }
}
