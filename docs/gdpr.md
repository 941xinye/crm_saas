# 租户数据导出与删除（GDPR）

平台为满足 GDPR 数据可携带权与被遗忘权，提供租户级数据一键导出与一键清除能力。两项操作**仅平台超管**可在平台后台触发（[app/Livewire/Platform/Tenants.php](../app/Livewire/Platform/Tenants.php)），普通租户用户与 API 均不可触达。实现位于 [app/Services/TenantDataService.php](../app/Services/TenantDataService.php)。

## 一、数据导出（可携带权）

平台后台 → 租户列表 → 目标租户「导出数据」。生成 ZIP 后以浏览器下载返回。

### ZIP 内容（8 张 CSV）

| 文件 | 说明 |
|------|------|
| `users.csv` | 团队成员（GDPR 个人数据核查） |
| `companies.csv` / `contacts.csv` / `leads.csv` / `opportunities.csv` / `activities.csv` / `attachments.csv` | 业务数据 |
| `audit_logs.csv` | 审计日志（合规留存记录一并导出） |

- CSV 带 UTF-8 BOM，Excel 直接打开不乱码；全量列，非空表首行为列名
- 附件 CSV 只含元数据记录，附件物理文件不在 ZIP 内（可通过[附件模块](security.md)逐一认证下载）
- 导出按 `tenant_id` 精确过滤，不依赖租户上下文（平台超管无 Tenancy）

### 临时文件

ZIP 先写入 `storage/app/tenant-exports/` 再返回下载。**注意**：Laravel 的 `response()->download()` 不会自动删除临时文件，生产环境建议对该目录做定期清理（如 cron 每日删除 24 小时前的 `tenant_*.zip`），见[部署手册](deployment.md)。

## 二、数据清除（被遗忘权）

平台后台 → 租户列表 → 目标租户「清除数据」，前端有二次确认弹窗。

### 删除范围

| 表 | 处理 |
|----|------|
| `attachments` | 删除行 **并逐个删除私有磁盘物理文件** |
| `activities` | morph 子表，无 FK，先于业务表清除 |
| `opportunities` / `leads` / `contacts` / `companies` | 按子 → 父顺序删除 |
| `tenant_invitations` | 含被邀请人邮箱，属个人数据，一并删除 |

### 保留范围

| 数据 | 原因 |
|------|------|
| `audit_logs` | 合规留存（保留策略见[审计日志](audit.md)的 `crm:prune-audit-logs`） |
| `users` 账号 | 租户可继续付费使用；如需彻底删除成员，走团队成员管理移除 |
| `stages` / `tenant_webhooks` / `tenant_workflows` | 租户配置，清除数据后重建业务无需重配 |

清除完成后，操作本身会产生新的审计记录（谁在何时清了什么），保留可追溯性。

## 三、操作记录

两项操作均要求平台超管身份（`platform.admin` 中间件守卫），且清除动作的执行结果（各表删除行数）会以 flash 消息反馈给操作者。
