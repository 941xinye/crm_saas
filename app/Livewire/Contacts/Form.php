<?php

namespace App\Livewire\Contacts;

use App\Models\Company;
use App\Models\Contact;
use App\Services\CustomFieldService;
use App\Services\PlanService;
use App\Services\Tenancy;
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
    /** 自定义字段值（key => value，动态定义见 CustomFieldService） */
    public array $custom_fields = [];
    /** 同邮箱联系人查重提示（非阻塞，仅警告；同租户邮箱有唯一约束） */
    public ?string $duplicateWarning = null;

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

        $this->custom_fields = $this->contact?->custom_fields ?? [];
    }

    /**
     * 邮箱实时查重（租户内精确匹配，编辑时排除自身；emails 表级唯一 (tenant_id, email)）。
     */
    public function updatedEmail(): void
    {
        $email = trim($this->email);

        if ($email === '') {
            $this->duplicateWarning = null;
            return;
        }

        $query = Contact::where('email', $email);
        if ($this->contact?->exists) {
            $query->whereKeyNot($this->contact->id);
        }

        $this->duplicateWarning = $query->exists()
            ? __('contacts.duplicate_email_warning', ['email' => $email])
            : null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', app(Tenancy::class)->existsRule('companies')],
            ...app(CustomFieldService::class)->validationRules('contact'),
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
            'custom_fields' => app(CustomFieldService::class)->sanitizeValues('contact', $this->custom_fields),
        ];

        if ($this->contact->exists) {
            $this->contact->update($data);
            session()->flash('success', __('contacts.updated'));
        } else {
            app(PlanService::class)->ensureCanCreate(app(Tenancy::class)->current(), 'contacts');
            Contact::create($data);
            session()->flash('success', __('contacts.created'));
        }
        $this->redirect(route('contacts.index'), navigate: true);
    }

    public function render()
    {
        $companies = Company::orderBy('name')->get();
        return view('livewire.contacts.form', ['companies' => $companies])
            ->layout('layouts.app', ['title' => $this->contact ? __('contacts.form_edit_title') : __('contacts.form_create_title')]);
    }
}
