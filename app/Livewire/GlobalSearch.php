<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithDataPermissions;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 全局跨实体搜索页。
 *
 * 覆盖公司 / 联系人 / 线索 / 商机四类实体，TenantScope 自动隔离租户，
 * Company / Lead / Opportunity 按数据权限过滤（sales 仅见本人负责）。
 * URL 参数 ?q= 驱动，顶栏搜索框跳转本页。
 */
class GlobalSearch extends Component
{
    use WithPagination, WithDataPermissions;

    #[Url]
    public string $q = '';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->q);
        $results = ['companies' => null, 'contacts' => null, 'leads' => null, 'opportunities' => null];
        $total = 0;

        if ($term !== '') {
            $escaped = addcslashes($term, '%_\\');
            $like = "%{$escaped}%";

            $companies = $this->applyDataScope(
                Company::query()->with('owner')
                    ->where(fn ($query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('industry', 'like', $like)
                        ->orWhere('website', 'like', $like)
                        ->orWhere('phone', 'like', $like))
            )->latest()->limit(10)->get();

            $contacts = Contact::query()->with('company')
                ->where(fn ($query) => $query
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('position', 'like', $like))
                ->latest()->limit(10)->get();

            $leads = $this->applyDataScope(
                Lead::query()->with('owner')
                    ->where(fn ($query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like))
            )->latest()->limit(10)->get();

            $opportunities = $this->applyDataScope(
                Opportunity::query()->with(['company', 'contact', 'stage', 'owner'])
                    ->where('title', 'like', $like)
            )->latest()->limit(10)->get();

            $results = [
                'companies' => $companies,
                'contacts' => $contacts,
                'leads' => $leads,
                'opportunities' => $opportunities,
            ];
            $total = $companies->count() + $contacts->count() + $leads->count() + $opportunities->count();
        }

        return view('livewire.global-search', [
            'results' => $results,
            'total' => $total,
            'term' => $term,
        ])->layout('layouts.app', ['title' => '全局搜索']);
    }
}
