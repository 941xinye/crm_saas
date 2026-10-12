<?php

namespace App\Livewire\WebForm;

use App\Models\Lead;
use App\Models\Tenant;
use App\Services\PlanService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * 公共线索表单（web-to-lead）：访客无需登录即可提交线索。
 * 访问入口 /f/{key}，key 为租户 UUID（不可枚举）；
 * 防滥用：蜜罐字段 + IP 限流（路由层 throttle:10,1）+ 租户配额前置检查。
 */
#[Layout('layouts.guest')]
class LeadWebForm extends Component
{
    public string $key = '';

    /** 蜜罐字段：隐藏输入框，人类不会填写；有值即判定为机器人 */
    public string $website = '';

    public string $name = '';
    public string $email = '';
    public string $companyName = '';
    public string $phone = '';

    public bool $submitted = false;
    public bool $unavailable = false;

    public function mount(string $key): void
    {
        if (! $this->resolveTenant()) {
            $this->unavailable = true;
        }
    }

    /**
     * 提交线索。蜜罐命中时静默返回成功（不泄露拦截逻辑，也不创建数据）。
     */
    public function submit(): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant || $this->submitted) {
            return;
        }

        // 蜜罐命中：假装成功，直接返回
        if (trim($this->website) !== '') {
            $this->reset('name', 'email', 'companyName', 'phone', 'website');
            $this->submitted = true;
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'companyName' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        // 租户配额前置检查（订阅到期 / 线索配额满时拒绝提交）
        $plans = app(PlanService::class);
        if (! $plans->canCreate($tenant) || ($plans->quota($tenant, 'leads') >= 0 && $plans->usage($tenant, 'leads') >= $plans->quota($tenant, 'leads'))) {
            session()->flash('web_form_error', __('web_form.quota_reached'));
            return;
        }

        Lead::create([
            'tenant_id' => $tenant->id,
            'name' => $this->name,
            'email' => $this->email,
            'company_name' => $this->companyName ?: null,
            'phone' => $this->phone ?: null,
            'source' => 'web_form',
            'status' => 'new',
        ]);

        $this->reset('name', 'email', 'companyName', 'phone', 'website');
        $this->submitted = true;
    }

    private function resolveTenant(): ?Tenant
    {
        return Tenant::where('lead_form_key', $this->key)
            ->where('is_active', true)
            ->first();
    }

    public function render()
    {
        return view('livewire.web-form.lead', [
            'tenantName' => $this->resolveTenant()?->name,
        ]);
    }
}
