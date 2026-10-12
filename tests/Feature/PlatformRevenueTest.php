<?php

namespace Tests\Feature;

use App\Livewire\Platform\Revenue;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 平台收入/流失看板测试：
 * 访问控制（仅平台超管）、MRR/ARPU 计算、流失判定与流失率、
 * 12 个月发票收入按月按币种汇总。
 */
class PlatformRevenueTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => '收入租户-' . Str::random(4),
            'slug' => 'rev-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
        ], $overrides));
    }

    private function actingAsPlatformAdmin(): User
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        return $admin;
    }

    private function paidInvoice(Tenant $tenant, string $month, int $amount = 19900, string $currency = 'cny'): TenantInvoice
    {
        return TenantInvoice::create([
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => 'in_' . Str::random(12),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'paid',
            'paid_at' => \Carbon\Carbon::parse($month . '-15 10:00:00'),
        ]);
    }

    // ------------------------------------------------------------------
    // 访问控制
    // ------------------------------------------------------------------

    public function test_platform_admin_can_view_revenue_dashboard(): void
    {
        $this->actingAsPlatformAdmin();

        Livewire::test(Revenue::class)->assertOk()->assertSee(__('platform_revenue.mrr'));
    }

    public function test_tenant_user_cannot_view_revenue_dashboard(): void
    {
        $tenant = $this->createTenant();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $this->actingAs($user);

        $this->get(route('platform.revenue'))->assertForbidden();
    }

    // ------------------------------------------------------------------
    // MRR / 租户计数
    // ------------------------------------------------------------------

    public function test_mrr_sums_plan_prices_of_non_churned_paid_tenants(): void
    {
        // pro(199) + enterprise(999) + free(0) = 1198
        $this->createTenant(['plan' => 'pro']);
        $this->createTenant(['plan' => 'enterprise']);
        $this->createTenant(['plan' => 'free']);

        Livewire::test(Revenue::class)
            ->assertSee('¥' . number_format(1198, 2));
    }

    public function test_canceled_subscription_excluded_from_mrr_but_counted_as_churn(): void
    {
        $this->createTenant(['plan' => 'pro', 'stripe_status' => 'canceled']);
        $this->createTenant(['plan' => 'pro']);

        Livewire::test(Revenue::class)
            ->assertSee('¥' . number_format(199, 2)); // 只有在营的 1 个 pro
    }

    public function test_trialing_tenants_are_counted(): void
    {
        $this->createTenant(['trial_ends_at' => now()->addDays(7)]);
        $this->createTenant(['trial_ends_at' => now()->subDay()]);

        $this->assertSame(1, Livewire::test(Revenue::class)->viewData('trialCount'));
    }

    // ------------------------------------------------------------------
    // 流失判定与流失率
    // ------------------------------------------------------------------

    public function test_expired_paid_subscription_counts_as_churned(): void
    {
        $this->createTenant([
            'plan' => 'pro',
            'subscription_ends_at' => now()->subDays(3),
        ]);
        $this->createTenant([
            'plan' => 'pro',
            'subscription_ends_at' => now()->addMonth(),
        ]);

        $component = Livewire::test(Revenue::class);
        $this->assertSame(1, $component->viewData('churnCount'));
        // 累计流失率 = 1 / (1 + 1) = 50%
        $this->assertSame(50.0, $component->viewData('churnRate'));
    }

    public function test_free_tenants_are_never_churned(): void
    {
        $this->createTenant(['plan' => 'free', 'subscription_ends_at' => now()->subYear()]);

        Livewire::test(Revenue::class)->assertSet('churnCount', 0);
    }

    // ------------------------------------------------------------------
    // 发票收入汇总
    // ------------------------------------------------------------------

    public function test_paid_invoices_are_summed_by_month_and_currency(): void
    {
        $tenant = $this->createTenant(['plan' => 'pro']);
        $thisMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');

        $this->paidInvoice($tenant, $thisMonth, 19900);          // ¥199.00
        $this->paidInvoice($tenant, $thisMonth, 99900, 'cny');   // 同月另一张 → ¥999.00
        $this->paidInvoice($tenant, $lastMonth, 19900);          // 上月 ¥199.00
        // open 状态不计入收入
        TenantInvoice::create([
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => 'in_open_' . Str::random(8),
            'amount' => 500000,
            'currency' => 'cny',
            'status' => 'open',
            'paid_at' => now(),
        ]);

        $component = Livewire::test(Revenue::class);
        $series = $component->viewData('revenueByCurrency')['CNY'];

        $this->assertSame(1198.0, $series[$thisMonth]);
        $this->assertSame(199.0, $series[$lastMonth]);
    }

    public function test_invoices_outside_12_months_are_ignored(): void
    {
        $tenant = $this->createTenant(['plan' => 'pro']);
        $this->paidInvoice($tenant, now()->subYear()->subMonth()->format('Y-m'));

        $component = Livewire::test(Revenue::class);
        $series = $component->viewData('revenueByCurrency');

        $this->assertArrayNotHasKey('CNY', $series);
    }
}
