<?php

namespace App\Livewire\Contacts;

use App\Livewire\Concerns\WithAdvancedFiltering;
use App\Models\Contact;
use App\Services\AuditService;
use App\Services\CsvService;
use App\Services\Tenancy;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads, WithAdvancedFiltering;

    protected array $sortableColumns = ['first_name', 'last_name', 'email', 'created_at', 'updated_at'];

    public string $search = '';
    public $importFile;
    public array $selected = [];

    protected $queryString = ['search', 'sort_by', 'sort_dir', 'cf_key', 'cf_value'];

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
        $c = Contact::findOrFail($id);
        $c->delete();
        session()->flash('success', "联系人「{$c->full_name}」已删除");
    }

    /**
     * 批量删除选中的联系人。
     * 用 AuditService::withBatch 包起来，逐条 delete() 触发 Auditable trait，
     * 所有单条日志会自动带同一个 batch_id，另外还有一条汇总日志。
     */
    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', '请先选择要删除的联系人');
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->selected)));
        $count = AuditService::withBatch('deleted', Contact::class, function () use ($ids) {
            $deleted = 0;
            foreach ($ids as $id) {
                $contact = Contact::find($id);
                if ($contact) {
                    $contact->delete();
                    $deleted++;
                }
            }
            return $deleted;
        });

        $this->selected = [];
        session()->flash('success', "已批量删除 {$count} 条联系人");
    }

    public function export()
    {
        return app(CsvService::class)->download(Contact::class, '联系人_' . now()->format('Y-m-d') . '.csv');
    }

    public function downloadTemplate()
    {
        return app(CsvService::class)->templateDownload(Contact::class, '联系人_导入模板.csv');
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        try {
            $result = app(CsvService::class)->import(
                Contact::class,
                $this->importFile->get(),
                app(Tenancy::class)->current(),
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->importFile = null;
            return;
        }

        $this->importFile = null;

        $dupNote = ($result['duplicates'] ?? 0) > 0 ? "，跳过重复 " . ($result['duplicates'] ?? 0) . " 条（按邮箱查重）" : '';

        if ($result['errors'] === []) {
            session()->flash('success', "导入成功：{$result['created']} 条联系人{$dupNote}");
        } else {
            session()->flash('error', $this->formatImportErrors($result, $dupNote));
        }
    }

    private function formatImportErrors(array $result, string $dupNote = ''): string
    {
        return sprintf(
            "导入完成：成功 {$result['created']} 条{$dupNote}，失败 %d 条。<br>错误详情：%s",
            count($result['errors']),
            implode('<br>', array_map(
                fn ($row, $msg) => "行 {$row}：{$msg}",
                array_keys($result['errors']),
                $result['errors'],
            )),
        );
    }

    public function render()
    {
        $query = Contact::query()->with('company')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            }));

        $contacts = $this->applySorting($this->applyCustomFieldFilter($query, 'contact'))->paginate(15);

        return view('livewire.contacts.index', [
            'contacts' => $contacts,
            'cfDefinitions' => app(\App\Services\CustomFieldService::class)->definitions('contact'),
        ])->layout('layouts.app', ['title' => '联系人']);
    }
}
