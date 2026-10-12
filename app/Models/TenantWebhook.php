<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\TenantWebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 租户 Webhook：事件推送配置。
 *
 * events 字段存储订阅事件名数组，格式：
 *   company.created, company.updated, company.deleted,
 *   lead.created, lead.updated, lead.deleted,
 *   contact.created, contact.updated, contact.deleted,
 *   opportunity.created, opportunity.updated, opportunity.deleted,
 *   activity.created, activity.updated, activity.deleted
 */
class TenantWebhook extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'last_sent_at',
        'last_failed_at',
        'last_response',
        'failure_count',
    ];

    /**
     * secret 脱敏：所有序列化输出（API 响应 / UI 渲染）默认不含明文密钥。
     * 属性访问（WebhookService 签名等内部使用）不受影响；
     * 仅在创建 / 重置 Secret 时通过 makeVisible('secret') 一次性展示。
     */
    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }

    /**
     * 是否订阅指定事件。
     */
    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }
}
