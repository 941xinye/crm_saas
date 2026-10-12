<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendRemindersCommand extends Command
{
    protected $signature = 'crm:send-reminders {--activity-hours= : 活动到期前瞻小时数}
                                             {--close-days= : 商机成交日前瞻天数}
                                             {--stalled-days= : 商机停滞天数阈值}
                                             {--no-activity-days= : 商机无活动天数阈值}';

    protected $description = '扫描活动/商机并发送站内提醒';

    public function handle(ReminderService $service): int
    {
        $service->activityDueHours = (int) ($this->option('activity-hours') ?: $service->activityDueHours);
        $service->oppCloseDays = (int) ($this->option('close-days') ?: $service->oppCloseDays);
        $service->oppStalledDays = (int) ($this->option('stalled-days') ?: $service->oppStalledDays);
        $service->oppNoActivityDays = (int) ($this->option('no-activity-days') ?: $service->oppNoActivityDays);

        $result = $service->scanAll();

        $this->info(sprintf(
            '提醒扫描完成：活动到期 %d 条，商机成交日 %d 条，商机无活动 %d 条，商机停滞 %d 条',
            $result['activity_due'],
            $result['opp_close'],
            $result['opp_no_activity'],
            $result['opp_stalled'],
        ));

        return self::SUCCESS;
    }
}
