<?php

namespace App\Livewire\Settings;

use App\Services\Tenancy;
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
            session()->flash('success', '租户设置已更新');
        }
    }

    public function render()
    {
        $tenant = app(Tenancy::class)->current();
        return view('livewire.settings.tenant-profile', [
            'tenant' => $tenant,
        ])->layout('layouts.app', ['title' => '租户设置']);
    }
}
