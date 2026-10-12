<?php

namespace App\Livewire\Contacts;

use App\Models\Company;
use App\Models\Contact;
use Livewire\Component;

class Form extends Component
{
    public ?Contact $contact = null;
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone = '';
    public string $position = '';
    public ?int $company_id = null;

    public function mount(?Contact $contact = null): void
    {
        if ($contact) {
            $this->contact = $contact;
            $this->first_name = $contact->first_name;
            $this->last_name = $contact->last_name;
            $this->email = $contact->email ?? '';
            $this->phone = $contact->phone ?? '';
            $this->position = $contact->position ?? '';
            $this->company_id = $contact->company_id;
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'exists:companies,id'],
        ];
    }

    public function save(): void
    {
        $this->validate();
        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'company_id' => $this->company_id,
        ];

        if ($this->contact) {
            $this->contact->update($data);
            session()->flash('success', '联系人已更新');
        } else {
            Contact::create($data);
            session()->flash('success', '联系人已创建');
        }
        $this->redirect(route('contacts.index'), navigate: true);
    }

    public function render()
    {
        $companies = Company::orderBy('name')->get();
        return view('livewire.contacts.form', ['companies' => $companies])
            ->layout('layouts.app', ['title' => ($this->contact ? '编辑' : '新建') . '联系人']);
    }
}
