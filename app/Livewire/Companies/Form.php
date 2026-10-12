<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use App\Services\CustomFieldService;
use App\Services\PlanService;
use App\Services\Tenancy;
use Livewire\Component;

class Form extends Component
{
    public ?Company $company = null;
    public string $name = '';
    public string $industry = '';
    public string $website = '';
    public string $phone = '';
    public string $address = '';
    public ?int $owner_id = null;
    /** 自定义字段值（key => value，动态定义见 CustomFieldService） */
    public array $custom_fields = [];
    /** 同名公司查重提示（非阻塞，仅警告） */
    public ?string $duplicateWarning = null;

    public function mount(?Company $company = null): void
    {
        $this->company = $company ?? new Company();

        $this->authorize($company ? 'update' : 'create', $this->company);

        if ($company) {
            $this->company = $company;
            $this->name = $company->name;
            $this->industry = $company->industry ?? '';
            $this->website = $company->website ?? '';
            $this->phone = $company->phone ?? '';
            $this->address = $company->address ?? '';
            $this->owner_id = $company->owner_id;
        } else {
            $this->owner_id = auth()->id();
        }

        $this->custom_fields = $this->company->custom_fields ?? [];
    }

    /**
     * 名称实时查重（租户内精确匹配，编辑时排除自身），仅提示不阻塞保存。
     */
    public function updatedName(): void
    {
        $name = trim($this->name);

        if ($name === '') {
            $this->duplicateWarning = null;
            return;
        }

        $query = Company::where('name', $name);
        if ($this->company?->exists) {
            $query->whereKeyNot($this->company->id);
        }

        $this->duplicateWarning = $query->exists()
            ? "租户内已存在同名公司「{$name}」，请确认是否重复创建"
            : null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'owner_id' => ['nullable', app(Tenancy::class)->existsRule('users')],
            ...app(CustomFieldService::class)->validationRules('company'),
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'industry' => $this->industry,
            'website' => $this->website,
            'phone' => $this->phone,
            'address' => $this->address,
            'owner_id' => $this->owner_id,
            'custom_fields' => app(CustomFieldService::class)->sanitizeValues('company', $this->custom_fields),
        ];

        if ($this->company->exists) {
            $this->company->update($data);
            session()->flash('success', '公司信息已更新');
            $this->redirect(route('companies.show', $this->company), navigate: true);
        } else {
            app(PlanService::class)->ensureCanCreate(app(Tenancy::class)->current(), 'companies');
            $company = Company::create($data);
            session()->flash('success', '公司已创建');
            $this->redirect(route('companies.show', $company), navigate: true);
        }
    }

    public function render()
    {
        $owners = app(Tenancy::class)->users()->orderBy('name')->get();

        return view('livewire.companies.form', [
            'owners' => $owners,
        ])->layout('layouts.app', ['title' => ($this->company ? '编辑' : '新建') . '公司']);
    }
}
