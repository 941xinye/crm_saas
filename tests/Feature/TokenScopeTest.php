<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Token scope（abilities）测试。
 * 覆盖：token.scope 中间件按 HTTP 方法检查 ability（GET→read，写→write），
 *       缺少对应 scope 返回 403，双 scope 正常读写。
 */
class TokenScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant, 'tenant')->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function tokenHeader(string $token)
    {
        return $this->withHeaders(['Authorization' => "Bearer {$token}"]);
    }

    public function test_read_only_token_can_list_and_view(): void
    {
        $token = $this->user->createToken('report', ['read'])->plainTextToken;

        $this->tokenHeader($token)->getJson('/api/v1/companies')->assertOk();
    }

    public function test_read_only_token_cannot_create(): void
    {
        $token = $this->user->createToken('report', ['read'])->plainTextToken;

        $this->tokenHeader($token)->postJson('/api/v1/companies', [
            'name' => '新公司',
        ])->assertForbidden();
    }

    public function test_read_only_token_cannot_update_or_delete(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create();
        $token = $this->user->createToken('report', ['read'])->plainTextToken;

        $this->tokenHeader($token)->putJson("/api/v1/companies/{$company->id}", [
            'name' => '改名',
        ])->assertForbidden();

        $this->tokenHeader($token)->deleteJson("/api/v1/companies/{$company->id}")
            ->assertForbidden();
    }

    public function test_write_only_token_can_create_but_cannot_list(): void
    {
        $token = $this->user->createToken('ingest', ['write'])->plainTextToken;

        $this->tokenHeader($token)->postJson('/api/v1/companies', [
            'name' => '新公司',
        ])->assertCreated();

        $this->tokenHeader($token)->getJson('/api/v1/companies')->assertForbidden();
    }

    public function test_full_scope_token_can_read_and_write(): void
    {
        $token = $this->user->createToken('api', ['read', 'write'])->plainTextToken;

        $this->tokenHeader($token)->getJson('/api/v1/companies')->assertOk();
        $this->tokenHeader($token)->postJson('/api/v1/companies', [
            'name' => '新公司',
        ])->assertCreated();
    }

    public function test_login_issued_token_has_full_scopes(): void
    {
        // login 端点签发的 Token 默认含 read/write（向后兼容）
        $plain = $this->postJson('/api/v1/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        $this->tokenHeader($plain)->getJson('/api/v1/companies')->assertOk();
        $this->tokenHeader($plain)->postJson('/api/v1/companies', [
            'name' => '新公司',
        ])->assertCreated();
    }

    public function test_empty_scope_token_cannot_access_anything(): void
    {
        // 空 abilities 的 Token 既不能读也不能写
        $token = $this->user->createToken('blocked', [])->plainTextToken;

        $this->tokenHeader($token)->getJson('/api/v1/companies')->assertForbidden();
        $this->tokenHeader($token)->postJson('/api/v1/companies', [
            'name' => '新公司',
        ])->assertForbidden();
    }
}
