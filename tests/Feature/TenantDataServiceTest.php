<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * 租户数据导出/清空（GDPR 合规）服务测试。
 */
class TenantDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantDataService::class);
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'name' => '租户-' . Str::random(4),
            'slug' => 'x-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function test_export_zip_contains_tenant_data_only(): void
    {
        $tenant = $this->makeTenant();
        $other = $this->makeTenant();
        Company::factory()->for($tenant, 'tenant')->create(['name' => 'A公司']);
        Company::factory()->for($other, 'tenant')->create(['name' => 'B公司']);

        $path = $this->service->exportZip($tenant);
        try {
            $this->assertFileExists($path);

            $zip = new ZipArchive;
            $zip->open($path);
            $csv = $zip->getFromName('companies.csv');
            $this->assertNotFalse($csv);
            $zip->close();

            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
            $this->assertStringContainsString('A公司', $csv);
            $this->assertStringNotContainsString('B公司', $csv);
        } finally {
            @unlink($path);
        }
    }

    public function test_purge_deletes_business_data_keeps_audit_and_other_tenants(): void
    {
        Storage::fake('local');
        $tenant = $this->makeTenant();
        $other = $this->makeTenant();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $company = Company::factory()->for($tenant, 'tenant')->create();
        Company::factory()->for($other, 'tenant')->create(); // 其他租户数据

        // 附件（物理文件 + 行）
        $attPath = "attachments/{$tenant->id}/{$company->id}/test.pdf";
        Storage::disk('local')->put($attPath, 'fake-pdf-content');
        Attachment::create([
            'tenant_id' => $tenant->id,
            'attachable_type' => Company::class,
            'attachable_id' => $company->id,
            'original_name' => 'test.pdf',
            'file_path' => $attPath,
            'mime_type' => 'application/pdf',
            'size' => 16,
            'uploaded_by' => $user->id,
        ]);

        $counts = $this->service->purgeData($tenant);

        $this->assertSame(1, $counts['companies']);
        $this->assertSame(0, DB::table('companies')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, DB::table('companies')->where('tenant_id', $other->id)->count(), '其他租户数据不受影响');

        // 审计日志合规保留
        $this->assertGreaterThan(0, AuditLog::count());

        // 附件物理文件已删除
        Storage::disk('local')->assertMissing($attPath);
        $this->assertSame(0, DB::table('attachments')->where('tenant_id', $tenant->id)->count());
    }
}
