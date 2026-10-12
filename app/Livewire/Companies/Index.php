<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    protected $queryString = ['search'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $company = Company::findOrFail($id);
        $company->delete();
        session()->flash('success', "公司「{$company->name}」已删除");
    }

    public function render()
    {
        $companies = Company::query()
            ->with('owner')
            ->when($this->search, function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('industry', 'like', "%{$this->search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.companies.index', [
            'companies' => $companies,
        ])->layout('layouts.app', ['title' => '公司']);
    }
}
