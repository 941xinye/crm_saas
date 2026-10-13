# 白标 SMTP 邮件（White-label Email）

租户可配置自己的 SMTP 服务器与发件人身份（From），提醒、邀请、试用到期等系统邮件将从租户品牌发出；未配置时自动回落到平台默认发件（graceful degradation）。

## 功能入口

- 设置 → 「白标邮件（SMTP）」（仅租户管理员可见与可操作），路由 `settings.white-label`
- 表单字段：SMTP 服务器 / 端口 / 加密方式（TLS / SSL / 无）/ 用户名 / 密码 / 发件地址 / 发件人名称
- 密码使用 `Crypt::encryptString` 加密存储于数据库，表单不回显；留空表示保留已存密码
- 「发送测试邮件」发送到当前管理员登录邮箱，SMTP 失败时原样展示错误信息便于排查
- 「停用白标」移除配置并立即恢复平台默认发件

## 配置存储

配置存放在 `tenants.settings['whitelabel']`（JSON）：

```json
{
    "smtp_host": "smtp.example.com",
    "smtp_port": 587,
    "smtp_encryption": "tls",
    "smtp_username": "crm@example.com",
    "smtp_password": "<Crypt::encryptString 密文>",
    "from_address": "crm@example.com",
    "from_name": "Acme CRM"
}
```

`smtp_host` 与 `from_address` 均非空视为「已启用」（`TenantMailer::configured()`）。

## 运行机制

核心服务 [TenantMailer](../app/Services/TenantMailer.php)：

- `activate(?Tenant $tenant)`：租户已配置时，运行时注册名为 `tenant_whitelabel` 的 mailer（含解密后的密码与白标 From）并切换 `mail.default`，同时**切换全局 `mail.from.name` / `mail.from.address`**（驱动邮件模板品牌，见下文）；未配置时**复位平台默认**——长驻 worker（queue / Octane）处理完租户 A 后再处理租户 B，B 不会沿用 A 的 SMTP 配置
- `sendTestMail(Tenant $tenant, string $toEmail)`：以租户身份发送测试邮件，发送后立即复位
- 密码解密失败（如 APP_KEY 轮换）视为未配置密码，静默降级而不中断发送

激活挂载点（确保两类邮件路径全覆盖）：

| 场景 | 挂载位置 | 说明 |
| --- | --- | --- |
| Web / API 请求 | [BootstrapTenancy](../app/Http/Middleware/BootstrapTenancy.php) 中间件 | 租户上下文建立后激活；未配置租户每次请求复位 |
| 控制台提醒命令 | [ReminderService](../app/Services/ReminderService.php) | 每条 `notify()` 前按收件人所属租户激活；试用到期通知中平台超管走平台默认 |

## 邮件模板品牌化（Phase 10）

系统邮件（markdown 通知）的**页眉品牌名、默认署名、页脚版权、HTML `<title>`** 全部跟随租户品牌：

- 覆盖视图：[resources/views/vendor/mail/html/message.blade.php](../resources/views/vendor/mail/html/message.blade.php)（页眉/页脚）、[resources/views/vendor/mail/html/layout.blade.php](../resources/views/vendor/mail/html/layout.blade.php)（title）、[resources/views/vendor/notifications/email.blade.php](../resources/views/vendor/notifications/email.blade.php)（默认署名）
- 品牌名取值统一为 `config('mail.from.name') ?: config('app.name')`：白标激活时 TenantMailer 将 `mail.from.name` 切为租户品牌（`from_name` 或租户名），复位时从进程内快照恢复平台值，保证不泄漏
- 仅品牌文本与框架原版有差异，其余主题样式、按钮、布局结构保持 Laravel 默认

## 与通知渠道的关系

通知仍遵循 `config('crm.reminder.channels')` 渠道配置（默认站内信）。白标 SMTP 不改变「发不发邮件」，只改变「用什么服务器和发件人发」——将渠道配置加入 `mail` 后，邮件即从租户品牌发出。

## 安全与边界

- 仅租户管理员可读写白标配置（`role === 'admin'` 校验）
- SMTP 密码加密存储、不回显、不留明文日志
- 队列驱动注意：通知类若经 queue 投递，实际发送发生在 worker 进程，需 worker 侧同样具备租户上下文（当前提醒命令为进程内同步发送，已覆盖）；测试环境 `MAIL_MAILER=array` 不受影响

## 测试

`tests/Feature/WhiteLabelTest.php`（16 个用例）：激活/复位与 worker 隔离、密码加解密、留空保留密码、校验、非管理员拦截、停用只移除 whitelabel 键、测试邮件成功/失败/未配置路径、Web 请求级激活与复位、**全局 mail.from 品牌切换与恢复、邮件模板租户品牌渲染**。
