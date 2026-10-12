<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 活动 / 附件 REST API 测试：CRUD、多态关联租户校验、附件下载认证、跨租户隔离。
 */
class ActivityAttachmentApiTest extends TestCase
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
    // 活动 API
    // ------------------------------------------------------------------

    public function test_can_create_and_list_activity(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create();

        $response = $this->jsonWithToken()->postJson('/api/v1/activities', [
            'type' => 'call',
            'subject' => '电话回访',
            'notes' => '客户有意向',
            'user_id' => $this->user->id,
            'activitable_type' => 'company',
            'activitable_id' => $company->id,
        ])->assertCreated();

        $this->assertSame('电话回访', $response->json('data.subject'));

        $this->jsonWithToken()->getJson('/api/v1/activities')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_activity_can_link_tenant_company(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create();

        $this->jsonWithToken()->postJson('/api/v1/activities', [
            'type' => 'meeting',
            'subject' => '商务会谈',
            'activitable_type' => 'company',
            'activitable_id' => $company->id,
        ])->assertCreated();

        $activity = Activity::query()->firstOrFail();
        $this->assertSame(Company::class, $activity->activitable_type);
        $this->assertSame($company->id, $activity->activitable_id);
    }

    public function test_activity_rejects_morph_target_of_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCompany = Company::factory()->for($otherTenant, 'tenant')->create();

        $this->jsonWithToken()->postJson('/api/v1/activities', [
            'type' => 'call',
            'subject' => '跨租户注入尝试',
            'activitable_type' => 'company',
            'activitable_id' => $foreignCompany->id,
        ])->assertStatus(422);
    }

    public function test_activity_rejects_unknown_morph_type(): void
    {
        $this->jsonWithToken()->postJson('/api/v1/activities', [
            'type' => 'call',
            'subject' => '非法多态',
            'activitable_type' => 'tenant',
            'activitable_id' => 1,
        ])->assertStatus(422);
    }

    public function test_activity_owner_permission_for_sales(): void
    {
        $sales = User::factory()->for($this->tenant, 'tenant')->create([
            'role' => 'sales',
            'email_verified_at' => now(),
        ]);
        $otherUser = User::factory()->for($this->tenant, 'tenant')->create([
            'role' => 'sales',
            'email_verified_at' => now(),
        ]);

        $activity = Activity::factory()->for($this->tenant, 'tenant')->create([
            'user_id' => $otherUser->id,
            'subject' => '别人的活动',
        ]);

        $salesToken = $sales->createToken('', ['read', 'write'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$salesToken}"])
            ->putJson("/api/v1/activities/{$activity->id}", ['subject' => '篡改'])
            ->assertForbidden();
    }

    public function test_activity_isolated_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Activity::factory()->for($otherTenant, 'tenant')->create([
            'user_id' => $this->user->id,
            'subject' => 'B 活动',
        ]);

        $this->jsonWithToken()->getJson("/api/v1/activities/{$foreign->id}")
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // 附件 API
    // ------------------------------------------------------------------

    private function createAttachment(Company $company): Attachment
    {
        Storage::disk('local')->put($path = "attachments/{$this->tenant->id}/{$company->id}/test.pdf", 'PDF-CONTENT');

        return Attachment::create([
            'tenant_id' => $this->tenant->id,
            'attachable_type' => Company::class,
            'attachable_id' => $company->id,
            'original_name' => '报价单.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 11,
            'uploaded_by' => $this->user->id,
        ]);
    }

    public function test_can_list_and_filter_attachments(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create();
        $attachment = $this->createAttachment($company);

        $this->jsonWithToken()->getJson('/api/v1/attachments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_name', '报价单.pdf');

        // 类型过滤
        $this->jsonWithToken()->getJson('/api/v1/attachments?attachable_type=company')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 非法类型过滤被拒绝
        $this->jsonWithToken()->getJson('/api/v1/attachments?attachable_type=tenant_webhook')
            ->assertStatus(422);
    }

    public function test_can_download_attachment_with_auth(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create();
        $attachment = $this->createAttachment($company);

        $this->jsonWithToken()
            ->get("/api/v1/attachments/{$attachment->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_attachment_download_requires_auth(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create();
        $attachment = $this->createAttachment($company);

        // 未认证 401（独立测试，避免 default headers 泄漏）
        $this->getJson("/api/v1/attachments/{$attachment->id}/download")
            ->assertUnauthorized();
    }

    public function test_attachment_download_isolated_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCompany = Company::factory()->for($otherTenant, 'tenant')->create();

        Storage::disk('local')->put($path = "attachments/{$otherTenant->id}/{$otherCompany->id}/b.pdf", 'B');
        $foreign = Attachment::create([
            'tenant_id' => $otherTenant->id,
            'attachable_type' => Company::class,
            'attachable_id' => $otherCompany->id,
            'original_name' => 'b.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 1,
            'uploaded_by' => $this->user->id,
        ]);

        $this->jsonWithToken()
            ->get("/api/v1/attachments/{$foreign->id}/download")
            ->assertNotFound();
    }
}
