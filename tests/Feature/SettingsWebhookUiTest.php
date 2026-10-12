<?php

namespace Tests\Feature;

use App\Livewire\Settings\Webhooks;
use App\Models\Tenant;
use App\Models\TenantWebhook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Webhook 管理 UI 测试：
 * 页面套餐门控（enterprise 专属）、admin/manager 可管理、sales 只读、
 * 创建/编辑/删除/启停/重置 Secret/测试推送、事件名白名单校验。
 */
class SettingsWebhookUiTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $plan = 'enterprise'): Tenant
    {
        return Tenant::create([
            'name' => '测试租户',
            'slug' => 't-' . Str::lower(Str::random(8)),
            'plan' => $plan,
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

    // ------------------------------------------------------------------
    // 页面访问与套餐门控
    // ------------------------------------------------------------------

    public function test_enterprise_admin_can_open_webhooks_page(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant);

        $this->actingAs($user)
            ->get(route('settings.webhooks'))
            ->assertOk()
            ->assertSee('Webhook 管理');
    }

    public function test_pro_plan_is_rejected_by_feature_gate(): void
    {
        $tenant = $this->createTenant('pro'); // pro 无 webhooks feature
        $user = $this->createUser($tenant);

        $this->actingAs($user)
            ->get(route('settings.webhooks'))
            ->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------------------
    // 创建 / 校验
    // ------------------------------------------------------------------

    public function test_admin_can_create_webhook_with_generated_secret(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'admin');

        Livewire::actingAs($user)
            ->test(Webhooks::class)
            ->call('showCreateForm')
            ->set('name', 'ERP 同步')
            ->set('url', 'https://erp.example.com/hooks')
            ->set('selectedEvents', ['company.created', 'opportunity.updated'])
            ->set('isActive', true)
            ->call('save')
            ->assertHasNoErrors();

        $webhook = TenantWebhook::query()->where('name', 'ERP 同步')->first();
        $this->assertNotNull($webhook);
        $this->assertSame('https://erp.example.com/hooks', $webhook->url);
        $this->assertSame(['company.created', 'opportunity.updated'], $webhook->events);
        $this->assertTrue($webhook->is_active);
        $this->assertStringStartsWith('whsec_', $webhook->secret);
    }

    public function test_create_rejects_invalid_event_names(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'admin');

        Livewire::actingAs($user)
            ->test(Webhooks::class)
            ->call('showCreateForm')
            ->set('name', '恶意配置')
            ->set('url', 'https://evil.example.com/hooks')
            ->set('selectedEvents', ['tenant_webhook.deleted']) // 白名单外事件
            ->call('save')
            ->assertHasErrors(['selectedEvents.0']);

        $this->assertNull(TenantWebhook::query()->where('name', '恶意配置')->first());
    }

    public function test_create_requires_at_least_one_event(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'admin');

        Livewire::actingAs($user)
            ->test(Webhooks::class)
            ->call('showCreateForm')
            ->set('name', '无事件')
            ->set('url', 'https://erp.example.com/hooks')
            ->set('selectedEvents', [])
            ->call('save')
            ->assertHasErrors(['selectedEvents']);
    }

    public function test_sales_cannot_manage_webhooks(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'sales');

        Livewire::actingAs($user)
            ->test(Webhooks::class)
            ->call('showCreateForm')
            ->set('name', '越权创建')
            ->set('url', 'https://erp.example.com/hooks')
            ->set('selectedEvents', ['company.created'])
            ->call('save');

        $this->assertNull(TenantWebhook::query()->where('name', '越权创建')->first());
    }

    public function test_manager_can_manage_webhooks(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'manager');

        Livewire::actingAs($user)
            ->test(Webhooks::class)
            ->call('showCreateForm')
            ->set('name', '经理创建')
            ->set('url', 'https://erp.example.com/hooks')
            ->set('selectedEvents', ['lead.created'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(TenantWebhook::query()->where('name', '经理创建')->first());
    }

    // ------------------------------------------------------------------
    // 编辑 / 删除 / 启停 / 重置 Secret
    // ------------------------------------------------------------------

    public function test_admin_can_edit_webhook(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'admin');
        $webhook = TenantWebhook::factory()->for($tenant, 'tenant')->create([
            'name' => '旧名称',
            'events' => ['company.created'],
        ]);

        Livewire::actingAs($user)
            ->test(Webhooks::class)
            ->call('edit', $webhook->id)
            ->set('name', '新名称')
            ->set('selectedEvents', ['company.created', 'company.deleted'])
            ->call('save')
            ->assertHasNoErrors();

        $webhook->refresh();
        $this->assertSame('新名称', $webhook->name);
        $this->assertSame(['company.created', 'company.deleted'], $webhook->events);
    }

    public function test_admin_can_delete_and_toggle_webhook(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'admin');
        $webhook = TenantWebhook::factory()->for($tenant, 'tenant')->create(['is_active' => true]);

        Livewire::actingAs($user)->test(Webhooks::class)->call('toggleActive', $webhook->id);
        $this->assertFalse($webhook->refresh()->is_active);

        Livewire::actingAs($user)->test(Webhooks::class)->call('delete', $webhook->id);
        $this->assertDatabaseMissing('tenant_webhooks', ['id' => $webhook->id]);
    }

    public function test_reset_secret_generates_new_secret(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'admin');
        $webhook = TenantWebhook::factory()->for($tenant, 'tenant')->create(['secret' => 'whsec_old']);

        Livewire::actingAs($user)->test(Webhooks::class)->call('resetSecret', $webhook->id);

        $webhook->refresh();
        $this->assertNotSame('whsec_old', $webhook->secret);
        $this->assertStringStartsWith('whsec_', $webhook->secret);
    }

    // ------------------------------------------------------------------
    // 测试推送
    // ------------------------------------------------------------------

    public function test_send_test_event_posts_signed_payload(): void
    {
        Http::fake(['*' => Http::response(['ok'], 200)]);

        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'admin');
        $webhook = TenantWebhook::factory()->for($tenant, 'tenant')->create([
            'url' => 'https://erp.example.com/hooks',
        ]);

        Livewire::actingAs($user)
            ->test(Webhooks::class)
            ->call('sendTest', $webhook->id);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($webhook) {
            $timestamp = $request->header('X-CRM-Timestamp')[0];
            $signature = $request->header('X-CRM-Signature')[0];
            $expected = hash_hmac('sha256', $timestamp . '.' . $request->body(), $webhook->secret);

            return $request->url() === $webhook->url
                && $request->header('X-CRM-Event')[0] === 'webhook.test'
                && hash_equals($expected, $signature);
        });

        $this->assertSame(0, $webhook->refresh()->failure_count);
    }

    public function test_send_test_failure_increments_failure_count(): void
    {
        Http::fake(['*' => Http::response('server error', 500)]);

        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'admin');
        $webhook = TenantWebhook::factory()->for($tenant, 'tenant')->create(['failure_count' => 0]);

        Livewire::actingAs($user)
            ->test(Webhooks::class)
            ->call('sendTest', $webhook->id);

        $this->assertSame(1, $webhook->refresh()->failure_count);
    }

    // ------------------------------------------------------------------
    // 租户隔离
    // ------------------------------------------------------------------

    public function test_cannot_edit_webhook_of_other_tenant(): void
    {
        $tenantA = $this->createTenant('enterprise');
        $tenantB = $this->createTenant('enterprise');
        $userB = $this->createUser($tenantB, 'admin');
        $webhookA = TenantWebhook::factory()->for($tenantA, 'tenant')->create(['name' => 'A 租户']);

        // tenantB 的管理员编辑 tenantA 的 webhook：findOrFail 被 TenantScope 过滤，应 404
        $this->assertThrows(
            fn () => Livewire::actingAs($userB)
                ->test(Webhooks::class)
                ->call('edit', $webhookA->id),
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        );

        $this->assertSame('A 租户', $webhookA->refresh()->name);
    }
}
