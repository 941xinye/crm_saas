<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Services\StripeSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stripe 发票同步测试（Http-free：直接调用 Service 处理事件数据）：
 * invoice.paid / invoice.payment_failed 落库、幂等 upsert、
 * customer 反查租户、未知租户跳过、账单页展示与租户隔离。
 */
class StripeInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => '测试租户',
            'slug' => 't-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ], $overrides));
    }

    private function invoicePayload(Tenant $tenant, array $overrides = []): array
    {
        return array_merge([
            'id' => 'in_' . Str::random(14),
            'customer' => $tenant->stripe_customer_id ?? 'cus_' . Str::random(10),
            'metadata' => ['tenant_id' => $tenant->id],
            'amount_paid' => 4900,
            'currency' => 'usd',
            'status' => 'paid',
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/acct_x/test',
            'period_start' => 1750000000,
            'period_end' => 1752592000,
            'status_transitions' => ['paid_at' => 1750100000],
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // invoice.paid
    // ------------------------------------------------------------------

    public function test_invoice_paid_creates_tenant_invoice(): void
    {
        $tenant = $this->createTenant();
        $data = $this->invoicePayload($tenant);

        app(StripeSubscriptionService::class)->handleInvoiceEvent('invoice.paid', $data);

        $invoice = TenantInvoice::withoutTenant()->where('stripe_invoice_id', $data['id'])->first();
        $this->assertNotNull($invoice);
        $this->assertSame($tenant->id, $invoice->tenant_id);
        $this->assertSame(4900, $invoice->amount);
        $this->assertSame('usd', $invoice->currency);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame($data['hosted_invoice_url'], $invoice->hosted_invoice_url);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(1750000000, $invoice->period_start->getTimestamp());
        $this->assertSame(1752592000, $invoice->period_end->getTimestamp());
    }

    public function test_invoice_event_is_idempotent_upsert(): void
    {
        $tenant = $this->createTenant();
        $data = $this->invoicePayload($tenant);

        $service = app(StripeSubscriptionService::class);
        $service->handleInvoiceEvent('invoice.paid', $data);
        // 同一发票重复推送（Stripe 会重试），更新而非新建
        $data['amount_paid'] = 9900;
        $service->handleInvoiceEvent('invoice.paid', $data);

        $this->assertSame(1, TenantInvoice::withoutTenant()->where('stripe_invoice_id', $data['id'])->count());
        $this->assertSame(9900, TenantInvoice::withoutTenant()->where('stripe_invoice_id', $data['id'])->value('amount'));
    }

    public function test_invoice_resolves_tenant_by_stripe_customer_id(): void
    {
        $tenant = $this->createTenant(['stripe_customer_id' => 'cus_abc123']);
        // 真实 Stripe 发票的 metadata 常缺失，customer id 反查兜底
        $data = $this->invoicePayload($tenant, ['metadata' => [], 'customer' => 'cus_abc123']);

        app(StripeSubscriptionService::class)->handleInvoiceEvent('invoice.paid', $data);

        $this->assertDatabaseHas('tenant_invoices', [
            'stripe_invoice_id' => $data['id'],
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_invoice_for_unknown_tenant_is_skipped(): void
    {
        $data = $this->invoicePayload($this->createTenant(), [
            'id' => 'in_orphan',
            'metadata' => [],
            'customer' => 'cus_unknown',
        ]);

        app(StripeSubscriptionService::class)->handleInvoiceEvent('invoice.paid', $data);

        $this->assertDatabaseMissing('tenant_invoices', ['stripe_invoice_id' => 'in_orphan']);
    }

    // ------------------------------------------------------------------
    // invoice.payment_failed
    // ------------------------------------------------------------------

    public function test_payment_failed_marks_invoice_failed(): void
    {
        $tenant = $this->createTenant();
        $data = $this->invoicePayload($tenant, [
            'id' => 'in_failed',
            'status' => 'open',
            'amount_due' => 4900,
            'amount_paid' => 0,
        ]);

        app(StripeSubscriptionService::class)->handleInvoiceEvent('invoice.payment_failed', $data);

        $invoice = TenantInvoice::withoutTenant()->where('stripe_invoice_id', 'in_failed')->first();
        $this->assertSame('failed', $invoice->status);
        $this->assertNull($invoice->paid_at);
    }

    // ------------------------------------------------------------------
    // 账单页展示与租户隔离
    // ------------------------------------------------------------------

    public function test_subscription_page_shows_billing_history(): void
    {
        $tenant = $this->createTenant();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $this->actingAs($user);
        app(\App\Services\Tenancy::class)->forUser($user);

        $invoice = TenantInvoice::create([
            'stripe_invoice_id' => 'in_page',
            'amount' => 4900,
            'currency' => 'usd',
            'status' => 'paid',
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/acct_x/page',
            'period_start' => now()->subMonth(),
            'period_end' => now(),
            'paid_at' => now()->subWeeks(3),
        ]);

        $this->get(route('settings.subscription'))
            ->assertOk()
            ->assertSee('账单历史')
            ->assertSee('已支付')
            ->assertSee('USD 49.00')
            ->assertSee($invoice->hosted_invoice_url);
    }

    public function test_tenant_cannot_see_other_tenants_invoices(): void
    {
        $mine = $this->createTenant();
        $other = $this->createTenant();
        $user = \App\Models\User::factory()->create(['tenant_id' => $mine->id, 'role' => 'admin']);
        $this->actingAs($user);
        app(\App\Services\Tenancy::class)->forUser($user);

        TenantInvoice::create([
            'tenant_id' => $other->id,
            'stripe_invoice_id' => 'in_other',
            'amount' => 9900,
            'currency' => 'usd',
            'status' => 'paid',
        ]);

        $this->get(route('settings.subscription'))
            ->assertOk()
            ->assertDontSee('$99.00')
            ->assertDontSee('in_other');
    }
}
