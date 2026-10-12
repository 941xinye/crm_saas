<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tenants 表新增 Stripe 订阅字段。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('plan')->index();
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id')->index();
            $table->string('stripe_price_id')->nullable()->after('stripe_subscription_id');
            $table->string('stripe_status')->nullable()->after('stripe_price_id')->comment('active/canceled/past_due/incomplete');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_customer_id',
                'stripe_subscription_id',
                'stripe_price_id',
                'stripe_status',
                'subscription_ends_at',
            ]);
        });
    }
};
