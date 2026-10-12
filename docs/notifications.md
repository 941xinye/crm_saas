# 通知体系

系统的通知由三部分组成：**定时提醒扫描**（五类业务提醒）、**事件驱动通知**（工作流 / 评分骤降 / 邀请等）、以及**用户级偏好静音**。本文汇总通知的类型、渠道与个性化控制。

## 一、提醒类型

由 [ReminderService](../app/Services/ReminderService.php) 每日 09:00 定时扫描（`crm:send-reminders`，调度见 [config.md](config.md)）：

| 类型键 | 提醒 | 触发条件 | 默认阈值配置 |
|--------|------|----------|--------------|
| `activity_due` | 活动到期 | 活动 due_at 进入未来 N 小时内且未完成 | `crm.reminder.activity_due_hours`（24h） |
| `opp_close` | 商机成交日 | close_date 在未来 N 天内且未赢单 | `crm.reminder.opp_close_days`（7 天） |
| `opp_no_activity` | 商机无活动 | 超过 N 天无任何跟进活动 | `crm.reminder.opp_no_activity_days`（14 天） |
| `opp_stalled` | 商机停滞 | 停留当前阶段超过 N 天 | `crm.reminder.opp_stalled_days`（30 天） |
| `trial_ending` | 试用期到期 | free 租户 `trial_ends_at` 剩余 ≤ N 天，通知租户 admin 与平台超管 | `crm.reminder.trial_notice_days`（3 天） |

另有事件驱动通知不在每日扫描内：

| 类型键 | 提醒 | 触发时机 |
|--------|------|----------|
| `score_drop` | 评分骤降 | `crm:refresh-scores` 刷新后降幅超阈值（见 [scoring.md](scoring.md)） |
| `queue_failure` | 队列失败告警 | `crm:health-check` 发现冷却窗口内新增 failed_jobs，通知平台超管（见 [deployment.md](deployment.md)） |
| — | 团队邀请 | 管理员发出邀请（[TenantInvitationNotification](../app/Notifications/TenantInvitationNotification.php)） |
| — | 工作流通知 | 规则动作 `notify_owner`（见 [workflow.md](workflow.md)） |

## 二、发送渠道

渠道由 `config/crm.php` 的 `reminder.channels` 控制，默认 `['database']`（站内信），可叠加：

- `mail` — 邮件通知（需配置邮件服务，见 [config.md](config.md)）
- `robot` — 群机器人推送（钉钉 / 企业微信 / Slack，配置方法见 [config.md](config.md)）

## 三、冷却去重

同一**对象 + 类型**的通知在冷却期内不重复发送，默认 **24 小时**（`crm.reminder.cooldown_hours`）。防止每日扫描对同一商机反复轰炸；试用期提醒对租户 admin 与平台超管分别去重。

## 四、用户级通知偏好

入口：**设置 → 通知偏好**（`/settings/notifications`，[NotificationPreferences](../app/Livewire/Settings/NotificationPreferences.php)）。用户可按类型键关闭某一类提醒，关闭后站内信与邮件均不再发送；不设置时默认**全部接收**。

| 可开关类型 | 说明 |
|------------|------|
| `activity_due` / `opp_close` / `opp_no_activity` / `opp_stalled` / `trial_ending` | 五类定时提醒 |
| `score_drop` | 评分骤降提醒 |

- 偏好存储于 `users.notification_prefs`（JSON，键 → 布尔），判断逻辑为 `User::wantsNotification($key)`
- 团队邀请与工作流 `notify_owner` 动作**不受**偏好控制（事务性通知，确保送达）

## 五、通知中心

站内信统一进入 `/notifications` 通知中心：未读徽标、逐条 / 全部已读、点击跳转业务对象。
