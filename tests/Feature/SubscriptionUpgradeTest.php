<?php

namespace Tests\Feature;

use App\Livewire\Settings\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 套餐页自助升级流程测试：
 * admin 权限校验、只升不降、Stripe 未配置降级提示、已配置时跳转 Checkout。
 */
class SubscriptionUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantUser(string $plan = 'free', string $role = 'admin', array $tenantExtra = []): array
    {
        $tenant = Tenant::create(array_merge([
            'name' => '测试租户',
            'slug' => 't-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)),
            'plan' => $plan,
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ], $tenantExtra));
        $user = User::factory()->for($tenant, 'tenant')->create([
            'role' => $role,
            'email_verified_at' => now(),
        ]);
        // Livewire::test 不走中间件链，需手动初始化租户上下文
        app(\App\Services\Tenancy::class)->forUser($user);

        return [$tenant, $user];
    }

    public function test_sales_cannot_upgrade(): void
    {
        [, $user] = $this->createTenantUser('free', 'sales');

        Livewire::actingAs($user)
            ->test(Subscription::class)
            ->call('upgrade', 'pro')
            ->assertSee('仅租户管理员可以变更套餐');
    }

    public function test_cannot_downgrade_via_self_service(): void
    {
        [$tenant, $user] = $this->createTenantUser('pro');

        Livewire::actingAs($user)
            ->test(Subscription::class)
            ->call('upgrade', 'free')
            ->assertSee('只能升级到更高套餐');

        $this->assertSame('pro', $tenant->fresh()->plan);
    }

    public function test_upgrade_shows_fallback_message_when_stripe_not_configured(): void
    {
        [$tenant, $user] = $this->createTenantUser('free');

        Livewire::actingAs($user)
            ->test(Subscription::class)
            ->call('upgrade', 'pro')
            ->assertSee('在线支付暂未开通');

        $this->assertSame('free', $tenant->fresh()->plan);
    }

    public function test_upgrade_redirects_to_stripe_checkout_when_configured(): void
    {
        [$tenant, $user] = $this->createTenantUser('free');

        $mock = \Mockery::mock(StripeSubscriptionService::class);
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->with(\Mockery::on(fn ($t) => $t->id === $tenant->id), 'pro')
            ->andReturn('https://checkout.stripe.com/c/pay/test-session');
        $this->instance(StripeSubscriptionService::class, $mock);

        Livewire::actingAs($user)
            ->test(Subscription::class)
            ->call('upgrade', 'pro')
            ->assertRedirect('https://checkout.stripe.com/c/pay/test-session');
    }

    // ------------------------------------------------------------------
    // Stripe Customer Portal（自助降级/取消/账单）
    // ------------------------------------------------------------------

    public function test_sales_cannot_open_portal(): void
    {
        [, $user] = $this->createTenantUser('pro', 'sales');

        Livewire::actingAs($user)
            ->test(Subscription::class)
            ->call('portal')
            ->assertSee('仅租户管理员可以管理订阅');
    }

    public function test_portal_redirects_when_configured(): void
    {
        [$tenant, $user] = $this->createTenantUser('pro');

        $mock = \Mockery::mock(StripeSubscriptionService::class);
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldReceive('createPortalSession')
            ->once()
            ->andReturn('https://billing.stripe.com/p/session/test');
        $this->instance(StripeSubscriptionService::class, $mock);

        Livewire::actingAs($user)
            ->test(Subscription::class)
            ->call('portal')
            ->assertRedirect('https://billing.stripe.com/p/session/test');
    }

    public function test_portal_shows_fallback_when_not_configured(): void
    {
        [$tenant, $user] = $this->createTenantUser('pro');

        Livewire::actingAs($user)
            ->test(Subscription::class)
            ->call('portal')
            ->assertSee('在线账单管理暂未开通');
    }

    public function test_past_due_status_shows_warning_banner(): void
    {
        // stripe_status 需在创建时设定：Tenancy 会缓存租户实例，后置 update 不影响渲染
        [, $user] = $this->createTenantUser('pro', 'admin', ['stripe_status' => 'past_due']);

        Livewire::actingAs($user)
            ->test(Subscription::class)
            ->assertSee('订阅付款失败或已逾期');
    }
}
