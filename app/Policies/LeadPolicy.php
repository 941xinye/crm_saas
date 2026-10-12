<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $lead->owner_id === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->role === 'admin';
    }
}
