<?php

namespace Tests\Unit;

use App\NotificationChannels\RobotWebhookChannel;
use App\Models\User;
use App\Notifications\ActivityDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 群机器人 Webhook 通知渠道测试：
 * robot 渠道按配置启用/剔除（graceful degradation）、
 * 钉钉/企业微信/Slack 三种请求体格式、推送失败不抛异常。
 */
class RobotWebhookChannelTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(): ActivityDueNotification
    {
        return new ActivityDueNotification(1, '测试活动', '2026-10-13 10:00', 'https://crm.test/activities/1');
    }

    private function setUser(): User
    {
        return User::factory()->create(['email' => 'u@example.com']);
    }

    private function enableRobot(string $type = 'dingtalk', string $url = 'https://robot.example.com/hook'): void
    {
        config()->set('crm.reminder.channels', ['database', 'robot']);
        config()->set('crm.reminder.robot', ['type' => $type, 'url' => $url]);
    }

    public function test_robot_channel_excluded_when_url_not_configured(): void
    {
        config()->set('crm.reminder.channels', ['database', 'robot']);
        config()->set('crm.reminder.robot', ['type' => 'dingtalk', 'url' => null]);

        $notification = $this->makeNotification();

        $this->assertSame(['database'], $notification->via($this->setUser()));
    }

    public function test_robot_alias_maps_to_channel_class(): void
    {
        $this->enableRobot();

        $notification = $this->makeNotification();

        $this->assertSame(['database', RobotWebhookChannel::class], $notification->via($this->setUser()));
    }

    public function test_default_channels_have_no_robot(): void
    {
        config()->set('crm.reminder.channels', ['database']);
        config()->set('crm.reminder.robot', ['type' => 'dingtalk', 'url' => null]);

        $this->assertSame(['database'], $this->makeNotification()->via($this->setUser()));
    }

    public function test_sends_dingtalk_format(): void
    {
        Http::fake(['*' => Http::response(['errcode' => 0], 200)]);
        $this->enableRobot('dingtalk');

        $this->setUser()->notify($this->makeNotification());

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://robot.example.com/hook'
                && $body['msgtype'] === 'text'
                && str_contains($body['text']['content'], 'CRM通知')
                && str_contains($body['text']['content'], '活动「测试活动」将于 2026-10-13 10:00 到期')
                && str_contains($body['text']['content'], 'https://crm.test/activities/1');
        });
    }

    public function test_sends_wecom_format(): void
    {
        Http::fake(['*' => Http::response(['errcode' => 0], 200)]);
        $this->enableRobot('wecom');

        $this->setUser()->notify($this->makeNotification());

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['msgtype'] === 'text'
                && str_contains($body['text']['content'], 'CRM通知');
        });
    }

    public function test_sends_slack_format(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $this->enableRobot('slack');

        $this->setUser()->notify($this->makeNotification());

        Http::assertSent(function ($request) {
            $body = $request->data();

            return isset($body['text'])
                && str_contains($body['text'], 'CRM通知')
                && ! isset($body['msgtype']);
        });
    }

    public function test_push_failure_does_not_throw(): void
    {
        Http::fake(['*' => Http::response('server error', 500)]);
        $this->enableRobot();

        $this->setUser()->notify($this->makeNotification());

        // 站内信仍正常落库（渠道间互不影响）
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_network_exception_does_not_throw(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $this->enableRobot();

        $this->setUser()->notify($this->makeNotification());

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_no_http_request_when_robot_not_configured(): void
    {
        Http::fake();
        config()->set('crm.reminder.channels', ['database']);
        config()->set('crm.reminder.robot', ['type' => 'dingtalk', 'url' => null]);

        $this->setUser()->notify($this->makeNotification());

        Http::assertNothingSent();
        $this->assertDatabaseCount('notifications', 1);
    }
}
