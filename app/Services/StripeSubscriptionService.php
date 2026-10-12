<?php

namespace App\Services;

use App\Models\Tenant;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe 订阅服务：Checkout Session 创建 + Webhook 事件处理。
 *
 * 依赖 config('stripe.secret_key')，未配置时所有方法返回 null/false，
 * 保证本地/测试环境（无 Stripe 账户）代码路径不崩溃。
 */
class StripeSubscriptionService
{
    private ?StripeClient $stripe = null;

    public function __construct()
    {
        $secret = config('stripe.secret_key');
        if ($secret) {
            $this->stripe = new StripeClient($secret);
        }
    }

    /**
     * 是否已配置 Stripe。
     */
    public function configured(): bool
    {
        return $this->stripe !== null;
    }

    /**
     * 为租户创建 Stripe Customer（幂等：存在则复用）。
     */
    public function ensureCustomer(Tenant $tenant): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        if ($tenant->stripe_customer_id) {
            return $tenant->stripe_customer_id;
        }

        try {
            $customer = $this->stripe->customers->create([
                'name' => $tenant->name,
                'email' => optional($tenant->users()->first())->email,
                'metadata' => [
                    'tenant_id' => $tenant->id,
                ],
            ]);

            $tenant->update(['stripe_customer_id' => $customer->id]);

            return $customer->id;
        } catch (ApiErrorException $e) {
            report($e);

            return null;
        }
    }

    /**
     * 创建 Checkout Session，返回 URL（前端跳转支付）。
     */
    public function createCheckoutSession(Tenant $tenant, string $plan): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        $priceId = config("stripe.prices.{$plan}");
        if (! $priceId) {
            report(new \RuntimeException("Stripe price_id 未配置: {$plan}"));
            return null;
        }

        $customerId = $this->ensureCustomer($tenant);

        try {
            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'subscription',
                'customer' => $customerId,
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'subscription_data' => [
                    'metadata' => [
                        'tenant_id' => $tenant->id,
                        'plan' => $plan,
                    ],
                ],
                'metadata' => [
                    'tenant_id' => $tenant->id,
                ],
                'success_url' => config('stripe.success_url'),
                'cancel_url' => config('stripe.cancel_url'),
                'allow_promotion_codes' => true,
            ]);

            return $session->url;
        } catch (ApiErrorException $e) {
            report($e);

            return null;
        }
    }

    /**
     * 创建 Stripe Customer Portal 会话，返回 URL。
     * 供租户自助查看账单、更换支付方式、降级/取消订阅（商业化闭环）。
     */
    public function createPortalSession(Tenant $tenant): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        $customerId = $this->ensureCustomer($tenant);
        if (! $customerId) {
            return null;
        }

        try {
            $session = $this->stripe->billingPortal->sessions->create([
                'customer' => $customerId,
                'return_url' => config('stripe.success_url'),
            ]);

            return $session->url;
        } catch (ApiErrorException $e) {
            report($e);

            return null;
        }
    }

    /**
     * 从 SubScription 事件中更新租户状态。
     */
    public function handleSubscriptionEvent(string $event, array $data): void
    {
        if (! $this->configured()) {
            return;
        }

        $tenantId = $data['metadata']['tenant_id'] ?? null;
        if (! $tenantId) {
            return;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return;
        }

        match ($event) {
            'customer.subscription.created' => $tenant->update([
                'stripe_subscription_id' => $data['id'],
                'stripe_status' => 'active',
                'subscription_ends_at' => null,
            ]),
            'customer.subscription.updated' => $tenant->update([
                'stripe_subscription_id' => $data['id'],
                'stripe_price_id' => $data['items']['data'][0]['price']['id'] ?? null,
                'stripe_status' => $data['status'],
            ]),
            'customer.subscription.deleted' => $tenant->update([
                'plan' => 'free',
                'stripe_status' => 'canceled',
                'subscription_ends_at' => now(),
            ]),
            default => null,
        };
    }

    /**
     * 验证 Webhook 签名。
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $secret = config('stripe.webhook_secret');
        if (! $secret) {
            return false;
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
            return true;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }
}
