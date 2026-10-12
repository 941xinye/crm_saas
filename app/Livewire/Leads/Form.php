<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use App\Services\PlanService;
use App\Services\Tenancy;
use Livewire\Component;

class Form extends Component
{
    public ?Lead $lead = null;
    public string $name = '';
    public string $company_name = '';
    public string $email = '';
    public string $phone = '';
    public string $source = '';
    public string $status = 'new';
    public ?int $owner_id = null;

    public function mount(?Lead $lead = null): void
    {
        $this->lead = $lead ?? new Lead();

        $this->authorize($lead ? 'update' : 'create', $this->lead);

        if ($lead) {
            $this->lead = $lead;
            $this->name = $lead->name;
            $this->company_name = $lead->company_name ?? '';
            $this->email = $lead->email ?? '';
            $this->phone = $lead->phone ?? '';
            $this->source = $lead->source ?? '';
            $this->status = $lead->status ?? 'new';
            $this->owner_id = $lead->owner_id;
        } else {
            $this->owner_id = auth()->id();
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string'],
            'owner_id' => ['nullable', app(Tenancy::class)->existsRule('users')],
        ];
    }

    public function save(): void
    {
        $this->validate();
        $data = [
            'name' => $this->name, 'company_name' => $this->company_name,
            'email' => $this->email, 'phone' => $this->phone,
            'source' => $this->source, 'status' => $this->status, 'owner_id' => $this->owner_id,
        ];
        if ($this->lead->exists) { $this->lead->update($data); session()->flash('success', __('leads.updated')); }
        else { app(PlanService::class)->ensureCanCreate(app(Tenancy::class)->current(), 'leads'); Lead::create($data); session()->flash('success', __('leads.created')); }
        $this->redirect(route('leads.index'), navigate: true);
    }

    public function render()
    {
        $owners = app(Tenancy::class)->users()->orderBy('name')->get();
        return view('livewire.leads.form', ['owners' => $owners])
            ->layout('layouts.app', ['title' => $this->lead ? __('leads.edit_title') : __('leads.create')]);
    }
}
