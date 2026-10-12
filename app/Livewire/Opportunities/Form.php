<?php

namespace App\Livewire\Opportunities;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Services\CustomFieldService;
use App\Services\PlanService;
use App\Services\Tenancy;
use Livewire\Component;

class Form extends Component
{
    public ?Opportunity $opportunity = null;
    public string $title = '';
    public ?float $value = null;
    public ?string $expected_close_date = null;
    public int $probability = 50;
    public ?int $stage_id = null;
    public ?int $company_id = null;
    public ?int $contact_id = null;
    public ?int $owner_id = null;
    /** 自定义字段值（key => value，动态定义见 CustomFieldService） */
    public array $custom_fields = [];

    public function mount(?Opportunity $opportunity = null): void
    {
        $this->opportunity = $opportunity ?? new Opportunity();

        $this->authorize($opportunity ? 'update' : 'create', $this->opportunity);

        if ($opportunity) {
            $this->opportunity = $opportunity;
            $this->title = $opportunity->title;
            $this->value = $opportunity->value;
            $this->expected_close_date = $opportunity->expected_close_date?->format('Y-m-d');
            $this->probability = $opportunity->probability ?? 50;
            $this->stage_id = $opportunity->stage_id;
            $this->company_id = $opportunity->company_id;
            $this->contact_id = $opportunity->contact_id;
            $this->owner_id = $opportunity->owner_id;
        } else {
            $this->owner_id = auth()->id();
            $firstStage = Stage::ordered()->first();
            $this->stage_id = $firstStage?->id;
        }

        $this->custom_fields = $this->opportunity->custom_fields ?? [];
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'numeric'],
            'expected_close_date' => ['nullable', 'date'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'stage_id' => ['required', app(Tenancy::class)->existsRule('stages')],
            'company_id' => ['nullable', app(Tenancy::class)->existsRule('companies')],
            'contact_id' => ['nullable', app(Tenancy::class)->existsRule('contacts')],
            'owner_id' => ['nullable', app(Tenancy::class)->existsRule('users')],
            ...app(CustomFieldService::class)->validationRules('opportunity'),
        ];
    }

    public function save(): void
    {
        $this->validate();
        $data = [
            'title' => $this->title, 'value' => $this->value,
            'expected_close_date' => $this->expected_close_date,
            'probability' => $this->probability, 'stage_id' => $this->stage_id,
            'company_id' => $this->company_id, 'contact_id' => $this->contact_id,
            'owner_id' => $this->owner_id,
            'custom_fields' => app(CustomFieldService::class)->sanitizeValues('opportunity', $this->custom_fields),
        ];
        if ($this->opportunity->exists) { $this->opportunity->update($data); session()->flash('success', '商机已更新');
            $this->redirect(route('opportunities.show', $this->opportunity), navigate: true); }
        else { app(PlanService::class)->ensureCanCreate(app(Tenancy::class)->current(), 'opportunities');
            $op = Opportunity::create($data); session()->flash('success', '商机已创建');
            $this->redirect(route('opportunities.show', $op), navigate: true); }
    }

    public function render()
    {
        $stages = Stage::ordered()->get();
        $companies = Company::orderBy('name')->get();
        $contacts = Contact::orderBy('first_name')->get();
        $owners = app(Tenancy::class)->users()->orderBy('name')->get();
        return view('livewire.opportunities.form', [
            'stages' => $stages, 'companies' => $companies,
            'contacts' => $contacts, 'owners' => $owners,
        ])->layout('layouts.app', ['title' => ($this->opportunity ? '编辑' : '新建') . '商机']);
    }
}
