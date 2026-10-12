<?php

namespace Tests\Feature;

use App\Livewire\Settings\Automation;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\TenantWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 自动化工作流管理 UI 测试：
 * 页面套餐门控（automation feature）、admin/manager 可管理、sales 只读、
 * 规则创建/编辑/删除/启停、条件与动作参数校验、租户隔离。
 */
class SettingsAutomationUiTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $plan = 'enterprise'): Tenant
    {
        return Tenant::create([
            'name' => '自动化测试租户',
            'slug' => 'auto-' . Str::lower(Str::random(8)),
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

    public function test_enterprise_admin_can_open_automation_page(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant);

        $this->actingAs($user)
            ->get(route('settings.automation'))
            ->assertOk()
            ->assertSee('自动化工作流');
    }

    public function test_pro_plan_is_rejected_by_feature_gate(): void
    {
        $tenant = $this->createTenant('pro');
        $user = $this->createUser($tenant);

        $this->actingAs($user)
            ->get(route('settings.automation'))
            ->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------------------
    // 创建 / 校验
    // ------------------------------------------------------------------

    public function test_admin_can_create_rule_with_condition_and_activity_action(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant);

        Livewire::actingAs($user)
            ->test(Automation::class)
            ->call('showCreateForm')
            ->set('name', '商机停滞自动跟进')
            ->set('trigger', 'stalled')
            ->set('conditions', [['field' => 'stalled_days', 'operator' => '>=', 'value' => '14']])
            ->set('actions', [[
                'type' => 'create_activity',
                'activity_type' => 'task',
                'subject' => '跟进停滞商机「{title}」',
                'notes' => '已停滞 {stalled_days} 天',
                'scheduled_at' => '+1 day',
                'message' => '',
                'stage_id' => '',
                'probability' => '',
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $rule = TenantWorkflow::query()->where('name', '商机停滞自动跟进')->first();
        $this->assertNotNull($rule);
        $this->assertSame(Opportunity::class, $rule->model);
        $this->assertSame('stalled', $rule->trigger);
        $this->assertSame([['stalled_days', '>=', 14]], $rule->conditions);
        $this->assertSame('create_activity', $rule->actions[0]['type']);
        $this->assertSame('{activitable_id}', $rule->actions[0]['params']['activitable_id']);
        $this->assertTrue($rule->is_active);
    }

    public function test_create_requires_at_least_one_action(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant);

        Livewire::actingAs($user)
            ->test(Automation::class)
            ->call('showCreateForm')
            ->set('name', '无动作规则')
            ->set('trigger', 'updated')
            ->set('actions', [])
            ->call('save')
            ->assertHasErrors(['actions']);

        $this->assertNull(TenantWorkflow::query()->where('name', '无动作规则')->first());
    }

    public function test_create_rejects_invalid_trigger(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant);

        Livewire::actingAs($user)
            ->test(Automation::class)
            ->call('showCreateForm')
            ->set('name', '非法触发器')
            ->set('trigger', 'deleted') // 白名单外
            ->set('actions', [['type' => 'notify_owner', 'message' => 'x']])
            ->call('save')
            ->assertHasErrors(['trigger']);

        $this->assertNull(TenantWorkflow::query()->where('name', '非法触发器')->first());
    }

    public function test_set_stage_action_requires_stage_id(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant);

        Livewire::actingAs($user)
            ->test(Automation::class)
            ->call('showCreateForm')
            ->set('name', '缺阶段动作')
            ->set('trigger', 'updated')
            ->set('actions', [[
                'type' => 'set_stage',
                'activity_type' => 'task', 'subject' => '', 'notes' => '', 'scheduled_at' => '',
                'message' => '', 'stage_id' => '', 'probability' => '',
            ]])
            ->call('save')
            ->assertHasErrors(['actions.0.stage_id']);

        $this->assertNull(TenantWorkflow::query()->where('name', '缺阶段动作')->first());
    }

    public function test_set_probability_action_validates_range(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant);

        Livewire::actingAs($user)
            ->test(Automation::class)
            ->call('showCreateForm')
            ->set('name', '概率越界')
            ->set('trigger', 'updated')
            ->set('actions', [[
                'type' => 'set_probability',
                'activity_type' => 'task', 'subject' => '', 'notes' => '', 'scheduled_at' => '',
                'message' => '', 'stage_id' => '', 'probability' => '150',
            ]])
            ->call('save')
            ->assertHasErrors(['actions.0.probability']);

        $this->assertNull(TenantWorkflow::query()->where('name', '概率越界')->first());
    }

    public function test_sales_cannot_manage_rules(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'sales');

        Livewire::actingAs($user)
            ->test(Automation::class)
            ->call('showCreateForm')
            ->set('name', '越权规则')
            ->set('trigger', 'updated')
            ->set('actions', [['type' => 'notify_owner', 'message' => 'x']])
            ->call('save');

        $this->assertNull(TenantWorkflow::query()->where('name', '越权规则')->first());
    }

    public function test_manager_can_manage_rules(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant, 'manager');

        Livewire::actingAs($user)
            ->test(Automation::class)
            ->call('showCreateForm')
            ->set('name', '经理规则')
            ->set('trigger', 'updated')
            ->set('actions', [['type' => 'notify_owner', 'message' => '经理创建']])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(TenantWorkflow::query()->where('name', '经理规则')->first());
    }

    // ------------------------------------------------------------------
    // 编辑 / 删除 / 启停
    // ------------------------------------------------------------------

    public function test_admin_can_edit_rule(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant);
        $rule = TenantWorkflow::create([
            'tenant_id' => $tenant->id,
            'name' => '旧规则',
            'model' => Opportunity::class,
            'trigger' => 'created',
            'conditions' => [],
            'actions' => [['type' => 'notify_owner', 'params' => ['message' => '旧消息']]],
        ]);

        Livewire::actingAs($user)
            ->test(Automation::class)
            ->call('edit', $rule->id)
            ->set('name', '新规则')
            ->set('trigger', 'updated')
            ->set('actions', [['type' => 'notify_owner', 'message' => '新消息']])
            ->call('save')
            ->assertHasNoErrors();

        $rule->refresh();
        $this->assertSame('新规则', $rule->name);
        $this->assertSame('updated', $rule->trigger);
        $this->assertSame('新消息', $rule->actions[0]['params']['message']);
    }

    public function test_admin_can_delete_and_toggle_rule(): void
    {
        $tenant = $this->createTenant('enterprise');
        $user = $this->createUser($tenant);
        $rule = TenantWorkflow::create([
            'tenant_id' => $tenant->id,
            'name' => '待删规则',
            'model' => Opportunity::class,
            'trigger' => 'created',
            'conditions' => [],
            'actions' => [['type' => 'notify_owner', 'params' => ['message' => 'x']]],
            'is_active' => true,
        ]);

        Livewire::actingAs($user)->test(Automation::class)->call('toggleActive', $rule->id);
        $this->assertFalse($rule->refresh()->is_active);

        Livewire::actingAs($user)->test(Automation::class)->call('delete', $rule->id);
        $this->assertDatabaseMissing('tenant_workflows', ['id' => $rule->id]);
    }

    // ------------------------------------------------------------------
    // 租户隔离
    // ------------------------------------------------------------------

    public function test_cannot_edit_rule_of_other_tenant(): void
    {
        $tenantA = $this->createTenant('enterprise');
        $tenantB = $this->createTenant('enterprise');
        $userB = $this->createUser($tenantB, 'admin');

        app(\App\Services\Tenancy::class)->setTenant($tenantA);
        $ruleA = TenantWorkflow::create([
            'tenant_id' => $tenantA->id,
            'name' => 'A 租户规则',
            'model' => Opportunity::class,
            'trigger' => 'created',
            'conditions' => [],
            'actions' => [['type' => 'notify_owner', 'params' => ['message' => 'x']]],
        ]);
        // 清除手动租户上下文：TenantScope 仅在 tenancy 为 null 时从 acting 用户引导，
        // 否则上一行残留的 tenantA 上下文会让 userB 命中 A 租户数据
        app(\App\Services\Tenancy::class)->setTenant(null);

        // tenantB 的管理员编辑 tenantA 的规则：findOrFail 被 TenantScope 过滤，应 404
        $this->assertThrows(
            fn () => Livewire::actingAs($userB)
                ->test(Automation::class)
                ->call('edit', $ruleA->id),
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        );

        $this->assertSame('A 租户规则', $ruleA->refresh()->name);
    }
}
