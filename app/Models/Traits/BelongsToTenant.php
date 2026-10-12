<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Services\Tenancy;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $model->tenant_id ??= app(Tenancy::class)->id();
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function scopeWithoutTenant($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
