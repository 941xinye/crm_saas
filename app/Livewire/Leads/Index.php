<?php

namespace App\Livewire\Leads;

use App\Livewire\Concerns\WithAdvancedFiltering;
use App\Livewire\Concerns\WithCsvAsyncImport;
use App\Livewire\Concerns\WithDataPermissions;
use App\Models\Lead;
use App\Services\AuditService;
use App\Services\CsvService;
use App\Services\Tenancy;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads, WithAdvancedFiltering, WithDataPermissions, WithCsvAsyncImport;

    protected array $sortableColumns = ['name', 'status', 'created_at', 'updated_at'];

    public string $search = '';
    public $importFile;
    public array $selected = [];

    protected $queryString = ['search', 'sort_by', 'sort_dir', 'owner_filter'];

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatingPage(): void
    {
        $this->selected = [];
    }

    public function delete(int $id): void
    {
        $l = Lead::findOrFail($id);
        $l->delete();
        session()->flash('success', __('leads.deleted', ['name' => $l->name]));
    }

    /**
     * 批量删除选中的线索。
     */
    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', __('leads.select_for_delete'));
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->selected)));
        $count = AuditService::withBatch('deleted', Lead::class, function () use ($ids) {
            $deleted = 0;
            foreach ($ids as $id) {
                $lead = Lead::find($id);
                if ($lead) {
                    $lead->delete();
                    $deleted++;
                }
            }
            return $deleted;
        });

        $this->selected = [];
        session()->flash('success', __('leads.bulk_deleted', ['count' => $count]));
    }

    public function export()
    {
        return app(CsvService::class)->download(Lead::class, __('leads.export_filename', ['date' => now()->format('Y-m-d')]));
    }

    public function downloadTemplate()
    {
        return app(CsvService::class)->templateDownload(Lead::class, __('leads.template_filename'));
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        // 超过阈值的文件转后台队列导入（见 docs/csv.md）
        if ($this->queueLargeImport(Lead::class)) {
            return;
        }

        try {
            $result = app(CsvService::class)->import(
                Lead::class,
                $this->importFile->get(),
                app(Tenancy::class)->current(),
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->importFile = null;
            return;
        }

        $this->importFile = null;

        $dupNote = ($result['duplicates'] ?? 0) > 0 ? __('leads.import_duplicates_note', ['count' => $result['duplicates'] ?? 0]) : '';

        if ($result['errors'] === []) {
            session()->flash('success', __('leads.import_success', ['created' => $result['created']]) . $dupNote);
        } else {
            session()->flash('error',
                __('leads.import_completed', ['created' => $result['created']])
                . $dupNote
                . __('leads.import_failed_part', ['count' => count($result['errors'])])
                . implode('<br>', array_map(
                    fn ($row, $msg) => __('leads.import_error_row', ['row' => $row, 'message' => $msg]),
                    array_keys($result['errors']),
                    $result['errors'],
                ))
            );
        }
    }

    public function render()
    {
        $query = $this->applyDataScope(
            $this->applyOwnerFilter(
                Lead::query()->with('owner')
                    ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"))
            )
        );

        $leads = $this->applySorting($query)->paginate(15);
        $owners = $this->ownerOptions();

        return view('livewire.leads.index', ['leads' => $leads, 'owners' => $owners])
            ->layout('layouts.app', ['title' => __('leads.title')]);
    }
}
