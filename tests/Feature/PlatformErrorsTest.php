<?php

namespace Tests\Feature;

use App\Livewire\Platform\Errors;
use App\Models\ErrorEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 平台错误监控面板测试（P1）：
 * 访问控制（仅平台超管）、聚合列表展示、统计数字。
 */
class PlatformErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_errors_panel(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        Livewire::actingAs($admin)
            ->test(Errors::class)
            ->assertOk()
            ->assertSee(__('platform_errors.signatures'));
    }

    public function test_tenant_user_cannot_view_errors_panel(): void
    {
        $tenant = Tenant::create([
            'name' => '错误面板租户',
            'slug' => 'pe-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $this->actingAs($user);

        $this->get(route('platform.errors'))->assertForbidden();
    }

    public function test_panel_lists_aggregated_events_and_stats(): void
    {
        $tenant = Tenant::create([
            'name' => '来源租户',
            'slug' => 'pe2-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
        ]);

        ErrorEvent::create([
            'signature' => md5('A|a.php|1'),
            'type' => \RuntimeException::class,
            'file' => '/app/a.php',
            'line' => 1,
            'message' => '数据库连接超时',
            'tenant_id' => $tenant->id,
            'count' => 12,
            'first_seen_at' => now()->subHours(30),
            'last_seen_at' => now()->subMinutes(5),
        ]);
        ErrorEvent::create([
            'signature' => md5('B|b.php|2'),
            'type' => \LogicException::class,
            'file' => '/app/b.php',
            'line' => 2,
            'message' => '旧错误',
            'count' => 3,
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now()->subDays(2),
        ]);

        $component = Livewire::test(Errors::class);

        $component->assertOk()
            ->assertSee('RuntimeException')
            ->assertSee('数据库连接超时')
            ->assertSee('来源租户');

        $this->assertSame(2, $component->viewData('totalSignatures'));
        $this->assertSame(15, $component->viewData('totalOccurrences'));
        $this->assertSame(1, $component->viewData('recentCount')); // 仅 RuntimeException 近 24h 活跃
    }

    public function test_panel_shows_empty_state_when_no_errors(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        Livewire::actingAs($admin)
            ->test(Errors::class)
            ->assertOk()
            ->assertSee(__('platform_errors.no_events'));
    }
}
