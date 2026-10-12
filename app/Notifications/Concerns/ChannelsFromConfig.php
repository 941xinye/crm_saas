<?php

namespace App\Notifications\Concerns;

use App\NotificationChannels\RobotWebhookChannel;

/**
 * 提醒类通知的渠道解析：从 config('crm.reminder.channels') 读取，
 * 默认仅站内信，可配置叠加 'mail'、'robot'（群机器人 webhook）渠道。
 *
 * 'robot' 渠道仅在 config('crm.reminder.robot.url') 非空时启用，
 * 未配置时自动剔除（graceful degradation）。
 */
trait ChannelsFromConfig
{
    public function via(object $notifiable): array
    {
        $channels = config('crm.reminder.channels', ['database']);

        // 无邮箱的用户跳过邮件渠道
        if (in_array('mail', $channels, true) && blank($notifiable->email ?? null)) {
            $channels = array_diff($channels, ['mail']);
        }

        // 未配置群机器人 URL 时跳过 robot 渠道
        if (in_array('robot', $channels, true) && blank(config('crm.reminder.robot.url'))) {
            $channels = array_diff($channels, ['robot']);
        }

        // 'robot' 是别名，实际派发到自定义渠道类
        return array_values(array_map(
            fn (string $channel) => $channel === 'robot' ? RobotWebhookChannel::class : $channel,
            $channels,
        ));
    }

    abstract public function toDatabase(object $notifiable): array;
}
