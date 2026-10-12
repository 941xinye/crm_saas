<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class NotificationPreferences extends Component
{
    /** 可开关的通知类型（键与 ReminderService / Notification data.type 对应，值为翻译 key） */
    public const TYPES = [
        'activity_due' => 'settings_notifpref.type_activity_due',
        'opp_close' => 'settings_notifpref.type_opp_close',
        'opp_no_activity' => 'settings_notifpref.type_opp_no_activity',
        'opp_stalled' => 'settings_notifpref.type_opp_stalled',
        'trial_ending' => 'settings_notifpref.type_trial_ending',
        'score_drop' => 'settings_notifpref.type_score_drop',
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

        session()->flash('success', __('settings_notifpref.flash_saved'));
    }

    public function render()
    {
        return view('livewire.settings.notification-preferences', [
            'types' => array_map(fn (string $translationKey): string => __($translationKey), self::TYPES),
        ])->layout('layouts.app', ['title' => __('settings_notifpref.title')]);
    }
}
