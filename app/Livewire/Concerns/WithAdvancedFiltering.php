<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\CustomFieldService;
use App\Services\Tenancy;

/**
 * 列表页高级筛选/排序：
 * - sort_by + sort_dir 白名单排序，表头点击切换
 * - owner_filter 负责人筛选
 * - cf_key + cf_value 自定义字段值筛选（key 必须在租户已定义字段内，防 JSON path 注入）
 *
 * 使用方需：
 * 1. use 本 trait
 * 2. 定义 protected array $sortableColumns
 * 3. 将 sort_by/sort_dir/owner_filter/cf_key/cf_value 加入 $queryString
 * 4. render 中调用 applyOwnerFilter()、applyCustomFieldFilter($query, $entity) 与 applySorting()
 */
trait WithAdvancedFiltering
{
    public string $sort_by = 'created_at';
    public string $sort_dir = 'desc';
    public ?int $owner_filter = null;
    public ?string $cf_key = null;
    public ?string $cf_value = null;

    public function updatingSortBy(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatingSortDir(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatingOwnerFilter(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatingCfKey(): void
    {
        $this->resetPage();
        $this->selected = [];
        $this->cf_value = null;
    }

    public function updatingCfValue(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    /**
     * 表头点击排序：同列切换方向，新列默认升序。
     */
    public function sortBy(string $column): void
    {
        if (! in_array($column, $this->sortableColumns, true)) {
            return;
        }

        if ($this->sort_by === $column) {
            $this->sort_dir = $this->sort_dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort_by = $column;
            $this->sort_dir = 'asc';
        }
    }

    /**
     * 应用白名单排序（防注入，非法字段回退 created_at）。
     */
    protected function applySorting($query)
    {
        $column = in_array($this->sort_by, $this->sortableColumns, true)
            ? $this->sort_by
            : 'created_at';
        $dir = $this->sort_dir === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $dir);
    }

    /**
     * 应用负责人筛选。
     */
    protected function applyOwnerFilter($query)
    {
        return $this->owner_filter
            ? $query->where('owner_id', $this->owner_filter)
            : $query;
    }

    /**
     * 应用自定义字段值筛选（key 白名单校验：必须为租户已定义字段且标识符合法）。
     */
    protected function applyCustomFieldFilter($query, string $entity)
    {
        if (! $this->cf_key || $this->cf_value === null || $this->cf_value === '') {
            return $query;
        }

        if (! app(CustomFieldService::class)->isFilterableKey($entity, $this->cf_key)) {
            return $query;
        }

        return $query->where("custom_fields->{$this->cf_key}", $this->cf_value);
    }

    /**
     * 当前租户的用户列表（负责人下拉选项）。
     */
    protected function ownerOptions()
    {
        return app(Tenancy::class)->users()->orderBy('name')->get(['id', 'name']);
    }
}
