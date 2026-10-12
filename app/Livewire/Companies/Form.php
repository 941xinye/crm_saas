<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use App\Models\User;
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
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'owner_id' => ['nullable', 'exists:users,id'],
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
        ];

        if ($this->company) {
            $this->company->update($data);
            session()->flash('success', '公司信息已更新');
            $this->redirect(route('companies.show', $this->company), navigate: true);
        } else {
            $company = Company::create($data);
            session()->flash('success', '公司已创建');
            $this->redirect(route('companies.show', $company), navigate: true);
        }
    }

    public function render()
    {
        $owners = User::orderBy('name')->get();

        return view('livewire.companies.form', [
            'owners' => $owners,
        ])->layout('layouts.app', ['title' => ($this->company ? '编辑' : '新建') . '公司']);
    }
}
