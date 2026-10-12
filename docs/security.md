# 安全设计（两步验证 / API Token / 安全要点）

本文档汇总平台的安全机制。核心原则：多租户隔离见 [architecture.md](architecture.md)，审计见 [audit.md](audit.md)。

---

## 一、两步验证（2FA，TOTP）

实现在 [app/Services/TwoFactorService.php](../app/Services/TwoFactorService.php)，**无第三方依赖**的 RFC 6238 TOTP 实现：

| 参数 | 默认值 | 说明 |
|------|--------|------|
| 算法 | SHA1（HMAC-SHA1 + 动态截断） | RFC 4226/6238 标准 |
| 时间步长 | 30 秒 | `intdiv(time(), 30)` 计数器 |
| 码长 | 6 位十进制 | 前导零保留 |
| 容差窗口 | ±1（共 3 个窗口） | 允许 ±30 秒时钟偏差，`verify($secret, $code, $window)` 可调 |
| Secret | Base32 编码 32 字符（160 位熵） | RFC 4648，兼容所有认证器 App |

### 1.1 启用流程（设置 → 安全设置，`/settings/security`）

[app/Livewire/Settings/TwoFactor.php](../app/Livewire/Settings/TwoFactor.php)：

1. **enable**：生成待确认 secret 存入 `users.two_factor_secret`（`two_factor_confirmed_at` 为空），展示 otpauth URI（认证器扫码）与手动密钥；确认前可反复重新生成覆盖
2. **confirm**：校验动态码 → 写入 `two_factor_confirmed_at` → 生成 **8 个恢复码**（`xxxx-xxxx`），**明文仅展示一次**，数据库只存 bcrypt 哈希
3. **disable / regenerate**：均需验证当前密码；重新生成恢复码后旧码全部失效

> `hasTwoFactorEnabled()` = secret 存在 **且** `two_factor_confirmed_at` 非空——确认前半途放弃不影响账号安全。

### 1.2 登录流程

密码验证通过但用户已启用 2FA 时（[app/Livewire/Auth/Login.php](../app/Livewire/Auth/Login.php)）：

- 会话**仅暂存** `2fa:user:id` + `2fa:remember`，**不建立登录态**，重定向到 `/two-factor-challenge`
- 挑战页（[app/Livewire/Auth/TwoFactorChallenge.php](../app/Livewire/Auth/TwoFactorChallenge.php)）校验 6 位动态码或恢复码后 `Auth::login()` 建立完整会话
- 无有效挑战会话直接访问挑战页会被弹回登录页
- 限流：每用户 5 次/5 分钟（`RateLimiter`，key `2fa:{user_id}`）
- 恢复码命中后**用后即焚**；剩余 ≤2 个时登录后提示重新生成

### 1.3 数据表字段

迁移 `2026_10_13_000002_add_two_factor_to_users_table.php`：

| 字段 | 类型 | 说明 |
|------|------|------|
| `two_factor_secret` | text nullable | Base32 密钥 |
| `two_factor_recovery_codes` | text nullable | JSON 数组，bcrypt 哈希 |
| `two_factor_confirmed_at` | timestamp nullable | 首次动态码确认时间 |

---

## 二、API Token 自助管理

- 页面 `/settings/tokens`（[app/Livewire/Settings/Tokens.php](../app/Livewire/Settings/Tokens.php)）：创建（明文一次性展示）、列表（名称/权限范围/创建时间/最近使用）、吊销（仅限本人 Token）
- API 侧使用方式见 [api.md](api.md) 第一章

### 2.1 Token 权限范围（Scopes / abilities，Phase 6 / P3）

创建 Token 时可勾选权限范围，按需最小授权：

| Scope | 能力 |
|-------|------|
| `read` | GET 请求（列表 / 详情 / 下载） |
| `write` | 写操作（POST / PUT / PATCH / DELETE，含上传） |

中间件 [app/Http/Middleware/EnsureTokenScope.php](../app/Http/Middleware/EnsureTokenScope.php)（别名 `token.scope`）挂在 API 主路由组（[routes/api.php](../routes/api.php)）：

- 按 HTTP 方法映射所需 ability：`GET/HEAD → read`，其余方法 → `write`
- 通过 `$user->tokenCan($required)` 校验，不满足返回 `403`（`API Token 缺少 X scope`）
- **仅对 Token 认证生效**：Session 登录（Web 端）无 `currentAccessToken()`，直接放行
- 空 abilities 的 Token 读写均被拒绝

兼容性约定：

- `/settings/tokens` 页创建时默认勾选 `read + write`（行为与旧版一致）
- API `/login`、`/register` 签发的 Token 固定含 `['read', 'write']`，存量集成不受影响
- 细粒度授权（如报表类只读集成只勾 `read`）在设置页创建

---

## 三、安全设计要点汇总

| 机制 | 实现位置 |
|------|----------|
| 租户隔离 | `BelongsToTenant` Trait + `TenantScope`（所有业务模型自动过滤，见 architecture.md） |
| 数据权限 | admin/manager 全租户，sales 仅本人数据（`User::canSeeAllRecords()`） |
| 批量操作权限 | 批量删除/恢复均走 Model Policy 校验 |
| 跨租户注入防护 | 批量操作前校验目标对象租户归属 |
| 附件私有存储 | 私有磁盘 + 认证下载 + TenantScope 归属校验（[csv.md](csv.md)/attachments 相关章节） |
| Webhook secret 脱敏 | `TenantWebhook::$hidden` 隐藏，仅创建响应一次性返回（[webhook.md](webhook.md)） |
| API Token | Sanctum 单表令牌，明文仅创建时返回；支持 read/write scope 按方法校验（见 2.1） |
| 登录限流 | 登录 5 次/分钟（按邮箱+IP），2FA 挑战 5 次/5 分钟 |
| 两步验证 | 见上文第一章 |
| 审计日志 | 全量记录创建/更新/删除/恢复，含 batch_id、IP、before/after（[audit.md](audit.md)） |

---

## 四、测试

`tests/Feature/TwoFactorTest.php`（12 个用例）覆盖：

- TOTP：secret 格式、当前码验证、非法码拒绝、±1/±2 窗口容差
- 设置页：enable→confirm 激活并生成 8 个哈希恢复码、错误码不激活、关闭需密码
- 登录：无 2FA 直登、有 2FA 拦截到挑战页（不建立登录态）、无挑战会话弹回、正确码登录、错误码拒绝、恢复码一次有效（用后即焚）
