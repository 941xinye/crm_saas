<?php

namespace Tests\Unit;

use App\Livewire\ActivitySummary as ActivitySummaryComponent;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivitySummaryService;
use App\Services\LLM\LLMProvider;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AI 活动摘要测试（Phase 6 / P2）：
 * 未配置降级、无活动短路、缓存命中不重复调用、限流、prompt 截断、组件白名单。
 */
class ActivitySummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'AI 摘要测试租户',
            'slug' => 'as-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'name' => '摘要公司',
        ]);

        RateLimiter::clear('ai-summary-' . $this->user->id);
    }

    private function addActivity(string $subject, string $type = 'call'): Activity
    {
        return Activity::create([
            'tenant_id' => $this->tenant->id,
            'activitable_type' => Company::class,
            'activitable_id' => $this->company->id,
            'user_id' => $this->user->id,
            'type' => $type,
            'subject' => $subject,
            'notes' => '跟进内容',
        ]);
    }

    /** 可编程的 LLM 假实现（captured 记录收到的 prompt 供断言） */
    private function fakeLLM(bool $configured = true, ?string $response = null): LLMProvider
    {
        return new class($configured, $response) implements LLMProvider {
            public array $captured = [];

            public function __construct(
                private readonly bool $configured,
                private readonly ?string $response,
            ) {
            }

            public function configured(): bool
            {
                return $this->configured;
            }

            public function generate(string $prompt, string $systemRole = '', int $maxTokens = 400): ?string
            {
                $this->captured[] = ['prompt' => $prompt, 'system' => $systemRole, 'max_tokens' => $maxTokens];

                return $this->response;
            }
        };
    }

    public function test_returns_null_when_llm_not_configured(): void
    {
        $this->addActivity('初次电话沟通');

        $service = new ActivitySummaryService($this->fakeLLM(configured: false));

        $this->assertNull($service->summarize($this->company));
    }

    public function test_returns_null_when_no_activities(): void
    {
        $service = new ActivitySummaryService($this->fakeLLM(configured: true, response: '要点'));

        $this->assertNull($service->summarize($this->company));
    }

    public function test_generates_and_caches_summary(): void
    {
        $this->addActivity('初次电话沟通');
        $this->addActivity('发送方案报价', 'email');

        $llm = $this->fakeLLM(configured: true, response: "- 关键进展\n- 建议下一步");
        $service = new ActivitySummaryService($llm);

        $summary = $service->summarize($this->company);
        $this->assertNotNull($summary);

        // 第二次调用走缓存，LLM 只被调用一次
        $this->assertSame($summary, $service->summarize($this->company));
        $this->assertCount(1, $llm->captured);
    }

    public function test_new_activity_invalidates_cache(): void
    {
        $this->addActivity('第一条');
        $llm = $this->fakeLLM(configured: true, response: '- 摘要');
        $service = new ActivitySummaryService($llm);

        $service->summarize($this->company);

        // 新活动 → 签名变化 → 重新生成
        $this->addActivity('第二条');
        $service->summarize($this->company);

        $this->assertCount(2, $llm->captured);
    }

    public function test_rate_limiter_blocks_after_ten_uncached_generations(): void
    {
        $llm = $this->fakeLLM(configured: true, response: '- 摘要');
        $service = new ActivitySummaryService($llm);

        // 每次都换签名（新活动）绕开缓存
        for ($i = 0; $i < 10; $i++) {
            $this->addActivity("活动 {$i}");
            $this->assertNotNull($service->summarize($this->company), "第 " . ($i + 1) . " 次应成功");
        }

        $this->addActivity('压垮限流的活动');
        $this->assertNull($service->summarize($this->company));
    }

    public function test_prompt_contains_entity_and_activities(): void
    {
        $this->addActivity(str_repeat('超长主题', 60), 'meeting'); // 240 字，验证截断
        $llm = $this->fakeLLM(configured: true, response: '- 摘要');
        $service = new ActivitySummaryService($llm);

        $service->summarize($this->company);

        $prompt = $llm->captured[0]['prompt'];
        $this->assertStringContainsString('摘要公司', $prompt);
        $this->assertStringContainsString('超长主题', $prompt);
        // 单条活动截断到 150 字符 + 后缀，不应包含完整 240 字重复
        $this->assertLessThan(400, mb_strlen(str_replace(['-', ' '], '', mb_substr($prompt, 0, 600))));
    }

    public function test_component_marks_unavailable_on_degradation(): void
    {
        $this->addActivity('初次电话沟通');
        RateLimiter::clear('ai-summary-' . $this->user->id);

        // LLM 未配置 → AppServiceProvider 绑定的真实 provider configured() 为 false → 组件提示不可用
        Livewire::test(ActivitySummaryComponent::class, [
            'entityType' => Company::class,
            'entityId' => $this->company->id,
        ])
            ->call('generate')
            ->assertSet('unavailable', true);

        // 白名单外实体直接标记不可用
        Livewire::test(ActivitySummaryComponent::class, [
            'entityType' => \App\Models\Lead::class,
            'entityId' => 1,
        ])
            ->call('generate')
            ->assertSet('unavailable', true);
    }
}
