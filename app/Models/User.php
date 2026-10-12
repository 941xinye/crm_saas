<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
        'is_platform_admin',
        'notification_prefs',
        // 仪表板自定义小组件偏好（null = 全部默认显示）
        'dashboard_widgets',
        'locale',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'notification_prefs' => 'array',
            'dashboard_widgets' => 'array',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * 是否已启用两步验证（密钥存在且已通过首次动态码确认）。
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * 用户是否接收某类通知（键见 Settings\NotificationPreferences TYPES）。
     * notification_prefs 为 null（未设置）时默认全部接收。
     */
    public function wantsNotification(string $key): bool
    {
        $prefs = $this->notification_prefs;

        return $prefs === null ? true : (bool) ($prefs[$key] ?? true);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 覆盖 Notifiable trait 的 notifications()，
     * 使用自定义 App\Models\Notification 给 data 字段加 array cast，
     * 使 where('data->key', $value) 精确 JSON 查询生效。
     */
    public function notifications()
    {
        return $this->morphMany(\App\Models\Notification::class, 'notifiable')->orderBy('created_at', 'desc');
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /**
     * 数据权限：admin/manager 可见租户内全部数据，sales 仅可见自己负责的数据。
     */
    public function canSeeAllRecords(): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        return in_array($this->role, ['admin', 'manager'], true);
    }
}
