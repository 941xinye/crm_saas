<?php

namespace Tests\Feature;

use App\Livewire\Settings\Tokens;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * API Token 自助管理（创建一次性展示 / 列表 / 吊销）。
 */
class TokenManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'name' => 'Token 租户',
            'slug' => 'tk-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(\App\Services\Tenancy::class)->forUser($this->user);
    }

    public function test_create_token_displays_plain_text_once(): void
    {
        $component = Livewire::test(Tokens::class)
            ->set('name', '报表集成')
            ->call('create');

        $plain = $component->newToken;
        $this->assertNotNull($plain);
        $this->assertSame(1, $this->user->tokens()->count());
        $this->assertSame('报表集成', $this->user->tokens()->first()->name);

        // 明文 Token 可真实调用 API
        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->getJson('/api/v1/stages')
            ->assertOk();
    }

    public function test_revoke_only_own_tokens(): void
    {
        $own = $this->user->createToken('mine');
        $stranger = User::factory()->create(['tenant_id' => $this->user->tenant_id, 'role' => 'sales']);
        $foreign = $stranger->createToken('theirs');

        Livewire::test(Tokens::class)
            ->call('revoke', $own->accessToken->id)
            ->call('revoke', $foreign->accessToken->id); // 尝试吊销他人 Token

        $this->assertSame(0, $this->user->tokens()->count(), '自己的 Token 已吊销');
        $this->assertSame(1, $stranger->tokens()->count(), '他人的 Token 不受影响');
    }
}
