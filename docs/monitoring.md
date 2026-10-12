# 监控与告警

生产环境故障发现与处理的完整方案。部署流程见 [deployment.md](deployment.md)，机器人通知配置见 [config.md](config.md)。

## 一、监控体系总览

| 层 | 手段 | 告警目的地 |
|----|------|-----------|
| 应用异常（5xx） | `ErrorReporter` 自动推送 | 群机器人（钉钉/企业微信/Slack） |
| 队列失败 | `crm:health-check`（每小时） | 站内信（平台超管） |
| 队列积压 | `php artisan queue:failed` 人工排查 | — |
| 进程存活 | Supervisor / systemctl | — |
| 应用日志 | `storage/logs/laravel.log` | — |
| 过期导出 | `crm:health-check` 自动清理 | — |

## 二、错误告警（ErrorReporter）

应用抛出 **5xx 级异常**时自动向群机器人推送告警，实现位于 [app/Services/ErrorReporter.php](../app/Services/ErrorReporter.php)，通过 [bootstrap/app.php](../bootstrap/app.php) 的 `$exceptions->reportable()` 挂载，无需任何手动埋点。

### 行为规则

- **不告警的异常**：验证失败（ValidationException）、未认证、404 等 4xx 用户态错误——这些是正常业务流，告警只会制造噪音
- **冷却去重**：同一错误签名（异常类 + 文件 + 行）在冷却期内只推送一次，默认 600 秒，防止异常风暴刷屏
- **旁路化**：告警推送失败仅记日志，绝不影响主请求流程
- **复用机器人配置**：使用与提醒/工作流通知相同的 `CRM_ROBOT_WEBHOOK_URL` / `CRM_ROBOT_TYPE`

### 配置

```ini
CRM_ERROR_ALERTS=true           # 总开关（默认 true）
CRM_ERROR_ALERT_COOLDOWN=600    # 冷却期（秒）
CRM_ROBOT_WEBHOOK_URL=          # 机器人地址（为空则静默降级，不告警）
CRM_ROBOT_TYPE=dingtalk         # dingtalk / wecom / slack
```

> 本地开发若配置了机器人且不希望收到告警，在 `.env` 设 `CRM_ERROR_ALERTS=false` 即可。

> 钉钉机器人如启用「自定义关键词」安全设置，请添加关键词 `CRM` 或异常文本中的「应用异常告警」。

## 三、可选升级：Sentry

`ErrorReporter` 覆盖「第一时间知道出了错」；如需堆栈聚合、issue 管理、发布关联等能力，可叠加 Sentry：

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=你的DSN   # 自动写入 .env 与 exception handler
```

- `SENTRY_LVN` 写入 `.env` 后自动上报；未配置时 SDK 静默不启用
- 与 ErrorReporter 不冲突：Sentry 管聚合分析，机器人管即时触达，两者可并存
- 生产建议设置 `SENTRY_TRACES_SAMPLE_RATE=0.1` 控制性能开销

## 四、告警响应流程建议

1. 收到异常告警 → 确认频率与影响面（同签名冷却期内不会重复轰炸，多次告警即多个不同错误）
2. `tail -f storage/logs/laravel.log` 查完整堆栈（工作流/LLM/Webhook 降级异常也在此）
3. 涉及队列时 `php artisan queue:failed` 检查积压，修复后 `queue:retry all`
4. 处理完成后确认告警停止；如属已知低危问题，可临时 `CRM_ERROR_ALERTS=false` 止损后修复
