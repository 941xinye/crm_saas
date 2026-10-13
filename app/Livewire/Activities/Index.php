<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Services\AuditService;
use App\Services\CsvService;
use App\Services\Tenancy;
use Livewire\Component;
use App\Livewire\Concerns\WithCsvAsyncImport;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads, WithCsvAsyncImport;

    public string $search = '';
    public array $selected = [];
    public $importFile;

    protected $queryString = ['search'];

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
        $a = Activity::findOrFail($id);
        $a->delete();
        session()->flash('success', __('activities.deleted'));
    }

    /**
     * 批量删除选中的活动。
     */
    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', __('activities.select_for_delete'));
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->selected)));
        $count = AuditService::withBatch('deleted', Activity::class, function () use ($ids) {
            $deleted = 0;
            foreach ($ids as $id) {
                $activity = Activity::find($id);
                if ($activity) {
                    $activity->delete();
                    $deleted++;
                }
            }
            return $deleted;
        });

        $this->selected = [];
        session()->flash('success', __('activities.bulk_deleted', ['count' => $count]));
    }

    public function export()
    {
        return app(CsvService::class)->download(Activity::class, __('activities.export_filename', ['date' => now()->format('Y-m-d')]));
    }

    public function downloadTemplate()
    {
        return app(CsvService::class)->templateDownload(Activity::class, __('activities.template_filename'));
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        // 超过阈值的文件转后台队列导入（见 docs/csv.md）
        if ($this->queueLargeImport(Activity::class)) {
            return;
        }

        try {
            $result = app(CsvService::class)->import(
                Activity::class,
                $this->importFile->get(),
                app(Tenancy::class)->current(),
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->importFile = null;
            return;
        }

        $this->importFile = null;

        $dupNote = ($result['duplicates'] ?? 0) > 0 ? __('activities.import_duplicates_note', ['count' => $result['duplicates'] ?? 0]) : '';

        if ($result['errors'] === []) {
            session()->flash('success', __('activities.import_success', ['created' => $result['created']]) . $dupNote);
        } else {
            session()->flash('error',
                __('activities.import_completed', ['created' => $result['created']])
                . $dupNote
                . __('activities.import_failed_part', ['count' => count($result['errors'])])
                . implode('<br>', array_map(
                    fn ($row, $msg) => __('activities.import_error_row', ['row' => $row, 'message' => $msg]),
                    array_keys($result['errors']),
                    $result['errors'],
                ))
            );
        }
    }

    public function render()
    {
        $activities = Activity::query()->with(['user', 'activitable'])
            ->when($this->search, fn ($q) => $q->where('subject', 'like', "%{$this->search}%"))
            ->orderByDesc('scheduled_at')->paginate(15);
        return view('livewire.activities.index', ['activities' => $activities])
            ->layout('layouts.app', ['title' => __('activities.title')]);
    }
}
