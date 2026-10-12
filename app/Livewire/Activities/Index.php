<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
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
        $a = Activity::findOrFail($id);
        $a->delete();
        session()->flash('success', '活动已删除');
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
