<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithDataPermissions;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 全局跨实体搜索页。
 *
 * 覆盖公司 / 联系人 / 线索 / 商机四类实体，TenantScope 自动隔离租户，
 * Company / Lead / Opportunity 按数据权限过滤（sales 仅见本人负责）。
 * URL 参数 ?q= 驱动，顶栏搜索框跳转本页。
 *
 * 搜索驱动：crm.search.driver=like（默认，LIKE %kw%）或 fulltext
 * （MySQL FULLTEXT + ngram 分词；非 mysql 连接自动回退 like）。
 * SEARCH_FIELDS 的列与顺序必须与 FULLTEXT 迁移中的索引定义一致。
 */
class GlobalSearch extends Component
{
    use WithPagination, WithDataPermissions;

    /** 各实体的搜索字段清单（顺序与 FULLTEXT 索引定义一致） */
    private const SEARCH_FIELDS = [
        'companies' => ['name', 'industry', 'website', 'phone'],
        'contacts' => ['first_name', 'last_name', 'email', 'phone', 'position'],
        'leads' => ['name', 'company_name', 'email', 'phone'],
        'opportunities' => ['title'],
    ];

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
            $companies = $this->applyDataScope(
                Company::query()->with('owner')->where($this->matchAny('companies', $term))
            )->latest()->limit(10)->get();

            $contacts = Contact::query()->with('company')
                ->where($this->matchAny('contacts', $term))
                ->latest()->limit(10)->get();

            $leads = $this->applyDataScope(
                Lead::query()->with('owner')->where($this->matchAny('leads', $term))
            )->latest()->limit(10)->get();

            $opportunities = $this->applyDataScope(
                Opportunity::query()->with(['company', 'contact', 'stage', 'owner'])
                    ->where($this->matchAny('opportunities', $term))
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
        ])->layout('layouts.app', ['title' => __('misc.global_search')]);
    }

    /**
     * 按配置驱动返回 WHERE 闭包：fulltext（仅 mysql 连接）走 MATCH...AGAINST，
     * 否则 LIKE %term%（% _ \ 转义防通配符注入）。
     */
    private function matchAny(string $table, string $term): \Closure
    {
        $columns = self::SEARCH_FIELDS[$table];

        if (config('crm.search.driver') === 'fulltext'
            && DB::connection()->getDriverName() === 'mysql') {
            return fn ($query) => $query->whereRaw(
                sprintf('MATCH(%s) AGAINST(? IN BOOLEAN MODE)', implode(', ', $columns)),
                [$this->toBooleanQuery($term)],
            );
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return fn ($query) => $query->where(
            fn ($q) => collect($columns)->each(fn (string $column) => $q->orWhere($column, 'like', $like))
        );
    }

    /**
     * BOOLEAN MODE 查询串：剥离布尔操作符（+ - > < ( ) * ~ "）防止语法错误，
     * 按空白拆词后逐词双引号包裹（词组精确匹配、AND 语义）。
     */
    private function toBooleanQuery(string $term): string
    {
        $words = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($words)
            ->map(fn (string $word) => trim((string) preg_replace('/[+\-><()~*"]/', '', $word)))
            ->filter(fn (string $word) => $word !== '')
            ->map(fn (string $word) => "\"{$word}\"")
            ->implode(' ');
    }
}
