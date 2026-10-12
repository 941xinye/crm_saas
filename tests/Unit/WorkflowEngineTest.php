<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AutomationTriggeredNotification;
use App\Services\Tenancy;
use App\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 工作流引擎测试：触发器匹配、条件 AND 逻辑、动作执行、占位符替换、租户上下文。
 */
class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Stage $stage;
    private Opportunity $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '工作流测试租户',
            'slug' => 'wf-' . Str::lower(Str::random(8)),
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
        $this->opportunity = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '测试商机',
            'value' => 50000,
            'probability' => 40,
            'stage_id' => $this->stage->id,
            'owner_id' => $this->user->id,
            'expected_close_date' => now()->addDays(30)->toDateString(),
        ]);
    }

    /**
     * 注入临时测试规则（替换 config，不影响真实 workflows.php）。
     */
    private function setRules(array $rules): void
    {
        config()->set('workflows.rules', $rules);
    }

    public function test_matching_trigger_and_condition_executes_notify_owner(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => '大额商机提醒',
                'model' => Opportunity::class,
                'trigger' => 'updated',
                'conditions' => [['value', '>=', 10000]],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '大额商机已更新']],
                ],
            ],
        ]);

        $this->opportunity->update(['probability' => 45]);

        Notification::assertSentTo($this->user, AutomationTriggeredNotification::class);
    }

    public function test_condition_mismatch_skips_actions(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => '超大额商机提醒',
                'model' => Opportunity::class,
                'trigger' => 'updated',
                'conditions' => [['value', '>=', 999999]],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '不应触发']],
                ],
            ],
        ]);

        $this->opportunity->update(['probability' => 50]);

        Notification::assertNothingSent();
    }

    public function test_conditions_use_and_logic(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => '组合条件',
                'model' => Opportunity::class,
                'trigger' => 'updated',
                // value 满足但 probability 不满足 → 整体不匹配
                'conditions' => [
                    ['value', '>=', 10000],
                    ['probability', '>=', 90],
                ],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '不应触发']],
                ],
            ],
        ]);

        $this->opportunity->update(['probability' => 55]);

        Notification::assertNothingSent();
    }

    public function test_wrong_trigger_does_not_fire(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => '仅创建时触发',
                'model' => Opportunity::class,
                'trigger' => 'created',
                'conditions' => [],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '不应触发']],
                ],
            ],
        ]);

        $this->opportunity->update(['probability' => 60]);

        Notification::assertNothingSent();
    }

    public function test_entered_stage_triggered_by_stage_change(): void
    {
        Notification::fake();
        $nextStage = Stage::create([
            'tenant_id' => $this->tenant->id,
            'name' => '需求确认',
            'order' => 2,
            'probability' => 80,
        ]);
        $this->setRules([
            [
                'name' => '进入高概率阶段',
                'model' => Opportunity::class,
                'trigger' => 'entered_stage',
                'conditions' => [['probability', '>=', 70]],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '高概率阶段']],
                ],
            ],
        ]);

        // stage_id 必须实际变化才会触发 entered_stage
        $this->opportunity->update(['probability' => 80, 'stage_id' => $nextStage->id]);

        Notification::assertSentTo($this->user, AutomationTriggeredNotification::class);
    }

    public function test_create_activity_action_resolves_placeholders(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => '停滞自动建任务',
                'model' => Opportunity::class,
                'trigger' => 'stalled',
                'conditions' => [['stalled_days', '>=', 14]],
                'actions' => [
                    [
                        'type' => 'create_activity',
                        'params' => [
                            'activitable_type' => '{activitable_type}',
                            'activitable_id' => '{activitable_id}',
                            'owner_id' => '{owner_id}',
                            'type' => 'task',
                            'subject' => '跟进停滞商机「{title}」',
                            'notes' => '已停滞 {stalled_days} 天',
                            'scheduled_at' => '+1 day',
                        ],
                    ],
                ],
            ],
        ]);

        // 商机最后更新在 30 天前，无活动 → stalled_days = 30
        $this->opportunity->forceFill(['updated_at' => now()->subDays(30)])->saveQuietly();

        app(WorkflowEngine::class)->evaluate('stalled', $this->opportunity->refresh());

        $activity = Activity::query()
            ->where('activitable_type', Opportunity::class)
            ->where('activitable_id', $this->opportunity->id)
            ->where('subject', 'like', '%测试商机%')
            ->first();

        $this->assertNotNull($activity, '应自动创建跟进活动');
        $this->assertSame('task', $activity->type);
        $this->assertSame($this->user->id, $activity->user_id);
        $this->assertStringContainsString('30 天', $activity->notes);
        $this->assertSame($this->tenant->id, $activity->tenant_id);
    }

    public function test_stalled_days_uses_latest_activity(): void
    {
        Notification::fake();
        // 商机 updated_at 30 天前，但昨天有一条活动 → stalled_days < 14，不触发
        $this->opportunity->forceFill(['updated_at' => now()->subDays(30)])->saveQuietly();
        $activity = Activity::create([
            'activitable_type' => Opportunity::class,
            'activitable_id' => $this->opportunity->id,
            'user_id' => $this->user->id,
            'type' => 'call',
            'subject' => '昨天跟进过',
        ]);
        // created_at 不可批量赋值，单独补写
        $activity->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        app(WorkflowEngine::class)->evaluate('stalled', $this->opportunity->refresh());

        $recent = Activity::query()->where('subject', 'like', '跟进停滞商机%')->exists();
        $this->assertFalse($recent, '最近有活动不应触发停滞规则');
        Notification::assertNothingSent();
    }

    public function test_unknown_action_type_is_skipped_safely(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => '未知动作',
                'model' => Opportunity::class,
                'trigger' => 'updated',
                'conditions' => [],
                'actions' => [
                    ['type' => 'bogus_action', 'params' => []],
                ],
            ],
        ]);

        $this->opportunity->update(['probability' => 65]);

        Notification::assertNothingSent();
        $this->assertDatabaseHas('opportunities', ['id' => $this->opportunity->id]);
    }

    public function test_no_tenancy_context_means_no_rules(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => '需租户上下文',
                'model' => Opportunity::class,
                'trigger' => 'updated',
                'conditions' => [],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '不应触发']],
                ],
            ],
        ]);

        // 重置租户上下文（console 逃生舱场景）
        app(Tenancy::class)->setTenant(null);

        app(WorkflowEngine::class)->evaluate('updated', $this->opportunity);

        Notification::assertNothingSent();
    }

    public function test_set_stage_action_updates_opportunity(): void
    {
        $targetStage = Stage::create([
            'tenant_id' => $this->tenant->id,
            'name' => '需求确认',
            'order' => 2,
            'probability' => 60,
        ]);
        $this->setRules([
            [
                'name' => '自动推进阶段',
                'model' => Opportunity::class,
                'trigger' => 'stalled',
                'conditions' => [],
                'actions' => [
                    ['type' => 'set_stage', 'params' => ['stage_id' => $targetStage->id]],
                ],
            ],
        ]);

        app(WorkflowEngine::class)->evaluate('stalled', $this->opportunity->refresh());

        $this->assertSame($targetStage->id, $this->opportunity->refresh()->stage_id);
    }

    // ------------------------------------------------------------------
    // 条件匹配模式 any（OR）与 send_webhook 动作（Phase 6 / P2）
    // ------------------------------------------------------------------

    public function test_match_any_triggers_when_one_condition_met(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => 'OR 条件规则',
                'model' => Opportunity::class,
                'trigger' => 'updated',
                'match' => 'any',
                // value 满足，probability 不满足 → 任一满足即触发
                'conditions' => [
                    ['value', '>=', 10000],
                    ['probability', '>=', 99],
                ],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '任一条件满足']],
                ],
            ],
        ]);

        $this->opportunity->update(['probability' => 55]);

        Notification::assertSentTo($this->user, AutomationTriggeredNotification::class);
    }

    public function test_match_none_of_any_conditions_skips(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => 'OR 条件规则',
                'model' => Opportunity::class,
                'trigger' => 'updated',
                'match' => 'any',
                'conditions' => [
                    ['value', '>=', 999999],
                    ['probability', '>=', 99],
                ],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '不应触发']],
                ],
            ],
        ]);

        $this->opportunity->update(['probability' => 55]);

        Notification::assertNothingSent();
    }

    public function test_missing_match_defaults_to_all(): void
    {
        Notification::fake();
        $this->setRules([
            [
                'name' => '无 match 键（向后兼容）',
                'model' => Opportunity::class,
                'trigger' => 'updated',
                'conditions' => [
                    ['value', '>=', 10000],
                    ['probability', '>=', 99], // 不满足 → all 模式下整体不匹配
                ],
                'actions' => [
                    ['type' => 'notify_owner', 'params' => ['message' => '不应触发']],
                ],
            ],
        ]);

        $this->opportunity->update(['probability' => 55]);

        Notification::assertNothingSent();
    }

    public function test_send_webhook_action_dispatches_to_subscribed_webhooks(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://receiver.example.com/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        \App\Models\TenantWebhook::create([
            'tenant_id' => $this->tenant->id,
            'name' => '外部接收器',
            'url' => 'https://receiver.example.com/hook',
            'secret' => 'test-secret-123',
            'events' => ['workflow.triggered'],
            'is_active' => true,
        ]);

        $this->setRules([
            [
                'name' => '创建商机推送 Webhook',
                'model' => Opportunity::class,
                'trigger' => 'created',
                'conditions' => [],
                'actions' => [
                    [
                        'type' => 'send_webhook',
                        'params' => ['event' => 'workflow.triggered', 'message' => '商机「{title}」已创建'],
                    ],
                ],
            ],
        ]);

        $opportunity = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '推送商机',
            'value' => 10000,
            'probability' => 30,
            'stage_id' => $this->stage->id,
            'owner_id' => $this->user->id,
        ]);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($opportunity) {
            return str_starts_with($request->url(), 'https://receiver.example.com')
                && $request->header('X-CRM-Event')[0] === 'workflow.triggered'
                && $request['entity_type'] === 'Opportunity'
                && $request['entity_id'] === $opportunity->id
                && str_contains($request['message'], '推送商机');
        });
    }

    public function test_send_webhook_failure_does_not_block_other_actions(): void
    {
        Notification::fake();

        // 无任何订阅 Webhook → dispatch 空转；URL 指向不可达地址的场景由 Http fake 模拟失败
        \Illuminate\Support\Facades\Http::fake([
            'https://unreachable.example.com/*' => \Illuminate\Support\Facades\Http::response(status: 500),
        ]);
        \App\Models\TenantWebhook::create([
            'tenant_id' => $this->tenant->id,
            'name' => '不可达接收器',
            'url' => 'https://unreachable.example.com/hook',
            'secret' => 'x',
            'events' => ['workflow.triggered'],
            'is_active' => true,
        ]);

        $this->setRules([
            [
                'name' => 'webhook 失败不影响通知',
                'model' => Opportunity::class,
                'trigger' => 'stalled',
                'conditions' => [],
                'actions' => [
                    ['type' => 'send_webhook', 'params' => ['event' => 'workflow.triggered']],
                    ['type' => 'notify_owner', 'params' => ['message' => '后续动作仍执行']],
                ],
            ],
        ]);

        app(WorkflowEngine::class)->evaluate('stalled', $this->opportunity->refresh());

        Notification::assertSentTo($this->user, AutomationTriggeredNotification::class);
    }
}
