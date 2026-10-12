<?php

namespace App\Policies;

use App\Models\Stage;
use App\Models\User;

class StagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Stage $stage): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Stage $stage): bool
    {
        return $user->role === 'admin' || $user->role === 'manager';
    }

    public function delete(User $user, Stage $stage): bool
    {
        return $user->role === 'admin';
    }
}
