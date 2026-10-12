<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * 租户账单：由 Stripe Webhook（invoice.paid / invoice.payment_failed）同步写入。
 * 通过 BelongsToTenant 自动租户隔离。
 */
class TenantInvoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        // tenant_id 由 StripeWebhook 平台侧同步时显式写入（无租户上下文）
        'tenant_id',
        'stripe_invoice_id',
        'amount',
        'currency',
        'status',
        'hosted_invoice_url',
        'period_start',
        'period_end',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * 状态徽章样式（账单页展示用）。
     */
    public function badgeClass(): string
    {
        return match ($this->status) {
            'paid' => 'bg-green-100 text-green-700',
            'open' => 'bg-amber-100 text-amber-700',
            'failed' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }
}
