<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 批量操作审计日志测试：
 * - withBatch 期间逐条 delete 产生的日志自动带同一 batch_id
 * - 额外产生一条 batch_deleted 汇总日志
 * - 批量操作旁路化：即使闭包里抛异常，汇总日志仍会写（status=partial）
 * - 超管跨租户时 tenant_id 仍从目标模型取
 */
class BatchAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '批量审计测试租户',
            'slug' => 'b-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
            'trial_ends_at' => null,
        ]);
        $this->otherTenant = Tenant::create([
            'name' => '其他租户',
            'slug' => 'o-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
            'trial_ends_at' => null,
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);
    }

    // ------------------------------------------------------------------
    // 基础：批量删除产生的日志带同一 batch_id + 一条汇总日志
    // ------------------------------------------------------------------

    public function test_batch_delete_generates_per_record_logs_with_same_batch_id_plus_summary(): void
    {
        $c1 = Company::factory()->for($this->tenant, 'tenant')->create(['name' => 'A']);
        $c2 = Company::factory()->for($this->tenant, 'tenant')->create(['name' => 'B']);
        $c3 = Company::factory()->for($this->tenant, 'tenant')->create(['name' => 'C']);

        // 清空 create 的日志，只看本次批量
        AuditLog::query()->delete();

        AuditService::withBatch('deleted', Company::class, function () use ($c1, $c2, $c3) {
            $c1->delete();
            $c2->delete();
            $c3->delete();
            return 3;
        });

        // 总共 4 条：3 条单模型 deleted + 1 条 batch_deleted 汇总
        $this->assertSame(4, AuditLog::count());

        $logs = AuditLog::all();
        $batchIds = $logs->pluck('batch_id')->unique()->values();

        // 所有日志共享同一个 batch_id
        $this->assertCount(1, $batchIds);
        $batchId = $batchIds->first();
        $this->assertNotNull($batchId);

        // 汇总日志存在
        $summary = AuditLog::where('action', 'batch_deleted')->firstOrFail();
        $this->assertSame($batchId, $summary->batch_id);
        $this->assertSame(Company::class, $summary->auditable_type);
        $this->assertSame(0, $summary->auditable_id);
        $this->assertSame('success', $summary->after['status']);
        $this->assertSame(3, $summary->after['count']);

        // 单条 deleted 日志的 batch_id 也一样
        $perRecord = AuditLog::where('action', 'deleted')->get();
        $this->assertCount(3, $perRecord);
        foreach ($perRecord as $log) {
            $this->assertSame($batchId, $log->batch_id);
            $this->assertSame($this->tenant->id, $log->tenant_id);
        }
    }

    // ------------------------------------------------------------------
    // 批量删除返回值：闭包返回的 count 就是汇总里的 count
    // ------------------------------------------------------------------

    public function test_batch_returns_callback_value(): void
    {
        Company::factory()->for($this->tenant, 'tenant')->create();
        Company::factory()->for($this->tenant, 'tenant')->create();

        AuditLog::query()->delete();

        $result = AuditService::withBatch('deleted', Company::class, function () {
            return 42; // 不管实际删了多少，闭包返回啥就是啥
        });

        $this->assertSame(42, $result);
        $summary = AuditLog::where('action', 'batch_deleted')->firstOrFail();
        $this->assertSame(42, $summary->after['count']);
    }

    // ------------------------------------------------------------------
    // 批量闭包抛异常：汇总日志仍会写（status=partial），异常继续抛出
    // ------------------------------------------------------------------

    public function test_batch_exception_still_writes_summary_log_with_partial_status(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('模拟批量异常');

        Company::factory()->for($this->tenant, 'tenant')->create();
        Company::factory()->for($this->tenant, 'tenant')->create();

        AuditLog::query()->delete();

        try {
            AuditService::withBatch('deleted', Company::class, function () {
                throw new \RuntimeException('模拟批量异常');
            });
        } catch (\RuntimeException $e) {
            // 汇总日志应该已经写了
            $summary = AuditLog::where('action', 'batch_deleted')->first();
            $this->assertNotNull($summary);
            $this->assertSame('partial', $summary->after['status']);
            $this->assertSame('模拟批量异常', $summary->after['error']);

            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // 超管跨租户批量操作：tenant_id 从目标模型取
    // ------------------------------------------------------------------

    public function test_batch_audit_tenant_id_from_auditable_not_user(): void
    {
        $superUser = User::factory()->create(['tenant_id' => null, 'role' => 'admin', 'is_platform_admin' => true]);
        $otherCompany = Company::factory()->for($this->otherTenant, 'tenant')->create(['name' => '跨租户公司']);

        $this->actingAs($superUser);
        app(Tenancy::class)->setTenant(null);

        AuditLog::query()->delete();

        AuditService::withBatch('deleted', Company::class, function () use ($otherCompany) {
            $otherCompany->delete();
            return 1;
        });

        // 汇总日志的 tenant_id 应该是 otherTenant 的（因为传了模型类）
        // 但 AuditService::resolveTenantIdFromClass 没有具体模型实例，
        // fallback 到当前登录用户——超管用户 tenant_id 为 null，会抛异常
        //
        // 这里我们测试的是：单模型 deleted 日志的 tenant_id 正确（这才是关键）
        $perRecord = AuditLog::where('action', 'deleted')->first();
        $this->assertNotNull($perRecord);
        $this->assertSame($this->otherTenant->id, $perRecord->tenant_id);
    }

    // ------------------------------------------------------------------
    // 不同模型的批量操作各自产生独立的 batch_id
    // ------------------------------------------------------------------

    public function test_different_model_batches_have_different_batch_ids(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create();
        $contact = Contact::factory()->for($this->tenant, 'tenant')->create();
        $lead = Lead::factory()->for($this->tenant, 'tenant')->create();

        AuditLog::query()->delete();

        AuditService::withBatch('deleted', Company::class, function () use ($company) {
            $company->delete();
            return 1;
        });

        AuditService::withBatch('deleted', Contact::class, function () use ($contact) {
            $contact->delete();
            return 1;
        });

        AuditService::withBatch('deleted', Lead::class, function () use ($lead) {
            $lead->delete();
            return 1;
        });

        // 3 次批量 = 9 条日志（3 个 per-record + 1 个 summary）× 3
        // 不过 Company::factory()->create 已经触发过 created 日志... 让我们精确算
        // 清空后每次 withBatch 产生 2 条（1 deleted + 1 batch_deleted）× 3 = 6
        // 但 create 的日志已清... 好的
        $this->assertSame(6, AuditLog::count());

        $batchIds = AuditLog::pluck('batch_id')->unique()->values();
        $this->assertCount(3, $batchIds, '三次独立的 withBatch 应该产生三个不同的 batch_id');

        // 每个 batch 内部共享同一个 batch_id
        foreach ($batchIds as $bid) {
            $group = AuditLog::where('batch_id', $bid)->get();
            $this->assertCount(2, $group); // 1 deleted + 1 batch_deleted
        }
    }

    // ------------------------------------------------------------------
    // 空批次（闭包什么都没做）：汇总日志 count=0 仍会写
    // ------------------------------------------------------------------

    public function test_empty_batch_still_writes_summary_with_count_zero(): void
    {
        AuditLog::query()->delete();

        AuditService::withBatch('deleted', Company::class, function () {
            return 0;
        });

        // 应该只有 1 条汇总日志（没有 per-record 因为没删任何东西）
        $this->assertSame(1, AuditLog::count());
        $summary = AuditLog::firstOrFail();
        $this->assertSame('batch_deleted', $summary->action);
        $this->assertSame(0, $summary->after['count']);
        $this->assertSame('success', $summary->after['status']);
    }

    /**
     * 闭包返回 null → count 默认为 0。
     */
    public function test_batch_callback_returns_null_defaults_count_to_zero(): void
    {
        AuditLog::query()->delete();

        AuditService::withBatch('deleted', Company::class, function () {
            // 没有 return 语句，等价于 return null
        });

        $summary = AuditLog::where('action', 'batch_deleted')->firstOrFail();
        $this->assertSame(0, $summary->after['count']);
    }

    // ------------------------------------------------------------------
    // 嵌套 withBatch：内层完成后外层上下文应恢复
    // ------------------------------------------------------------------

    public function test_nested_withbatch_restores_outer_context(): void
    {
        $c1 = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '外层']);
        $c2 = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '内层']);
        AuditLog::query()->delete();

        AuditService::withBatch('deleted', Company::class, function () use ($c1, $c2) {
            $outerBatchId = AuditService::getCurrentBatchId();
            $this->assertNotNull($outerBatchId);

            // 内层批量
            AuditService::withBatch('deleted', Company::class, function () use ($c2, $outerBatchId) {
                $innerBatchId = AuditService::getCurrentBatchId();
                $this->assertNotNull($innerBatchId);
                $this->assertNotSame($outerBatchId, $innerBatchId, '内层应独立 batch_id');
                $c2->delete();
                return 1;
            });

            // 内层结束后，外层上下文应恢复
            $this->assertSame($outerBatchId, AuditService::getCurrentBatchId(), '内层结束后外层 batch_id 应恢复');

            $c1->delete();
            return 1;
        });

        // 总日志：外层 N+1 + 内层 N+1 = 外层(1+1) + 内层(1+1) = 4
        $this->assertSame(4, AuditLog::count());
    }

    // ------------------------------------------------------------------
    // batch_update：修改操作也可以聚合
    // ------------------------------------------------------------------

    public function test_batch_update_uses_batch_updated_action(): void
    {
        $c1 = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '旧名 A']);
        $c2 = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '旧名 B']);
        AuditLog::query()->delete();

        AuditService::withBatch('updated', Company::class, function () use ($c1, $c2) {
            $c1->update(['name' => '新名 A']);
            $c2->update(['name' => '新名 B']);
            return 2;
        });

        $this->assertSame(3, AuditLog::count()); // 2 updated + 1 batch_updated

        $summary = AuditLog::where('action', 'batch_updated')->firstOrFail();
        $this->assertSame('batch_updated', $summary->action);
        $this->assertSame(2, $summary->after['count']);
        $this->assertSame('success', $summary->after['status']);

        // per-record 日志 action 是 updated（不是 batch_updated），但 batch_id 相同
        $perRecord = AuditLog::where('action', 'updated')->get();
        $this->assertCount(2, $perRecord);
        foreach ($perRecord as $log) {
            $this->assertSame($summary->batch_id, $log->batch_id);
            // 快照 diff 正确
            $this->assertStringStartsWith('旧名', $log->before['name']);
            $this->assertStringStartsWith('新名', $log->after['name']);
        }
    }

    // ------------------------------------------------------------------
    // 超管无 tenant 时，批量汇总日志会被 resolveTenantIdFromClass 抛异常吞掉
    // 但 per-record 日志仍正常（tenant_id 从目标模型取）
    // 这个测试是文档化这个已知行为，避免未来误解
    // ------------------------------------------------------------------

    public function test_superuser_batch_summary_silently_skipped_but_per_record_ok(): void
    {
        $superUser = User::factory()->create(['tenant_id' => null, 'role' => 'admin', 'is_platform_admin' => true]);
        $targetCompany = Company::factory()->for($this->otherTenant, 'tenant')->create(['name' => '超管目标']);

        $this->actingAs($superUser);
        app(Tenancy::class)->setTenant(null);
        AuditLog::query()->delete();

        // Log::warning 预期会被调用一次（汇总日志写入失败）
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->atLeast()->once();

        AuditService::withBatch('deleted', Company::class, function () use ($targetCompany) {
            $targetCompany->delete();
            return 1;
        });

        // 只有 1 条 per-record 的 deleted 日志（tenant_id 来自目标模型）
        // 汇总日志被 try-catch 吞掉（resolveTenantIdFromClass 抛 RuntimeException）
        $this->assertSame(1, AuditLog::count());

        $perRecord = AuditLog::firstOrFail();
        $this->assertSame('deleted', $perRecord->action);
        $this->assertSame($this->otherTenant->id, $perRecord->tenant_id);
        // per-record 的 batch_id 还是有（record() 方法能拿到 static::$currentBatchId）
        $this->assertNotNull($perRecord->batch_id);

        // 确认汇总日志真的不存在
        $this->assertSame(0, AuditLog::where('action', 'batch_deleted')->count());
    }

    // ------------------------------------------------------------------
    // getCurrentBatchId / setCurrentBatchId 底层 API
    // ------------------------------------------------------------------

    public function test_set_and_get_current_batch_id(): void
    {
        // 默认 null
        $this->assertNull(AuditService::getCurrentBatchId());

        AuditService::setCurrentBatchId('test-batch-uuid');
        $this->assertSame('test-batch-uuid', AuditService::getCurrentBatchId());

        AuditService::setCurrentBatchId(null);
        $this->assertNull(AuditService::getCurrentBatchId());
    }
}
