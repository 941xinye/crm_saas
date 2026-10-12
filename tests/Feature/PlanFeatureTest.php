<?php

namespace Tests\Feature;

use App\Livewire\Opportunities\Show;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanService;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 订阅计划功能权限（plan.feature）测试：
 * PlanService 功能矩阵、EnsurePlanHasFeature 中间件（web 重定向 + API 403 JSON）。
 * 侧边栏菜单按套餐过滤、AI 跟进建议功能拦截。
 */
class PlanFeatureTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $plans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plans = app(PlanService::class);
    }

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => '测试租户',
            'slug' => 't-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ], $overrides));
    }

    private function createTenantUser(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    /** 创建带 Stage 的商机（OpportunityFactory 不兜底 stage_id，必须显式提供） */
    private function createOpportunity(Tenant $tenant, User $owner): Opportunity
    {
        $stage = \App\Models\Stage::factory()->for($tenant, 'tenant')->create();

        return Opportunity::factory()->for($tenant, 'tenant')->for($stage)->create([
            'owner_id' => $owner->id,
        ]);
    }

    // ------------------------------------------------------------------
    // PlanService::hasFeature 功能矩阵
    // ------------------------------------------------------------------

    public function test_free_plan_has_basic_features_only(): void
    {
        $tenant = $this->createTenant(['plan' => 'free']);

        $this->assertTrue($this->plans->hasFeature($tenant, 'dashboard'));
        $this->assertTrue($this->plans->hasFeature($tenant, 'opportunities'));
        $this->assertTrue($this->plans->hasFeature($tenant, 'opportunities.board'));

        $this->assertFalse($this->plans->hasFeature($tenant, 'reports'));
        $this->assertFalse($this->plans->hasFeature($tenant, 'audit-logs'));
        $this->assertFalse($this->plans->hasFeature($tenant, 'ai_followup'));
        $this->assertFalse($this->plans->hasFeature($tenant, 'settings.team'));
        $this->assertFalse($this->plans->hasFeature($tenant, 'webhooks'));
        $this->assertFalse($this->plans->hasFeature($tenant, 'automation'));
    }

    public function test_pro_plan_unlocks_reports_audit_ai_team(): void
    {
        $tenant = $this->createTenant(['plan' => 'pro']);

        $this->assertTrue($this->plans->hasFeature($tenant, 'reports'));
        $this->assertTrue($this->plans->hasFeature($tenant, 'audit-logs'));
        $this->assertTrue($this->plans->hasFeature($tenant, 'ai_followup'));
        $this->assertTrue($this->plans->hasFeature($tenant, 'settings.team'));

        // 企业版专属仍未解锁
        $this->assertFalse($this->plans->hasFeature($tenant, 'webhooks'));
        $this->assertFalse($this->plans->hasFeature($tenant, 'automation'));
    }

    public function test_enterprise_plan_unlocks_everything(): void
    {
        $tenant = $this->createTenant(['plan' => 'enterprise']);

        foreach (['reports', 'audit-logs', 'ai_followup', 'settings.team', 'webhooks', 'automation'] as $feature) {
            $this->assertTrue($this->plans->hasFeature($tenant, $feature), "enterprise 应解锁 {$feature}");
        }
    }

    public function test_null_tenant_grants_all_features_for_platform_admin_context(): void
    {
        $this->assertTrue($this->plans->hasFeature(null, 'reports'));
        $this->assertTrue($this->plans->hasFeature(null, 'webhooks'));
        $this->assertTrue($this->plans->hasFeature(null, 'anything-unknown'));
    }

    public function test_unknown_feature_is_denied_for_real_plans(): void
    {
        $tenant = $this->createTenant(['plan' => 'enterprise']);

        $this->assertFalse($this->plans->hasFeature($tenant, 'not-a-feature'));
    }

    public function test_upgrade_message_returns_configured_text(): void
    {
        $this->assertSame('Webhook 推送功能仅对企业版开放，请升级套餐。', $this->plans->upgradeMessage('webhooks'));
        $this->assertSame('数据报表功能仅对专业版及以上开放，请升级套餐。', $this->plans->upgradeMessage('reports'));

        // 未知 feature 返回默认文案
        $this->assertSame('当前套餐不支持此功能，请升级后使用。', $this->plans->upgradeMessage('unknown-x'));
    }

    // ------------------------------------------------------------------
    // EnsurePlanHasFeature 中间件（Web）
    // ------------------------------------------------------------------

    public function test_free_user_web_request_to_reports_redirects_to_dashboard_with_error(): void
    {
        $tenant = $this->createTenant(['plan' => 'free']);
        $this->actingAs($this->createTenantUser($tenant));

        $this->get(route('reports'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', '数据报表功能仅对专业版及以上开放，请升级套餐。');
    }

    public function test_free_user_web_request_to_audit_logs_redirects(): void
    {
        $tenant = $this->createTenant(['plan' => 'free']);
        $this->actingAs($this->createTenantUser($tenant));

        $this->get(route('audit-logs'))->assertRedirect(route('dashboard'));
    }

    public function test_free_user_web_request_to_team_settings_redirects(): void
    {
        $tenant = $this->createTenant(['plan' => 'free']);
        $this->actingAs($this->createTenantUser($tenant));

        $this->get(route('settings.team'))->assertRedirect(route('dashboard'));
    }

    public function test_pro_user_can_access_reports_page(): void
    {
        $tenant = $this->createTenant(['plan' => 'pro']);
        $this->actingAs($this->createTenantUser($tenant));

        $this->get(route('reports'))->assertOk();
    }

    public function test_pro_tenant_api_webhooks_returns_403(): void
    {
        $tenant = $this->createTenant(['plan' => 'pro']);
        $token = $this->createTenantUser($tenant)->createToken('', ['read', 'write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/webhooks')
            ->assertStatus(403)
            ->assertJsonPath('feature', 'webhooks')
            ->assertJsonPath('current_plan', 'pro');
    }

    // ------------------------------------------------------------------
    // EnsurePlanHasFeature 中间件（API / Sanctum）
    // ------------------------------------------------------------------

    public function test_free_tenant_api_webhooks_returns_friendly_403_json(): void
    {
        $tenant = $this->createTenant(['plan' => 'free']);
        $token = $this->createTenantUser($tenant)->createToken('', ['read', 'write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/webhooks')
            ->assertStatus(403)
            ->assertJsonPath('feature', 'webhooks')
            ->assertJsonPath('current_plan', 'free')
            ->assertJsonPath('message', 'Webhook 推送功能仅对企业版开放，请升级套餐。');
    }

    public function test_enterprise_tenant_api_webhooks_allowed(): void
    {
        $tenant = $this->createTenant(['plan' => 'enterprise']);
        $token = $this->createTenantUser($tenant)->createToken('', ['read', 'write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/webhooks')
            ->assertOk();
    }

    public function test_unauthenticated_api_request_still_returns_401_not_plan_403(): void
    {
        // 未登录请求必须仍由 auth 中间件返回 401，plan.feature 不应放行也不应改变语义
        $this->getJson('/api/v1/webhooks')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    // ------------------------------------------------------------------
    // 侧边栏菜单按套餐过滤
    // ------------------------------------------------------------------

    public function test_free_user_sidebar_hides_pro_and_enterprise_menus(): void
    {
        $tenant = $this->createTenant(['plan' => 'free']);
        $this->actingAs($this->createTenantUser($tenant));

        $this->get(route('dashboard'))
            ->assertOk()
            // 无权限菜单：链接整体不渲染
            ->assertDontSee('href="' . route('reports') . '"', false)
            ->assertDontSee('href="' . route('audit-logs') . '"', false)
            ->assertDontSee('href="' . route('settings.team') . '"', false)
            // 基础菜单仍在
            ->assertSee('href="' . route('companies.index') . '"', false);
    }

    public function test_pro_user_sidebar_shows_pro_menus_but_not_enterprise(): void
    {
        $tenant = $this->createTenant(['plan' => 'pro']);
        $this->actingAs($this->createTenantUser($tenant));

        // pro 拥有报表/审计；webhooks 无独立侧边栏菜单，故以 reports 为准
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('reports') . '"', false)
            ->assertSee('href="' . route('audit-logs') . '"', false)
            ->assertSee('href="' . route('settings.team') . '"', false);
    }

    // ------------------------------------------------------------------
    // AI 跟进建议功能拦截（Livewire 组件级）
    // ------------------------------------------------------------------

    public function test_free_tenant_ai_followup_shows_upgrade_message(): void
    {
        $tenant = $this->createTenant(['plan' => 'free']);
        $user = $this->createTenantUser($tenant);
        $this->actingAs($user);
        app(Tenancy::class)->forUser($user);

        $opportunity = $this->createOpportunity($tenant, $user);

        Livewire::test(Show::class, ['opportunity' => $opportunity])
            ->call('generateFollowUp')
            ->assertSet('aiMessage', 'AI 跟进建议功能仅对专业版及以上开放，请升级套餐。')
            ->assertSet('suggestion', null);
    }

    public function test_pro_tenant_ai_followup_passes_feature_gate(): void
    {
        $tenant = $this->createTenant(['plan' => 'pro']);
        $user = $this->createTenantUser($tenant);
        $this->actingAs($user);
        app(Tenancy::class)->forUser($user);

        $opportunity = $this->createOpportunity($tenant, $user);

        Livewire::test(Show::class, ['opportunity' => $opportunity])
            ->call('generateFollowUp')
            // 通过 plan.feature gate：提示不再是升级文案（LLM 未配置 → 功能未启用提示）
            ->assertSet('aiMessage', fn ($message) => $message !== $this->plans->upgradeMessage('ai_followup'));
    }

    public function test_free_tenant_ai_panel_hidden_in_view(): void
    {
        $tenant = $this->createTenant(['plan' => 'free']);
        $user = $this->createTenantUser($tenant);
        $this->actingAs($user);
        app(Tenancy::class)->forUser($user);

        $opportunity = $this->createOpportunity($tenant, $user);

        Livewire::test(Show::class, ['opportunity' => $opportunity])
            ->assertDontSee('AI 跟进邮件建议');
    }

    public function test_pro_tenant_ai_panel_visible_in_view(): void
    {
        $tenant = $this->createTenant(['plan' => 'pro']);
        $user = $this->createTenantUser($tenant);
        $this->actingAs($user);
        app(Tenancy::class)->forUser($user);

        $opportunity = $this->createOpportunity($tenant, $user);

        Livewire::test(Show::class, ['opportunity' => $opportunity])
            ->assertSee('AI 跟进邮件建议');
    }
}
