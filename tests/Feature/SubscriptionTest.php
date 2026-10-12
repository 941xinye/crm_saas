<?php

namespace Tests\Feature;

use App\Livewire\Companies\Form;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanService;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 套餐与订阅体系测试：
 * 试用期起点、到期禁新增（可编辑存量）、配额上限、付费放行、
 * 平台管理员切换套餐语义、订阅页展示。
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => '测试租户',
            'slug' => 't-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ], $overrides));
    }

    private function createTenantUser(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    }

    private function actingAsTenantUser(User $user): void
    {
        $this->actingAs($user);
        app(Tenancy::class)->forUser($user);
    }

    // ------------------------------------------------------------------
    // 试用期起点
    // ------------------------------------------------------------------

    public function test_register_grants_14_day_trial(): void
    {
        Livewire::test(\App\Livewire\Auth\Register::class)
            ->set('name', '张三')
            ->set('email', 'zhang@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('tenant_name', '新租户公司')
            ->call('register');

        $tenant = Tenant::latest('id')->first();

        $this->assertSame('free', $tenant->plan);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->greaterThan(now()->addDays(13)));
        $this->assertTrue($tenant->trial_ends_at->lessThan(now()->addDays(15)));
    }

    // ------------------------------------------------------------------
    // 试用期与新增
    // ------------------------------------------------------------------

    public function test_trial_active_tenant_can_create(): void
    {
        $tenant = $this->createTenant();
        $this->actingAsTenantUser($this->createTenantUser($tenant));

        Livewire::test(Form::class)
            ->set('name', '试用期内的新客户')
            ->call('save');

        $this->assertDatabaseHas('companies', ['name' => '试用期内的新客户', 'tenant_id' => $tenant->id]);
    }

    public function test_expired_trial_tenant_cannot_create(): void
    {
        $tenant = $this->createTenant(['trial_ends_at' => now()->subDay()]);
        $this->actingAsTenantUser($this->createTenantUser($tenant));

        Livewire::test(Form::class)
            ->set('name', '过期后的新客户')
            ->call('save')
            ->assertHasErrors(['companies']);

        $this->assertDatabaseMissing('companies', ['name' => '过期后的新客户']);
    }

    public function test_expired_trial_tenant_can_still_edit_existing_records(): void
    {
        $tenant = $this->createTenant(['trial_ends_at' => now()->subDay()]);
        $company = Company::factory()->for($tenant, 'tenant')->create(['name' => '旧客户']);
        $this->actingAsTenantUser($this->createTenantUser($tenant));

        Livewire::test(Form::class, ['company' => $company])
            ->set('name', '旧客户改名')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => '旧客户改名']);
    }

    public function test_paid_plan_can_create_even_with_expired_trial(): void
    {
        $tenant = $this->createTenant(['plan' => 'pro', 'trial_ends_at' => now()->subDay()]);
        $this->actingAsTenantUser($this->createTenantUser($tenant));

        Livewire::test(Form::class)
            ->set('name', '付费后的新客户')
            ->call('save');

        $this->assertDatabaseHas('companies', ['name' => '付费后的新客户', 'tenant_id' => $tenant->id]);
    }

    // ------------------------------------------------------------------
    // 配额
    // ------------------------------------------------------------------

    public function test_quota_exceeded_create_is_rejected(): void
    {
        $tenant = $this->createTenant();
        $quota = app(PlanService::class)->quota($tenant, 'companies');
        $this->assertSame(50, $quota);

        Company::factory()->for($tenant, 'tenant')->count($quota)->create();
        $this->actingAsTenantUser($this->createTenantUser($tenant));

        Livewire::test(Form::class)
            ->set('name', '超额的新客户')
            ->call('save')
            ->assertHasErrors(['companies']);

        $this->assertSame($quota, Company::withoutTenant()->where('tenant_id', $tenant->id)->count());
    }

    public function test_enterprise_quota_is_unlimited(): void
    {
        $tenant = $this->createTenant(['plan' => 'enterprise']);
        $quota = app(PlanService::class)->quota($tenant, 'companies');
        $this->assertSame(-1, $quota);

        Company::factory()->for($tenant, 'tenant')->count(51)->create();
        $this->actingAsTenantUser($this->createTenantUser($tenant));

        Livewire::test(Form::class)
            ->set('name', '企业版第 52 家')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(52, Company::withoutTenant()->where('tenant_id', $tenant->id)->count());
    }

    // ------------------------------------------------------------------
    // 订阅页
    // ------------------------------------------------------------------

    public function test_subscription_page_shows_plan_and_usage(): void
    {
        $tenant = $this->createTenant();
        Company::factory()->for($tenant, 'tenant')->count(3)->create();
        $this->actingAs($this->createTenantUser($tenant));

        $this->get(route('settings.subscription'))
            ->assertOk()
            ->assertSee('免费版')
            ->assertSee('公司')
            ->assertSee('3');
    }

    // ------------------------------------------------------------------
    // 平台管理员切换套餐
    // ------------------------------------------------------------------

    public function test_platform_tenants_panel_shows_usage_and_last_activity(): void
    {
        $tenant = $this->createTenant();
        Company::factory()->for($tenant, 'tenant')->count(2)->create();
        $other = $this->createTenant();
        Company::factory()->for($other, 'tenant')->create();

        $platformAdmin = User::factory()->create(['tenant_id' => null, 'is_platform_admin' => true]);
        $this->actingAs($platformAdmin);

        Livewire::test(\App\Livewire\Platform\Tenants::class)
            ->assertSee('配额使用率')
            // 两个租户各自按自己的用量展示（分组聚合正确性）
            ->assertSee('公司 2/50')
            ->assertSee('公司 1/50');
    }

    public function test_platform_admin_can_upgrade_and_downgrade_plan(): void
    {
        $tenant = $this->createTenant();
        $platformAdmin = User::factory()->create(['tenant_id' => null, 'is_platform_admin' => true]);
        $this->actingAs($platformAdmin);

        $component = Livewire::test(\App\Livewire\Platform\Tenants::class);

        // 升级：清空试用期
        $component->call('changePlan', $tenant->id, 'pro');
        $this->assertSame('pro', $tenant->fresh()->plan);
        $this->assertNull($tenant->fresh()->trial_ends_at);

        // 降级：试用期立即结束
        $component->call('changePlan', $tenant->id, 'free');
        $this->assertSame('free', $tenant->fresh()->plan);
        $this->assertNotNull($tenant->fresh()->trial_ends_at);
        $this->assertFalse($tenant->fresh()->trial_ends_at->isFuture());

        // 未知套餐不生效
        $component->call('changePlan', $tenant->id, 'ultra');
        $this->assertSame('free', $tenant->fresh()->plan);
    }

    public function test_downgraded_tenant_cannot_create(): void
    {
        // 降回 free 的租户（trial_ends_at = now）应立即失去新增能力
        $tenant = $this->createTenant(['plan' => 'free', 'trial_ends_at' => now()]);
        $this->actingAsTenantUser($this->createTenantUser($tenant));

        Livewire::test(Form::class)
            ->set('name', '降级后的新客户')
            ->call('save')
            ->assertHasErrors(['companies']);

        $this->assertDatabaseMissing('companies', ['name' => '降级后的新客户']);
    }
}
