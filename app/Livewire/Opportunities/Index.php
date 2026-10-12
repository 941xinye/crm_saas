<?php

namespace App\Livewire\Opportunities;

use App\Models\Opportunity;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $search = '';
    public ?int $stage_filter = null;
    protected $queryString = ['search', 'stage_filter'];
    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStageFilter(): void { $this->resetPage(); }

    public function delete(int $id): void
    {
        $o = Opportunity::findOrFail($id);
        $o->delete();
        session()->flash('success', "商机「{$o->title}」已删除");
    }

    public function render()
    {
        $opportunities = Opportunity::query()
            ->with(['company', 'stage', 'owner'])
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->stage_filter, fn ($q) => $q->where('stage_id', $this->stage_filter))
            ->orderByDesc('created_at')->paginate(15);

        $stages = \App\Models\Stage::ordered()->get();

        return view('livewire.opportunities.index', [
            'opportunities' => $opportunities,
            'stages' => $stages,
        ])->layout('layouts.app', ['title' => '商机']);
    }
}
