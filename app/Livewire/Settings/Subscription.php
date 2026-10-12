<?php

namespace App\Livewire\Settings;

use App\Services\PlanService;
use App\Services\StripeSubscriptionService;
use App\Services\Tenancy;
use Livewire\Component;

class Subscription extends Component
{
    /**
     * 发起自助升级：创建 Stripe Checkout Session 并跳转支付。
     * Stripe 未配置时（本地/未上线）给出明确提示，不阻塞页面。
     */
    public function upgrade(string $plan): void
    {
        $tenant = app(Tenancy::class)->current();
        $user = auth()->user();

        if (! $tenant || $user->role !== 'admin') {
            session()->flash('error', __('settings_subscription.flash_only_admin_change_plan'));
            return;
        }

        $order = ['free' => 0, 'pro' => 1, 'enterprise' => 2];
        if (($order[$plan] ?? 0) <= ($order[$tenant->plan] ?? 0)) {
            session()->flash('error', __('settings_subscription.flash_upgrade_only_higher'));
            return;
        }

        $checkoutUrl = app(StripeSubscriptionService::class)->createCheckoutSession($tenant, $plan);

        if (! $checkoutUrl) {
            session()->flash('error', __('settings_subscription.flash_stripe_unavailable'));
            return;
        }

        $this->redirect($checkoutUrl);
    }

    /**
     * 打开 Stripe Customer Portal：自助查看账单、更换支付方式、降级/取消订阅。
     * Stripe 未配置时给出明确提示，不阻塞页面。
     */
    public function portal(): void
    {
        $tenant = app(Tenancy::class)->current();

        if (! $tenant || auth()->user()->role !== 'admin') {
            session()->flash('error', __('settings_subscription.flash_only_admin_manage'));
            return;
        }

        $url = app(StripeSubscriptionService::class)->createPortalSession($tenant);

        if (! $url) {
            session()->flash('error', __('settings_subscription.flash_portal_unavailable'));
            return;
        }

        $this->redirect($url);
    }

    public function render()
    {
        $tenancy = app(Tenancy::class);
        $tenant = $tenancy->current();
        $plans = app(PlanService::class);
        $stripe = app(StripeSubscriptionService::class);

        $resources = ['companies', 'contacts', 'leads', 'opportunities'];

        $usage = [];
        foreach ($resources as $resource) {
            $usage[$resource] = [
                'label' => __('plans.resource_' . $resource),
                'current' => $plans->usage($tenant, $resource),
                'quota' => $plans->quota($tenant, $resource),
            ];
        }

        return view('livewire.settings.subscription', [
            'tenant' => $tenant,
            'planName' => $plans->name($tenant),
            'maxUsers' => $plans->maxUsers($tenant),
            'memberCount' => $tenancy->users()->count(),
            'usage' => $usage,
            'trialEndsAt' => $tenant->trial_ends_at,
            'trialDaysLeft' => $tenant->trial_ends_at?->isFuture()
                ? ceil(now()->diffInHours($tenant->trial_ends_at) / 24)
                : 0,
            'canCreate' => $plans->canCreate($tenant),
            'stripeEnabled' => $stripe->configured(),
            'isAdmin' => auth()->user()->role === 'admin',
            // 账单历史（Stripe Webhook invoice.* 事件同步；未配置时为空集合）
            'invoices' => \App\Models\TenantInvoice::orderByDesc('period_start')->orderByDesc('id')->limit(12)->get(),
        ])->layout('layouts.app', ['title' => __('settings_subscription.title')]);
    }
}
