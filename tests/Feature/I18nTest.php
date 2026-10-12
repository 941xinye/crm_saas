<?php

namespace Tests\Feature;

use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * i18n 界面语言测试（Phase 6 / P3）：
 * 默认 zh_CN 与旧行为一致；用户级 locale 偏好生效；切换端点保存并渲染对应语言。
 */
class I18nTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant, 'tenant')->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    public function test_default_locale_renders_chinese_layout(): void
    {
        // 用户未设置偏好（locale = null）→ 跟随应用默认 zh_CN
        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('控制台', false);
        $response->assertSee('退出登录', false);
    }

    public function test_user_locale_en_renders_english_layout(): void
    {
        $this->user->update(['locale' => 'en']);
        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        // 英文导航与登出按钮
        $response->assertSee('Dashboard', false);
        $response->assertSee('Log out', false);
        // 不再渲染中文导航文案
        $response->assertDontSee('退出登录', false);
    }

    public function test_locale_switch_endpoint_persists_preference(): void
    {
        $this->actingAs($this->user);

        $this->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect();

        $this->assertSame('en', $this->user->fresh()->locale);

        // 切换后页面渲染英文
        $this->get(route('dashboard'))->assertSee('Log out', false);
    }

    public function test_locale_switch_back_to_chinese(): void
    {
        $this->user->update(['locale' => 'en']);
        $this->actingAs($this->user);

        $this->post(route('locale.update'), ['locale' => 'zh_CN'])
            ->assertRedirect();

        $this->get(route('dashboard'))->assertSee('退出登录', false);
    }

    public function test_locale_switch_rejects_unsupported_value(): void
    {
        $this->actingAs($this->user);

        $this->from(route('dashboard'))
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertNull($this->user->fresh()->locale);
    }

    public function test_onboarding_labels_follow_locale(): void
    {
        // 预置阶段，避免商机创建被外键约束阻塞
        Stage::factory()->for($this->tenant, 'tenant')->create();

        $this->user->update(['locale' => 'en']);
        $this->actingAs($this->user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Create your first company', false);
    }
}
