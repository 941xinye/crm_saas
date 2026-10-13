<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 全局跨实体搜索测试：
 * 四实体命中、租户隔离、sales 数据权限、空关键词提示、顶栏搜索框渲染。
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $name = '搜索测试租户'): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => 'search-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    private function createUser(Tenant $tenant, string $role = 'admin'): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function seedCompany(Tenant $tenant, array $attributes = []): Company
    {
        $user = $this->createUser($tenant, 'admin');

        return Company::create(array_merge([
            'tenant_id' => $tenant->id,
            'owner_id' => $user->id,
            'name' => '云图科技',
            'industry' => '软件',
        ], $attributes));
    }

    // ------------------------------------------------------------------
    // 页面访问
    // ------------------------------------------------------------------

    public function test_search_page_requires_auth(): void
    {
        $this->get(route('search'))->assertRedirect(route('login'));
    }

    public function test_search_page_renders_with_empty_query(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);

        $this->actingAs($user)
            ->get(route('search'))
            ->assertOk()
            ->assertSee('输入关键词开始搜索');
    }

    public function test_header_search_box_renders_for_tenant_user(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('搜索公司、联系人、线索、商机');
    }

    // ------------------------------------------------------------------
    // 四实体命中
    // ------------------------------------------------------------------

    public function test_finds_company_by_name(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $this->seedCompany($tenant, ['name' => '云图科技']);

        $this->actingAs($user)
            ->get(route('search', ['q' => '云图']))
            ->assertOk()
            ->assertSee('云图科技')
            ->assertSee('公司');
    }

    public function test_finds_contact_by_email(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        Contact::create([
            'tenant_id' => $tenant->id,
            'first_name' => '小明',
            'last_name' => '王',
            'email' => 'xiaoming@example.com',
            'phone' => '13800138000',
        ]);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'xiaoming@example.com']))
            ->assertOk()
            ->assertSee('小明');
    }

    public function test_finds_lead_by_company_name(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $owner = $user;
        Lead::create([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'name' => '李咨询',
            'company_name' => '星辰咨询集团',
            'status' => 'new',
        ]);

        $this->actingAs($user)
            ->get(route('search', ['q' => '星辰咨询']))
            ->assertOk()
            ->assertSee('李咨询')
            ->assertSee('线索');
    }

    public function test_finds_opportunity_by_title(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $company = $this->seedCompany($tenant);
        $stage = Stage::create([
            'tenant_id' => $tenant->id,
            'name' => '初步接触',
            'sort_order' => 1,
        ]);
        Opportunity::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'stage_id' => $stage->id,
            'owner_id' => $user->id,
            'title' => 'ERP 系统采购项目',
            'value' => 500000,
        ]);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'ERP']))
            ->assertOk()
            ->assertSee('ERP 系统采购项目')
            ->assertSee('商机');
    }

    // ------------------------------------------------------------------
    // 租户隔离
    // ------------------------------------------------------------------

    public function test_cannot_see_other_tenant_results(): void
    {
        $tenantA = $this->createTenant('租户A');
        $tenantB = $this->createTenant('租户B');
        $userA = $this->createUser($tenantA);
        $this->seedCompany($tenantB, ['name' => '隔离验证公司']);

        $this->actingAs($userA)
            ->get(route('search', ['q' => '隔离验证公司']))
            ->assertOk()
            // 搜索租户 B 的公司精确名称，租户 A 应得到零结果（隔离证明）
            ->assertSee('未找到');
    }

    // ------------------------------------------------------------------
    // 数据权限（sales 仅见本人负责）
    // ------------------------------------------------------------------

    public function test_sales_only_sees_own_records(): void
    {
        $tenant = $this->createTenant();
        $sales = $this->createUser($tenant, 'sales');
        $admin = $this->createUser($tenant, 'admin');

        Company::create([
            'tenant_id' => $tenant->id,
            'owner_id' => $sales->id,
            'name' => '销售自己的公司',
            'industry' => '零售',
        ]);
        Company::create([
            'tenant_id' => $tenant->id,
            'owner_id' => $admin->id,
            'name' => '他人负责的公司',
            'industry' => '制造',
        ]);

        $this->actingAs($sales)
            ->get(route('search', ['q' => '公司']))
            ->assertOk()
            ->assertSee('销售自己的公司')
            ->assertDontSee('他人负责的公司');
    }

    // ------------------------------------------------------------------
    // 空结果
    // ------------------------------------------------------------------

    public function test_no_results_shows_fallback(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $this->seedCompany($tenant, ['name' => '云图科技']);

        $this->actingAs($user)
            ->get(route('search', ['q' => '不存在的关键词xyz']))
            ->assertOk()
            ->assertSee('未找到');
    }

    // ------------------------------------------------------------------
    // 搜索驱动（P1）
    // ------------------------------------------------------------------

    public function test_fulltext_driver_falls_back_to_like_on_non_mysql(): void
    {
        // sqlite 测试库无 FULLTEXT 支持，配置 fulltext 时应自动回退 LIKE 且结果一致
        config(['crm.search.driver' => 'fulltext']);
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $this->seedCompany($tenant, ['name' => '云图科技']);

        $this->actingAs($user)
            ->get(route('search', ['q' => '云图']))
            ->assertOk()
            ->assertSee('云图科技');
    }

    public function test_boolean_query_strips_operators(): void
    {
        $method = new \ReflectionMethod(\App\Livewire\GlobalSearch::class, 'toBooleanQuery');

        // 布尔操作符（+ - > < * ~ "）全部剥离，防止 BOOLEAN MODE 语法错误
        $this->assertSame('"acme" "corp2"', $method->invoke(new \App\Livewire\GlobalSearch, 'acme -corp*">2'));
        // 纯操作符输入清洗后为空串
        $this->assertSame('', $method->invoke(new \App\Livewire\GlobalSearch, '+ -*'));
    }
}
