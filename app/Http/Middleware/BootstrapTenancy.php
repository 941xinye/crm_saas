<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy;
use App\Services\TenantMailer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BootstrapTenancy
{
    /**
     * 全局中间件：初始化 Tenancy 服务。
     *
     * 租户上下文解析顺序：
     * 1. 子域名识别（需配置 crm.subdomain_root，如 crm.example.com）：
     *    acme.crm.example.com → slug=acme 的租户，并锁定上下文（后续
     *    ensure.has.tenant 等 forUser 调用不覆盖）。登录用户（平台超管除外）
     *    只能访问自己所属租户的子域名，防止跨租户访问。
     * 2. 无子域名（主站 / localhost / 未配置）：回退为登录用户所属租户。
     * 未登录用户仅跳过，不影响 guest 页面（如 login/register）。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenancy = app(Tenancy::class);

        $subdomain = $this->extractSubdomain($request->getHost());

        if ($subdomain === null) {
            if ($user) {
                $tenancy->forUser($user);
            }

            // 白标 SMTP：未配置租户在此复位平台默认（对 null 同样安全）
            app(TenantMailer::class)->activate($tenancy->current());

            return $next($request);
        }

        $tenant = Tenant::where('slug', $subdomain)->where('is_active', true)->first();

        if (! $tenant) {
            abort(404, __('api.tenancy_tenant_not_found'));
        }

        // 归属校验：登录用户（平台超管除外）只能访问自己所属租户的子域名
        if ($user && ! $user->is_platform_admin && (int) $user->tenant_id !== $tenant->id) {
            abort(403, __('api.tenancy_tenant_forbidden'));
        }

        $tenancy->forUser($user);
        $tenancy->lockTenant($tenant);

        // 白标 SMTP：按子域名租户激活（未配置则复位平台默认）
        app(TenantMailer::class)->activate($tenant);

        return $next($request);
    }

    /**
     * 从请求 host 提取租户子域名。未配置 crm.subdomain_root 时完全禁用（返回 null）。
     */
    private function extractSubdomain(?string $host): ?string
    {
        $root = strtolower(trim((string) config('crm.subdomain_root')));

        if ($root === '' || ! $host) {
            return null;
        }

        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? '');

        // 主站（无子域名）或非本平台域名：不识别
        if ($host === $root || ! str_ends_with($host, '.' . $root)) {
            return null;
        }

        $sub = substr($host, 0, -(strlen($root) + 1));
        $sub = explode('.', $sub)[0]; // 多级子域名取第一段

        return preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $sub) ? $sub : null;
    }
}
