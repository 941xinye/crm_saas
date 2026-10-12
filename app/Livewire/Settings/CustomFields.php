<?php

namespace App\Livewire\Settings;

use App\Services\CustomFieldService;
use App\Services\Tenancy;
use Livewire\Component;

/**
 * 自定义字段定义管理页（Settings）— 专业版及以上（plan.feature:custom_fields）。
 *
 * 定义存租户（tenants.custom_fields），按实体（公司/联系人/商机）分组。
 * 仅 admin 可保存修改（对齐 TeamMembers 的角色校验模式）。
 */
class CustomFields extends Component
{
    /** @var array<int, array{entity: string, key: string, label: string, type: string, options: string, required: bool}> */
    public array $rows = [];

    public function mount(): void
    {
        $this->rows = CustomFieldService::expandRows(app(Tenancy::class)->current());
    }

    public function addRow(): void
    {
        $this->rows[] = CustomFieldService::blankRow();
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        if (auth()->user()->role !== 'admin') {
            session()->flash('error', __('settings_customfields.flash_only_admin'));
            return;
        }

        $error = app(CustomFieldService::class)->saveDefinitions($this->rows);

        if ($error !== null) {
            session()->flash('error', $error);
            return;
        }

        $this->rows = CustomFieldService::expandRows(app(Tenancy::class)->current());
        session()->flash('success', __('settings_customfields.flash_saved'));
    }

    public function render()
    {
        return view('livewire.settings.custom-fields', [
            'entities' => collect(CustomFieldService::ENTITY_LABELS)
                ->map(fn ($_, $key) => __("settings_customfields.entity_{$key}"))->all(),
            'types' => collect(CustomFieldService::TYPES)
                ->map(fn ($_, $key) => __("settings_customfields.type_{$key}"))->all(),
        ])->layout('layouts.app', ['title' => __('settings_customfields.title')]);
    }
}
