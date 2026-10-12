<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * 租户邀请：管理员通过 email 邀请新成员加入租户。
 *
 * 不使用 BelongsToTenant（邀请通过 token 访问，不经过租户上下文）。
 */
class TenantInvitation extends Model
{
    protected $fillable = [
        'tenant_id',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
        'accepted_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function accepter()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * 生成新邀请并返回实例。
     */
    public static function createFor(Tenant $tenant, string $email, string $role): self
    {
        return self::create([
            'tenant_id'  => $tenant->id,
            'email'      => $email,
            'role'       => $role,
            'token'      => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * 是否已过期。
     */
    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    /**
     * 是否已被接受。
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * 是否有效（未过期 + 未接受）。
     */
    public function isValid(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * 有效邀请范围。
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * 待接受邀请范围。
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }
}
