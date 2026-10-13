<?php

use App\Console\Commands\HealthCheckCommand;
use App\Console\Commands\PruneAuditLogsCommand;
use App\Console\Commands\RefreshOpportunityScores;
use App\Console\Commands\RunWorkflowsCommand;
use App\Console\Commands\SendRemindersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 提醒扫描：每天 09:00 跑一次
Schedule::command(SendRemindersCommand::class)->dailyAt('09:00');

// 智能评分刷新：每天 08:30（在提醒扫描前刷新，保证评分新鲜）
Schedule::command(RefreshOpportunityScores::class)->dailyAt('08:30');

// 停滞商机工作流扫描：每天 08:45（评分刷新后、提醒扫描前）
Schedule::command(RunWorkflowsCommand::class)->dailyAt('08:45');

// 系统健康检查：每小时一次（队列失败告警 + 过期租户导出 ZIP 清理）
Schedule::command(HealthCheckCommand::class)->hourly();

// 审计日志按保留策略清理：每天 08:15（README Phase 6 承诺的定时清理）
Schedule::command(PruneAuditLogsCommand::class)->dailyAt('08:15');
