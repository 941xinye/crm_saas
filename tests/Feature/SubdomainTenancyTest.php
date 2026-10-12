<?php

namespace Tests\Feature;

use App\Http\Middleware\BootstrapTenancy;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * 子域名租户识别：配置 crm.subdomain_root 后，{slug}.{root} 请求锁定
 * 对应租户上下文；主站/localhost/未配置时回退为登录用户所属租户。
 */
class SubdomainTenancyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $acme;

    private Tenant $globex;

    private User $acmeAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = Tenant::create([
            'name' => 'Acme',
            'slug' => 'acme-' . Str::lower(Str::random(6)),
            'plan' => 'free',
            'is_active' => true,
        ]);
        $this->globex = Tenant::create([
            'name' => 'Globex',
            'slug' => 'globex-' . Str::lower(Str::random(6)),
            'plan' => 'free',
            'is_active' => true,
        ]);
        $this->acmeAdmin = User::factory()->create([
            'tenant_id' => $this->acme->id,
            'role' => 'admin',
        ]);
    }

    /** 直接调用中间件并断言 Tenancy 上下文 */
    private function runMiddleware(string $host, ?User $user): void
    {
        $request = Request::create('http://' . $host . '/dashboard');
        $request->setUserResolver(fn () => $user);

        (new BootstrapTenancy)->handle($request, fn () => response('ok'));
    }

    public function test_no_subdomain_root_configured_is_noop(): void
    {
        // 默认配置为空：localhost/任意域名都不识别，走用户所属租户
        $this->runMiddleware('localhost', $this->acmeAdmin);
        $this->assertSame($this->acme->id, app(Tenancy::class)->id());
        $this->assertFalse(app(Tenancy::class)->isTenantLocked());

        $this->runMiddleware('acme.crm.example.com', $this->acmeAdmin);
        $this->assertSame($this->acme->id, app(Tenancy::class)->id(), '未配置时子域名不识别');
    }

    public function test_subdomain_locks_matching_tenant(): void
    {
        config(['crm.subdomain_root' => 'crm.example.com']);

        $this->runMiddleware($this->acme->slug . '.crm.example.com', $this->acmeAdmin);

        $tenancy = app(Tenancy::class);
        $this->assertSame($this->acme->id, $tenancy->id());
        $this->assertTrue($tenancy->isTenantLocked());
        // 锁定后 forUser 不覆盖（ensure.has.tenant 中间件的行为）
        $tenancy->forUser($this->acmeAdmin);
        $this->assertSame($this->acme->id, $tenancy->id());
    }

    public function test_main_host_falls_back_to_user_tenant(): void
    {
        config(['crm.subdomain_root' => 'crm.example.com']);

        $this->runMiddleware('crm.example.com', $this->acmeAdmin);

        $tenancy = app(Tenancy::class);
        $this->assertSame($this->acme->id, $tenancy->id());
        $this->assertFalse($tenancy->isTenantLocked());
    }

    public function test_guest_on_subdomain_gets_tenant_context(): void
    {
        config(['crm.subdomain_root' => 'crm.example.com']);

        $this->runMiddleware($this->acme->slug . '.crm.example.com', null);

        $this->assertSame($this->acme->id, app(Tenancy::class)->id());
    }

    public function test_unknown_or_inactive_subdomain_is_404(): void
    {
        config(['crm.subdomain_root' => 'crm.example.com']);

        try {
            $this->runMiddleware('nonexistent.crm.example.com', $this->acmeAdmin);
            $this->fail('未知子域名应当 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_cross_tenant_user_is_403(): void
    {
        config(['crm.subdomain_root' => 'crm.example.com']);

        try {
            $this->runMiddleware($this->globex->slug . '.crm.example.com', $this->acmeAdmin);
            $this->fail('跨租户访问应当 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_platform_admin_may_visit_any_subdomain(): void
    {
        config(['crm.subdomain_root' => 'crm.example.com']);

        $platformAdmin = User::factory()->create(['is_platform_admin' => true, 'tenant_id' => null]);

        $this->runMiddleware($this->globex->slug . '.crm.example.com', $platformAdmin);

        $this->assertSame($this->globex->id, app(Tenancy::class)->id());
    }

    public function test_end_to_end_data_isolation_via_subdomain(): void
    {
        config(['crm.subdomain_root' => 'crm.example.com']);

        Company::create([
            'tenant_id' => $this->acme->id,
            'name' => 'Acme 独有公司',
            'created_by' => $this->acmeAdmin->id,
        ]);
        Company::create([
            'tenant_id' => $this->globex->id,
            'name' => 'Globex 独有公司',
            'created_by' => null,
        ]);

        $this->actingAs($this->acmeAdmin);

        // 子域名识别：看到的是子域名租户（= 自己所属租户）的数据
        $this->withHeaders(['HOST' => $this->acme->slug . '.crm.example.com'])
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Acme 独有公司')
            ->assertDontSee('Globex 独有公司');

        // 主站回退：用户所属租户的数据
        $this->withHeaders(['HOST' => 'crm.example.com'])
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Acme 独有公司')
            ->assertDontSee('Globex 独有公司');
    }
}
