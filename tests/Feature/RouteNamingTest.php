<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 路由名称回归测试：
 * api.php 中 apiResource 默认生成的路由名（companies.index 等）曾与 web 路由名冲突，
 * 由于 API 路由后注册会覆盖 RouteCollection 名称映射，导致 route('companies.index')
 * 生成 /api/v1/... 链接，页面点击后返回 {"message":"Unauthenticated."}。
 * 修复方式：API 路由名统一加 api. 前缀。本测试防止此类冲突再次发生。
 */
class RouteNamingTest extends TestCase
{
    /** 所有已注册路由名称必须唯一（后注册者会覆盖先注册者的名称映射） */
    public function test_all_route_names_are_unique(): void
    {
        $names = [];
        foreach (Route::getRoutes() as $route) {
            if ($name = $route->getName()) {
                $names[] = $name;
            }
        }

        $duplicates = array_keys(array_filter(
            array_count_values($names),
            fn (int $count) => $count > 1,
        ));

        $this->assertSame([], $duplicates, '存在重复的路由名称，后注册者会覆盖先注册者: ' . implode(', ', $duplicates));
    }

    /** web 路由名不得解析为 /api/ URL */
    public function test_web_route_names_resolve_to_web_urls(): void
    {
        $cases = [
            'companies.index'           => [],
            'companies.show'            => [1],
            'companies.create'          => [],
            'contacts.index'            => [],
            'contacts.show'             => [1],
            'leads.index'               => [],
            'leads.show'                => [1],
            'opportunities.index'       => [],
            'opportunities.show'        => [1],
            'opportunities.board'       => [],
            'activities.index'          => [],
            'stages.index'              => [],
            'dashboard'                 => [],
            'settings.profile'          => [],
        ];

        foreach ($cases as $name => $params) {
            $url = route($name, ...[$params]);
            $this->assertStringNotContainsString('/api/', $url, "route('$name') 被解析为 API 路由: {$url}");
        }
    }

    /** API 路由必须使用 api. 名称前缀并解析为 /api/v1 URL */
    public function test_api_route_names_use_api_prefix(): void
    {
        $cases = [
            'api.companies.index'   => [],
            'api.companies.show'    => [1],
            'api.contacts.index'    => [],
            'api.leads.index'       => [],
            'api.opportunities.show' => [1],
            'api.activities.index'  => [],
            'api.webhooks.index'    => [],
            'api.webhooks.test'     => [1],
            'api.attachments.index' => [],
            'api.attachments.show'  => [1],
            'api.attachments.download' => [1],
            'api.login'             => [],
            'api.register'          => [],
            'api.logout'            => [],
            'api.user'              => [],
        ];

        foreach ($cases as $name => $params) {
            $url = route($name, ...[$params]);
            $this->assertStringContainsString('/api/v1/', $url, "route('$name') 未指向 /api/v1: {$url}");
        }
    }
}
