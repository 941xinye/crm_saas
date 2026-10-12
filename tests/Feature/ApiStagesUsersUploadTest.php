<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 只读发现端点（stages / users）与附件上传 API 测试。
 * 覆盖：租户隔离、敏感字段脱敏、上传校验与跨租户注入防护。
 */
class ApiStagesUsersUploadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant, 'tenant')->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->token = $this->user->createToken('', ['read', 'write'])->plainTextToken;
    }

    private function jsonWithToken()
    {
        return $this->withHeaders(['Authorization' => "Bearer {$this->token}"]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/stages（只读）
    // ------------------------------------------------------------------

    public function test_stages_lists_only_current_tenant(): void
    {
        Stage::factory()->for($this->tenant, 'tenant')->create(['name' => '初步接触']);
        $otherTenant = Tenant::factory()->create();
        Stage::factory()->for($otherTenant, 'tenant')->create(['name' => '别家阶段']);

        $this->jsonWithToken()->getJson('/api/v1/stages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', '初步接触');
    }

    // ------------------------------------------------------------------
    // GET /api/v1/users（只读，不含邮箱）
    // ------------------------------------------------------------------

    public function test_users_lists_tenant_members_without_email(): void
    {
        User::factory()->for($this->tenant, 'tenant')->create(['role' => 'sales']);
        $otherTenant = Tenant::factory()->create();
        User::factory()->for($otherTenant, 'tenant')->create(['role' => 'admin']);

        $response = $this->jsonWithToken()->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // 只暴露 id / name / role，不泄露邮箱
        $this->assertArrayNotHasKey('email', $response->json('data.0'));
    }

    // ------------------------------------------------------------------
    // POST /api/v1/attachments（上传）
    // ------------------------------------------------------------------

    public function test_can_upload_attachment_via_api(): void
    {
        Storage::fake('local');
        $company = Company::factory()->for($this->tenant, 'tenant')->create();

        $response = $this->jsonWithToken()->post('/api/v1/attachments', [
            'file' => UploadedFile::fake()->create('报价单.pdf', 20, 'application/pdf'),
            'attachable_type' => 'company',
            'attachable_id' => $company->id,
        ])->assertCreated();

        $path = $response->json('data.file_path');
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->assertDatabaseHas('attachments', [
            'tenant_id' => $this->tenant->id,
            'attachable_type' => Company::class,
            'attachable_id' => $company->id,
            'original_name' => '报价单.pdf',
            'uploaded_by' => $this->user->id,
        ]);
    }

    public function test_upload_rejects_disallowed_extension(): void
    {
        Storage::fake('local');
        $company = Company::factory()->for($this->tenant, 'tenant')->create();

        $this->jsonWithToken()->post('/api/v1/attachments', [
            'file' => UploadedFile::fake()->create('malware.exe', 10),
            'attachable_type' => 'company',
            'attachable_id' => $company->id,
        ])->assertStatus(422);

        $this->assertSame(0, Attachment::count());
    }

    public function test_upload_rejects_cross_tenant_target(): void
    {
        Storage::fake('local');
        $otherTenant = Tenant::factory()->create();
        $foreignCompany = Company::factory()->for($otherTenant, 'tenant')->create();

        $this->jsonWithToken()->post('/api/v1/attachments', [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            'attachable_type' => 'company',
            'attachable_id' => $foreignCompany->id,
        ])->assertNotFound();

        $this->assertSame(0, Attachment::count());
    }
}
