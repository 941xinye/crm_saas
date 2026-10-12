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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 审计日志测试：create/update/delete 自动记录、快照正确、旁路化、租户隔离。
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => '审计测试租户',
            'slug' => 'a-' . Str::lower(Str::random(8)),
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
    // Create
    // ------------------------------------------------------------------

    public function test_create_generates_audit_log(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '审计公司 A']);

        $this->assertSame(1, AuditLog::count());
        $log = AuditLog::firstOrFail();
        $this->assertSame('created', $log->action);
        $this->assertSame($company->id, $log->auditable_id);
        $this->assertSame(Company::class, $log->auditable_type);
        $this->assertSame($this->tenant->id, $log->tenant_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertNull($log->before);
        $this->assertSame('审计公司 A', $log->after['name']);
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function test_update_generates_audit_log_with_before_after(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '原名', 'industry' => '科技']);
        AuditLog::query()->delete(); // 清空 create 的日志

        $company->update(['name' => '新名', 'industry' => '金融']);

        $this->assertSame(1, AuditLog::count());
        $log = AuditLog::firstOrFail();
        $this->assertSame('updated', $log->action);

        // before 快照应存修改前的值
        $this->assertSame('原名', $log->before['name']);
        $this->assertSame('科技', $log->before['industry']);
        // after 快照应存修改后的值
        $this->assertSame('新名', $log->after['name']);
        $this->assertSame('金融', $log->after['industry']);
    }

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    public function test_delete_generates_audit_log(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '待删除公司']);
        AuditLog::query()->delete();

        $company->delete();

        $this->assertSame(1, AuditLog::count());
        $log = AuditLog::firstOrFail();
        $this->assertSame('deleted', $log->action);
        $this->assertSame('待删除公司', $log->before['name']);
        $this->assertNull($log->after);
    }

    // ------------------------------------------------------------------
    // 旁路化：审计写入失败不阻断业务，且会写 Log::warning
    // ------------------------------------------------------------------

    public function test_audit_failure_does_not_block_create(): void
    {
        $beforeCompanies = Company::count();
        $beforeLogs = AuditLog::count();

        // 预期 Log::warning 会被调用（AuditService::record 内部 catch 到异常后写 warning）
        Log::shouldReceive('warning')->once()->withArgs(function ($msg, $ctx) {
            return $msg === '审计日志写入失败';
        });

        // 直接给 AuditLog 注册 saving 拦截（不能包在 booted 里，否则 saving 注册不及时）
        AuditLog::saving(function ($log) {
            throw new \RuntimeException('模拟审计表故障');
        });

        // 业务操作：应不受阻断
        $company = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '旁路验证']);

        $this->assertNotNull($company, '审计故障不应阻断 Company 创建');
        $this->assertSame($beforeCompanies + 1, Company::count());
        // 审计日志表应该一条都没写进去（全被 saving hook 拦截了）
        $this->assertSame($beforeLogs, AuditLog::count());
    }

    // ------------------------------------------------------------------
    // Snapshot 正确性
    // ------------------------------------------------------------------

    /**
     * tenant_id 是内部字段，快照里必须排除掉（避免泄露隔离边界）。
     */
    public function test_snapshot_excludes_tenant_id(): void
    {
        $lead = Lead::factory()->for($this->tenant, 'tenant')->create(['name' => '快照测试']);

        $log = AuditLog::where('auditable_id', $lead->id)->where('action', 'created')->firstOrFail();

        $this->assertArrayNotHasKey('tenant_id', $log->after ?? []);

        // update 时 before / after 也都不应有 tenant_id
        $lead->update(['status' => 'qualified']);
        AuditLog::query()->delete();
        $lead->update(['status' => 'new']);
        $updateLog = AuditLog::where('auditable_id', $lead->id)->where('action', 'updated')->firstOrFail();

        $this->assertArrayNotHasKey('tenant_id', $updateLog->before ?? []);
        $this->assertArrayNotHasKey('tenant_id', $updateLog->after ?? []);
    }

    /**
     * Opportunity 有 cast 字段（value:decimal, expected_close_date:date），
     * 快照应正确处理 DateTimeInterface → 字符串、Decimal → 字符串。
     */
    public function test_snapshot_handles_casted_fields(): void
    {
        // 先创建一个 Stage（Opportunity FK 依赖）
        $stage = \App\Models\Stage::factory()->for($this->tenant, 'tenant')->create();

        $opp = Opportunity::factory()->for($this->tenant, 'tenant')
            ->recycle($stage)
            ->create(['value' => 9999.99, 'expected_close_date' => '2026-12-31']);

        $log = AuditLog::where('auditable_id', $opp->id)->where('action', 'created')->firstOrFail();

        $this->assertNotNull($log->after);
        // value 和 expected_close_date 应该出现在快照里
        $this->assertArrayHasKey('value', $log->after);
        $this->assertArrayHasKey('expected_close_date', $log->after);
    }

    /**
     * 直接调用 AuditService::record，resolveTenantId 找不到 tenant_id 字段
     * 且无登录用户 → 应吞异常并写 Log::warning（旁路化）。
     */
    public function test_record_swallows_exception_when_tenant_id_cannot_be_resolved(): void
    {
        // 构造一个没有 tenant_id 字段的假模型
        $fake = new class extends \Illuminate\Database\Eloquent\Model {
            public $exists = true;
            public $id = 99;
        };

        // 直接用 Auth::logout() 登出（actingAs(null) 需要 Authenticatable 对象）
        auth()->logout();
        app(Tenancy::class)->setTenant(null);

        Log::shouldReceive('warning')->once()->withArgs(function ($msg) {
            return str_contains($msg, '审计日志写入失败');
        });

        // 不应抛异常
        AuditService::record($fake, 'created', null, ['foo' => 'bar']);
    }

    // ------------------------------------------------------------------
    // Lead / Contact / Opportunity（非 Company）也正常工作
    // ------------------------------------------------------------------

    public function test_lead_create_generates_audit_log(): void
    {
        $lead = Lead::factory()->for($this->tenant, 'tenant')->create(['name' => '审计线索']);

        $log = AuditLog::where('auditable_type', Lead::class)->firstOrFail();
        $this->assertSame('created', $log->action);
        $this->assertSame($lead->id, $log->auditable_id);
        $this->assertSame('审计线索', $log->after['name']);
    }

    public function test_contact_delete_generates_audit_log(): void
    {
        $contact = Contact::factory()->for($this->tenant, 'tenant')->create(['first_name' => '审', 'last_name' => '计']);
        AuditLog::query()->delete();

        $contact->delete();

        $log = AuditLog::where('auditable_type', Contact::class)->where('action', 'deleted')->firstOrFail();
        $this->assertSame('审', $log->before['first_name']);
        $this->assertSame('计', $log->before['last_name']);
        $this->assertNull($log->after);
    }

    // ------------------------------------------------------------------
    // SoftDeletes restored
    // ------------------------------------------------------------------

    public function test_restore_generates_audit_log(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '待恢复公司']);
        $company->delete();
        AuditLog::query()->delete();

        $company->restore();

        $this->assertSame(1, AuditLog::count());
        $log = AuditLog::firstOrFail();
        $this->assertSame('restored', $log->action);
        $this->assertSame('待恢复公司', $log->after['name']);
    }

    /**
     * restore() 会同时触发 restored + updated，Auditable trait 要跳过这个
     * 副作用 updated 事件，否则会多出一条重复审计。
     */
    public function test_restore_does_not_generate_duplicate_updated_log(): void
    {
        $company = Company::factory()->for($this->tenant, 'tenant')->create(['name' => '不重复']);
        $company->delete();
        AuditLog::query()->delete();

        $company->restore();

        // 应该只有 restored 一条，没有多余的 updated
        $this->assertSame(1, AuditLog::count());
        $this->assertSame('restored', AuditLog::firstOrFail()->action);
    }

    // ------------------------------------------------------------------
    // 租户隔离
    // ------------------------------------------------------------------

    public function test_audit_logs_are_tenant_scoped(): void
    {
        // 本租户 1 条 + 其他租户 1 条
        Company::factory()->for($this->tenant, 'tenant')->create();
        Company::factory()->for($this->otherTenant, 'tenant')->create();

        // TenantScope 自动过滤，当前用户只能看到本租户
        $visible = AuditLog::withOutGlobalScope(\App\Models\Scopes\TenantScope::class)->count();
        $this->assertSame(2, $visible);

        // 正常查询（带 scope）只返回本租户
        $this->assertSame(1, AuditLog::count());
    }

    // ------------------------------------------------------------------
    // 超管跨租户场景
    // ------------------------------------------------------------------

    public function test_audit_log_tenant_id_from_auditable_not_user(): void
    {
        // 超管：tenant_id=null, role=admin, is_platform_admin=true
        $superUser = User::factory()->create(['tenant_id' => null, 'role' => 'admin', 'is_platform_admin' => true]);
        $otherCompany = Company::factory()->for($this->otherTenant, 'tenant')->create(['name' => '超管操作公司']);

        $this->actingAs($superUser);
        app(Tenancy::class)->setTenant(null);

        $otherCompany->update(['name' => '超管改后']);

        // 审计日志的 tenant_id 应从被操作的 Company 取（otherTenant），而非当前用户的 null
        $log = AuditLog::withOutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->where('auditable_id', $otherCompany->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame($this->otherTenant->id, $log->tenant_id);
        $this->assertSame($superUser->id, $log->user_id);
    }

    // ------------------------------------------------------------------
    // 路由页面
    // ------------------------------------------------------------------

    public function test_audit_logs_page_renders(): void
    {
        Company::factory()->for($this->tenant, 'tenant')->create();

        $this->get(route('audit-logs'))
            ->assertOk()
            ->assertSee('审计日志');
    }

    public function test_audit_logs_page_requires_tenant(): void
    {
        $orphan = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($orphan)
            ->get(route('audit-logs'))
            ->assertForbidden();
    }
}
