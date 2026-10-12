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
        session()->flash('success', __('companies.deleted', ['name' => $company->name]));
    }

    /**
     * 批量删除选中的公司。
     */
    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            session()->flash('error', __('companies.select_for_bulk_delete'));
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
        session()->flash('success', __('companies.bulk_deleted', ['count' => $count]));
    }

    public function export()
    {
        return app(CsvService::class)->download(
            Company::class,
            __('companies.export_filename', ['date' => now()->format('Y-m-d')]),
        );
    }

    public function downloadTemplate()
    {
        return app(CsvService::class)->templateDownload(
            Company::class,
            __('companies.template_filename'),
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

        $dupNote = ($result['duplicates'] ?? 0) > 0
            ? __('companies.import_duplicates_note', ['count' => $result['duplicates'] ?? 0])
            : '';

        if ($result['errors'] === []) {
            session()->flash('success', __('companies.import_success', [
                'created' => $result['created'],
                'note' => $dupNote,
            ]));
        } else {
            session()->flash('error', __('companies.import_partial', [
                'created' => $result['created'],
                'note' => $dupNote,
                'failed' => count($result['errors']),
                'details' => implode('<br>', array_map(
                    fn ($row, $msg) => __('companies.import_error_row', ['row' => $row, 'message' => $msg]),
                    array_keys($result['errors']),
                    $result['errors'],
                )),
            ]));
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
        ])->layout('layouts.app', ['title' => __('companies.index_title')]);
    }
}
