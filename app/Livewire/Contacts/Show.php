<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use Livewire\Component;

class Show extends Component
{
    public Contact $contact;

    public function render()
    {
        return view('livewire.contacts.show')
            ->layout('layouts.app', ['title' => $this->contact->full_name]);
    }
}
