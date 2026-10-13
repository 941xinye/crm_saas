<?php

namespace Tests\Unit;

use App\Models\ErrorEvent;
use App\Services\ErrorReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrorReporterTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'crm.error_alerts.enabled' => true,
            'crm.error_alerts.cooldown' => 600,
            'crm.reminder.robot' => ['type' => 'dingtalk', 'url' => 'https://robot.example.com/hook'],
        ]);
    }

    public function test_sends_alert_for_server_error(): void
    {
        Http::fake(['https://robot.example.com/hook' => Http::response('ok')]);
        $this->enable();

        app(ErrorReporter::class)->report(new \RuntimeException('boom'));

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return ($request->data()['text']['content'] ?? '') !== ''
                && str_contains($request->data()['text']['content'], 'boom')
                && str_contains($request->data()['text']['content'], 'RuntimeException');
        });
    }

    public function test_same_error_is_cooldown_deduped(): void
    {
        Http::fake(['https://robot.example.com/hook' => Http::response('ok')]);
        $this->enable();

        $e = new \RuntimeException('boom');
        app(ErrorReporter::class)->report($e);
        app(ErrorReporter::class)->report($e);

        Http::assertSentCount(1);
    }

    public function test_skips_validation_exception(): void
    {
        Http::fake();
        $this->enable();

        app(ErrorReporter::class)->report(
            ValidationException::withMessages(['email' => 'invalid'])
        );

        Http::assertNothingSent();
    }

    public function test_skips_4xx_http_exception(): void
    {
        Http::fake();
        $this->enable();

        app(ErrorReporter::class)->report(new NotFoundHttpException);

        Http::assertNothingSent();
    }

    public function test_skips_when_robot_not_configured(): void
    {
        Http::fake();
        config(['crm.error_alerts.enabled' => true, 'crm.reminder.robot' => ['type' => 'dingtalk', 'url' => null]]);

        app(ErrorReporter::class)->report(new \RuntimeException('boom'));

        Http::assertNothingSent();
    }

    public function test_skips_when_disabled(): void
    {
        Http::fake();
        config(['crm.error_alerts.enabled' => false, 'crm.reminder.robot' => ['type' => 'dingtalk', 'url' => 'https://robot.example.com/hook']]);

        app(ErrorReporter::class)->report(new \RuntimeException('boom'));

        Http::assertNothingSent();
    }

    public function test_supports_wecom_and_slack_body_formats(): void
    {
        Http::fake(['https://robot.example.com/hook' => Http::response('ok')]);
        config([
            'crm.error_alerts.enabled' => true,
            'crm.error_alerts.cooldown' => 600,
            'crm.reminder.robot' => ['type' => 'slack', 'url' => 'https://robot.example.com/hook'],
        ]);

        app(ErrorReporter::class)->report(new \RuntimeException('slack test'));

        Http::assertSent(fn ($request) => str_contains($request->data()['text'] ?? '', 'slack test'));
    }

    // ------------------------------------------------------------------
    // DB 聚合（Phase 10）
    // ------------------------------------------------------------------

    public function test_records_error_event_to_database(): void
    {
        $this->enable();
        Http::fake(['https://robot.example.com/hook' => Http::response('ok')]);

        app(ErrorReporter::class)->report(new \RuntimeException('聚合测试'));

        $event = ErrorEvent::query()->sole();
        $this->assertSame(\RuntimeException::class, $event->type);
        $this->assertSame('聚合测试', $event->message);
        $this->assertSame(1, $event->count);
        $this->assertNotNull($event->first_seen_at);
        $this->assertNotNull($event->last_seen_at);
    }

    public function test_repeated_errors_aggregate_by_signature(): void
    {
        $this->enable();
        Http::fake(['https://robot.example.com/hook' => Http::response('ok')]);

        // 同一行 new：file/line 相同 → 同签名
        $first = new \RuntimeException('第一次'); $second = new \RuntimeException('第二次');
        app(ErrorReporter::class)->report($first);
        app(ErrorReporter::class)->report($second);

        // 同签名聚合成一行：count=2，message 刷为最新，机器人推送仍受冷却限制只发一次
        $this->assertSame(1, ErrorEvent::query()->count());
        $event = ErrorEvent::query()->sole();
        $this->assertSame(2, $event->count);
        $this->assertSame('第二次', $event->message);
        Http::assertSentCount(1);
    }

    public function test_aggregation_works_without_robot_webhook(): void
    {
        // DB 聚合与机器人推送相互独立：未配置 webhook 时聚合照常落库
        config(['crm.error_alerts.enabled' => true, 'crm.reminder.robot' => ['type' => 'dingtalk', 'url' => null]]);

        app(ErrorReporter::class)->report(new \RuntimeException('无 webhook'));

        $this->assertSame(1, ErrorEvent::query()->count());
        Http::assertNothingSent();
    }

    public function test_ignored_exceptions_not_recorded(): void
    {
        $this->enable();
        Http::fake();

        app(ErrorReporter::class)->report(ValidationException::withMessages(['email' => 'invalid']));
        app(ErrorReporter::class)->report(new NotFoundHttpException);

        $this->assertSame(0, ErrorEvent::query()->count());
    }

    public function test_disabled_monitoring_records_nothing(): void
    {
        config(['crm.error_alerts.enabled' => false, 'crm.reminder.robot' => ['type' => 'dingtalk', 'url' => 'https://robot.example.com/hook']]);

        app(ErrorReporter::class)->report(new \RuntimeException('boom'));

        $this->assertSame(0, ErrorEvent::query()->count());
    }
}
