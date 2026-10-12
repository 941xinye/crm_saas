<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        // sales 仅可查看自己负责的公司
        return $user->canSeeAllRecords() || $company->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $company->owner_id === $user->id;
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->role === 'admin';
    }
}
