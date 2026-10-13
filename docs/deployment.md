# 部署手册

从开发环境（ServBay）到生产服务器的完整上线流程。本地搭建见 [setup.md](setup.md)，运行时配置项汇总见 [config.md](config.md)。

## 一、服务器要求

| 组件 | 版本 | 用途 |
|------|------|------|
| PHP | 8.4+ | 扩展：pdo_mysql, mbstring, zip, gd（缩略图）, bcmath, intl |
| MySQL | 8.4+ | 业务数据 |
| Composer | 2.x | 依赖安装 |
| Node.js | 20+ | 前端构建（Vite） |
| Redis（可选） | 7+ | 缓存/队列升级项，默认 database 驱动即可运行 |

## 二、发布流程

```bash
# 1. 拉取代码
git clone <your-repo> /var/www/crm && cd /var/www/crm

# 2. 依赖与配置
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate

# 3. 前端构建
npm ci && npm run build

# 4. 数据库迁移 + 缓存
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 5. 目录权限
chown -R www-data:www-data storage bootstrap/cache
```

## 三、.env 生产要点

```ini
APP_ENV=production
APP_DEBUG=false            # 必须关闭，否则异常堆栈直接暴露
APP_URL=https://crm.example.com

QUEUE_CONNECTION=database  # 队列依赖：邮件/Stripe/队列化 Webhook

# --- 可选外部服务，全部支持无配置降级 ---
CRM_WEBHOOK_QUEUE=false    # 队列化 Webhook 推送（需先保证 worker 常驻）
CRM_SUBDOMAIN_ROOT=        # 如 crm.example.com，启用子域名租户识别（见 architecture.md 第七章）
CRM_ROBOT_WEBHOOK_URL=     # 群机器人通知（见 config.md）
CRM_ERROR_ALERTS=true           # 异常告警推送群机器人（Phase 7，见 monitoring.md）
CRM_QUEUE_FAILURE_NOTIFY=true   # 队列失败告警（见下文监控）

STRIPE_PUBLIC_KEY= / STRIPE_SECRET_KEY= / STRIPE_WEBHOOK_SECRET=   # Stripe 订阅（见 stripe.md）
LLM_API_KEY=               # AI 建议/摘要（见 llm.md）
MAIL_MAILER=smtp ...       # 邮箱验证与邮件提醒（见 config.md）
```

> 安全提醒：`.env` 含 APP_KEY 与全部密钥，**必须纳入服务器备份**；丢失/轮换 APP_KEY 将导致加密数据（2FA secret、租户白标 SMTP 密码等）不可解密——其中白标 SMTP 解密失败会自动降级平台默认邮件、不阻塞业务，但轮换前仍应评估影响（见 [white-label.md](white-label.md)）。

## 四、Web 服务器

### Nginx 参考

