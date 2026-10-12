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
        ])->layout('layouts.app', ['title' => __('reports.title')]);
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
            'trend' => __('reports.trend_title'),
            'performance' => __('reports.performance_title'),
            'stage' => __('reports.stage_title'),
            'funnel' => __('reports.funnel_title'),
        ];

        return app(CsvService::class)->downloadReport($names[$dataset] . '.csv', $rows);
    }

    public static function trendRows(ReportService $service, int $months = 6): array
    {
        $trend = $service->monthlyTrend($months);
        $rows = [];

        foreach ($trend['labels'] as $i => $label) {
            $rows[] = [
                __('reports.csv_month') => $label,
                __('reports.new_leads') => $trend['leads'][$i] ?? 0,
                __('reports.new_companies') => $trend['companies'][$i] ?? 0,
                __('reports.new_opportunities') => $trend['opportunities'][$i] ?? 0,
                __('reports.total_value') => $trend['values'][$i] ?? 0,
            ];
        }

        return $rows;
    }

    public static function performanceRows(ReportService $service): array
    {
        return collect($service->performanceByOwner())
            ->map(fn ($p) => [
                __('reports.owner') => $p['user_name'],
                __('reports.opp_count') => $p['opp_count'],
                __('reports.closed_value') => $p['total_value'],
                __('reports.won_count') => $p['won_count'],
                __('reports.csv_win_rate') => $p['win_rate'],
            ])
            ->all();
    }

    public static function stageRows(ReportService $service): array
    {
        return collect($service->stageBreakdown())
            ->map(fn ($s) => [
                __('reports.stage') => $s['stage_name'],
                __('reports.opp_count') => $s['count'],
                __('reports.csv_amount_total') => $s['total_value'],
            ])
            ->all();
    }

    public static function funnelRows(ReportService $service): array
    {
        $funnel = $service->funnel();

        return [
            [__('reports.csv_metric') => __('reports.total_leads'), __('reports.csv_value') => $funnel['total_leads']],
            [__('reports.csv_metric') => __('reports.total_contacts'), __('reports.csv_value') => $funnel['total_contacts']],
            [__('reports.csv_metric') => __('reports.total_opportunities'), __('reports.csv_value') => $funnel['total_opportunities']],
            [__('reports.csv_metric') => __('reports.converted_leads'), __('reports.csv_value') => $funnel['converted_leads']],
            [__('reports.csv_metric') => __('reports.conversion_rate'), __('reports.csv_value') => $funnel['conversion_rate']],
        ];
    }
}
