<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 仪表板自定义小组件测试：
 * 默认全部显示、开关切换持久化到用户、区块隐藏、非法 key 忽略、用户间隔离。
 */
class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '小组件租户',
            'slug' => 't-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);
    }

    public function test_default_shows_all_widgets(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('待办活动')
            ->assertSee('商机阶段分布')
            ->assertSee('最近商机')
            ->assertSee('高优先级商机');

        // 未自定义时用户偏好仍为 null
        $this->assertNull($this->user->fresh()->dashboard_widgets);
    }

    public function test_toggle_persists_and_hides_section(): void
    {
        Livewire::test(Dashboard::class)
            ->call('toggleWidget', 'my_todo');

        $this->assertSame(['analytics', 'recent_opportunities', 'top_scored'], $this->user->fresh()->dashboard_widgets);

        // 隐藏后不再渲染该区块标题
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('待办活动')
            ->assertSee('商机阶段分布');
    }

    public function test_toggle_back_on_restores_section(): void
    {
        Livewire::test(Dashboard::class)->call('toggleWidget', 'my_todo');
        Livewire::test(Dashboard::class)->call('toggleWidget', 'my_todo');

        $this->assertSame(['analytics', 'recent_opportunities', 'top_scored', 'my_todo'], $this->user->fresh()->dashboard_widgets);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('待办活动');
    }

    public function test_invalid_widget_key_is_ignored(): void
    {
        Livewire::test(Dashboard::class)
            ->call('toggleWidget', 'not_a_widget');

        $this->assertNull($this->user->fresh()->dashboard_widgets);
    }

    public function test_preferences_are_per_user(): void
    {
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->call('toggleWidget', 'top_scored');

        $this->assertNull($other->fresh()->dashboard_widgets);
        $this->assertNotNull($this->user->fresh()->dashboard_widgets);
    }
}
