<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class Tenancy
{
    private ?Tenant $current = null;
    private ?User $user = null;

    /** 子域名识别锁定租户后为 true，forUser 不再将租户覆盖回用户所属租户 */
    private bool $tenantLocked = false;

    public function forUser(?User $user): self
    {
        $this->user = $user;

        // 子域名模式下（BootstrapTenancy 已锁定租户），后续中间件
        // （ensure.has.tenant 等）再次调用 forUser 时不得覆盖租户上下文
        if (! $this->tenantLocked) {
            $this->current = $user?->tenant;
        }

        return $this;
    }

    public function setTenant(?Tenant $tenant): self
    {
        $this->current = $tenant;
        return $this;
    }

    /**
     * 子域名识别锁定租户（同一请求内 forUser 不再覆盖）。
     */
    public function lockTenant(Tenant $tenant): self
    {
        $this->tenantLocked = true;
        $this->current = $tenant;
        return $this;
    }

    public function isTenantLocked(): bool
    {
        return $this->tenantLocked;
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

    /**
     * 当前租户的用户查询。User 模型未挂 TenantScope（认证需在租户上下文
     * 建立前解析用户），涉及用户的列表/查找必须经过此方法，避免跨租户泄露或操作。
     */
    public function users(): Builder
    {
        return User::query()->where('tenant_id', $this->id());
    }

    /**
     * 限定当前租户的 exists 验证规则。验证器不走模型全局作用域，
     * 跨租户外键引用必须在此显式过滤。
     */
    public function existsRule(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where('tenant_id', $this->id());
    }
}
