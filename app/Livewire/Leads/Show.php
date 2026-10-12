<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use Livewire\Component;

class Show extends Component
{
    public Lead $lead;

    public function render()
    {
        return view('livewire.leads.show')
            ->layout('layouts.app', ['title' => $this->lead->name]);
    }
}
