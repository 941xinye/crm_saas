<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 核心查询路径性能基准：php artisan crm:benchmark [--iterations=5] [--json]
 *
 * 在当前库上运行代表性查询（仪表板统计、列表分页、全局搜索、
 * 提醒扫描、审计分页），输出平均耗时。测试库（sqlite :memory:）数据量小、
 * 结果仅作冒烟验证；生产 MySQL 上大表数据更能反映真实差异。
 *
 * 评估结论与索引清单见 docs/performance.md。
 */
class CrmBenchmark extends Command
{
    protected $signature = 'crm:benchmark {--iterations=5 : 每项执行次数} {--json : 以 JSON 输出}';

    protected $description = '运行核心查询路径性能基准';

    public function handle(): int
    {
        $iterations = max(1, (int) $this->option('iterations'));
        $tenantId = Tenant::query()->value('id');

        $results = [];
        foreach ($this->cases($tenantId) as $key => $case) {
            [$label, $query] = $case;

            $query(); // 预热（连接建立 / 词法缓存）

            $times = [];
            for ($i = 0; $i < $iterations; $i++) {
                $start = hrtime(true);
                $query();
                $times[] = (hrtime(true) - $start) / 1e6;
            }

            sort($times);
            $results[$key] = [
                'label' => $label,
                'avg_ms' => round(array_sum($times) / count($times), 2),
                'median_ms' => round($times[intdiv(count($times), 2)], 2),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("crm:benchmark（每项执行 {$iterations} 次，租户上下文："
            . ($tenantId ? "tenant #{$tenantId}" : '无租户数据') . '）');

        $this->table(
            ['基准项', '说明', '平均 (ms)', '中位数 (ms)'],
            collect($results)->map(fn ($r, $key) => [$key, $r['label'], $r['avg_ms'], $r['median_ms']])->all()
        );

        $this->comment('提示：sqlite 测试库数据量小仅作冒烟；生产评估请用 MySQL 与真实数据量，并配合 EXPLAIN 验证索引命中（docs/performance.md）。');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{0: string, 1: callable}>
     */
    private function cases(?int $tenantId): array
    {
        $tenantFilter = fn ($query) => $tenantId ? $query->where('tenant_id', $tenantId) : $query;

        return [
            'dashboard_stats' => ['仪表板统计卡片（4 项计数/求和）', function () use ($tenantFilter) {
                $tenantFilter(Company::query())->count();
                $tenantFilter(Lead::query())->count();
                $tenantFilter(Opportunity::query())->sum('value');
                $tenantFilter(Opportunity::query())->where('created_at', '>=', now()->startOfMonth())->count();
            }],

            'opportunity_list' => ['商机列表分页（关联预载入，15/页）', function () use ($tenantFilter) {
                $tenantFilter(Opportunity::query())
                    ->with(['company:id,name', 'contact:id,first_name,last_name', 'stage:id,name'])
                    ->orderByDesc('created_at')
                    ->paginate(15);
            }],

            'global_search' => ['全局搜索（4 表 LIKE 各取 5 条）', function () use ($tenantFilter) {
                $term = '%a%';
                $tenantFilter(Company::query())->where('name', 'like', $term)->limit(5)->get();
                $tenantFilter(Contact::query())->where('first_name', 'like', $term)->limit(5)->get();
                $tenantFilter(Lead::query())->where('name', 'like', $term)->limit(5)->get();
                $tenantFilter(Opportunity::query())->where('title', 'like', $term)->limit(5)->get();
            }],

            'reminder_scan' => ['提醒扫描（活动到期窗口查询）', function () {
                Activity::query()
                    ->whereNotNull('scheduled_at')
                    ->whereNull('completed_at')
                    ->where('scheduled_at', '<=', now()->addHours(24))
                    ->where('scheduled_at', '>=', now())
                    ->with(['user:id,tenant_id'])
                    ->get();
            }],

            'audit_recent' => ['审计日志按租户倒序分页（50 条）', function () use ($tenantId) {
                AuditLog::query()
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get();
            }],

            'reports_aggregate' => ['报表聚合（按阶段分组求和/计数）', function () use ($tenantFilter) {
                $tenantFilter(Opportunity::query())
                    ->select('stage_id', DB::raw('count(*) as total'), DB::raw('sum(value) as value'))
                    ->groupBy('stage_id')
                    ->get();
            }],
        ];
    }
}
