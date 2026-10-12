<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

class Tenancy
{
    private ?Tenant $current = null;
    private ?User $user = null;

    public function forUser(?User $user): self
    {
        $this->user = $user;
        $this->current = $user?->tenant;
        return $this;
    }

    public function setTenant(?Tenant $tenant): self
    {
        $this->current = $tenant;
        return $this;
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function id(): ?int
    {
        return $this->current?->id;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->user?->is_platform_admin;
    }
}
