<?php

namespace App\NotificationChannels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 群机器人 Webhook 通知渠道（钉钉 / 企业微信 / Slack）。
 *
 * 复用通知类的 toDatabase() 载荷（message + url）作为消息内容，
 * 按 config('crm.reminder.robot') 的 type 格式化推送。
 *
 * 未配置 URL 时优雅跳过；推送失败仅记日志，绝不阻塞主业务流程。
 */
class RobotWebhookChannel
{
    public function send(object $notifiable, object $notification): void
    {
        try {
            $config = config('crm.reminder.robot', []);
            $url = $config['url'] ?? null;
            $type = $config['type'] ?? 'dingtalk';

            if (blank($url)) {
                return; // 未配置机器人 → 静默跳过（graceful degradation）
            }

            $payload = method_exists($notification, 'toDatabase')
                ? $notification->toDatabase($notifiable)
                : [];
            $text = (string) ($payload['message'] ?? '');
            $link = (string) ($payload['url'] ?? '');

            if ($link !== '') {
                $text .= "\n" . __('notifications.view_details', ['url' => $link]);
            }

            $body = $this->buildBody($type, $text);

            if ($body === null) {
                Log::warning('RobotWebhookChannel: 未知机器人类型，跳过推送', ['type' => $type]);

                return;
            }

            $response = Http::timeout(5)->asJson()->post($url, $body);

            if (! $response->successful()) {
                Log::warning('RobotWebhookChannel: 推送失败', [
                    'type' => $type,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            // 旁路化：机器人推送失败不影响提醒/工作流主流程
            Log::warning('RobotWebhookChannel: 推送异常', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 按平台构造请求体；未知类型返回 null。
     */
    private function buildBody(string $type, string $text): ?array
    {
        return match ($type) {
            // 钉钉群机器人（安全设置含自定义关键词时，建议机器人关键词设为「CRM」）
            'dingtalk' => [
                'msgtype' => 'text',
                'text' => ['content' => __('notifications.robot_title') . "\n{$text}"],
            ],
            // 企业微信群机器人
            'wecom' => [
                'msgtype' => 'text',
                'text' => ['content' => __('notifications.robot_title') . "\n{$text}"],
            ],
            // Slack Incoming Webhook
            'slack' => [
                'text' => __('notifications.robot_title') . "\n{$text}",
            ],
            default => null,
        };
    }
}
