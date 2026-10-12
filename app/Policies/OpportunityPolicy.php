<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        // sales 仅可查看自己负责的商机
        return $user->canSeeAllRecords() || $opportunity->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $opportunity->owner_id === $user->id;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->role === 'admin';
    }

    /**
     * 恢复软删除的商机：仅管理员。
     */
    public function restore(User $user, Opportunity $opportunity): bool
    {
        return $user->role === 'admin';
    }

    /**
     * 彻底删除商机（不可恢复）：仅管理员。
     */
    public function forceDelete(User $user, Opportunity $opportunity): bool
    {
        return $user->role === 'admin';
    }
}
