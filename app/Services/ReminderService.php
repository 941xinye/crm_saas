<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ActivityDueNotification;
use App\Notifications\OpportunityCloseDateNotification;
use App\Notifications\OpportunityNoActivityNotification;
use App\Notifications\OpportunityStalledNotification;
use App\Notifications\TrialEndingNotification;

/**
 * 提醒扫描服务：每天定时扫描活动/商机，发站内通知。
 *
 * 触发条件（可调整阈值）：
 * - 活动到期：scheduled_at 在未来 ACTIVITY_DUE_HOURS 小时内，未完成，有负责人
 * - 商机无活动：created_at 超过 OPP_NO_ACTIVITY_DAYS 天且无近期活动，有负责人
 * - 商机成交日：expected_close_date 在未来 OPP_CLOSE_DAYS 天内，有负责人
 * - 商机停滞：created_at 超过 OPP_STALLED_DAYS 天，有负责人
 *
 * 防重复：同一对象+同一类型，24 小时内不重复通知（基于 notifications.data 的 id 匹配）。
 */
class ReminderService
{
    /** 活动到期提醒前瞻小时数 */
    public int $activityDueHours = 24;

    /** 商机成交日提醒前瞻天数 */
    public int $oppCloseDays = 7;

    /** 商机停滞天数阈值 */
    public int $oppStalledDays = 30;

    /** 商机无活动天数阈值 */
    public int $oppNoActivityDays = 14;

    /** 试用期到期前提醒天数 */
    public int $trialNoticeDays = 3;

    /** 同一对象+类型的通知冷却期（小时） */
    public int $cooldownHours = 24;

    public function __construct(
        ?int $activityDueHours = null,
        ?int $oppCloseDays = null,
        ?int $oppStalledDays = null,
        ?int $oppNoActivityDays = null,
        ?int $trialNoticeDays = null,
    ) {
        $this->activityDueHours = $activityDueHours ?? config('crm.reminder.activity_due_hours', 24);
        $this->oppCloseDays = $oppCloseDays ?? config('crm.reminder.opp_close_days', 7);
        $this->oppStalledDays = $oppStalledDays ?? config('crm.reminder.opp_stalled_days', 30);
        $this->oppNoActivityDays = $oppNoActivityDays ?? config('crm.reminder.opp_no_activity_days', 14);
        $this->trialNoticeDays = $trialNoticeDays ?? config('crm.reminder.trial_notice_days', 3);
    }

    /**
     * 扫描并发送所有提醒，返回发送计数数组（用于日志/测试）。
     *
     * @return array{activity_due: int, opp_close: int, opp_no_activity: int, opp_stalled: int, trial_ending: int}
     */
    public function scanAll(): array
    {
        return [
            'activity_due' => $this->sendActivityDue(),
            'opp_no_activity' => $this->sendOpportunityNoActivity(),
            'opp_close' => $this->sendOpportunityCloseDate(),
            'opp_stalled' => $this->sendOpportunityStalled(),
            'trial_ending' => $this->sendTrialEnding(),
        ];
    }

    private function sendActivityDue(): int
    {
        $threshold = now()->addHours($this->activityDueHours);
        $count = 0;

        Activity::query()
            ->whereNotNull('scheduled_at')
            ->whereNull('completed_at')
            ->where('scheduled_at', '<=', $threshold)
            ->where('scheduled_at', '>=', now())
            ->whereNotNull('user_id')
            ->with(['user.tenant'])
            ->chunkById(200, function ($activities) use (&$count) {
                foreach ($activities as $activity) {
                    // 用户级通知偏好：静音则跳过
                    if (! $activity->user?->wantsNotification('activity_due')) {
                        continue;
                    }

                    if ($this->alreadyNotified($activity->user, ActivityDueNotification::class, $activity->id, 'activity_id')) {
                        continue;
                    }

                    // 白标 SMTP：按收件人所属租户激活（未配置租户复位平台默认）
                    app(TenantMailer::class)->activate($activity->user->tenant);

                    $activity->user->notify(new ActivityDueNotification(
                        activityId: $activity->id,
                        activitySubject: $activity->subject ?? __('dashboard.activity_fallback'),
                        scheduledAt: $activity->scheduled_at->format('Y-m-d H:i'),
                        resourceUrl: route('activities.index'),
                    ));
                    $count++;
                }
            });

        return $count;
    }

    /**
     * 商机无活动提醒：商机创建超过 N 天且在此期间无任何活动记录。
     */
    private function sendOpportunityNoActivity(): int
    {
        $threshold = now()->subDays($this->oppNoActivityDays);
        $count = 0;

        Opportunity::query()
            ->whereNotNull('owner_id')
            ->where('created_at', '<=', $threshold)
            ->whereNotIn('id', function ($q) use ($threshold) {
                $q->select('activitable_id')
                    ->from('activities')
                    ->where('activitable_type', Opportunity::class)
                    ->where('created_at', '>', $threshold)
                    ->whereNull('deleted_at');
            })
            ->with(['owner.tenant'])
            ->chunkById(200, function ($opportunities) use (&$count, $threshold) {
                foreach ($opportunities as $opportunity) {
                    if (! $opportunity->owner?->wantsNotification('opp_no_activity')) {
                        continue;
                    }

                    if ($this->alreadyNotified($opportunity->owner, OpportunityNoActivityNotification::class, $opportunity->id, 'opportunity_id')) {
                        continue;
                    }

                    // 计算距离上次活动（或创建）的天数
                    $lastActivity = $opportunity->activities()->latest('created_at')->first();
                    $refDate = $lastActivity?->created_at ?? $opportunity->created_at;
                    $daysSinceLast = (int) now()->diffInDays($refDate);

                    // 白标 SMTP：按收件人所属租户激活
                    app(TenantMailer::class)->activate($opportunity->owner->tenant);

                    $opportunity->owner->notify(new OpportunityNoActivityNotification(
                        opportunityId: $opportunity->id,
                        opportunityTitle: $opportunity->title,
                        daysSinceLast: $daysSinceLast,
                        resourceUrl: route('opportunities.show', $opportunity),
                    ));
                    $count++;
                }
            });

        return $count;
    }

