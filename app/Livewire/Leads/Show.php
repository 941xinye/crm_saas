<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use Livewire\Component;

class Show extends Component
{
    public Lead $lead;

    public function mount(Lead $lead): void
    {
        // sales 仅可查看自己负责的线索
        $this->authorize('view', $lead);
        $this->lead = $lead;
    }

    public function render()
    {
        return view('livewire.leads.show')
            ->layout('layouts.app', ['title' => $this->lead->name]);
    }
}
