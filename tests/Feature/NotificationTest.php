<?php

namespace Tests\Feature;

use App\Livewire\NotificationsCenter;
use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReminderService;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 通知与提醒功能测试：
 * ReminderService 扫描逻辑、防重复、通知中心交互、命令执行。
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '通知测试租户',
            'slug' => 'n-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);
    }

    // ------------------------------------------------------------------
    // 活动到期提醒
    // ------------------------------------------------------------------

    public function test_activity_due_notification_is_sent(): void
    {
        Activity::factory()->for($this->tenant, 'tenant')->create([
            'user_id' => $this->user->id,
            'scheduled_at' => now()->addHours(23),
            'completed_at' => null,
        ]);

        $result = app(ReminderService::class)->scanAll();

        $this->assertSame(1, $result['activity_due']);
        $this->assertSame(1, $this->user->unreadNotifications()->count());

        $notification = $this->user->unreadNotifications->first();
        $this->assertStringContainsString('ActivityDueNotification', $notification->type);
        $this->assertStringContainsString('将于', $notification->data['message']);
    }

    public function test_completed_activity_is_not_notified(): void
    {
        Activity::factory()->for($this->tenant, 'tenant')->create([
            'user_id' => $this->user->id,
            'scheduled_at' => now()->addHours(23),
            'completed_at' => now(),
        ]);

        $result = app(ReminderService::class)->scanAll();

        $this->assertSame(0, $result['activity_due']);
    }

    // ------------------------------------------------------------------
    // 商机成交日提醒
    // ------------------------------------------------------------------

    public function test_opportunity_close_date_notification_is_sent(): void
    {
        $stage = Stage::factory()->for($this->tenant, 'tenant')->create();
        Opportunity::factory()->for($this->tenant, 'tenant')->create([
            'owner_id' => $this->user->id,
            'stage_id' => $stage->id,
            'expected_close_date' => now()->addDays(5)->toDateString(),
        ]);

        $result = app(ReminderService::class)->scanAll();

        $this->assertSame(1, $result['opp_close']);
        $this->assertStringContainsString('OpportunityCloseDateNotification', $this->user->unreadNotifications->first()->type);
    }

    // ------------------------------------------------------------------
    // 商机停滞提醒
    // ------------------------------------------------------------------

    public function test_opportunity_stalled_notification_is_sent(): void
    {
        $stage = Stage::factory()->for($this->tenant, 'tenant')->create();
        Opportunity::factory()->for($this->tenant, 'tenant')->create([
            'owner_id' => $this->user->id,
            'stage_id' => $stage->id,
            'created_at' => now()->subDays(31),
        ]);

        $result = app(ReminderService::class)->scanAll();

        $this->assertSame(1, $result['opp_stalled']);
        $stalled = $this->user->notifications()
            ->where('type', \App\Notifications\OpportunityStalledNotification::class)
            ->first();
        $this->assertNotNull($stalled, 'Should have an OpportunityStalledNotification');
    }

    // ------------------------------------------------------------------
    // 防重复通知
    // ------------------------------------------------------------------

    public function test_same_object_notification_is_not_duplicated_in_cooldown(): void
    {
        Activity::factory()->for($this->tenant, 'tenant')->create([
            'user_id' => $this->user->id,
            'scheduled_at' => now()->addHours(23),
            'completed_at' => null,
        ]);

        $result1 = app(ReminderService::class)->scanAll();
        $result2 = app(ReminderService::class)->scanAll();

        $this->assertSame(1, $result1['activity_due']);
        $this->assertSame(0, $result2['activity_due']);
        $this->assertSame(1, $this->user->notifications()->count());
    }

    // ------------------------------------------------------------------
    // 试用期到期提醒
    // ------------------------------------------------------------------

    public function test_trial_ending_notification_sent_to_admins_and_platform_admin(): void
    {
        $this->tenant->update(['trial_ends_at' => now()->addDays(2)]);

        // 平台超管（无租户）也会收到
        User::factory()->create(['tenant_id' => null, 'is_platform_admin' => true]);
        // 非 admin 成员不应收到
        User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'sales']);

        $result = app(ReminderService::class)->scanAll();

        // 租户 admin + 平台超管
        $this->assertSame(2, $result['trial_ending']);
        $this->assertStringContainsString('TrialEndingNotification', $this->user->unreadNotifications->first()->type);

        // 第二次扫描不重复（冷却期去重）
        $result2 = app(ReminderService::class)->scanAll();
        $this->assertSame(0, $result2['trial_ending']);
        $this->assertSame(1, $this->user->notifications()->count());
    }

    // ------------------------------------------------------------------
    // 用户级通知偏好
    // ------------------------------------------------------------------

    public function test_user_can_mute_activity_due_notifications(): void
    {
        $this->user->update(['notification_prefs' => ['activity_due' => false]]);

        Activity::factory()->for($this->tenant, 'tenant')->create([
            'user_id' => $this->user->id,
            'scheduled_at' => now()->addHours(23),
            'completed_at' => null,
        ]);

        $result = app(ReminderService::class)->scanAll();

        $this->assertSame(0, $result['activity_due']);
        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    public function test_notification_preferences_page_renders_and_saves(): void
    {
        Livewire::test(\App\Livewire\Settings\NotificationPreferences::class)
            ->assertSee('活动到期提醒')
            ->set('prefs.activity_due', false)
            ->call('save');

        $fresh = $this->user->fresh();
        $this->assertFalse($fresh->notification_prefs['activity_due']);
        // 未设置的键默认接收
        $this->assertTrue($fresh->wantsNotification('opp_close'));
    }

    // ------------------------------------------------------------------
    // 通知中心 UI
    // ------------------------------------------------------------------

    public function test_notifications_center_page_renders(): void
    {
        $this->user->notify(new \App\Notifications\ActivityDueNotification(
            activityId: 1,
            activitySubject: '测试活动',
            scheduledAt: '2026-10-13 09:00',
            resourceUrl: route('activities.index'),
        ));

        $this->get(route('notifications'))
            ->assertOk()
            ->assertSee('测试活动');
    }

    public function test_mark_as_read(): void
    {
        $this->user->notify(new \App\Notifications\ActivityDueNotification(
            activityId: 1,
            activitySubject: '待读活动',
            scheduledAt: '2026-10-13 09:00',
            resourceUrl: '#',
        ));

        $this->assertSame(1, $this->user->unreadNotifications()->count());

        $notification = $this->user->notifications->first();

        Livewire::test(NotificationsCenter::class)
            ->call('markAsRead', $notification->getKey());

        $this->assertSame(0, $this->user->fresh()->unreadNotifications()->count());
    }

    public function test_mark_all_as_read(): void
    {
        $this->user->notify(new \App\Notifications\ActivityDueNotification(
            activityId: 1,
            activitySubject: '活动 A',
            scheduledAt: '2026-10-13 09:00',
            resourceUrl: '#',
        ));
        $this->user->notify(new \App\Notifications\OpportunityStalledNotification(
            opportunityId: 2,
            opportunityTitle: '商机 B',
            createdDate: '2026-09-01',
            resourceUrl: '#',
        ));

        $this->assertSame(2, $this->user->unreadNotifications()->count());

        Livewire::test(NotificationsCenter::class)
            ->call('markAllAsRead');

        $this->assertSame(0, $this->user->fresh()->unreadNotifications()->count());
    }

    public function test_cannot_access_notifications_without_tenant(): void
    {
        $orphan = User::factory()->create(['tenant_id' => null]);

        $this->actingAs($orphan)
            ->get(route('notifications'))
            ->assertForbidden();
    }
}