```nginx
server {
    listen 443 ssl http2;
    server_name crm.example.com;
    root /var/www/crm/public;          # 必须指向 public/
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

- 全站 HTTPS（Let's Encrypt / certbot），Stripe Checkout 与 2FA 均要求安全上下文
- 启用子域名租户识别时，需为 `*.crm.example.com` 配置泛域名证书与 server_name

## 五、常驻进程

### 1. 调度器（cron，必需）

五个定时任务全部经由 Laravel 调度器触发（[routes/console.php](../routes/console.php)）：

```cron
* * * * * cd /var/www/crm && php artisan schedule:run >> /dev/null 2>&1
```

| 任务 | 时刻 | 作用 |
|------|------|------|
| `crm:refresh-scores` | 每日 08:30 | 商机评分刷新 + 骤降提醒 |
| `crm:run-workflows` | 每日 08:45 | 停滞商机工作流扫描 |
| `crm:send-reminders` | 每日 09:00 | 五类业务提醒扫描 |
| `crm:health-check` | 每小时 | 队列失败告警 + 过期导出清理 |
| `crm:prune-audit-logs` | 每日 08:15 | 审计日志按保留策略清理 |

### 2. 队列 worker（Supervisor，生产必需）

```ini
[program:crm-worker]
command=php /var/www/crm/artisan queue:work --sleep=3 --tries=3 --timeout=60
numprocs=2
autostart=true
autorestart=true
stdout_logfile=/var/www/crm/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start crm-worker
```

部署新代码后必须 `php artisan queue:restart`，否则 worker 持旧代码。

## 六、备份与恢复

### 备份（每日凌晨，cron）

```bash
#!/bin/bash
# /etc/cron.d/crm-backup — 每日 02:30 备份数据库与附件
TS=$(date +%F)
mysqldump --single-transaction --routines -u<user> -p<pass> crm | gzip > /backup/db_${TS}.sql.gz
tar -czf /backup/attachments_${TS}.tar.gz -C /var/www/crm/storage/app private/   # 私有磁盘附件
cp /var/www/crm/.env /backup/env_${TS}.bak                                        # 含 APP_KEY 与密钥
find /backup -mtime +14 -delete                                                   # 保留 14 天
```

- **必须备份的三样**：数据库、`storage/app/private`（附件）、`.env`（APP_KEY）
- 生产附件磁盘若为 S3，直接开桶版本化/跨区复制
- 异地容灾：每日将 `/backup` 同步到对象存储（`rclone sync`）

### 恢复

```bash
gunzip < /backup/db_2026-10-12.sql.gz | mysql -u<user> -p crm
tar -xzf /backup/attachments_2026-10-12.tar.gz -C /var/www/crm/storage/app
```

恢复后检查：`php artisan queue:failed`（积压任务）、登录一次验证 `APP_KEY` 未变。

## 七、监控

| 项 | 方式 |
|----|------|
| 应用异常 | `ErrorReporter` 自动推送群机器人（5xx 告警 + 冷却去重，见 [monitoring.md](monitoring.md)） |
| 队列失败 | `crm:health-check`（每小时，站内信告警平台超管，24h 冷却去重） |
| 队列积压 | `php artisan queue:failed` 人工排查；`queue:retry all` 重试 |
| 过期导出 | `crm:health-check` 自动清理 `storage/app/tenant-exports` 超 24h 的 GDPR ZIP |
| 应用日志 | `storage/logs/laravel.log`（工作流/LLM/Webhook 降级异常在此旁路记录） |
| 进程 | Supervisor status / `systemctl status php8.4-fpm` |

## 八、上线检查清单

- [ ] `APP_ENV=production`、`APP_DEBUG=false`、`APP_URL` 正确
- [ ] `php artisan migrate --force` 已执行
- [ ] cron `schedule:run` 已配置且手动触发一次验证
- [ ] Supervisor worker 常驻；`CRM_WEBHOOK_QUEUE=true` 时必须先验证
- [ ] HTTPS 证书生效（含泛域名，如启用子域名识别）
- [ ] Stripe Webhook 端点已在后台注册（见 stripe.md）
- [ ] 邮件发送已配置（邮箱验证依赖，未配置时注册用户无法过验证）
- [ ] 备份 cron 运行一次并验证可恢复
- [ ] `php artisan health-check`（`crm:health-check`）手动跑一次确认输出「队列正常」
- [ ] `CRM_ERROR_ALERTS=true` 且 `CRM_ROBOT_WEBHOOK_URL` 可达（触发一次测试异常验证告警送达）
- [ ] 如有 API 变更，`php artisan api:generate-docs` 已重新生成并提交 `public/api/openapi.json`（`/api/docs` 可访问）
- [ ] 发布含前端静态资源变更时，`public/sw.js` 的 `CACHE_NAME` 已更新（否则老客户端继续命中旧缓存，见 [pwa.md](pwa.md)）
- [ ] `php artisan crm:benchmark` 记录一次上线基线（见第十节）

## 九、CI/CD（GitHub Actions）

`.github/workflows/ci.yml` 在每次 push / PR 时自动执行：composer install → 生成测试 `.env`（sqlite :memory:）→ `php artisan migrate:fresh` → `php artisan test` 全量回归。**合并前必须绿灯**，防止带病上线。

注意：CI 用 sqlite :memory: 跑测试，与生产 MySQL 存在方言差异；涉及 MySQL 特有语法的迁移（如函数索引），上线前应在预发环境手动执行 `php artisan migrate --force` 验证。

## 十、性能基准

上线前后各跑一次，记录基线并对比索引效果（详见 [performance.md](performance.md)）：

```bash
php artisan crm:benchmark                    # 表格输出，6 类核心查询路径
php artisan crm:benchmark --iterations=10    # 提高采样次数降低抖动
php artisan crm:benchmark --json             # JSON 输出，可脚本化留存对比
```
