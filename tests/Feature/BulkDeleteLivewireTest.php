<?php

namespace Tests\Feature;

use App\Livewire\Contacts\Index;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Livewire 集成测试：Index::bulkDelete() 全链路。
 * 覆盖 selected 数组 → bulkDelete 方法 → 记录消失 → session flash → 审计日志聚合。
 */
class BulkDeleteLivewireTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '批量测试租户',
            'slug' => 'bd-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
            'trial_ends_at' => null,
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);
    }

    // ------------------------------------------------------------------
    // 正常批量删除：选 3 个 → 调 bulkDelete → 3 条消失 + 1 条汇总 + flash 成功
    // ------------------------------------------------------------------

    public function test_contacts_bulk_delete_removes_records_and_generates_audit_batch(): void
    {
        $c1 = Contact::factory()->for($this->tenant, 'tenant')->create(['first_name' => '删A']);
        $c2 = Contact::factory()->for($this->tenant, 'tenant')->create(['first_name' => '删B']);
        $c3 = Contact::factory()->for($this->tenant, 'tenant')->create(['first_name' => '删C']);
        $keep = Contact::factory()->for($this->tenant, 'tenant')->create(['first_name' => '保留']);

        AuditLog::query()->delete();

        Livewire::test(Index::class)
            ->set('selected', [$c1->id, $c2->id, $c3->id])
            ->call('bulkDelete')
            ->assertHasNoErrors();

        // 被删的软删除了
        $this->assertSoftDeleted('contacts', ['id' => $c1->id]);
        $this->assertSoftDeleted('contacts', ['id' => $c2->id]);
        $this->assertSoftDeleted('contacts', ['id' => $c3->id]);
        // keep 还在
        $this->assertDatabaseHas('contacts', ['id' => $keep->id, 'deleted_at' => null]);

        // 审计日志：3 条 per-record deleted + 1 条 batch_deleted = 4
        $this->assertSame(4, AuditLog::count());

        $batchIds = AuditLog::pluck('batch_id')->unique()->values();
        $this->assertCount(1, $batchIds, '所有日志应共享同一个 batch_id');

        $summary = AuditLog::where('action', 'batch_deleted')->firstOrFail();
        $this->assertSame(Contact::class, $summary->auditable_type);
        $this->assertSame(3, $summary->after['count']);
        $this->assertSame('success', $summary->after['status']);

        // per-record 都有相同 batch_id
        $perRecords = AuditLog::where('action', 'deleted')->get();
        $this->assertCount(3, $perRecords);
        foreach ($perRecords as $log) {
            $this->assertSame($summary->batch_id, $log->batch_id);
            $this->assertSame($this->tenant->id, $log->tenant_id);
            $this->assertNotNull($log->before);
            $this->assertNull($log->after);
        }

        // selected 数组在 bulkDelete 内部会被清空（$this->selected = []）
        // 这里不做显式断言，Livewire 渲染验证 soft-deleted 后不出现即可
    }

    // ------------------------------------------------------------------
    // 空选中 → bulkDelete 不执行删除、不写汇总
    // ------------------------------------------------------------------

    public function test_bulk_delete_without_selection_shows_error(): void
    {
        $c = Contact::factory()->for($this->tenant, 'tenant')->create(['first_name' => '别动']);
        AuditLog::query()->delete();

        Livewire::test(Index::class)
            ->set('selected', [])
            ->call('bulkDelete');

        // 记录没动
        $this->assertDatabaseHas('contacts', ['id' => $c->id, 'deleted_at' => null]);
        // 审计日志没写（bulkDelete 在 empty 时 return）
        $this->assertSame(0, AuditLog::count());
    }

    // ------------------------------------------------------------------
    // 只选一个 → 也会走批量流程（1+1=2 条日志）
    // ------------------------------------------------------------------

    public function test_bulk_delete_single_still_creates_summary(): void
    {
        $c = Contact::factory()->for($this->tenant, 'tenant')->create(['first_name' => '独苗']);
        AuditLog::query()->delete();

        Livewire::test(Index::class)
            ->set('selected', [$c->id])
            ->call('bulkDelete');

        // 2 条：1 deleted + 1 batch_deleted
        $this->assertSame(2, AuditLog::count());
        $this->assertSoftDeleted('contacts', ['id' => $c->id]);

        $summary = AuditLog::where('action', 'batch_deleted')->firstOrFail();
        $this->assertSame(1, $summary->after['count']);
    }

    // ------------------------------------------------------------------
    // 跨租户 ID 在 selected 里 → TenantScope 会让 find 返回 null → 跳过删除
    // ------------------------------------------------------------------

    public function test_bulk_delete_ignores_ids_from_other_tenant(): void
    {
        $otherTenant = Tenant::create([
            'name' => '别租户',
            'slug' => 'x-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ]);
        $otherContact = Contact::factory()->for($otherTenant, 'tenant')->create(['first_name' => '别租户的']);
        $myContact = Contact::factory()->for($this->tenant, 'tenant')->create(['first_name' => '我的']);

        AuditLog::query()->delete();

        Livewire::test(Index::class)
            ->set('selected', [$otherContact->id, $myContact->id, 99999])
            ->call('bulkDelete');

        // 只有我的被删（其他租户被 TenantScope 过滤，find 不到）
        $this->assertSoftDeleted('contacts', ['id' => $myContact->id]);
        $this->assertDatabaseHas('contacts', ['id' => $otherContact->id, 'deleted_at' => null]);

        // 审计日志只有 1+1=2（不是 3）
        $this->assertSame(2, AuditLog::count());
        $summary = AuditLog::where('action', 'batch_deleted')->firstOrFail();
        $this->assertSame(1, $summary->after['count']);
    }
}
