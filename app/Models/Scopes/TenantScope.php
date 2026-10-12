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

        // 逃生舱：console/seeding/tinker 时 Tenancy::id() 为 null，不附加条件。
        // 但 Web 请求中若用户已认证而租户上下文尚未初始化（如路由模型绑定
        // 早于 BootstrapTenancy 中间件执行），必须立即从当前用户引导租户，
        // 否则作用域会被跳过，造成跨租户数据泄露。
        if ($tenancy->id() === null) {
            $user = auth()->user();

            if ($user === null) {
                return; // 游客或控制台场景，维持逃生舱
            }

            $tenancy->forUser($user);

            if ($tenancy->id() === null) {
                return; // 平台超管无绑定租户，维持逃生舱（可见全部数据）
            }
        }

        $builder->where(
            $model->getTable() . '.tenant_id',
            $tenancy->id()
        );
    }
}
