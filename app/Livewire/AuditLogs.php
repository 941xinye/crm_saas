<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Services\CsvService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogs extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $action_filter = null;
    public ?string $model_filter = null;
    public ?string $date_from = null;
    public ?string $date_to = null;

    protected $queryString = ['search', 'action_filter', 'model_filter', 'date_from', 'date_to'];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingActionFilter(): void { $this->resetPage(); }
    public function updatingModelFilter(): void { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }

    /**
     * 带筛选条件的基础查询（渲染与 CSV 导出共用）。
     * Web 请求下 AuditLog 挂 TenantScope 自动按租户过滤。
     */
    private function baseQuery(): Builder
    {
        $query = AuditLog::query();

        if ($this->action_filter) {
            $query->where('action', $this->action_filter);
        }
        if ($this->model_filter) {
            $query->where('auditable_type', $this->model_filter);
        }
        if ($this->date_from) {
            $query->whereDate('created_at', '>=', $this->date_from);
        }
        if ($this->date_to) {
            $query->whereDate('created_at', '<=', $this->date_to);
        }
        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('route', 'like', "%{$s}%")
                  ->orWhere('ip', 'like', "%{$s}%")
                  ->orWhere('batch_id', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$s}%"));
            });
        }

        return $query;
    }

    /**
     * 导出当前筛选结果为 CSV（复用 CsvService，含 UTF-8 BOM）。
     */
    public function exportCsv()
    {
        return app(CsvService::class)->downloadAuditLogs(
            $this->baseQuery()->orderByDesc('created_at'),
            __('audit.export_filename', ['date' => now()->format('Y-m-d')]),
        );
    }

    public function render()
    {
        $query = $this->baseQuery()
            ->with(['user:id,name', 'auditable'])
            ->orderByDesc('created_at');

        // 用于在视图里把同一次批量操作的日志分组高亮
        $batchGroups = AuditLog::query()
            ->select('batch_id')
            ->whereNotNull('batch_id')
            ->groupBy('batch_id')
            ->pluck('batch_id')
            ->toArray();

        return view('livewire.audit-logs', [
            'logs' => $query->paginate(20),
            'batchGroups' => $batchGroups,
            'actions' => [
                'created' => __('audit.action_created'),
                'updated' => __('audit.action_updated'),
                'deleted' => __('audit.action_deleted'),
                'restored' => __('audit.action_restored'),
                'batch_deleted' => __('audit.action_batch_deleted'),
                'batch_updated' => __('audit.action_batch_updated'),
                'batch_created' => __('audit.action_batch_created'),
            ],
            'models' => [
                \App\Models\Company::class => __('audit.model_company'),
                \App\Models\Contact::class => __('audit.model_contact'),
                \App\Models\Lead::class => __('audit.model_lead'),
                \App\Models\Opportunity::class => __('audit.model_opportunity'),
                \App\Models\Attachment::class => __('audit.model_attachment'),
            ],
        ])->layout('layouts.app', ['title' => __('audit.title')]);
    }
}
