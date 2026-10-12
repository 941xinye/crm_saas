<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class PlanService
{
    /** 资源 key => 模型类，用量统计与配额检查共用 */
    private const RESOURCES = [
        'companies' => Company::class,
        'contacts' => Contact::class,
        'leads' => Lead::class,
        'opportunities' => Opportunity::class,
    ];

    /** 资源 key => 中文名称，用于错误提示 */
    public const RESOURCE_LABELS = [
        'companies' => '公司',
        'contacts' => '联系人',
        'leads' => '线索',
        'opportunities' => '商机',
    ];

    public function config(Tenant $tenant): array
    {
        return config("plans.{$tenant->plan}", config('plans.free'));
    }

    public function name(Tenant $tenant): string
    {
        // 未知 plan 键回退 free（与 config() 回退行为一致），名称改由语言文件提供
        $plan = is_array(config("plans.{$tenant->plan}")) ? $tenant->plan : 'free';

        return __('plans.plan_' . $plan);
    }

    public function exists(string $plan): bool
    {
        return array_key_exists($plan, (array) config('plans'));
    }

    public function maxUsers(Tenant $tenant): int
    {
        return (int) $this->config($tenant)['max_users'];
    }

    /** 返回资源配额上限，-1 表示不限制 */
    public function quota(Tenant $tenant, string $resource): int
    {
        return (int) ($this->config($tenant)['quotas'][$resource] ?? -1);
    }

    /** 租户指定资源的当前用量（显式按 tenant_id 过滤，平台后台与租户上下文均正确） */
    public function usage(Tenant $tenant, string $resource): int
    {
        $model = self::RESOURCES[$resource] ?? null;
        if ($model === null) {
            return 0;
        }

        return $model::withoutTenant()->where('tenant_id', $tenant->id)->count();
    }

    /**
     * 当前租户是否允许新增业务数据：
     * 付费套餐恒可新增；free 套餐必须在试用期内。
     * $tenant 为 null（平台超管上下文）时放行。
     */
    public function canCreate(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return true;
        }

        if ($tenant->plan !== 'free') {
            return true;
        }

        return $tenant->trial_ends_at !== null && $tenant->trial_ends_at->isFuture();
    }

    /**
     * 断言可新增，否则抛出 ValidationException（Livewire 表单自动展示错误）。
     */
    public function ensureCanCreate(?Tenant $tenant, string $resource): void
    {
        if ($tenant === null) {
            return;
        }

        if (! $this->canCreate($tenant)) {
            throw ValidationException::withMessages([
                $resource => __('plans.trial_ended_create'),
            ]);
        }

        $quota = $this->quota($tenant, $resource);
        if ($quota >= 0 && $this->usage($tenant, $resource) >= $quota) {
            // RESOURCE_LABELS 的中文值供订阅页直读；错误提示按资源键走语言文件
            $label = isset(self::RESOURCE_LABELS[$resource])
                ? __("plans.resource_{$resource}")
                : $resource;

            throw ValidationException::withMessages([
                $resource => __('plans.quota_exceeded', [
                    'plan' => $this->name($tenant),
                    'resource' => $label,
                    'quota' => $quota,
                ]),
            ]);
        }
    }

    /**
     * 当前租户是否拥有指定 feature 权限。
     * $tenant 为 null（平台超管上下文）时全部放行。
     */
    public function hasFeature(?Tenant $tenant, string $feature): bool
    {
        if ($tenant === null) {
            return true;
        }

        return in_array($feature, $this->config($tenant)['features'] ?? [], true);
    }

    /**
     * 获取 feature 对应的升级提示文案（文案由语言文件提供，config 仅维护 feature 清单）。
     */
    public function upgradeMessage(string $feature): string
    {
        if (! array_key_exists($feature, (array) config('plans.upgrade_messages', []))) {
            return __('plans.upgrade_default');
        }

        return __('plans.upgrade_' . str_replace('-', '_', $feature));
    }
}
