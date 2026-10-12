<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 模型层跨租户隔离测试：验证 TenantScope 全局作用域、
 * BelongsToTenant 创建时自动填充 tenant_id、以及 Tenancy 服务的租户上下文切换行为。
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $name): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
        ]);
    }

    private function tenancy(): Tenancy
    {
        return app(Tenancy::class);
    }

    public function test_new_records_are_stamped_with_current_tenant_id(): void
    {
        $tenant = $this->createTenant('甲公司');
        $this->tenancy()->setTenant($tenant);

        $company = Company::factory()->create();

        $this->assertSame($tenant->id, $company->tenant_id);
    }

    public function test_queries_only_return_current_tenant_records(): void
    {
        $tenantA = $this->createTenant('甲公司');
        $tenantB = $this->createTenant('乙公司');

        $this->tenancy()->setTenant($tenantA);
        Company::factory()->count(2)->create();

        $this->tenancy()->setTenant($tenantB);
        Company::factory()->count(3)->create();

        $this->tenancy()->setTenant($tenantA);
        $this->assertSame(2, Company::count());
        Company::all()->each(
            fn (Company $company) => $this->assertSame($tenantA->id, $company->tenant_id)
        );

        $this->tenancy()->setTenant($tenantB);
        $this->assertSame(3, Company::count());
    }

    public function test_records_of_other_tenants_are_not_reachable_by_id(): void
    {
        $tenantA = $this->createTenant('甲公司');
        $tenantB = $this->createTenant('乙公司');

        $this->tenancy()->setTenant($tenantB);
        $companyB = Company::factory()->create();

        $this->tenancy()->setTenant($tenantA);

        $this->expectException(ModelNotFoundException::class);
        Company::findOrFail($companyB->id);
    }

    public function test_switching_tenancy_changes_visibility(): void
    {
        $tenantA = $this->createTenant('甲公司');
        $tenantB = $this->createTenant('乙公司');

        $this->tenancy()->setTenant($tenantA);
        $companyA = Company::factory()->create(['name' => '甲的独有客户']);

        // 乙租户看不到甲的数据
        $this->tenancy()->setTenant($tenantB);
        $this->assertNull(Company::find($companyA->id));
        $this->assertSame(0, Company::where('name', '甲的独有客户')->count());

        // 切回甲租户后可见
        $this->tenancy()->setTenant($tenantA);
        $this->assertTrue(Company::where('name', '甲的独有客户')->exists());
        $this->assertSame($companyA->id, Company::findOrFail($companyA->id)->id);
    }

    /**
     * 逃生舱：Tenancy::id() 为 null（console/seeding/tinker）时不附加租户条件。
     * 这是有意设计，但必须作为已知行为记录下来，防止 Web 层遗漏 tenancy 初始化。
     */
    public function test_scope_has_no_effect_without_tenancy_context(): void
    {
        $tenantA = $this->createTenant('甲公司');
        $tenantB = $this->createTenant('乙公司');

        $this->tenancy()->setTenant($tenantA);
        Company::factory()->count(2)->create();

        $this->tenancy()->setTenant($tenantB);
        Company::factory()->count(3)->create();

        // 重置租户上下文
        $this->tenancy()->setTenant(null);

        $this->assertSame(5, Company::count());
    }

    public function test_without_tenant_scope_bypasses_isolation(): void
    {
        $tenantA = $this->createTenant('甲公司');
        $tenantB = $this->createTenant('乙公司');

        $this->tenancy()->setTenant($tenantA);
        Company::factory()->count(2)->create();

        $this->tenancy()->setTenant($tenantB);
        Company::factory()->count(3)->create();

        $this->tenancy()->setTenant($tenantA);
        $this->assertSame(5, Company::withoutTenant()->count());
    }

    /**
     * `??=` 语义：创建时显式传入 tenant_id 则保留（不覆盖为当前租户）。
     * 该记录对当前租户不可见（被 TenantScope 过滤），只有显式绕过时才能读到。
     */
    public function test_explicit_tenant_id_is_not_overwritten_on_create(): void
    {
        $tenantA = $this->createTenant('甲公司');
        $tenantB = $this->createTenant('乙公司');

        $this->tenancy()->setTenant($tenantA);
        $company = Company::factory()->create(['tenant_id' => $tenantB->id]);

        $this->assertSame($tenantB->id, $company->fresh()->tenant_id);
        $this->assertSame(0, Company::count());
        $this->assertSame(1, Company::withoutTenant()->count());
    }

    public function test_user_model_is_not_tenant_scoped_by_design(): void
    {
        $tenantA = $this->createTenant('甲公司');
        $tenantB = $this->createTenant('乙公司');

        $this->tenancy()->setTenant($tenantA);
        User::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        User::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        // User 有意不挂 TenantScope（认证需在租户上下文建立前解析用户），
        // 业务代码必须自行按 tenant_id 过滤，本测试固化该前提。
        $this->tenancy()->setTenant($tenantA);
        $this->assertSame(5, User::count());
    }
}
