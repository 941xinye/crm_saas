<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use Livewire\Component;

class Show extends Component
{
    public Contact $contact;

    public function render()
    {
        $activities = $this->contact->activities()->with('user')->latest()->take(20)->get();

        return view('livewire.contacts.show', [
            'activities' => $activities,
        ])->layout('layouts.app', ['title' => $this->contact->full_name]);
    }
}
