<?php

namespace App\Livewire\Leads;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Services\PlanService;
use App\Services\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Convert extends Component
{
    public Lead $lead;

    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone = '';

    /** 公司关联方式：existing 关联已有 / new 新建 */
    public string $company_option = 'new';
    public ?int $company_id = null;
    public string $new_company_name = '';

    public bool $create_opportunity = false;
    public ?int $stage_id = null;
    public $value = null;
    public $expected_close_date = null;

    public function mount(Lead $lead): void
    {
        if (in_array($lead->status, ['converted', 'lost'], true)) {
            abort(403, $lead->status === 'converted' ? __('leads.abort_converted') : __('leads.abort_lost'));
        }

        $this->lead = $lead;
        [$this->last_name, $this->first_name] = self::splitName($lead->name);
        $this->email = $lead->email ?? '';
        $this->phone = $lead->phone ?? '';
        $this->new_company_name = $lead->company_name ?? '';
    }

    /**
     * 姓名拆分预填（用户可改）：
     * 含空格按西式（第一段=名，其余=姓）；无空格按中式（首字=姓，其余=名）。
     *
     * @return array{0: string, 1: string} [last_name, first_name]
     */
    public static function splitName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['', ''];
        }

        if (str_contains($name, ' ')) {
            [$first, $rest] = explode(' ', $name, 2);
            return [trim($rest), trim($first)];
        }

        return [mb_substr($name, 0, 1), mb_substr($name, 1)];
    }

    public function rules(): array
    {
        $tenancy = app(Tenancy::class);

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_option' => ['required', 'in:existing,new'],
            'company_id' => ['required_if:company_option,existing', 'nullable', $tenancy->existsRule('companies')],
            'new_company_name' => ['required_if:company_option,new', 'nullable', 'string', 'max:255'],
            'create_opportunity' => ['boolean'],
            'stage_id' => ['required_if:create_opportunity,true', 'nullable', $tenancy->existsRule('stages')],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
        ];
    }

    public function convert(): void
    {
        $this->validate();

        // contacts 表 unique(tenant_id, email)：转化前拦截重复邮箱
        if ($this->email !== '' && Contact::where('email', $this->email)->exists()) {
            $this->addError('email', __('leads.email_exists'));
            return;
        }

        $planService = app(PlanService::class);
        $tenant = app(Tenancy::class)->current();
        $planService->ensureCanCreate($tenant, 'contacts');
        if ($this->create_opportunity) {
            $planService->ensureCanCreate($tenant, 'opportunities');
        }

        $contact = DB::transaction(function () use ($tenant) {
            if ($this->company_option === 'existing') {
                $company = Company::findOrFail($this->company_id);
            } else {
                $company = Company::create([
                    'name' => $this->new_company_name,
                    'owner_id' => $this->lead->owner_id,
                ]);
            }

            $contact = Contact::create([
                'company_id' => $company->id,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email ?: null,
                'phone' => $this->phone ?: null,
            ]);

            if ($this->create_opportunity) {
                Opportunity::create([
                    'company_id' => $company->id,
                    'contact_id' => $contact->id,
                    'stage_id' => $this->stage_id,
                    'owner_id' => $this->lead->owner_id,
                    'title' => $company->name . ' - ' . $contact->full_name,
                    'value' => $this->value ?: 0,
                    'expected_close_date' => $this->expected_close_date ?: null,
                ]);
            }

            $this->lead->update(['status' => 'converted']);

            return $contact;
        });

        session()->flash('success', __('leads.converted', ['lead' => $this->lead->name, 'contact' => $contact->full_name]));
        $this->redirect(route('contacts.show', $contact), navigate: true);
    }

    public function render()
    {
        return view('livewire.leads.convert', [
            'companies' => Company::orderBy('name')->get(),
            'stages' => Stage::ordered()->get(),
        ])->layout('layouts.app', ['title' => __('leads.convert_page_title')]);
    }
}
