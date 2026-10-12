<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $search = '';
    protected $queryString = ['search'];
    public function updatingSearch(): void { $this->resetPage(); }

    public function delete(int $id): void
    {
        $l = Lead::findOrFail($id);
        $l->delete();
        session()->flash('success', "线索「{$l->name}」已删除");
    }

    public function render()
    {
        $leads = Lead::query()->with('owner')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"))
            ->orderByDesc('created_at')->paginate(15);
        return view('livewire.leads.index', ['leads' => $leads])
            ->layout('layouts.app', ['title' => '线索']);
    }
}
