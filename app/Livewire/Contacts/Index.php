<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
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
        $c = Contact::findOrFail($id);
        $c->delete();
        session()->flash('success', "联系人「{$c->full_name}」已删除");
    }

    public function render()
    {
        $contacts = Contact::query()->with('company')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('created_at')->paginate(15);

        return view('livewire.contacts.index', ['contacts' => $contacts])
            ->layout('layouts.app', ['title' => '联系人']);
    }
}
