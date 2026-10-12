<?php

namespace App\Livewire\Opportunities;

use App\Models\Opportunity;
use Livewire\Component;

class Show extends Component
{
    public Opportunity $opportunity;

    public function render()
    {
        $activities = $this->opportunity->activities()->with('user')->latest()->get();
        return view('livewire.opportunities.show', [
            'activities' => $activities,
        ])->layout('layouts.app', ['title' => $this->opportunity->title]);
    }
}
