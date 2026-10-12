<?php

namespace App\Livewire\Settings;

use App\Services\Tenancy;
use Illuminate\Support\Str;
use Livewire\Component;

class TenantProfile extends Component
{
    public string $name = '';
    public string $language = 'zh-CN';
    public string $currency = 'CNY';

    public function mount(): void
    {
        $tenant = app(Tenancy::class)->current();
        if ($tenant) {
            $this->name = $tenant->name;
            $this->language = $tenant->settings['language'] ?? 'zh-CN';
            $this->currency = $tenant->settings['currency'] ?? 'CNY';
        }
    }

    public function save(): void
    {
        $tenant = app(Tenancy::class)->current();
        if ($tenant) {
            $this->validate([
                'name' => ['required', 'string', 'max:255'],
            ]);
            $tenant->update([
                'name' => $this->name,
                'settings' => [
                    ...($tenant->settings ?? []),
                    'language' => $this->language,
                    'currency' => $this->currency,
                ],
            ]);
            session()->flash('success', __('settings_profile.flash_saved'));
        }
    }

    /**
     * 生成公共线索表单链接（web-to-lead）。仅租户管理员可操作。
     */
    public function generateLeadFormKey(): void
    {
        $tenant = app(Tenancy::class)->current();
        if (! $tenant || auth()->user()?->role !== 'admin') {
            session()->flash('error', __('settings_profile.flash_only_admin'));
            return;
        }

        $tenant->update(['lead_form_key' => (string) Str::uuid()]);
        session()->flash('success', __('settings_profile.flash_form_key_generated'));
    }

    /**
     * 重置（停用）公共表单：旧链接立即失效，需重新生成。
     */
    public function revokeLeadFormKey(): void
    {
        $tenant = app(Tenancy::class)->current();
        if (! $tenant || auth()->user()?->role !== 'admin') {
            session()->flash('error', __('settings_profile.flash_only_admin'));
            return;
        }

        $tenant->update(['lead_form_key' => null]);
        session()->flash('success', __('settings_profile.flash_form_key_revoked'));
    }

    public function render()
    {
        $tenant = app(Tenancy::class)->current();
        return view('livewire.settings.tenant-profile', [
            'tenant' => $tenant,
        ])->layout('layouts.app', ['title' => __('settings_profile.title')]);
    }
}
