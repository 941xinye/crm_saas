<?php

namespace App\Livewire\Opportunities;

use App\Livewire\Concerns\WithAdvancedFiltering;
use App\Livewire\Concerns\WithCsvAsyncImport;
use App\Livewire\Concerns\WithDataPermissions;
use App\Models\Opportunity;
use App\Services\AuditService;
use App\Services\CsvService;
use App\Services\Tenancy;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads, WithAdvancedFiltering, WithDataPermissions, WithCsvAsyncImport;

    protected array $sortableColumns = ['title', 'value', 'expected_close_date', 'created_at', 'updated_at', 'score'];

    public string $search = '';
    public ?int $stage_filter = null;
    public bool $showTrashed = false;
    public ?int $bulk_stage = null;
    public ?int $bulk_owner = null;
    public $importFile;
    public array $selected = [];

    protected $queryString = ['search', 'stage_filter', 'showTrashed', 'sort_by', 'sort_dir', 'owner_filter', 'cf_key', 'cf_value'];

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatingStageFilter(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatingShowTrashed(): void
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
        $o = Opportunity::findOrFail($id);
        $this->authorize('delete', $o);
        $o->delete();
        session()->flash('success', __('opportunities.index_deleted', ['title' => $o->title]));
    }

    /**
     * 恢复软删除的商机。
     */
    public function restore(int $id): void
    {
        $o = Opportunity::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $o);
        $o->restore();
        session()->flash('success', __('opportunities.index_restored', ['title' => $o->title]));
    }

    /**
     * 彻底删除商机（不可恢复）。
     */
    public function forceDelete(int $id): void
    {
        $o = Opportunity::onlyTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $o);
        $title = $o->title;
        $o->forceDelete();
        session()->flash('success', __('opportunities.index_force_deleted', ['title' => $title]));
    }

    /**
     * 批量删除选中的商机。
     */
    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', __('opportunities.index_error_select_for_delete'));
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->selected)));
        $count = AuditService::withBatch('deleted', Opportunity::class, function () use ($ids) {
            $deleted = 0;
            foreach ($ids as $id) {
                $opp = Opportunity::find($id);
                if ($opp) {
                    $opp->delete();
                    $deleted++;
                }
            }
            return $deleted;
        });

        $this->selected = [];
        session()->flash('success', __('opportunities.index_bulk_deleted', ['count' => $count]));
    }

    /**
     * 批量修改选中商机的阶段。
     */
    public function bulkUpdateStage(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', __('opportunities.index_error_select'));
            return;
        }
        if (!$this->bulk_stage) {
            session()->flash('error', __('opportunities.index_error_select_stage'));
            return;
        }

        // 阶段必须属于当前租户，防止跨租户注入
        $stage = \App\Models\Stage::findOrFail($this->bulk_stage);

        $ids = array_values(array_unique(array_map('intval', $this->selected)));
        $count = AuditService::withBatch('updated', Opportunity::class, function () use ($ids, $stage) {
            $updated = 0;
            foreach ($ids as $id) {
                $opp = Opportunity::find($id);
                if ($opp && $opp->stage_id !== $stage->id) {
                    $opp->update(['stage_id' => $stage->id]);
                    $updated++;
                }
            }
            return $updated;
        });

        $this->selected = [];
        $this->bulk_stage = null;
        session()->flash('success', __('opportunities.index_bulk_stage_updated', ['count' => $count, 'stage' => $stage->name]));
    }

    /**
     * 批量修改选中商机的负责人。
     */
    public function bulkUpdateOwner(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', __('opportunities.index_error_select'));
            return;
        }
        if (!$this->bulk_owner) {
            session()->flash('error', __('opportunities.index_error_select_owner'));
            return;
        }

        // 负责人必须属于当前租户
        $owner = app(Tenancy::class)->users()->findOrFail($this->bulk_owner);

        $ids = array_values(array_unique(array_map('intval', $this->selected)));
        $count = AuditService::withBatch('updated', Opportunity::class, function () use ($ids, $owner) {
            $updated = 0;
            foreach ($ids as $id) {
                $opp = Opportunity::find($id);
                if ($opp && $opp->owner_id !== $owner->id) {
                    $opp->update(['owner_id' => $owner->id]);
                    $updated++;
                }
            }
            return $updated;
        });

        $this->selected = [];
        $this->bulk_owner = null;
        session()->flash('success', __('opportunities.index_bulk_owner_updated', ['count' => $count, 'owner' => $owner->name]));
    }

    public function export()
    {
        return app(CsvService::class)->download(Opportunity::class, __('opportunities.index_export_filename', ['date' => now()->format('Y-m-d')]));
    }

    public function downloadTemplate()
    {
        return app(CsvService::class)->templateDownload(Opportunity::class, __('opportunities.index_template_filename'));
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        // 超过阈值的文件转后台队列导入（见 docs/csv.md）
        if ($this->queueLargeImport(Opportunity::class)) {
            return;
        }

        try {
            $result = app(CsvService::class)->import(
                Opportunity::class,
                $this->importFile->get(),
                app(Tenancy::class)->current(),
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->importFile = null;
            return;
        }

        $this->importFile = null;

        $dupNote = ($result['duplicates'] ?? 0) > 0 ? __('opportunities.index_import_duplicates', ['count' => $result['duplicates'] ?? 0]) : '';

        if ($result['errors'] === []) {
            session()->flash('success', __('opportunities.index_import_success', ['count' => $result['created']]) . $dupNote);
        } else {
            session()->flash('error', __('opportunities.index_import_failed_summary', [
                'created' => $result['created'],
                'duplicated' => $dupNote,
                'failed' => count($result['errors']),
                'details' => implode('<br>', array_map(
                    fn ($row, $msg) => __('opportunities.index_import_error_row', ['row' => $row, 'message' => $msg]),
                    array_keys($result['errors']),
                    $result['errors'],
                )),
            ]));
        }
    }

    public function render()
    {
        $query = Opportunity::query()
            ->with(['company', 'stage', 'owner'])
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->stage_filter, fn ($q) => $q->where('stage_id', $this->stage_filter));

        // 数据权限：sales 仅见自己负责的商机
        $query = $this->applyDataScope($query);
        $query = $this->applyOwnerFilter($query);
        $query = $this->applyCustomFieldFilter($query, 'opportunity');

        if ($this->showTrashed) {
            $query->onlyTrashed();
        }

        $opportunities = $this->applySorting($query)->paginate(15);

        $stages = \App\Models\Stage::ordered()->get();
        $owners = $this->ownerOptions();

        return view('livewire.opportunities.index', [
            'opportunities' => $opportunities,
            'stages' => $stages,
            'owners' => $owners,
            'cfDefinitions' => app(\App\Services\CustomFieldService::class)->definitions('opportunity'),
        ])->layout('layouts.app', ['title' => __('opportunities.common_opportunity')]);
    }
}
