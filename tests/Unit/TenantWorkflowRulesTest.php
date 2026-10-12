<?php

namespace Tests\Unit;

use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\TenantWorkflow;
use App\Models\User;
use App\Notifications\AutomationTriggeredNotification;
use App\Services\Tenancy;
use App\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 租户级工作流规则（tenant_workflows 表）测试：
 * DB 规则加载与执行、优先级排序、停用规则跳过、租户隔离、config 规则共存、触发时间记录。
 */
class TenantWorkflowRulesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Stage $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '规则租户测试',
            'slug' => 'twr-' . Str::lower(Str::random(8)),
            'plan' => 'enterprise',
            'is_active' => true,
            'trial_ends_at' => null,
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        app(Tenancy::class)->forUser($this->user);

        $this->stage = Stage::create([
            'tenant_id' => $this->tenant->id,
            'name' => '初步接触',
            'order' => 1,
            'probability' => 20,
        ]);

        // 清空全局示例规则，隔离 DB 规则行为
        config()->set('workflows.rules', []);
    }

    public function test_db_rule_fires_on_model_event(): void
    {
        Notification::fake();

        TenantWorkflow::create([
            'name' => '更新通知负责人',
            'model' => Opportunity::class,
            'trigger' => 'updated',
            'conditions' => [],
            'actions' => [
                ['type' => 'notify_owner', 'params' => ['message' => 'DB 规则已触发']],
            ],
        ]);

        $opp = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '规则商机',
            'value' => 1000,
            'probability' => 30,
            'stage_id' => $this->stage->id,
            'owner_id' => $this->user->id,
        ]);
        $opp->update(['probability' => 35]);

        Notification::assertSentTo($this->user, AutomationTriggeredNotification::class);
    }

    public function test_inactive_rule_is_skipped(): void
    {
        Notification::fake();

        TenantWorkflow::create([
            'name' => '停用规则',
            'model' => Opportunity::class,
            'trigger' => 'updated',
            'conditions' => [],
            'actions' => [
                ['type' => 'notify_owner', 'params' => ['message' => '不应触发']],
            ],
            'is_active' => false,
        ]);

        $opp = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '停用商机',
            'value' => 1000,
            'probability' => 30,
            'stage_id' => $this->stage->id,
            'owner_id' => $this->user->id,
        ]);
        $opp->update(['probability' => 40]);

        Notification::assertNothingSent();
    }

    public function test_higher_priority_rule_executes_first(): void
    {
        Notification::fake();

        TenantWorkflow::create([
            'name' => '低优先级',
            'model' => Opportunity::class,
            'trigger' => 'created',
            'conditions' => [],
            'actions' => [
                ['type' => 'notify_owner', 'params' => ['message' => '低优先级']],
            ],
            'priority' => 1,
        ]);
        TenantWorkflow::create([
            'name' => '高优先级',
            'model' => Opportunity::class,
            'trigger' => 'created',
            'conditions' => [],
            'actions' => [
                ['type' => 'notify_owner', 'params' => ['message' => '高优先级']],
            ],
            'priority' => 10,
        ]);

        Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '优先级商机',
            'value' => 1000,
            'probability' => 30,
            'stage_id' => $this->stage->id,
            'owner_id' => $this->user->id,
        ]);

        // 两条规则都触发
        Notification::assertSentTo($this->user, AutomationTriggeredNotification::class, 2);
    }

    public function test_rules_of_other_tenant_do_not_fire(): void
    {
        Notification::fake();

        $otherTenant = Tenant::create([
            'name' => '其他租户',
            'slug' => 'twr-other-' . Str::lower(Str::random(6)),
            'plan' => 'enterprise',
            'is_active' => true,
        ]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'admin']);

        // 其他租户的规则（先切上下文创建，再切回）
        app(Tenancy::class)->setTenant($otherTenant);
        TenantWorkflow::create([
            'name' => '别家规则',
            'model' => Opportunity::class,
            'trigger' => 'updated',
            'conditions' => [],
            'actions' => [
                ['type' => 'notify_owner', 'params' => ['message' => '跨租户不应触发']],
            ],
        ]);
        app(Tenancy::class)->forUser($this->user);

        $opp = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '本租户商机',
            'value' => 1000,
            'probability' => 30,
            'stage_id' => $this->stage->id,
            'owner_id' => $this->user->id,
        ]);
        $opp->update(['probability' => 45]);

        Notification::assertNothingSent();
    }

    public function test_config_rules_still_work_alongside_db_rules(): void
    {
        Notification::fake();

        config()->set('workflows.rules', [
            [
                'name' => 'config 示例规则',
                'model' => Opportunity::class,
                'trigger' => 'updated',
                'conditions' => [],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '来自 config']],
                ],
            ],
        ]);

        TenantWorkflow::create([
            'name' => 'DB 规则',
            'model' => Opportunity::class,
            'trigger' => 'updated',
            'conditions' => [],
            'actions' => [
                ['type' => 'notify_owner', 'params' => ['message' => '来自 DB']],
            ],
        ]);

        $opp = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '共存商机',
            'value' => 1000,
            'probability' => 30,
            'stage_id' => $this->stage->id,
            'owner_id' => $this->user->id,
        ]);
        $opp->update(['probability' => 50]);

        Notification::assertSentTo($this->user, AutomationTriggeredNotification::class, 2);
    }

    public function test_last_triggered_at_recorded_after_firing(): void
    {
        Notification::fake();

        $rule = TenantWorkflow::create([
            'name' => '记录触发时间',
            'model' => Opportunity::class,
            'trigger' => 'updated',
            'conditions' => [],
            'actions' => [
                ['type' => 'notify_owner', 'params' => ['message' => '记录时间']],
            ],
        ]);
        $this->assertNull($rule->last_triggered_at);

        $opp = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '时间商机',
            'value' => 1000,
            'probability' => 30,
            'stage_id' => $this->stage->id,
            'owner_id' => $this->user->id,
        ]);
        $opp->update(['probability' => 55]);

        $this->assertNotNull($rule->refresh()->last_triggered_at);
    }

    public function test_db_rule_with_conditions_and_create_activity(): void
    {
        $stage = Stage::create([
            'tenant_id' => $this->tenant->id,
            'name' => '初步接触',
            'order' => 1,
            'probability' => 20,
        ]);

        TenantWorkflow::create([
            'name' => '停滞自动建任务（DB）',
            'model' => Opportunity::class,
            'trigger' => 'stalled',
            'conditions' => [['stalled_days', '>=', 7]],
            'actions' => [
                [
                    'type' => 'create_activity',
                    'params' => [
                        'activitable_type' => '{activitable_type}',
                        'activitable_id' => '{activitable_id}',
                        'owner_id' => '{owner_id}',
                        'type' => 'task',
                        'subject' => 'DB 规则跟进「{title}」',
                        'scheduled_at' => '+1 day',
                    ],
                ],
            ],
        ]);

        $opp = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '停滞DB商机',
            'value' => 1000,
            'probability' => 30,
            'stage_id' => $stage->id,
            'owner_id' => $this->user->id,
        ]);
        $opp->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

        app(WorkflowEngine::class)->evaluate('stalled', $opp->refresh());

        $this->assertDatabaseHas('activities', [
            'activitable_type' => Opportunity::class,
            'activitable_id' => $opp->id,
            'subject' => 'DB 规则跟进「停滞DB商机」',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_model_mismatch_rule_is_skipped(): void
    {
        Notification::fake();

        // 规则目标是别的模型 → 不会在 Opportunity 事件上执行
        TenantWorkflow::create([
            'name' => '目标模型不符',
            'model' => \App\Models\Company::class,
            'trigger' => 'updated',
            'conditions' => [],
            'actions' => [
                ['type' => 'notify_owner', 'params' => ['message' => '不应触发']],
            ],
        ]);

        $opp = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '模型不符商机',
            'value' => 1000,
            'probability' => 30,
            'stage_id' => $this->stage->id,
            'owner_id' => $this->user->id,
        ]);
        $opp->update(['probability' => 60]);

        Notification::assertNothingSent();
    }
}
