<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Services\AuditService;
use App\Services\CsvService;
use App\Services\Tenancy;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads;

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
        session()->flash('success', '活动已删除');
    }

    /**
     * 批量删除选中的活动。
     */
    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', '请先选择要删除的活动');
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
        session()->flash('success', "已批量删除 {$count} 条活动");
    }

    public function export()
    {
        return app(CsvService::class)->download(Activity::class, '活动_' . now()->format('Y-m-d') . '.csv');
    }

    public function downloadTemplate()
    {
        return app(CsvService::class)->templateDownload(Activity::class, '活动_导入模板.csv');
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

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

        $dupNote = ($result['duplicates'] ?? 0) > 0 ? "，跳过重复 " . ($result['duplicates'] ?? 0) . " 条" : '';

        if ($result['errors'] === []) {
            session()->flash('success', "导入成功：{$result['created']} 条活动{$dupNote}");
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
        $activities = Activity::query()->with(['user', 'activitable'])
            ->when($this->search, fn ($q) => $q->where('subject', 'like', "%{$this->search}%"))
            ->orderByDesc('scheduled_at')->paginate(15);
        return view('livewire.activities.index', ['activities' => $activities])
            ->layout('layouts.app', ['title' => '活动']);
    }
}
