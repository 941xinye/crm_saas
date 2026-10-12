<?php

namespace App\Livewire\Companies;

use App\Livewire\Concerns\WithAdvancedFiltering;
use App\Livewire\Concerns\WithDataPermissions;
use App\Models\Company;
use App\Services\AuditService;
use App\Services\CsvService;
use App\Services\Tenancy;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads, WithAdvancedFiltering, WithDataPermissions;

    protected array $sortableColumns = ['name', 'created_at', 'updated_at'];

    public string $search = '';
    public $importFile;
    public array $selected = [];

    protected $queryString = ['search', 'sort_by', 'sort_dir', 'owner_filter', 'cf_key', 'cf_value'];

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
        $company = Company::findOrFail($id);
        $company->delete();
        session()->flash('success', "公司「{$company->name}」已删除");
    }

    /**
     * 批量删除选中的公司。
     */
    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', '请先选择要删除的公司');
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->selected)));
        $count = AuditService::withBatch('deleted', Company::class, function () use ($ids) {
            $deleted = 0;
            foreach ($ids as $id) {
                $company = Company::find($id);
                if ($company) {
                    $company->delete();
                    $deleted++;
                }
            }
            return $deleted;
        });

        $this->selected = [];
        session()->flash('success', "已批量删除 {$count} 条公司");
    }

    public function export()
    {
        return app(CsvService::class)->download(
            Company::class,
            '公司_' . now()->format('Y-m-d') . '.csv',
        );
    }

    public function downloadTemplate()
    {
        return app(CsvService::class)->templateDownload(
            Company::class,
            '公司_导入模板.csv',
        );
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        try {
            $result = app(CsvService::class)->import(
                Company::class,
                $this->importFile->get(),
                app(Tenancy::class)->current(),
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->importFile = null;
            return;
        }

        $this->importFile = null;

        $dupNote = ($result['duplicates'] ?? 0) > 0 ? "，跳过重复 " . ($result['duplicates'] ?? 0) . " 条（按名称查重）" : '';

        if ($result['errors'] === []) {
            session()->flash('success', "导入成功：{$result['created']} 条公司{$dupNote}");
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
        $query = $this->applyDataScope(
            $this->applyCustomFieldFilter(
                $this->applyOwnerFilter(
                    Company::query()->with('owner')
                        ->when($this->search, function ($q) {
                            $q->where('name', 'like', "%{$this->search}%")
                                ->orWhere('industry', 'like', "%{$this->search}%");
                        })
                ),
                'company'
            )
        );

        $companies = $this->applySorting($query)->paginate(15);
        $owners = $this->ownerOptions();

        return view('livewire.companies.index', [
            'companies' => $companies,
            'owners' => $owners,
            'cfDefinitions' => app(\App\Services\CustomFieldService::class)->definitions('company'),
        ])->layout('layouts.app', ['title' => '公司']);
    }
}
