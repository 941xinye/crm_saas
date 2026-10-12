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
        return $this->config($tenant)['name'];
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
                $resource => '试用期已结束，升级套餐后才能继续新增数据。',
            ]);
        }

        $quota = $this->quota($tenant, $resource);
        if ($quota >= 0 && $this->usage($tenant, $resource) >= $quota) {
            $label = self::RESOURCE_LABELS[$resource] ?? $resource;

            throw ValidationException::withMessages([
                $resource => "已达到「{$this->name($tenant)}」{$label}数量上限（{$quota} 条），请升级套餐。",
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
     * 获取 feature 对应的升级提示文案。
     */
    public function upgradeMessage(string $feature): string
    {
        return config("plans.upgrade_messages.{$feature}", '当前套餐不支持此功能，请升级后使用。');
    }
}
