<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PWA 测试：manifest / service worker / 离线页文件存在且有效，
 * 布局注入 manifest 链接、主题色与 SW 注册脚本。
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_exists_and_is_valid(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $manifest = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($manifest);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_service_worker_and_offline_page_exist(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('pwa/offline.html'));

        $sw = (string) file_get_contents(public_path('sw.js'));
        // 关键策略存在：仅缓存静态资源、导航回退离线页
        $this->assertStringContainsString('CACHE_NAME', $sw);
        $this->assertStringContainsString('offline.html', $sw);
    }

    public function test_icons_exist(): void
    {
        $this->assertFileExists(public_path('pwa/icon.svg'));
        $this->assertFileExists(public_path('pwa/icon-maskable.svg'));
    }

    public function test_layout_injects_pwa_tags(): void
    {
        $tenant = Tenant::create([
            'name' => 'PWA 租户',
            'slug' => 't-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $this->actingAs($user);
        app(Tenancy::class)->forUser($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
        $response->assertSee('<meta name="theme-color" content="#4f46e5">', false);
        $response->assertSee("navigator.register('/sw.js')", false);
    }
}
