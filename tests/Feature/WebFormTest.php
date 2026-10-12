<?php

namespace Tests\Feature;

use App\Livewire\Settings\TenantProfile;
use App\Livewire\WebForm\LeadWebForm;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanService;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 公共线索表单（web-to-lead）测试：
 * 设置页生成/重置链接、游客提交自动建 Lead、蜜罐拦截、
 * 配额前置拦截、无效/停用 key 拒绝、非管理员禁止操作。
 */
class WebFormTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => '表单租户',
            'slug' => 't-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ], $overrides));
    }

    private function actingAsTenantUser(Tenant $tenant, string $role = 'admin'): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
        $this->actingAs($user);
        app(Tenancy::class)->forUser($user);

        return $user;
    }

    // ------------------------------------------------------------------
    // 设置页：生成 / 重置
    // ------------------------------------------------------------------

    public function test_admin_can_generate_lead_form_key(): void
    {
        $tenant = $this->createTenant();
        $this->actingAsTenantUser($tenant);

        Livewire::test(TenantProfile::class)
            ->call('generateLeadFormKey');

        $tenant->refresh();
        $this->assertNotNull($tenant->lead_form_key);
        // UUID 格式（防枚举）
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $tenant->lead_form_key);
    }

    public function test_admin_can_revoke_lead_form_key(): void
    {
        $tenant = $this->createTenant(['lead_form_key' => (string) Str::uuid()]);
        $this->actingAsTenantUser($tenant);

        Livewire::test(TenantProfile::class)
            ->call('revokeLeadFormKey');

        $this->assertNull($tenant->fresh()->lead_form_key);
    }

    public function test_non_admin_cannot_generate_or_revoke(): void
    {
        $tenant = $this->createTenant();
        $this->actingAsTenantUser($tenant, 'sales');

        Livewire::test(TenantProfile::class)->call('generateLeadFormKey');
        $this->assertNull($tenant->fresh()->lead_form_key);

        $tenant->update(['lead_form_key' => (string) Str::uuid()]);
        Livewire::test(TenantProfile::class)->call('revokeLeadFormKey');
        $this->assertNotNull($tenant->fresh()->lead_form_key);
    }

    public function test_reset_key_invalidates_old_link(): void
    {
        $tenant = $this->createTenant();
        $oldKey = (string) Str::uuid();
        $tenant->update(['lead_form_key' => $oldKey]);

        $this->actingAsTenantUser($tenant);
        Livewire::test(TenantProfile::class)->call('revokeLeadFormKey');
        Livewire::test(TenantProfile::class)->call('generateLeadFormKey');

        $this->assertNotSame($oldKey, $tenant->fresh()->lead_form_key);
        // 旧 key 已查不到任何租户
        $this->assertNull(Tenant::where('lead_form_key', $oldKey)->first());
    }

    // ------------------------------------------------------------------
    // 公共表单页（游客）
    // ------------------------------------------------------------------

    public function test_public_form_renders_for_valid_key(): void
    {
        $tenant = $this->createTenant(['lead_form_key' => (string) Str::uuid()]);

        $this->get(route('web-form.lead', ['key' => $tenant->lead_form_key]))
            ->assertOk()
            ->assertSee('联系我们')
            ->assertSee($tenant->name);
    }

    public function test_invalid_key_shows_unavailable(): void
    {
        $this->get(route('web-form.lead', ['key' => Str::uuid()]))
            ->assertOk()
            ->assertSee('表单暂不可用');
    }

    public function test_inactive_tenant_shows_unavailable(): void
    {
        $tenant = $this->createTenant(['lead_form_key' => (string) Str::uuid(), 'is_active' => false]);

        $this->get(route('web-form.lead', ['key' => $tenant->lead_form_key]))
            ->assertOk()
            ->assertSee('表单暂不可用');
    }

    public function test_guest_submission_creates_lead_with_web_form_source(): void
    {
        $tenant = $this->createTenant(['lead_form_key' => (string) Str::uuid()]);

        Livewire::test(LeadWebForm::class, ['key' => $tenant->lead_form_key])
            ->set('name', '网站访客王五')
            ->set('email', 'visitor@example.com')
            ->set('companyName', '访客公司')
            ->set('phone', '13800000000')
            ->call('submit')
            ->assertHasNoErrors();

        $lead = Lead::withoutTenant()->where('email', 'visitor@example.com')->first();
        $this->assertNotNull($lead);
        $this->assertSame($tenant->id, $lead->tenant_id);
        $this->assertSame('web_form', $lead->source);
        $this->assertSame('new', $lead->status);
    }

    public function test_validation_rejects_invalid_email(): void
    {
        $tenant = $this->createTenant(['lead_form_key' => (string) Str::uuid()]);

        Livewire::test(LeadWebForm::class, ['key' => $tenant->lead_form_key])
            ->set('name', '张三')
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['email']);

        $this->assertSame(0, Lead::withoutTenant()->where('tenant_id', $tenant->id)->count());
    }

    // ------------------------------------------------------------------
    // 防滥用
    // ------------------------------------------------------------------

    public function test_honeypot_blocks_bot_submission(): void
    {
        $tenant = $this->createTenant(['lead_form_key' => (string) Str::uuid()]);

        Livewire::test(LeadWebForm::class, ['key' => $tenant->lead_form_key])
            ->set('name', '机器人')
            ->set('email', 'bot@spam.com')
            ->set('website', 'http://spam.example')  // 蜜罐被填写 → 机器人
            ->call('submit');

        $this->assertSame(0, Lead::withoutTenant()->where('tenant_id', $tenant->id)->count());
    }

    public function test_quota_reached_blocks_submission(): void
    {
        $tenant = $this->createTenant(['lead_form_key' => (string) Str::uuid()]);
        $quota = app(PlanService::class)->quota($tenant, 'leads');
        Lead::factory()->for($tenant, 'tenant')->count($quota)->create();

        Livewire::test(LeadWebForm::class, ['key' => $tenant->lead_form_key])
            ->set('name', '超额访客')
            ->set('email', 'over@quota.com')
            ->call('submit');

        $this->assertSame($quota, Lead::withoutTenant()->where('tenant_id', $tenant->id)->count());
        $this->assertNull(Lead::withoutTenant()->where('email', 'over@quota.com')->first());
    }

    public function test_expired_trial_blocks_submission(): void
    {
        $tenant = $this->createTenant([
            'lead_form_key' => (string) Str::uuid(),
            'plan' => 'free',
            'trial_ends_at' => now()->subDay(),
        ]);

        Livewire::test(LeadWebForm::class, ['key' => $tenant->lead_form_key])
            ->set('name', '过期访客')
            ->set('email', 'expired@trial.com')
            ->call('submit');

        $this->assertNull(Lead::withoutTenant()->where('email', 'expired@trial.com')->first());
    }
}
