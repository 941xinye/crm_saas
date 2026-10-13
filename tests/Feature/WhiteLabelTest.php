<?php

namespace Tests\Feature;

use App\Livewire\Settings\WhiteLabel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy;
use App\Services\TenantMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 租户白标 SMTP 邮件测试：
 * TenantMailer 激活/复位（长驻 worker 隔离）、密码加密存储、
 * 留空保留密码、停用、非管理员禁止、测试邮件成功/失败路径。
 */
class WhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => '白标租户',
            'slug' => 'wl-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ], $overrides));
    }

    private function actingAsTenantUser(Tenant $tenant, string $role = 'admin'): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
        $this->actingAs($user);
        app(Tenancy::class)->forUser($user);

        return $user;
    }

    private function whitelabelSettings(array $overrides = []): array
    {
        return ['whitelabel' => array_merge([
            'smtp_host' => 'smtp.tenant.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'crm@example.com',
            'smtp_password' => Crypt::encryptString('smtp-secret'),
            'from_address' => 'crm@example.com',
            'from_name' => 'Acme CRM',
        ], $overrides)];
    }

    // ------------------------------------------------------------------
    // TenantMailer：激活 / 复位
    // ------------------------------------------------------------------

    public function test_unconfigured_tenant_keeps_platform_default_mailer(): void
    {
        $platformDefault = config('mail.default');

        app(TenantMailer::class)->activate($this->createTenant());
        app(TenantMailer::class)->activate(null);

        $this->assertSame($platformDefault, config('mail.default'));
        $this->assertNull(config('mail.mailers.' . TenantMailer::MAILER));
    }

    public function test_activate_registers_tenant_mailer_with_decrypted_password(): void
    {
        $platformDefault = config('mail.default');
        $tenant = $this->createTenant(['settings' => $this->whitelabelSettings()]);

        app(TenantMailer::class)->activate($tenant);

        $this->assertSame(TenantMailer::MAILER, config('mail.default'));
        $mailer = config('mail.mailers.' . TenantMailer::MAILER);
        $this->assertSame('smtp.tenant.example.com', $mailer['host']);
        $this->assertSame(587, $mailer['port']);
        $this->assertSame('tls', $mailer['encryption']);
        // 密码解密生效（SMTP 发送需要明文）
        $this->assertSame('smtp-secret', $mailer['password']);
        // 白标发件人覆盖平台全局 from
        $this->assertSame('crm@example.com', $mailer['from']['address']);
        $this->assertSame('Acme CRM', $mailer['from']['name']);

        app(TenantMailer::class)->activate(null);
        $this->assertSame($platformDefault, config('mail.default'));
    }

    public function test_activate_resets_after_configured_tenant_worker_isolation(): void
    {
        // 长驻 worker 场景：先处理配置了白标的租户 A，再处理未配置的租户 B，
        // B 不得沿用 A 的 SMTP（防止跨租户配置泄漏）
        $platformDefault = config('mail.default');

        $tenantA = $this->createTenant(['settings' => $this->whitelabelSettings()]);
        app(TenantMailer::class)->activate($tenantA);
        $this->assertSame(TenantMailer::MAILER, config('mail.default'));

        $tenantB = $this->createTenant(['name' => '无配置租户']);
        app(TenantMailer::class)->activate($tenantB);
        $this->assertSame($platformDefault, config('mail.default'));
        $this->assertNull(config('mail.mailers.' . TenantMailer::MAILER));
    }

    public function test_corrupted_password_decrypts_to_null_instead_of_throwing(): void
    {
        $tenant = $this->createTenant(['settings' => [
            'whitelabel' => [
                'smtp_host' => 'smtp.tenant.example.com',
                'from_address' => 'crm@example.com',
                'smtp_password' => 'not-encrypted-plaintext',
            ],
        ]]);

        app(TenantMailer::class)->activate($tenant);

        $this->assertSame(TenantMailer::MAILER, config('mail.default'));
        $this->assertNull(config('mail.mailers.' . TenantMailer::MAILER . '.password'));
    }

    // ------------------------------------------------------------------
    // Settings\WhiteLabel 组件
    // ------------------------------------------------------------------

    public function test_admin_can_save_smtp_settings_with_encrypted_password(): void
    {
        $tenant = $this->createTenant();
        $this->actingAsTenantUser($tenant);

        Livewire::test(WhiteLabel::class)
            ->set('smtpHost', 'smtp.acme.com')
            ->set('smtpPort', '465')
            ->set('smtpEncryption', 'ssl')
            ->set('smtpUsername', 'no-reply@acme.com')
            ->set('smtpPassword', 'plain-secret')
            ->set('fromAddress', 'no-reply@acme.com')
            ->set('fromName', 'Acme')
            ->call('save');

        $tenant->refresh();
        $wl = $tenant->settings['whitelabel'];

        $this->assertSame('smtp.acme.com', $wl['smtp_host']);
        $this->assertSame(465, $wl['smtp_port']);
        $this->assertSame('ssl', $wl['smtp_encryption']);
        $this->assertSame('no-reply@acme.com', $wl['from_address']);
        // 数据库中不存明文密码，可解密还原
        $this->assertNotSame('plain-secret', $wl['smtp_password']);
        $this->assertSame('plain-secret', Crypt::decryptString($wl['smtp_password']));
    }

    public function test_blank_password_keeps_existing_password(): void
    {
        $tenant = $this->createTenant(['settings' => $this->whitelabelSettings()]);
        $this->actingAsTenantUser($tenant);

        Livewire::test(WhiteLabel::class)
            ->set('smtpHost', 'smtp.new.com')
            ->set('smtpPassword', '')
            ->set('fromAddress', 'new@acme.com')
            ->call('save');

        $tenant->refresh();
        $wl = $tenant->settings['whitelabel'];

        $this->assertSame('smtp.new.com', $wl['smtp_host']);
        // 留空 = 保留原加密密码
        $this->assertSame('smtp-secret', Crypt::decryptString($wl['smtp_password']));
    }

    public function test_save_validates_required_fields(): void
    {
        $tenant = $this->createTenant();
        $this->actingAsTenantUser($tenant);

        Livewire::test(WhiteLabel::class)
            ->set('smtpHost', '')
            ->set('fromAddress', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['smtpHost', 'fromAddress']);

        $tenant->refresh();
        $this->assertArrayNotHasKey('whitelabel', $tenant->settings ?? []);
    }

    public function test_non_admin_cannot_save_or_disable(): void
    {
        $tenant = $this->createTenant();
        $this->actingAsTenantUser($tenant, 'sales');

        Livewire::test(WhiteLabel::class)
            ->set('smtpHost', 'smtp.evil.com')
            ->set('fromAddress', 'evil@acme.com')
            ->call('save')
            ->assertNotSet('smtpHost', ''); // 组件状态可改但未持久化

        $tenant->refresh();
        $this->assertArrayNotHasKey('whitelabel', $tenant->settings ?? []);

        Livewire::test(WhiteLabel::class)->call('disable');
        $tenant->refresh();
        $this->assertArrayNotHasKey('whitelabel', $tenant->settings ?? []);
    }

    public function test_disable_removes_whitelabel_config_only(): void
    {
        $tenant = $this->createTenant(['settings' => [
            'language' => 'zh-CN',
            ...$this->whitelabelSettings(),
        ]]);
        $this->actingAsTenantUser($tenant);

        Livewire::test(WhiteLabel::class)->call('disable');

        $tenant->refresh();
        $this->assertArrayNotHasKey('whitelabel', $tenant->settings);
        // 其他 settings 键不受影响
        $this->assertSame('zh-CN', $tenant->settings['language']);
    }

    // ------------------------------------------------------------------
    // 邮件模板品牌化（Phase 10）
    // ------------------------------------------------------------------

    public function test_activate_switches_global_from_branding_and_reset_restores(): void
    {
        $platformName = config('mail.from.name');
        $platformAddress = config('mail.from.address');

        $tenant = $this->createTenant();
        $tenant->settings = array_merge($tenant->settings ?? [], $this->whitelabelSettings());
        $tenant->save();

        app(TenantMailer::class)->activate($tenant);
        $this->assertSame('Acme CRM', config('mail.from.name'));
        $this->assertSame('crm@example.com', config('mail.from.address'));

        app(TenantMailer::class)->activate(null);
        $this->assertSame($platformName, config('mail.from.name'), '复位后应恢复平台品牌名');
        $this->assertSame($platformAddress, config('mail.from.address'));
    }

    public function test_mail_template_renders_tenant_brand_header_and_footer(): void
    {
        $platformName = config('app.name');

        $tenant = $this->createTenant();
        $tenant->settings = array_merge($tenant->settings ?? [], $this->whitelabelSettings());
        $tenant->save();

        // 未激活时邮件模板显示平台品牌
        $plain = (new \Illuminate\Notifications\Messages\MailMessage)->line('正文')->render();
        $this->assertStringContainsString($platformName, $plain);

        // 白标激活后显示租户品牌
        app(TenantMailer::class)->activate($tenant);
        $branded = (new \Illuminate\Notifications\Messages\MailMessage)->line('正文')->render();
        $this->assertStringContainsString('Acme CRM', $branded);
        $this->assertStringNotContainsString($platformName, $branded);

        app(TenantMailer::class)->activate(null);
    }

    public function test_send_test_mail_success_flashes_message(): void
    {
        $tenant = $this->createTenant(['settings' => $this->whitelabelSettings()]);
        $user = $this->actingAsTenantUser($tenant);

        // Mock Mail 门面避免真实 SMTP 连接；断言以白标身份调用
        Mail::shouldReceive('raw')->once()->withArgs(function (string $body, $callback) use ($user) {
            $this->assertStringContainsString('测试', $body);

            $message = \Mockery::mock(\Illuminate\Mail\Message::class);
            $message->shouldReceive('to')->once()->with($user->email)->andReturnSelf();
            $message->shouldReceive('subject')->once()->andReturnSelf();
            $callback($message);

            // 发送时已切换到租户白标 mailer
            $this->assertSame(TenantMailer::MAILER, config('mail.default'));

            return true;
        });

        Livewire::test(WhiteLabel::class)
            ->call('sendTest')
            ->assertSee('已发送至');

        // 发送后复位平台默认
        $this->assertNull(config('mail.mailers.' . TenantMailer::MAILER));
    }

    public function test_send_test_mail_failure_shows_error_message(): void
    {
        $tenant = $this->createTenant(['settings' => $this->whitelabelSettings()]);
        $this->actingAsTenantUser($tenant);

        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Connection could not be established'));

        Livewire::test(WhiteLabel::class)
            ->call('sendTest')
            ->assertSee('Connection could not be established');
    }

    public function test_send_test_mail_requires_configuration(): void
    {
        $tenant = $this->createTenant();
        $this->actingAsTenantUser($tenant);

        Livewire::test(WhiteLabel::class)
            ->call('sendTest')
            ->assertSee(__('white_label.flash_not_configured'));
    }

    // ------------------------------------------------------------------
    // 中间件挂载：请求级激活/复位
    // ------------------------------------------------------------------

    public function test_web_request_activates_configured_tenant_mailer(): void
    {
        $tenant = $this->createTenant(['settings' => $this->whitelabelSettings()]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame(TenantMailer::MAILER, config('mail.default'));
    }

    public function test_web_request_resets_mailer_for_unconfigured_tenant(): void
    {
        $platformDefault = config('mail.default');
        $tenant = $this->createTenant();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        // 先污染运行时配置（模拟 worker 复用），请求后应复位
        config()->set('mail.default', TenantMailer::MAILER);
        config()->set('mail.mailers.' . TenantMailer::MAILER, ['transport' => 'smtp']);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame($platformDefault, config('mail.default'));
        $this->assertNull(config('mail.mailers.' . TenantMailer::MAILER));
    }
}
