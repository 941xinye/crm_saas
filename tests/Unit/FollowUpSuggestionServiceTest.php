<?php

namespace Tests\Unit;

use App\Models\Opportunity;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FollowUpSuggestionService;
use App\Services\LLM\LLMProvider;
use App\Services\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AI 跟进建议测试：未配置降级、生成失败降级、限流、响应解析、prompt 上下文。
 */
class FollowUpSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Opportunity $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'AI 建议测试租户',
            'slug' => 'ai-' . Str::lower(Str::random(8)),
            'plan' => 'pro',
            'is_active' => true,
            'trial_ends_at' => null,
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        app(Tenancy::class)->forUser($this->user);

        $stage = Stage::create([
            'tenant_id' => $this->tenant->id, 'name' => '需求确认', 'order' => 2, 'probability' => 60,
        ]);
        $this->opportunity = Opportunity::create([
            'tenant_id' => $this->tenant->id,
            'title' => '企业授权合作',
            'value' => 80000,
            'probability' => 60,
            'stage_id' => $stage->id,
            'owner_id' => $this->user->id,
            'expected_close_date' => now()->addDays(10)->toDateString(),
        ]);

        RateLimiter::clear('ai-suggest-' . $this->user->id);
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
        $service = new FollowUpSuggestionService($this->fakeLLM(configured: false));

        $this->assertNull($service->suggest($this->opportunity));
    }

    public function test_returns_null_when_llm_generation_fails(): void
    {
        $service = new FollowUpSuggestionService($this->fakeLLM(configured: true, response: null));

        $this->assertNull($service->suggest($this->opportunity));
    }

    public function test_parses_subject_and_body_from_response(): void
    {
        $service = new FollowUpSuggestionService($this->fakeLLM(
            configured: true,
            response: "关于「企业授权合作」的跟进\n\n您好，上周沟通后想确认一下合作意向……",
        ));

        $result = $service->suggest($this->opportunity);

        $this->assertNotNull($result);
        $this->assertSame('关于「企业授权合作」的跟进', $result['subject']);
        $this->assertSame('您好，上周沟通后想确认一下合作意向……', $result['body']);
        $this->assertFalse($result['fallback']);
    }

    public function test_prompt_contains_opportunity_context(): void
    {
        $llm = $this->fakeLLM(configured: true, response: "主题\n\n正文");
        $service = new FollowUpSuggestionService($llm);

        $service->suggest($this->opportunity);

        $this->assertCount(1, $llm->captured);
        $this->assertStringContainsString('企业授权合作', $llm->captured[0]['prompt']);
        $this->assertStringContainsString('需求确认', $llm->captured[0]['prompt']);
        $this->assertStringContainsString('80,000.00', $llm->captured[0]['prompt']);
        // system role 要求纯文本邮件输出
        $this->assertStringContainsString('plain text', $llm->captured[0]['system']);
    }

    public function test_rate_limiter_blocks_after_ten_attempts(): void
    {
        $service = new FollowUpSuggestionService($this->fakeLLM(
            configured: true,
            response: "主题\n\n正文",
        ));

        for ($i = 0; $i < 10; $i++) {
            $this->assertNotNull($service->suggest($this->opportunity), "第 " . ($i + 1) . " 次应成功");
        }

        // 第 11 次触发限流
        $this->assertNull($service->suggest($this->opportunity));
    }

    public function test_rate_limit_key_is_per_user(): void
    {
        $service = new FollowUpSuggestionService($this->fakeLLM(
            configured: true,
            response: "主题\n\n正文",
        ));

        // 用户 A 打满 10 次
        for ($i = 0; $i < 10; $i++) {
            $service->suggest($this->opportunity);
        }
        $this->assertNull($service->suggest($this->opportunity));

        // 用户 B 独立计数，不受影响
        $userB = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'sales']);
        $this->actingAs($userB);
        RateLimiter::clear('ai-suggest-' . $userB->id);

        $this->assertNotNull($service->suggest($this->opportunity));
    }
}
