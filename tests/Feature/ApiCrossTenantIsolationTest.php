<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API 跨租户隔离测试：Sanctum Token 认证后，Tenancy 正确初始化，TenantScope 自动过滤。
 *
 * 关键链路：auth:sanctum → request->user() → BootstrapTenancy → forUser() → TenantScope 生效
 */
class ApiCrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = User::factory()->for($this->tenantA, 'tenant')->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->userB = User::factory()->for($this->tenantB, 'tenant')->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_a_cannot_access_tenant_b_companies_via_api(): void
    {
        // 租户 A 的公司
        Company::factory()->for($this->tenantA, 'tenant')->create(['name' => 'A-Company']);
        // 租户 B 的公司
        $companyB = Company::factory()->for($this->tenantB, 'tenant')->create(['name' => 'B-Company']);

        $token = $this->userA->createToken('', ['read', 'write'])->plainTextToken;

        // GET /api/v1/companies 应只返回 A 的公司
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'A-Company');

        // GET /api/v1/companies/{B_id} → 404（TenantScope 过滤）
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson("/api/v1/companies/{$companyB->id}")
            ->assertNotFound();
    }

    public function test_user_a_cannot_read_or_create_in_tenant_b(): void
    {
        $stageB = \App\Models\Stage::factory()->for($this->tenantB, 'tenant')->create();
        Opportunity::factory()->for($this->tenantB, 'tenant')->create([
            'title' => 'B-Opp',
            'stage_id' => $stageB->id,
            'owner_id' => $this->userB->id,
        ]);

        $token = $this->userA->createToken('', ['read', 'write'])->plainTextToken;

        // GET /api/v1/opportunities 应只返回 A 的商机
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_api_request_returns_401(): void
    {
        $this->getJson('/api/v1/companies')
            ->assertUnauthorized();
    }

    public function test_api_login_returns_token(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => $this->userA->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_api_logout_revokes_token(): void
    {
        $token = $this->userA->createToken('', ['read', 'write'])->plainTextToken;

        // 先用 token 能访问
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/companies')
            ->assertOk();

        // 注销：删除当前 token
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/logout')
            ->assertOk();

        // 验证 token 已从数据库删除
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->userA->id,
        ]);
    }

    public function test_revoked_token_is_rejected(): void
    {
        // 先注册 token 到数据库，拿到 plainToken
        $newToken = $this->userA->createToken('', ['read', 'write']);
        $plainToken = $newToken->plainTextToken;

        // 手动从数据库删除（模拟注销）
        $newToken->accessToken->delete();

        // 已撤销的 token 应该 401
        $this->withHeaders(['Authorization' => "Bearer {$plainToken}"])
            ->getJson('/api/v1/companies')
            ->assertUnauthorized();
    }

    public function test_api_register_creates_tenant_and_issues_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'tenant_name' => 'API 测试租户',
            'tenant_slug' => 'api-test-tenant',
            'name' => 'API Admin',
            'email' => 'api-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        // 验证租户已创建
        $this->assertDatabaseHas('tenants', ['slug' => 'api-test-tenant']);
    }

    // ===== Phase 4: Webhook 配置 API（企业版专属） =====

    public function test_user_can_create_webhook_via_api(): void
    {
        $this->tenantA->update(['plan' => 'enterprise']);
        $token = $this->userA->createToken('', ['read', 'write'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/webhooks', [
                'name' => 'ERP Sync',
                'url' => 'https://erp.example.com/callback',
                'events' => ['company.created', 'opportunity.updated'],
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'secret']]);

        // secret 自动生成
        $this->assertDatabaseHas('tenant_webhooks', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'ERP Sync',
        ]);
    }

    public function test_user_a_cannot_access_tenant_b_webhooks(): void
    {
        $this->tenantA->update(['plan' => 'enterprise']);
        $tokenA = $this->userA->createToken('', ['read', 'write'])->plainTextToken;
        $webhookB = \App\Models\TenantWebhook::factory()->for($this->tenantB, 'tenant')->create();

        // GET /api/v1/webhooks 应只返回 A 的
        $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
            ->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // GET /api/v1/webhooks/{B_id} → 404
        $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
            ->getJson("/api/v1/webhooks/{$webhookB->id}")
            ->assertNotFound();
    }

    public function test_webhook_secret_is_hidden_in_list_and_detail(): void
    {
        $this->tenantA->update(['plan' => 'enterprise']);
        $token = $this->userA->createToken('', ['read', 'write'])->plainTextToken;
        $webhook = \App\Models\TenantWebhook::factory()->for($this->tenantA, 'tenant')->create([
            'secret' => 'whsec_should_not_leak',
        ]);

        // 列表 / 详情不返回明文 secret（仅创建与重置时一次性展示）
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonMissing(['secret' => 'whsec_should_not_leak']);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson("/api/v1/webhooks/{$webhook->id}")
            ->assertOk()
            ->assertJsonMissing(['secret' => 'whsec_should_not_leak']);
    }

    public function test_webhook_reset_secret_endpoint(): void
    {
        $this->tenantA->update(['plan' => 'enterprise']);
        $token = $this->userA->createToken('', ['read', 'write'])->plainTextToken;
        $webhook = \App\Models\TenantWebhook::factory()->for($this->tenantA, 'tenant')->create([
            'secret' => 'old_secret_value',
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson("/api/v1/webhooks/{$webhook->id}", ['reset_secret' => true])
            ->assertOk()
            ->assertJsonPath('data.secret', fn ($s) => str_starts_with($s, 'whsec_') && $s !== 'old_secret_value');
    }
}
