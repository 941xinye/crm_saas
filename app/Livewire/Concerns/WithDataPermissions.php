<?php

namespace App\Livewire\Concerns;

/**
 * 数据权限范围：sales 角色仅可见/操作自己负责的数据，
 * admin/manager 可见租户内全部数据。
 *
 * 使用方在 render 查询中调用 applyDataScope($query)。
 * 无 owner_id 字段的模型（如 Contact）不适用，保持租户内全员可见。
 */
trait WithDataPermissions
{
    protected function applyDataScope($query, string $ownerColumn = 'owner_id')
    {
        $user = auth()->user();

        if ($user && ! $user->canSeeAllRecords()) {
            $query->where($ownerColumn, $user->id);
        }

        return $query;
    }
}