    private function sendOpportunityCloseDate(): int
    {
        $threshold = now()->addDays($this->oppCloseDays);
        $count = 0;

        Opportunity::query()
            ->whereNotNull('expected_close_date')
            ->where('expected_close_date', '<=', $threshold)
            ->where('expected_close_date', '>=', today())
            ->whereNotNull('owner_id')
            ->with(['owner.tenant'])
            ->chunkById(200, function ($opportunities) use (&$count) {
                foreach ($opportunities as $opportunity) {
                    if (! $opportunity->owner?->wantsNotification('opp_close')) {
                        continue;
                    }

                    if ($this->alreadyNotified($opportunity->owner, OpportunityCloseDateNotification::class, $opportunity->id, 'opportunity_id')) {
                        continue;
                    }

                    // 白标 SMTP：按收件人所属租户激活
                    app(TenantMailer::class)->activate($opportunity->owner->tenant);

                    $opportunity->owner->notify(new OpportunityCloseDateNotification(
                        opportunityId: $opportunity->id,
                        opportunityTitle: $opportunity->title,
                        expectedCloseDate: $opportunity->expected_close_date->format('Y-m-d'),
                        resourceUrl: route('opportunities.show', $opportunity),
                    ));
                    $count++;
                }
            });

        return $count;
    }

    private function sendOpportunityStalled(): int
    {
        $threshold = now()->subDays($this->oppStalledDays);
        $count = 0;

        Opportunity::query()
            ->where('created_at', '<=', $threshold)
            ->whereNotNull('owner_id')
            // 简化版：所有老旧商机都提醒，后续可加 status not in 或 updated_at 条件
            ->with(['owner.tenant'])
            ->chunkById(200, function ($opportunities) use (&$count) {
                foreach ($opportunities as $opportunity) {
                    if (! $opportunity->owner?->wantsNotification('opp_stalled')) {
                        continue;
                    }

                    if ($this->alreadyNotified($opportunity->owner, OpportunityStalledNotification::class, $opportunity->id, 'opportunity_id')) {
                        continue;
                    }

                    // 白标 SMTP：按收件人所属租户激活
                    app(TenantMailer::class)->activate($opportunity->owner->tenant);

                    $opportunity->owner->notify(new OpportunityStalledNotification(
                        opportunityId: $opportunity->id,
                        opportunityTitle: $opportunity->title,
                        createdDate: $opportunity->created_at->format('Y-m-d'),
                        resourceUrl: route('opportunities.show', $opportunity),
                    ));
                    $count++;
                }
            });

        return $count;
    }

    /**
     * 试用期即将到期提醒：通知租户全部 admin 与平台超管（is_platform_admin）。
     */
    private function sendTrialEnding(): int
    {
        $threshold = now()->addDays($this->trialNoticeDays);
        $count = 0;

        Tenant::query()
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), $threshold])
            ->chunkById(100, function ($tenants) use (&$count) {
                foreach ($tenants as $tenant) {
                    $daysLeft = max(1, (int) ceil(now()->diffInHours($tenant->trial_ends_at) / 24));

                    // 租户 admin + 平台超管
                    $recipients = User::query()
                        ->where(function ($q) use ($tenant) {
                            $q->where('tenant_id', $tenant->id)->where('role', 'admin');
                        })
                        ->orWhere('is_platform_admin', true)
                        ->get();

                    foreach ($recipients as $recipient) {
                        if (! $recipient->wantsNotification('trial_ending')) {
                            continue;
                        }

                        if ($this->alreadyNotified($recipient, TrialEndingNotification::class, $tenant->id, 'tenant_id')) {
                            continue;
                        }

                        // 白标 SMTP：租户内收件人走租户品牌，平台超管走平台默认
                        app(TenantMailer::class)->activate(
                            $recipient->tenant_id === $tenant->id ? $tenant : null
                        );

                        $recipient->notify(new TrialEndingNotification(
                            tenantId: $tenant->id,
                            tenantName: $tenant->name,
                            endsAt: $tenant->trial_ends_at->format('Y-m-d'),
                            daysLeft: $daysLeft,
                        ));
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * 同一用户+同类型通知+同一对象，在冷却期内是否已通知过。
     *
     * @param  class-string  $type  Notification 类名（数据库 type 字段存完整类名）
     */
    private function alreadyNotified(User $user, string $type, int $objectId, string $objectKey): bool
    {
        // 用自定义 Notification 模型（data 已 cast 为 array），
        // where('data->key', value) 走 JSON 精确匹配，避免 LIKE 前缀误匹配。
        return $user->notifications()
            ->where('created_at', '>=', now()->subHours($this->cooldownHours))
            ->where('type', $type)
            ->where("data->{$objectKey}", $objectId)
            ->exists();
    }
}
