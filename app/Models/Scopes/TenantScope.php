<?php

namespace App\Models\Scopes;

use App\Services\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(Tenancy::class);

        // 逃生舱：console/seeding/tinker 时 Tenancy::id() 为 null，不附加条件
        if ($tenancy->id() === null) {
            return;
        }

        $builder->where(
            $model->getTable() . '.tenant_id',
            $tenancy->id()
        );
    }
}
