# 本地环境搭建

## 一、环境要求

| 组件 | 最低版本 | 推荐 |
|------|----------|------|
| PHP | 8.3 | 8.3+ |
| MySQL | 8.0 | 8.4 |
| Composer | 2.2 | 2.8+ |
| Node.js | 18+ | 20+ |

> Windows 下推荐用 **ServBay** 一键安装 PHP / MySQL / Nginx / Redis / Composer，避免手动配环境。

---

## 二、ServBay 环境准备

### 2.1 确认 PHP 版本

ServBay 自带多版本 PHP，项目要求 8.3+：

```bash
php -v
# PHP 8.3.x (cli) ...
```

### 2.2 升级 Composer

ServBay 自带的 Composer 是 2.2 LTS，建议升到 2.8 以获得更好的依赖解析性能：

```bash
composer self-update --2.8
# 或指定版本
composer self-update --2.8.8
```

如果升级失败（权限问题），手动替换：

```bash
# 下载最新 phar
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

### 2.3 创建数据库

ServBay → MySQL → 打开 phpMyAdmin 或命令行：

```sql
CREATE DATABASE crm DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## 三、项目安装

```bash
git clone <your-repo> crm
cd crm
composer install
```

### 3.1 配置 `.env`

复制模板：
```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env` 关键字段：

```ini
# ---- 数据库 ----
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm
DB_USERNAME=root
DB_PASSWORD=你的MySQL密码（ServBay默认 root/123456 或空）

# ---- 应用 ----
APP_URL=http://crm.servbay.host
APP_ENV=local
APP_DEBUG=true

# ---- 邮件（可选，用 Mailpit 测试验证码）----
# MAIL_HOST=127.0.0.1
# MAIL_PORT=1025
```

### 3.2 迁移 + 填充

```bash
# 首次：清空重建 + 全量 Seeder
php artisan migrate:fresh --seed

# 之后增量迁移
php artisan migrate
```

Seeder 会创建：
- 3 个租户（Acme / Globex / Initech）
- 1 个平台超管
- 3 个租户 admin
- 每个租户随机公司、联系人、线索、商机、活动

---

## 四、ServBay 添加网站

1. 打开 **ServBay** 应用 → **网站** → **添加**
2. 填写：
   - **域名**：`crm.servbay.host`
   - **根目录**：`e:\Work\多租户SaaS平台(Laravel)\public`  ← ⚠️ 指向 public 目录！
   - **PHP 版本**：8.3+
   - **HTTPS**：可先关，后期开
3. 点击保存，ServBay 自动配 Nginx 虚拟主机
4. hosts 文件自动写入 `127.0.0.1 crm.servbay.host`

浏览器访问 `http://crm.servbay.host` → 看到登录页 ✅

---

## 五、Laravel 常用命令

```bash
# 开发/维护
php artisan serve                          # 快速起开发服务器（绕过 Nginx）
php artisan dev                            # Laravel 13 内置热更新

# 迁移
php artisan migrate                        # 增量迁移
php artisan migrate:fresh --seed           # 清空重建 + 填充数据
php artisan migrate:rollback               # 回滚最后一批

# 缓存/优化
php artisan optimize:clear                 # 清全部缓存（debug 后必用）
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Tinker（调试时 Tenancy->id() = null，Scope 逃生舱开启）
php artisan tinker
# >>> Company::count();    # 查所有租户数据
# >>> app(Tenancy::class)->id();  # null
# >>> app(Tenancy::class)->setTenant(Tenant::first());
# >>> Company::count();    # 只查第一个租户

# 队列（生产用）
php artisan queue:work
```

---

## 六、排错指南

### 问题 1：Composer 版本太旧

```
Your Composer dependencies require a Composer version >= 2.8.0
```

**解决**：
```bash
composer self-update --2.8
```

### 问题 2：MySQL root 密码不对

```
SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'
```

**解决**：ServBay 面板 → MySQL → 重置 root 密码，然后更新 `.env` 的 `DB_PASSWORD`。

### 问题 3：Tenancy singleton 忘记注册

现象：Seeder 能跑通，但 HTTP 请求里 `TenantScope` 始终不生效（`id()` 为 null）。

**解决**：确认 `app/Providers/AppServiceProvider.php::register()` 有：
```php
$this->app->singleton(Tenancy::class);
```
> 注意是 `singleton` 不是 `bind`！每次 `bind` 都是新实例。

### 问题 4：Scope 逃生舱不生效

现象：`php artisan tinker` 里查不到数据，或者 Seeder 创建数据时报 SQL 错误。

**解决**：确认 `TenantScope::apply()` 里的逃生舱逻辑：
```php
if ($tenancy->id() === null) {
    return;  // ← 这行不能丢
}
```

### 问题 5：中间件未在 bootstrap/app.php 注册

现象：登录后查询仍不隔离（Tenancy 未初始化）。

**解决**：`bootstrap/app.php` 必须有：
```php
$middleware->web(append: [
    \App\Http\Middleware\BootstrapTenancy::class,
]);
```

需要手动加 `ensure.has.tenant` 的路由组：
```php
Route::middleware(['auth', 'ensure.has.tenant'])->group(function () { ... });

Route::middleware(['auth', 'platform.admin'])->prefix('platform')->name('platform.')->group(function () { ... });
```

### 问题 6：Nginx 返回 404 / 500

现象：浏览器打开显示 404，或 Laravel 返回 500。

**解决**：
1. 确认网站根目录指向的是 `public/` 而不是项目根
2. ServBay → 网站 → 你的站点 → 重建 Nginx 配置
3. `storage/` 和 `bootstrap/cache/` 目录权限：
   ```bash
   chmod -R 775 storage bootstrap/cache
   # Windows ServBay 下一般无需手动改
   ```

### 问题 7：登录后立即 403

现象：租户账号登录成功但被重定向到 403 页面。

**解决**：检查该用户是否有 `tenant_id`。如果 Seeder 没跑到或用户是手动建的：
```bash
php artisan tinker
>>> User::where('email', 'admin@acme.test')->first()?->tenant_id;
>>> Tenant::where('slug', 'acme')->first();
```

### 终极武器：清缓存！

每次改完中间件 / ServiceProvider / .env 后：
```bash
php artisan optimize:clear
```

Laravel 会缓存 bootstrap 文件，不清的话改了等于没改。
