<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 仪表板新手引导 checklist（Phase 6 / P1）：
 * 空租户展示待办步骤、完成后自动隐藏、手动关闭存租户 settings。
 */
class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '引导测试租户',
            'slug' => 'ob-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);
    }

    private function seedAllSteps(): void
    {
        Company::create(['tenant_id' => $this->tenant->id, 'owner_id' => $this->user->id, 'name' => '引导公司']);
        Contact::create(['tenant_id' => $this->tenant->id, 'first_name' => '小', 'last_name' => '李', 'company_id' => Company::first()->id]);

        Lead::create(['tenant_id' => $this->tenant->id, 'owner_id' => $this->user->id, 'name' => '线索甲']);

        $stage = Stage::create([
            'tenant_id' => $this->tenant->id,
            'name' => '初步接触',
            'order' => 1,
            'probability' => 20,
            'is_default' => true,
        ]);
        Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'stage_id' => $stage->id,
            'owner_id' => $this->user->id,
            'company_id' => Company::first()->id,
            'title' => '引导商机',
        ]);

        User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'sales']);
    }

    public function test_empty_tenant_shows_pending_steps(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('快速上手')
            ->assertSee('创建第一家公司')
            ->assertSee('邀请团队成员');
    }

    public function test_all_steps_done_hides_checklist(): void
    {
        $this->seedAllSteps();

        Livewire::test(Dashboard::class)
            ->assertDontSee('快速上手');
    }

    public function test_dismiss_persists_to_tenant_settings(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('快速上手')
            ->call('dismissOnboarding');

        $this->assertTrue($this->tenant->fresh()->settings['onboarding_dismissed']);

        Livewire::test(Dashboard::class)
            ->assertDontSee('快速上手');
    }
}
