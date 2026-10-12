<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use Livewire\Component;

class Show extends Component
{
    public Company $company;

    public function render()
    {
        $contacts = $this->company->contacts()->latest()->take(10)->get();
        $opportunities = $this->company->opportunities()->with('stage')->latest()->take(10)->get();

        return view('livewire.companies.show', [
            'contacts' => $contacts,
            'opportunities' => $opportunities,
        ])->layout('layouts.app', ['title' => $this->company->name]);
    }
}
