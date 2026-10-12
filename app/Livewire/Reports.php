<?php

namespace App\Livewire;

use App\Services\CsvService;
use App\Services\ReportService;
use Livewire\Component;

class Reports extends Component
{
    public string $period = '6'; // 近 N 个月

    protected $queryString = ['period'];

    public function render(ReportService $service)
    {
        $funnel = $service->funnel();
        $performance = $service->performanceByOwner();
        $trend = $service->monthlyTrend((int) $this->period);
        $stageBreakdown = $service->stageBreakdown();

        return view('livewire.reports', [
            'funnel' => $funnel,
            'performance' => $performance,
            'trend' => $trend,
            'stageBreakdown' => $stageBreakdown,
        ])->layout('layouts.app', ['title' => '数据报表']);
    }

    /**
     * 报表 CSV 导出：trend（月度趋势）/ performance（团队业绩）/ stage（阶段金额分布）/ funnel（销售漏斗）。
     */
    public function exportCsv(string $dataset)
    {
        $service = app(ReportService::class);

        $rows = match ($dataset) {
            'trend' => self::trendRows($service, (int) $this->period),
            'performance' => self::performanceRows($service),
            'stage' => self::stageRows($service),
            'funnel' => self::funnelRows($service),
            default => null,
        };

        if ($rows === null) {
            abort(404);
        }

        $names = [
            'trend' => '月度趋势',
            'performance' => '团队业绩',
            'stage' => '阶段金额分布',
            'funnel' => '销售漏斗',
        ];

        return app(CsvService::class)->downloadReport($names[$dataset] . '.csv', $rows);
    }

    public static function trendRows(ReportService $service, int $months = 6): array
    {
        $trend = $service->monthlyTrend($months);
        $rows = [];

        foreach ($trend['labels'] as $i => $label) {
            $rows[] = [
                '月份' => $label,
                '新增线索' => $trend['leads'][$i] ?? 0,
                '新增公司' => $trend['companies'][$i] ?? 0,
                '新增商机' => $trend['opportunities'][$i] ?? 0,
                '商机总额' => $trend['values'][$i] ?? 0,
            ];
        }

        return $rows;
    }

    public static function performanceRows(ReportService $service): array
    {
        return collect($service->performanceByOwner())
            ->map(fn ($p) => [
                '负责人' => $p['user_name'],
                '商机数' => $p['opp_count'],
                '成交额' => $p['total_value'],
                '赢单数' => $p['won_count'],
                '赢率(%)' => $p['win_rate'],
            ])
            ->all();
    }

    public static function stageRows(ReportService $service): array
    {
        return collect($service->stageBreakdown())
            ->map(fn ($s) => [
                '阶段' => $s['stage_name'],
                '商机数' => $s['count'],
                '金额合计' => $s['total_value'],
            ])
            ->all();
    }

    public static function funnelRows(ReportService $service): array
    {
        $funnel = $service->funnel();

        return [
            ['指标' => '线索总数', '数值' => $funnel['total_leads']],
            ['指标' => '联系人总数', '数值' => $funnel['total_contacts']],
            ['指标' => '商机总数', '数值' => $funnel['total_opportunities']],
            ['指标' => '已转化线索', '数值' => $funnel['converted_leads']],
            ['指标' => '线索→客户转化率(%)', '数值' => $funnel['conversion_rate']],
        ];
    }
}
