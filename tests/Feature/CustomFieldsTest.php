<?php

namespace Tests\Feature;

use App\Livewire\Companies\Form as CompanyForm;
use App\Livewire\Companies\Index as CompanyIndex;
use App\Livewire\Settings\CustomFields as CustomFieldsComponent;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 自定义字段测试（Phase 6 / P1）：
 * 设置页定义管理（保存/规范化/角色限制）、表单验证与落库；
 * 列表 JSON 筛选（含 key 白名单防注入）、REST API 写入与筛选。
 */
class CustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '自定义字段测试租户',
            'slug' => 'cf-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);
    }

    /**
     * 为租户写入一组字段定义（company: 客户等级 select 必填 + 年营收 number）。
     */
    private function seedDefinitions(): void
    {
        $this->tenant->update(['custom_fields' => [
            'company' => [
                ['key' => 'customer_level', 'label' => '客户等级', 'type' => 'select', 'required' => true, 'options' => ['A', 'B', 'C']],
                ['key' => 'annual_revenue', 'label' => '年营收（万）', 'type' => 'number', 'required' => false, 'options' => []],
            ],
        ]]);

        // Tenancy 单例在 setUp 时已解析持有旧 Tenant 实例，重新指向最新数据；
        // 同时刷新 user 上已缓存的 tenant 关系（API 请求经 ensure.has.tenant 会读取它），
        // 否则 definitions() 读到的 custom_fields 仍为 null
        $fresh = $this->tenant->fresh();
        app(Tenancy::class)->setTenant($fresh);
        $this->user->setRelation('tenant', $fresh);
    }

    // ------------------------------------------------------------------
    // 设置页：定义管理
    // ------------------------------------------------------------------

    public function test_admin_can_save_definitions(): void
    {
        Livewire::test(CustomFieldsComponent::class)
            ->set('rows', [[
                'entity' => 'company',
                'key' => 'Customer Level', // 含空格，应规范化为 customer_level
                'label' => '客户等级',
                'type' => 'select',
                'options' => 'A, B ,C',
                'required' => true,
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $saved = $this->tenant->fresh()->custom_fields;

        $this->assertSame('customer_level', $saved['company'][0]['key']);
        $this->assertSame(['A', 'B', 'C'], $saved['company'][0]['options']);
        $this->assertTrue($saved['company'][0]['required']);
    }

    public function test_sales_cannot_save_definitions(): void
    {
        $sales = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'sales']);
        $this->actingAs($sales);
        app(Tenancy::class)->forUser($sales);

        Livewire::test(CustomFieldsComponent::class)
            ->set('rows', [CustomFieldsTest::blankRow()])
            ->call('save');

        $this->assertNull($this->tenant->fresh()->custom_fields);
    }

    public function test_duplicate_key_is_rejected(): void
    {
        Livewire::test(CustomFieldsComponent::class)
            ->set('rows', [
                [...CustomFieldsTest::blankRow(), 'key' => 'level', 'label' => '等级一', 'type' => 'text'],
                [...CustomFieldsTest::blankRow(), 'key' => 'LEVEL', 'label' => '等级二', 'type' => 'text'],
            ])
            ->call('save');

        // 保存被拒绝，定义未落库
        $this->assertNull($this->tenant->fresh()->custom_fields);
    }

    public function test_select_type_requires_options(): void
    {
        Livewire::test(CustomFieldsComponent::class)
            ->set('rows', [[...CustomFieldsTest::blankRow(), 'key' => 'level', 'label' => '等级', 'type' => 'select', 'options' => '']])
            ->call('save');

        $this->assertNull($this->tenant->fresh()->custom_fields);
    }

    private static function blankRow(): array
    {
        return ['entity' => 'company', 'key' => '', 'label' => '', 'type' => 'text', 'options' => '', 'required' => false];
    }

    // ------------------------------------------------------------------
    // 表单：动态验证与落库
    // ------------------------------------------------------------------

    public function test_required_custom_field_blocks_company_save(): void
    {
        $this->seedDefinitions();

        Livewire::test(CompanyForm::class)
            ->set('name', '缺字段公司')
            ->set('custom_fields', ['annual_revenue' => '500'])
            ->call('save')
            ->assertHasErrors(['custom_fields.customer_level']);

        $this->assertDatabaseMissing('companies', ['name' => '缺字段公司']);
    }

    public function test_select_option_is_validated(): void
    {
        $this->seedDefinitions();

        Livewire::test(CompanyForm::class)
            ->set('name', '非法选项公司')
            ->set('custom_fields', ['customer_level' => 'X'])
            ->call('save')
            ->assertHasErrors(['custom_fields.customer_level']);
    }

    public function test_company_save_persists_custom_fields(): void
    {
        $this->seedDefinitions();

        Livewire::test(CompanyForm::class)
            ->set('name', '云图科技')
            ->set('custom_fields', ['customer_level' => 'A', 'annual_revenue' => '800', 'injected_key' => 'hack'])
            ->call('save')
            ->assertHasNoErrors();

        $company = Company::where('name', '云图科技')->firstOrFail();

        // 只保留已定义字段，未定义 key 被丢弃；number 类型转数值
        $this->assertSame(['customer_level' => 'A', 'annual_revenue' => 800], $company->custom_fields);
    }

    // ------------------------------------------------------------------
    // 列表：JSON 筛选
    // ------------------------------------------------------------------

    public function test_index_filters_by_custom_field_value(): void
    {
        $this->seedDefinitions();

        Company::create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'name' => 'A级客户',
            'custom_fields' => ['customer_level' => 'A'],
        ]);
        Company::create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'name' => 'B级客户',
            'custom_fields' => ['customer_level' => 'B'],
        ]);

        Livewire::withQueryParams(['cf_key' => 'customer_level', 'cf_value' => 'A'])
            ->test(CompanyIndex::class)
            ->assertSee('A级客户')
            ->assertDontSee('B级客户');
    }

    public function test_index_ignores_undefined_filter_key(): void
    {
        $this->seedDefinitions();

        Company::create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'name' => '唯一公司',
            'custom_fields' => ['customer_level' => 'A'],
        ]);

        // 未定义的 key 不应生效（也不报错）
        Livewire::withQueryParams(['cf_key' => 'evil_path', 'cf_value' => 'x'])
            ->test(CompanyIndex::class)
            ->assertSee('唯一公司');
    }

    // ------------------------------------------------------------------
    // REST API
    // ------------------------------------------------------------------

    public function test_api_store_and_filter_by_custom_fields(): void
    {
        $this->seedDefinitions();
        $token = $this->user->createToken('', ['read', 'write'])->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        // 创建（含自定义字段）
        $this->postJson('/api/v1/companies', [
            'name' => 'API公司',
            'custom_fields' => ['customer_level' => 'B', 'annual_revenue' => 1200, 'injected' => 'x'],
        ], $headers)->assertCreated();

        $company = Company::where('name', 'API公司')->firstOrFail();
        $this->assertSame(['customer_level' => 'B', 'annual_revenue' => 1200], $company->custom_fields);

        // 筛选
        $this->getJson('/api/v1/companies?cf_key=customer_level&cf_value=B', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'API公司');

        $this->getJson('/api/v1/companies?cf_key=customer_level&cf_value=A', $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_api_rejects_invalid_select_option(): void
    {
        $this->seedDefinitions();
        $token = $this->user->createToken('', ['read', 'write'])->plainTextToken;

        $this->postJson('/api/v1/companies', [
            'name' => '非法选项',
            'custom_fields' => ['customer_level' => 'Z'],
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_fields.customer_level']);
    }
}
