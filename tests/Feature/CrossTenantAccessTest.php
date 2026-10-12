<?php

namespace Tests\Feature;

use App\Livewire\Companies;
use App\Livewire\Contacts;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HTTP 层跨租户访问测试：
 * 1. 路由模型绑定经 TenantScope 过滤，跨租户 ID 一律 404；
 * 2. BootstrapTenancy 中间件按登录用户初始化租户上下文；
 * 3. ensure.has.tenant / platform.admin 中间件守卫。
 *
 * 注意：Livewire::test() 不经过 HTTP 中间件，因此组件级测试
 * 需先手动 app(Tenancy::class)->forUser($user) 模拟 BootstrapTenancy 的行为。
 */
class CrossTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = $this->createTenant('甲公司');
        $this->tenantB = $this->createTenant('乙公司');

        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'admin',
        ]);
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'admin',
        ]);
    }

    private function createTenant(string $name): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    // ------------------------------------------------------------------
    // 列表与详情隔离
    // ------------------------------------------------------------------

    public function test_company_index_only_lists_current_tenant_companies(): void
    {
        $companyA = Company::factory()->for($this->tenantA, 'tenant')->create(['name' => '甲租户的独有客户']);
        $companyB = Company::factory()->for($this->tenantB, 'tenant')->create(['name' => '乙租户的独有客户']);

        $this->actingAs($this->userA);
        app(Tenancy::class)->forUser($this->userA);

        Livewire::test(Companies\Index::class)
            ->assertSee($companyA->name)
            ->assertDontSee($companyB->name);
    }

    public function test_company_show_returns_404_for_other_tenant(): void
    {
        $companyB = Company::factory()->for($this->tenantB, 'tenant')->create();

        $this->actingAs($this->userA)
            ->get(route('companies.show', $companyB))
            ->assertNotFound();
    }

    public function test_company_edit_returns_404_for_other_tenant(): void
    {
        $companyB = Company::factory()->for($this->tenantB, 'tenant')->create();

        $this->actingAs($this->userA)
            ->get(route('companies.edit', $companyB))
            ->assertNotFound();
    }

    public function test_contact_show_returns_404_for_other_tenant(): void
    {
        $contactB = Contact::factory()->for($this->tenantB, 'tenant')->create();

        $this->actingAs($this->userA)
            ->get(route('contacts.show', $contactB))
            ->assertNotFound();
    }

    public function test_lead_show_returns_404_for_other_tenant(): void
    {
        $leadB = Lead::factory()->for($this->tenantB, 'tenant')->create();

        $this->actingAs($this->userA)
            ->get(route('leads.show', $leadB))
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // 写操作隔离
    // ------------------------------------------------------------------

    public function test_company_delete_cannot_touch_other_tenant_records(): void
    {
        $companyB = Company::factory()->for($this->tenantB, 'tenant')->create();

        $this->actingAs($this->userA);
        app(Tenancy::class)->forUser($this->userA);

        try {
            Livewire::test(Companies\Index::class)->call('delete', $companyB->id);
            $this->fail('跨租户删除未被拦截');
        } catch (ModelNotFoundException) {
            // 预期：TenantScope 使 findOrFail 找不到该记录
        }

        $this->assertDatabaseHas('companies', ['id' => $companyB->id, 'deleted_at' => null]);
    }

    public function test_created_company_is_stamped_with_current_tenant(): void
    {
        $this->actingAs($this->userA);
        app(Tenancy::class)->forUser($this->userA);

        Livewire::test(Companies\Form::class)
            ->set('name', '新客户公司')
            ->call('save');

        $company = Company::withoutTenant()->where('name', '新客户公司')->firstOrFail();
        $this->assertSame($this->tenantA->id, $company->tenant_id);
    }

    // ------------------------------------------------------------------
    // 租户上下文与中间件守卫
    // ------------------------------------------------------------------

    public function test_tenancy_is_bootstrapped_from_authenticated_user_on_request(): void
    {
        $this->actingAs($this->userA)->get(route('dashboard'));

        $tenancy = app(Tenancy::class);
        $this->assertSame($this->tenantA->id, $tenancy->id());
        $this->assertSame($this->userA->id, $tenancy->user()?->id);
        $this->assertFalse($tenancy->isPlatformAdmin());
    }

    public function test_user_without_tenant_is_rejected_from_business_routes(): void
    {
        $orphan = User::factory()->create(['tenant_id' => null]);

        $this->actingAs($orphan)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_platform_admin_routes_are_blocked_for_tenant_users(): void
    {
        $this->actingAs($this->userA)
            ->get(route('platform.tenants'))
            ->assertForbidden();
    }

    public function test_platform_admin_can_access_platform_routes(): void
    {
        $platformAdmin = User::factory()->create([
            'tenant_id' => null,
            'is_platform_admin' => true,
        ]);

        $this->actingAs($platformAdmin)
            ->get(route('platform.tenants'))
            ->assertOk();
    }

    public function test_guest_is_redirected_from_business_routes(): void
    {
        $this->get(route('dashboard'))->assertRedirect();
    }

    // ------------------------------------------------------------------
    // 用户层面的租户隔离（User 模型不挂全局作用域，需业务代码显式过滤）
    // ------------------------------------------------------------------

    public function test_team_member_management_cannot_touch_other_tenant_users(): void
    {
        $userB = User::factory()->create(['tenant_id' => $this->tenantB->id, 'role' => 'sales']);
        $this->actingAs($this->userA);
        app(Tenancy::class)->forUser($this->userA);

        try {
            Livewire::test(\App\Livewire\Settings\TeamMembers::class)
                ->call('updateRole', $userB->id, 'admin');
            $this->fail('跨租户角色更新未被拦截');
        } catch (ModelNotFoundException) {
            // 预期：查找被限定在当前租户内
        }

        try {
            Livewire::test(\App\Livewire\Settings\TeamMembers::class)
                ->call('removeMember', $userB->id);
            $this->fail('跨租户移除成员未被拦截');
        } catch (ModelNotFoundException) {
            // 预期：查找被限定在当前租户内
        }

        $this->assertDatabaseHas('users', ['id' => $userB->id, 'role' => 'sales']);
        $this->assertNotNull(User::find($userB->id), '其他租户用户不应被删除');
    }

    public function test_team_member_management_works_for_own_tenant(): void
    {
        $teammate = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'sales']);
        $this->actingAs($this->userA);
        app(Tenancy::class)->forUser($this->userA);

        Livewire::test(\App\Livewire\Settings\TeamMembers::class)
            ->call('updateRole', $teammate->id, 'manager');

        $this->assertDatabaseHas('users', ['id' => $teammate->id, 'role' => 'manager']);
    }

    public function test_owner_options_do_not_leak_other_tenant_users(): void
    {
        User::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => '乙租户的神秘员工']);
        $this->actingAs($this->userA);
        app(Tenancy::class)->forUser($this->userA);

        Livewire::test(Companies\Form::class)
            ->assertDontSee('乙租户的神秘员工');
    }

    public function test_owner_id_cannot_reference_other_tenant_user(): void
    {
        $userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->actingAs($this->userA);
        app(Tenancy::class)->forUser($this->userA);

        Livewire::test(Companies\Form::class)
            ->set('name', '带跨租户负责人的公司')
            ->set('owner_id', $userB->id)
            ->call('save')
            ->assertHasErrors(['owner_id']);

        $this->assertDatabaseMissing('companies', ['name' => '带跨租户负责人的公司']);
    }

    public function test_contact_company_id_cannot_reference_other_tenant_company(): void
    {
        $companyB = Company::factory()->for($this->tenantB, 'tenant')->create();
        $this->actingAs($this->userA);
        app(Tenancy::class)->forUser($this->userA);

        Livewire::test(Contacts\Form::class)
            ->set('first_name', '张')
            ->set('last_name', '三')
            ->set('company_id', $companyB->id)
            ->call('save')
            ->assertHasErrors(['company_id']);
    }
}
