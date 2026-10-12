<?php

namespace App\Livewire\Opportunities;

use App\Livewire\Concerns\WithAdvancedFiltering;
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
    use WithPagination, WithFileUploads, WithAdvancedFiltering, WithDataPermissions;

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
        session()->flash('success', "商机「{$o->title}」已删除");
    }

    /**
     * 恢复软删除的商机。
     */
    public function restore(int $id): void
    {
        $o = Opportunity::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $o);
        $o->restore();
        session()->flash('success', "商机「{$o->title}」已恢复");
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
        session()->flash('success', "商机「{$title}」已彻底删除");
    }

    /**
     * 批量删除选中的商机。
     */
    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', '请先选择要删除的商机');
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
        session()->flash('success', "已批量删除 {$count} 条商机");
    }

    /**
     * 批量修改选中商机的阶段。
     */
    public function bulkUpdateStage(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', '请先选择要操作的商机');
            return;
        }
        if (!$this->bulk_stage) {
            session()->flash('error', '请先选择目标阶段');
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
        session()->flash('success', "已将 {$count} 条商机的阶段更新为「{$stage->name}」");
    }

    /**
     * 批量修改选中商机的负责人。
     */
    public function bulkUpdateOwner(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', '请先选择要操作的商机');
            return;
        }
        if (!$this->bulk_owner) {
            session()->flash('error', '请先选择目标负责人');
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
        session()->flash('success', "已将 {$count} 条商机的负责人更新为「{$owner->name}」");
    }

    public function export()
    {
        return app(CsvService::class)->download(Opportunity::class, '商机_' . now()->format('Y-m-d') . '.csv');
    }

    public function downloadTemplate()
    {
        return app(CsvService::class)->templateDownload(Opportunity::class, '商机_导入模板.csv');
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

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

        $dupNote = ($result['duplicates'] ?? 0) > 0 ? "，跳过重复 " . ($result['duplicates'] ?? 0) . " 条" : '';

        if ($result['errors'] === []) {
            session()->flash('success', "导入成功：{$result['created']} 条商机{$dupNote}");
        } else {
            session()->flash('error', sprintf(
                "导入完成：成功 {$result['created']} 条{$dupNote}，失败 %d 条。<br>错误详情：%s",
                count($result['errors']),
                implode('<br>', array_map(
                    fn ($row, $msg) => "行 {$row}：{$msg}",
                    array_keys($result['errors']),
                    $result['errors'],
                )),
            ));
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
        ])->layout('layouts.app', ['title' => '商机']);
    }
}
