<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use App\Services\MergeService;
use Livewire\Component;

/**
 * 公司重复项合并：在详情页选择同名重复公司，将其数据并入当前公司。
 * 候选 = 同名其他公司；合并经 MergeService（事务 + 审计 batch + Policy 校验）。
 */
class Merge extends Component
{
    public Company $company;

    /** 待合并（源）公司 ID */
    public ?int $sourceId = null;

    public bool $confirming = false;

    public function mount(Company $company): void
    {
        $this->company = $company;
    }

    public function confirm(): void
    {
        $this->validate([
            'sourceId' => ['required', 'integer'],
        ]);
        $this->confirming = true;
    }

    public function cancel(): void
    {
        $this->confirming = false;
        $this->sourceId = null;
    }

    public function merge(MergeService $merger): void
    {
        $source = Company::find($this->sourceId);

        if (! $source || $source->id === $this->company->id) {
            $this->addError('sourceId', __('merge.company_select_required'));
            return;
        }

        try {
            $merger->mergeCompanies(auth()->user(), $source, $this->company);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors()['merge'] ?? [] as $message) {
                $this->addError('sourceId', $message);
            }
            $this->confirming = false;

            return;
        }

        session()->flash('success', __('merge.company_merged'));
        $this->redirect(route('companies.show', $this->company), navigate: true);
    }

    public function render()
    {
        // 候选：同名其他公司（租户隔离由 TenantScope 自动生效）
        $candidates = Company::where('name', $this->company->name)
            ->where('id', '!=', $this->company->id)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('livewire.companies.merge', [
            'candidates' => $candidates,
        ]);
    }
}
