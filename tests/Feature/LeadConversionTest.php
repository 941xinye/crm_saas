<?php

namespace Tests\Feature;

use App\Livewire\Leads\Convert;
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
 * 线索转化测试：线索 → 联系人 + 公司 +（可选）商机，状态流转与约束校验。
 */
class LeadConversionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '转化测试租户',
            'slug' => 't-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);
    }

    private function createLead(array $overrides = []): Lead
    {
        return Lead::factory()->for($this->tenant, 'tenant')->create(array_merge([
            'name' => '张三',
            'company_name' => ' prospective 客户公司',
            'email' => 'zhang@example.com',
            'phone' => '13800000000',
            'owner_id' => $this->user->id,
            'status' => 'new',
        ], $overrides));
    }

    public function test_name_splitting_rules(): void
    {
        // 中式：无空格首字为姓
        $this->assertSame(['张', '三'], Convert::splitName('张三'));
        // 西式：空格拆分第一段为名
        $this->assertSame(['Smith', 'John'], Convert::splitName('John Smith'));
    }

    public function test_convert_creates_contact_and_new_company(): void
    {
        $lead = $this->createLead();

        $component = Livewire::test(Convert::class, ['lead' => $lead]);

        // 预填断言：姓名拆分、邮箱电话带出、公司名预填
        $component->assertSet('last_name', '张')
            ->assertSet('first_name', '三')
            ->assertSet('email', 'zhang@example.com')
            ->assertSet('phone', '13800000000');

        $component->call('convert');

        $contact = Contact::withoutTenant()->where('email', 'zhang@example.com')->firstOrFail();
        $company = Company::withoutTenant()->find($contact->company_id);

        $this->assertNotNull($company, '应自动创建公司');
        $this->assertSame($lead->company_name, $company->name);
        $this->assertSame($this->tenant->id, $company->tenant_id);
        $this->assertSame('张', $contact->last_name);
        $this->assertSame('三', $contact->first_name);
        $this->assertSame('converted', $lead->fresh()->status);
    }

    public function test_convert_can_link_existing_company(): void
    {
        $existing = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '已有公司']);
        $lead = $this->createLead();

        Livewire::test(Convert::class, ['lead' => $lead])
            ->set('company_option', 'existing')
            ->set('company_id', $existing->id)
            ->call('convert');

        $contact = Contact::withoutTenant()->where('email', 'zhang@example.com')->firstOrFail();
        $this->assertSame($existing->id, $contact->company_id);
        $this->assertSame(1, Company::withoutTenant()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_convert_can_create_opportunity(): void
    {
        $stage = Stage::factory()->for($this->tenant, 'tenant')->create(['name' => '初步接洽']);
        $lead = $this->createLead();

        Livewire::test(Convert::class, ['lead' => $lead])
            ->set('create_opportunity', true)
            ->set('stage_id', $stage->id)
            ->set('value', 88000.5)
            ->set('expected_close_date', '2026-12-31')
            ->call('convert');

        $contact = Contact::withoutTenant()->where('email', 'zhang@example.com')->firstOrFail();
        $opportunity = Opportunity::withoutTenant()->where('contact_id', $contact->id)->firstOrFail();

        $this->assertSame($contact->company_id, $opportunity->company_id);
        $this->assertSame($stage->id, $opportunity->stage_id);
        $this->assertSame($lead->owner_id, $opportunity->owner_id);
        $this->assertSame($contact->company->name . ' - ' . $contact->full_name, $opportunity->title);
        $this->assertSame('converted', $lead->fresh()->status);
    }

    public function test_converted_lead_cannot_convert_again(): void
    {
        $lead = $this->createLead(['status' => 'converted']);

        $this->get(route('leads.convert', $lead))->assertForbidden();
    }

    public function test_lost_lead_cannot_convert(): void
    {
        $lead = $this->createLead(['status' => 'lost']);

        $this->get(route('leads.convert', $lead))->assertForbidden();
    }

    public function test_duplicate_email_is_rejected(): void
    {
        Contact::factory()->for($this->tenant, 'tenant')->create(['email' => 'zhang@example.com']);
        $lead = $this->createLead();

        $countBefore = Contact::withoutTenant()->where('tenant_id', $this->tenant->id)->count();

        Livewire::test(Convert::class, ['lead' => $lead])
            ->call('convert')
            ->assertHasErrors(['email']);

        $this->assertSame($countBefore, Contact::withoutTenant()->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame('new', $lead->fresh()->status);
    }

    public function test_expired_trial_tenant_cannot_convert(): void
    {
        $this->tenant->update(['trial_ends_at' => now()->subDay()]);
        // Tenancy 单例持有旧实例，刷新以模拟下一请求的中间件重新引导
        app(Tenancy::class)->setTenant($this->tenant->fresh());
        $lead = $this->createLead();

        Livewire::test(Convert::class, ['lead' => $lead])
            ->call('convert')
            ->assertHasErrors(['contacts']);

        $this->assertSame('new', $lead->fresh()->status);
        $this->assertSame(0, Contact::withoutTenant()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_cross_tenant_convert_page_returns_404(): void
    {
        $otherTenant = Tenant::create([
            'name' => '其他租户',
            'slug' => 'o-' . Str::lower(Str::random(8)),
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $otherLead = Lead::factory()->for($otherTenant, 'tenant')->create();

        $this->get(route('leads.convert', $otherLead))->assertNotFound();
    }
}
