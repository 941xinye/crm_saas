<?php

namespace Tests\Feature;

use App\Livewire\Companies\Merge as CompanyMerge;
use App\Livewire\Contacts\Merge as ContactMerge;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 重复数据合并测试：
 * 子记录迁移、空字段回填、源软删除、审计 batch 聚合、
 * 跨租户拒绝、数据权限（sales 无权合并）、Show 页候选与 Livewire 流程。
 */
class MergeTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => '合并租户',
            'slug' => 't-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ]);
    }

    private function actingAsTenantUser(Tenant $tenant, string $role = 'admin'): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
        $this->actingAs($user);
        app(\App\Services\Tenancy::class)->forUser($user);

        return $user;
    }

    // ------------------------------------------------------------------
    // 公司合并
    // ------------------------------------------------------------------

    public function test_company_merge_migrates_children_and_backfills_empty_fields(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actingAsTenantUser($tenant);
        $stage = Stage::factory()->for($tenant, 'tenant')->create();

        $source = Company::factory()->for($tenant, 'tenant')->create([
            'name' => '重复公司',
            'industry' => '制造业',
            'website' => 'https://dup.example.com',
            'phone' => '111',
            'address' => '源地址',
        ]);
        $target = Company::factory()->for($tenant, 'tenant')->create([
            'name' => '重复公司',
            'industry' => null,
            'website' => null,
            'phone' => '222',  // 目标已有值 → 不被覆盖
            'address' => null,
        ]);

        $contact = Contact::factory()->for($tenant, 'tenant')->for($source, 'company')->create();
        $opp = Opportunity::factory()->for($tenant, 'tenant')->for($source, 'company')->for($stage, 'stage')->create();
        $activity = Activity::create([
            'tenant_id' => $tenant->id,
            'activitable_type' => Company::class,
            'activitable_id' => $source->id,
            'user_id' => $user->id,
            'type' => 'call',
            'subject' => '跟进电话',
        ]);
        $attachment = \App\Models\Attachment::create([
            'tenant_id' => $tenant->id,
            'attachable_type' => Company::class,
            'attachable_id' => $source->id,
            'original_name' => 'a.pdf',
            'file_path' => 'attachments/a.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $user->id,
        ]);

        app(MergeService::class)->mergeCompanies($user, $source, $target);

        // 子记录全部迁移
        $this->assertSame($target->id, $contact->fresh()->company_id);
        $this->assertSame($target->id, $opp->fresh()->company_id);
        $this->assertSame($target->id, $activity->fresh()->activitable_id);
        $this->assertSame($target->id, $attachment->fresh()->attachable_id);

        // 空字段回填、已有值不动
        $this->assertSame('制造业', $target->fresh()->industry);
        $this->assertSame('https://dup.example.com', $target->fresh()->website);
        $this->assertSame('222', $target->fresh()->phone);
        $this->assertSame('源地址', $target->fresh()->address);

        // 源软删除（可恢复）
        $this->assertTrue($source->fresh()->trashed());
    }

    public function test_company_merge_records_batch_audit(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actingAsTenantUser($tenant);
        $source = Company::factory()->for($tenant, 'tenant')->create(['name' => '同名字']);
        $target = Company::factory()->for($tenant, 'tenant')->create(['name' => '同名字']);

        app(MergeService::class)->mergeCompanies($user, $source, $target);

        // 源删除审计与批量汇总审计共享同一 batch_id
        $deletedAudit = AuditLog::where('auditable_type', Company::class)
            ->where('auditable_id', $source->id)->where('action', 'deleted')->first();
        $this->assertNotNull($deletedAudit);
        $this->assertSame($tenant->id, $deletedAudit->tenant_id);

        $batchSummary = AuditLog::where('batch_id', $deletedAudit->batch_id)
            ->where('action', 'batch_merged')->first();
        $this->assertNotNull($batchSummary);
    }

    public function test_cross_tenant_company_merge_is_rejected(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $user = $this->actingAsTenantUser($tenantA);

        $source = Company::factory()->for($tenantB, 'tenant')->create();
        $target = Company::factory()->for($tenantA, 'tenant')->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MergeService::class)->mergeCompanies($user, $source, $target);
    }

    public function test_sales_cannot_merge_companies(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actingAsTenantUser($tenant, 'sales');

        $source = Company::factory()->for($tenant, 'tenant')->create(['owner_id' => $user->id]);
        $target = Company::factory()->for($tenant, 'tenant')->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MergeService::class)->mergeCompanies($user, $source, $target);
    }

    // ------------------------------------------------------------------
    // 联系人合并
    // ------------------------------------------------------------------

    public function test_contact_merge_migrates_opportunities_and_backfills(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actingAsTenantUser($tenant);
        $stage = Stage::factory()->for($tenant, 'tenant')->create();

        $company = Company::factory()->for($tenant, 'tenant')->create();
        // 同名（实际重复维度；邮箱有 UNIQUE 约束不会重复）
        $source = Contact::factory()->for($tenant, 'tenant')->create([
            'first_name' => '志强',
            'last_name' => '李',
            'email' => 'source@example.com',
            'phone' => '138',
            'position' => 'CTO',
        ]);
        $target = Contact::factory()->for($tenant, 'tenant')->for($company, 'company')->create([
            'first_name' => '志强',
            'last_name' => '李',
            'email' => 'target@example.com',
            'phone' => null,
            'position' => null,
        ]);

        $opp = Opportunity::factory()->for($tenant, 'tenant')->for($source, 'contact')->for($stage, 'stage')->create();
        $activity = Activity::create([
            'tenant_id' => $tenant->id,
            'activitable_type' => Contact::class,
            'activitable_id' => $source->id,
            'user_id' => $user->id,
            'type' => 'email',
            'subject' => '邮件跟进',
        ]);

        app(MergeService::class)->mergeContacts($user, $source, $target);

        $this->assertSame($target->id, $opp->fresh()->contact_id);
        $this->assertSame($target->id, $activity->fresh()->activitable_id);
        $this->assertSame('138', $target->fresh()->phone);
        $this->assertSame('CTO', $target->fresh()->position);
        $this->assertTrue($source->fresh()->trashed());
    }

    public function test_cross_tenant_contact_merge_is_rejected(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $user = $this->actingAsTenantUser($tenantA);

        $source = Contact::factory()->for($tenantB, 'tenant')->create();
        $target = Contact::factory()->for($tenantA, 'tenant')->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MergeService::class)->mergeContacts($user, $source, $target);
    }

    // ------------------------------------------------------------------
    // Show 页合并组件（Livewire）
    // ------------------------------------------------------------------

    public function test_company_show_merge_component_lists_candidates_and_merges(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actingAsTenantUser($tenant);
        $source = Company::factory()->for($tenant, 'tenant')->create(['name' => '重复公司']);
        $target = Company::factory()->for($tenant, 'tenant')->create(['name' => '重复公司']);

        Livewire::actingAs($user)
            ->test(CompanyMerge::class, ['company' => $target])
            ->assertSee($source->name)
            ->set('sourceId', $source->id)
            ->call('merge');

        $this->assertTrue($source->fresh()->trashed());
        $this->assertFalse($target->fresh()->trashed());
    }

    public function test_contact_show_merge_component_lists_candidates_and_merges(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actingAsTenantUser($tenant);
        $source = Contact::factory()->for($tenant, 'tenant')->create(['first_name' => '建国', 'last_name' => '王']);
        $target = Contact::factory()->for($tenant, 'tenant')->create(['first_name' => '建国', 'last_name' => '王']);

        Livewire::actingAs($user)
            ->test(ContactMerge::class, ['contact' => $target])
            ->assertSee($source->full_name)
            ->set('sourceId', $source->id)
            ->call('merge');

        $this->assertTrue($source->fresh()->trashed());
    }

    public function test_merge_component_without_selection_shows_error(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actingAsTenantUser($tenant);
        $target = Company::factory()->for($tenant, 'tenant')->create(['name' => '独公司']);

        Livewire::actingAs($user)
            ->test(CompanyMerge::class, ['company' => $target])
            ->call('merge')
            ->assertHasErrors(['sourceId']);
    }
}
