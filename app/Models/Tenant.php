<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'settings',
        'custom_fields',
        'trial_ends_at',
        'is_active',
        // Stripe 订阅状态（StripeSubscriptionService 经 update() 写入，缺失会被静默丢弃）
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'stripe_status',
        'subscription_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'custom_fields' => 'array',
            'trial_ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
