<?php

namespace Tests\Unit;

use App\Services\ErrorReporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrorReporterTest extends TestCase
{
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
}
