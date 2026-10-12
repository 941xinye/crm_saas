<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CsvService;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CSV 导入导出测试：租户隔离、配额约束、行级错误、敏感字段过滤、关联解析。
 */
class CsvImportExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'CSV 测试租户',
            'slug' => 'c-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);

        $this->otherTenant = Tenant::create([
            'name' => '其他租户',
            'slug' => 'o-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    private function makeCsv(array $headers, array $rows): string
    {
        $lines = [implode(',', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }
        return "\xEF\xBB\xBF" . implode("\n", $lines);
    }

    // ------------------------------------------------------------------
    // 导出
    // ------------------------------------------------------------------

    public function test_export_contains_current_tenant_only(): void
    {
        Company::factory()->for($this->tenant, 'tenant')->create(['name' => '本租户公司']);
        Company::factory()->for($this->otherTenant, 'tenant')->create(['name' => '其他租户公司']);

        $csv = app(CsvService::class)->export(Company::class);

        $this->assertStringContainsString('本租户公司', $csv);
        $this->assertStringNotContainsString('其他租户公司', $csv);
    }

    public function test_export_has_utf8_bom(): void
    {
        $csv = app(CsvService::class)->export(Company::class);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_export_uses_chinese_headers(): void
    {
        Company::factory()->for($this->tenant, 'tenant')->create(['name' => '测试公司']);

        $csv = app(CsvService::class)->export(Company::class);

        $this->assertStringContainsString('名称', $csv);
        $this->assertStringContainsString('测试公司', $csv);
    }

    // ------------------------------------------------------------------
    // 导入基础
    // ------------------------------------------------------------------

    public function test_import_creates_records(): void
    {
        $csv = $this->makeCsv(
            ['名称', '行业', '网站'],
            [['ACME Inc', '科技', 'https://acme.com']],
        );

        $result = app(CsvService::class)->import(Company::class, $csv, $this->tenant);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, count($result['errors']));
        $this->assertSame(1, Company::where('name', 'ACME Inc')->count());
    }

    public function test_import_strips_tenant_id_from_user_data(): void
    {
        // 即使 CSV 里带了 tenant_id 列，也应被过滤掉，由 Tenancy 自动填充
        $csv = $this->makeCsv(
            ['名称', 'tenant_id'],
            [['恶意公司', '99999']],
        );

        app(CsvService::class)->import(Company::class, $csv, $this->tenant);

        $company = Company::where('name', '恶意公司')->firstOrFail();
        $this->assertSame($this->tenant->id, $company->tenant_id, 'CSV 中的 tenant_id 应被忽略');
    }

    public function test_import_expired_trial_blocked(): void
    {
        $this->tenant->update(['trial_ends_at' => now()->subDay()]);
        $csv = $this->makeCsv(
            ['名称'],
            [['到期租户公司']],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('试用期已结束');
        app(CsvService::class)->import(Company::class, $csv, $this->tenant);
    }

    public function test_import_quota_exceeded_blocked(): void
    {
        // free 套餐公司配额 50 条，创建 50 条后再导入
        Company::factory()->for($this->tenant, 'tenant')->count(50)->create();

        $csv = $this->makeCsv(
            ['名称'],
            [['超配额公司']],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('配额不足');
        app(CsvService::class)->import(Company::class, $csv, $this->tenant);
    }

    // ------------------------------------------------------------------
    // 导入行级错误
    // ------------------------------------------------------------------

    public function test_invalid_rows_collected_with_row_numbers(): void
    {
        $csv = $this->makeCsv(
            ['名称', '行业'],
            [
                ['有效公司', '科技'],
                ['', '缺少名称的公司'],  // 行 3
                ['另一家有效公司', '金融'],
            ],
        );

        $result = app(CsvService::class)->import(Company::class, $csv, $this->tenant);

        $this->assertSame(2, $result['created']);
        $this->assertCount(1, $result['errors']);
        $this->assertArrayHasKey(3, $result['errors']);
        $this->assertStringContainsString('必填', $result['errors'][3]);
    }

    public function test_empty_rows_skipped(): void
    {
        $csv = $this->makeCsv(
            ['名称'],
            [['公司 A'], [''], ['公司 B']],
        );

        $result = app(CsvService::class)->import(Company::class, $csv, $this->tenant);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, count($result['errors']));
    }

    // ------------------------------------------------------------------
    // 关联解析
    // ------------------------------------------------------------------

    public function test_contact_import_resolves_company_by_name(): void
    {
        Company::factory()->for($this->tenant, 'tenant')->create(['name' => '关联公司']);

        $csv = $this->makeCsv(
            ['姓', '名', '邮箱', '公司'],
            [['王', '小明', 'wxm@example.com', '关联公司']],
        );

        $result = app(CsvService::class)->import(Contact::class, $csv, $this->tenant);

        $this->assertSame(1, $result['created']);
        $contact = Contact::where('email', 'wxm@example.com')->firstOrFail();
        $this->assertNotNull($contact->company_id);
        $this->assertSame('关联公司', $contact->company->name);
    }

    public function test_opportunity_import_resolves_stage_by_name(): void
    {
        $stage = Stage::factory()->for($this->tenant, 'tenant')->create(['name' => '初步接洽']);
        Company::factory()->for($this->tenant, 'tenant')->create(['name' => '商机公司']);

        $csv = $this->makeCsv(
            ['标题', '金额', '阶段', '公司'],
            [['Test Deal', '10000', '初步接洽', '商机公司']],
        );

        $result = app(CsvService::class)->import(Opportunity::class, $csv, $this->tenant);

        $this->assertSame(1, $result['created']);
        $opp = Opportunity::where('title', 'Test Deal')->firstOrFail();
        $this->assertSame($stage->id, $opp->stage_id);
        $this->assertEqualsWithDelta(10000, (float) $opp->value, 0.01);
    }

    public function test_opportunity_import_missing_stage_name_is_error(): void
    {
        Company::factory()->for($this->tenant, 'tenant')->create(['name' => '商机公司']);

        $csv = $this->makeCsv(
            ['标题', '阶段', '公司'],
            [['Bad Deal', '不存在的阶段', '商机公司']],
        );

        $result = app(CsvService::class)->import(Opportunity::class, $csv, $this->tenant);

        $this->assertSame(0, $result['created']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('找不到阶段', $result['errors'][2]);
    }

    // ------------------------------------------------------------------
    // 模板
    // ------------------------------------------------------------------

    public function test_template_has_only_headers(): void
    {
        $template = app(CsvService::class)->template(Company::class);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $template);
        $this->assertStringContainsString('名称', $template);
        $this->assertStringNotContainsString("\n", trim($template)); // 只有一行
    }

    public function test_contact_export_uses_company_name_not_id(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '导出公司X']);
        Contact::factory()->for($this->tenant, 'tenant')->create([
            'first_name' => '小',
            'last_name' => '王',
            'company_id' => $company->id,
            'phone' => '13900000000',
        ]);

        $csv = app(CsvService::class)->export(Contact::class);

        // 公司名列应为 "导出公司X" 而非 company_id
        $this->assertStringContainsString('导出公司X', $csv);
        // CSV 最后一列是公司名，不应该是纯数字 company_id
        $lines = explode("\n", trim($csv));
        $dataLine = $lines[1] ?? '';
        $lastCol = trim(substr($dataLine, strrpos($dataLine, ',') + 1));
        $this->assertNotSame('', $lastCol);
        $this->assertFalse(ctype_digit($lastCol), '公司名导出不应是纯数字 ID');
    }

    // ------------------------------------------------------------------
    // 查重（Phase 6 / P1）
    // ------------------------------------------------------------------

    public function test_import_skips_duplicate_companies_by_name(): void
    {
        Company::factory()->for($this->tenant, 'tenant')->create(['name' => '已有公司']);

        // 库里已有 1 条 + 同文件重复 1 条
        $csv = $this->makeCsv(['名称'], [['已有公司'], ['新公司'], ['新公司']]);

        $result = app(CsvService::class)->import(Company::class, $csv, $this->tenant);

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['duplicates']);
        $this->assertSame(0, count($result['errors']));
    }

    public function test_import_skips_duplicate_contact_emails(): void
    {
        Contact::factory()->for($this->tenant, 'tenant')->create([
            'last_name' => '旧',
            'first_name' => '人',
            'email' => 'dup@acme.test',
        ]);

        $csv = $this->makeCsv(['姓', '名', '邮箱'], [['新', '人', 'dup@acme.test']]);

        $result = app(CsvService::class)->import(Contact::class, $csv, $this->tenant);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['duplicates']);
    }

    public function test_import_does_not_confuse_same_email_across_tenants(): void
    {
        // 其他租户已有同邮箱联系人，不应阻断本租户导入
        Contact::factory()->for($this->otherTenant, 'tenant')->create([
            'last_name' => '别',
            'first_name' => '人',
            'email' => 'cross@acme.test',
        ]);

        $csv = $this->makeCsv(['姓', '名', '邮箱'], [['本', '人', 'cross@acme.test']]);

        $result = app(CsvService::class)->import(Contact::class, $csv, $this->tenant);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['duplicates']);
    }
}
