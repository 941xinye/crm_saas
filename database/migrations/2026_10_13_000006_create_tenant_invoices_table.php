<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 租户账单（Stripe 发票同步）：invoice.paid / invoice.payment_failed 事件落库，
 * 供租户在「设置 → 套餐订阅」页查看账单历史与下载链接。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_invoice_id')->unique()->comment('Stripe in_xxx');
            $table->unsignedBigInteger('amount')->comment('金额（最小货币单位，分）');
            $table->string('currency', 10)->default('usd');
            $table->string('status')->index()->comment('paid/open/void/uncollectible/failed');
            $table->string('hosted_invoice_url')->nullable()->comment('Stripe 托管账单页（查看/下载 PDF）');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invoices');
    }
};
