<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Services\Tenancy;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;

class RunWorkflowsCommand extends Command
{
    protected $signature = 'crm:run-workflows {--tenant= : 仅运行指定租户} {--all : 所有租户}';

    protected $description = '扫描停滞商机并触发 stalled 工作流（防重：24h 内已建过自动任务的跳过）';

    public function handle(WorkflowEngine $engine): int
    {
        $tenantSlug = $this->option('tenant');
        $all = $this->option('all');

        if ($tenantSlug) {
            $tenants = Tenant::where('slug', $tenantSlug)->get();
        } elseif ($all) {
            $tenants = Tenant::all();
        } else {
            $this->error('请指定 --tenant=xxx 或 --all');

            return self::FAILURE;
        }

        $tenancy = app(Tenancy::class);
        $triggered = 0;

        foreach ($tenants as $tenant) {
            $tenancy->setTenant($tenant);

            $wonLostStageIds = Stage::where(function ($q) {
                $q->where('is_won', true)->orWhere('is_lost', true);
            })->pluck('id')->toArray();

            Opportunity::query()
                ->whereNotIn('stage_id', $wonLostStageIds)
                ->chunkById(100, function ($opps) use ($engine, &$triggered) {
                    foreach ($opps as $opp) {
                        // 防重：24 小时内已自动创建过停滞跟进任务则跳过
                        $recent = Activity::query()
                            ->where('activitable_type', Opportunity::class)
                            ->where('activitable_id', $opp->id)
                            ->where('subject', 'like', '跟进停滞商机%')
                            ->where('created_at', '>=', now()->subDay())
                            ->exists();

                        if ($recent) {
                            continue;
                        }

                        $engine->evaluate('stalled', $opp);
                        $triggered++;
                    }
                });

            $this->info("  {$tenant->name}: 已扫描");
        }

        $this->info("stalled 工作流评估完成，共评估 {$triggered} 条商机");

        return self::SUCCESS;
    }
}
