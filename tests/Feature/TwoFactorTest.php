<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Settings\TwoFactor;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 两步验证（2FA）：TOTP 服务正确性、启用/确认流程、登录拦截、挑战页校验、恢复码用后即焚。
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'name' => '2FA 租户',
            'slug' => 'tfa-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    }

    /** 为用户直接启用 2FA（跳过设置页流程） */
    private function enableFor(User $user, string $secret): void
    {
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    // ---------- TOTP 服务 ----------

    public function test_totp_secret_is_32_chars_base32(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_totp_verify_accepts_current_code_and_rejects_invalid(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();

        $code = $service->generateCode($secret);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue($service->verify($secret, $code));

        // 格式非法直接拒绝
        $this->assertFalse($service->verify($secret, 'abcdef'));
        $this->assertFalse($service->verify($secret, '12345'));
    }

    public function test_totp_window_tolerance(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();

        // 2 个窗口（60 秒）前的码：默认 ±1 窗口拒绝，±2 窗口接受
        $stale = $service->generateCode($secret, now()->getTimestamp() - 60);
        $this->assertFalse($service->verify($secret, $stale));
        $this->assertTrue($service->verify($secret, $stale, 2));
    }

    // ---------- 设置页：启用 / 确认 / 关闭 ----------

    public function test_enable_then_confirm_activates_two_factor_with_recovery_codes(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(TwoFactor::class)->call('enable');

        $secret = $component->pendingSecret;
        $this->assertNotNull($secret);
        $this->assertStringContainsString('secret=' . $secret, $component->otpauthUri);
        // 确认前不算已启用（登录不被拦截）
        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());

        $code = app(TwoFactorService::class)->generateCode($secret);
        $component->set('code', $code)->call('confirm');

        $fresh = $this->user->fresh();
        $this->assertTrue($fresh->hasTwoFactorEnabled());
        $this->assertCount(8, $fresh->two_factor_recovery_codes);
        $this->assertNotNull($component->recoveryCodes);
        // 数据库只存 bcrypt 哈希，明文仅展示一次
        $this->assertNotContains($component->recoveryCodes[0], $fresh->two_factor_recovery_codes);
    }

    public function test_confirm_with_wrong_code_does_not_activate(): void
    {
        $this->actingAs($this->user);

        Livewire::test(TwoFactor::class)
            ->call('enable')
            ->set('code', '000000')
            ->call('confirm');

        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disable_requires_correct_password(): void
    {
        $service = app(TwoFactorService::class);
        $this->enableFor($this->user, $service->generateSecret());
        $this->actingAs($this->user);

        Livewire::test(TwoFactor::class)
            ->set('password', 'wrong-password')
            ->call('disable');

        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled(), '密码错误时不应关闭 2FA');

        Livewire::test(TwoFactor::class)
            ->set('password', 'password')
            ->call('disable');

        $fresh = $this->user->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
    }

    // ---------- 登录流程 ----------

    public function test_user_without_2fa_logs_in_directly(): void
    {
        Livewire::test(Login::class)
            ->set('email', $this->user->email)
            ->set('password', 'password')
            ->call('login');

        $this->assertTrue(auth()->check());
    }

    public function test_login_with_2fa_intercepts_to_challenge(): void
    {
        $this->enableFor($this->user, app(TwoFactorService::class)->generateSecret());

        Livewire::test(Login::class)
            ->set('email', $this->user->email)
            ->set('password', 'password')
            ->call('login');

        // 挑战页之前不应建立登录态
        $this->assertFalse(auth()->check());
    }

    public function test_challenge_requires_pending_session(): void
    {
        Livewire::test(TwoFactorChallenge::class)->assertRedirect('/login');
    }

    public function test_challenge_with_correct_code_logs_in(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();
        $this->enableFor($this->user, $secret);

        $code = app(TwoFactorService::class)->generateCode($secret);

        session(['2fa:user:id' => $this->user->id, '2fa:remember' => false]);

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', $code)
            ->call('verify');

        $this->assertTrue(auth()->check());
        $this->assertTrue(auth()->user()->is($this->user));
    }

    public function test_challenge_with_wrong_code_does_not_log_in(): void
    {
        $this->enableFor($this->user, app(TwoFactorService::class)->generateSecret());

        session(['2fa:user:id' => $this->user->id]);

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', '000000')
            ->call('verify');

        $this->assertFalse(auth()->check());
    }

    public function test_recovery_code_works_exactly_once(): void
    {
        $service = app(TwoFactorService::class);
        $this->enableFor($this->user, $service->generateSecret());
        $plainCodes = $service->generateRecoveryCodes();
        $this->user->forceFill([
            'two_factor_recovery_codes' => $service->hashRecoveryCodes($plainCodes),
        ])->save();

        $code = $plainCodes[0];

        // 首次使用恢复码成功，且该码被焚毁
        session(['2fa:user:id' => $this->user->id]);

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', $code)
            ->call('verify');

        $this->assertTrue(auth()->check());
        $this->assertCount(7, $this->user->fresh()->two_factor_recovery_codes, '恢复码用后即焚');

        // 同一恢复码第二次使用失败
        auth()->logout();
        session(['2fa:user:id' => $this->user->id]);

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', $code)
            ->call('verify');

        $this->assertFalse(auth()->check());
    }
}
