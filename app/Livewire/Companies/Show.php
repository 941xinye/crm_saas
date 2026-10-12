<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use Livewire\Component;

class Show extends Component
{
    public Company $company;

    public function mount(Company $company): void
    {
        // sales 仅可查看自己负责的公司
        $this->authorize('view', $company);
        $this->company = $company;
    }

    public function render()
    {
        $contacts = $this->company->contacts()->latest()->take(10)->get();
        $opportunities = $this->company->opportunities()->with('stage')->latest()->take(10)->get();
        $activities = $this->company->activities()->with('user')->latest()->take(20)->get();

        return view('livewire.companies.show', [
            'contacts' => $contacts,
            'opportunities' => $opportunities,
            'activities' => $activities,
        ])->layout('layouts.app', ['title' => $this->company->name]);
    }
}
