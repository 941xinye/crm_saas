# 多租户 CRM SaaS 平台

一个基于 Laravel 13 的多租户 CRM（客户关系管理）SaaS 平台，灵感来自 HubSpot 的简化版实现。

## 技术栈

| 分类 | 技术 |
|------|------|
| 后端框架 | Laravel 13.17 |
| PHP | 8.3+ |
| 前端驱动 | Livewire 4.4 + Volt 1.11 |
| 数据库 | MySQL 8.4 |
| UI | Tailwind CSS |
| 本地环境 | ServBay |
| 队列/缓存 | Laravel 默认（数据库/文件） |

## 功能清单

- **多租户隔离** — 单库 + `tenant_id` 全局 Scope，自动注入，console/Tinker 安全逃生舱
- **客户公司管理** — Company CRUD、行业分类、负责人分配
- **联系人** — Contact CRUD、关联公司、多联系人归属同一公司
- **线索（Leads）** — 潜在客户录入、来源追踪、状态流转
- **商机（Opportunities）** — 金额、预计成交日期、赢单概率、关联公司/联系人
- **销售漏斗（Kanban）** — 商机看板，按阶段拖拽实时移动
- **销售漏斗阶段** — Stage 可配置、有序排列
- **跟进活动** — Activity 多态关联（morphs），记录跟进历史
- **仪表板** — 公司数、线索数、商机总金额、本月新增、阶段分布、最近活动
- **平台超管后台** — 租户管理（新建/编辑/禁用/套餐变更）
- **租户设置** — 租户资料、套餐状态
- **团队成员管理** — 角色（admin / manager / sales）、负责人分配

## 快速开始

```bash
# 1. 克隆代码
git clone <your-repo> crm
cd crm

# 2. 安装依赖
composer install

# 3. 复制环境变量
cp .env.example .env
php artisan key:generate

# 4. 编辑 .env（重点项）
#   DB_DATABASE=crm
#   DB_USERNAME=root
#   DB_PASSWORD=你的密码
#   APP_URL=http://crm.servbay.host

# 5. 迁移并填充演示数据
php artisan migrate:fresh --seed

# 6. 在 ServBay 中添加网站
#    域名：crm.servbay.host
#    Root 指向：项目目录的 public/
#    PHP 版本：8.3+

# 7. 访问 http://crm.servbay.host
```

## 演示账号

| 角色 | 邮箱 | 密码 | 租户 |
|------|------|------|------|
| 平台超管 | `admin@platform.test` | `password` | — |
| 租户 admin | `admin@acme.test` | `password` | Acme Corporation（pro） |
| 租户 admin | `admin@globex.test` | `password` | Globex Industries（enterprise） |
| 租户 admin | `admin@initech.test` | `password` | Initech Solutions（free，trial 已过期） |

> 平台超管登录后进入 `/platform/tenants` 管理租户。
> 租户用户登录后自动进入自己的 CRM 仪表板，数据被严格隔离。

## 目录速览

```
app/
├── Http/
│   ├── Controllers/         # 传统控制器（项目主要用 Livewire）
│   └── Middleware/
│       ├── BootstrapTenancy.php       # auth 后初始化 Tenancy 服务
│       ├── EnsureUserHasTenant.php    # 检查用户是否绑定租户
│       └── EnsureUserIsPlatformAdmin.php  # 平台超管守卫
├── Livewire/
│   ├── Dashboard.php
│   ├── Companies/  Index / Form / Show
│   ├── Contacts/   Index / Form / Show
│   ├── Leads/      Index / Form / Show
│   ├── Opportunities/ Index / Form / Show / Board
│   ├── Activities/ Index / Form
│   ├── Platform/   Tenants.php
│   ├── Settings/   TenantProfile.php / TeamMembers.php
│   └── Auth/       Login / Register / ForgotPassword / ResetPassword
├── Models/
│   ├── Tenant.php           # 租户模型（**不**挂 BelongsToTenant）
│   ├── User.php             # 用户模型
│   ├── Company / Contact / Lead / Opportunity / Stage / Activity
│   ├── Scopes/TenantScope.php          # 全局 Scope 实现
│   └── Traits/BelongsToTenant.php      # 业务模型通用 Trait
├── Policies/                # RBAC 策略（每模型一个 Policy）
├── Providers/AppServiceProvider.php  # ⚠️ Tenancy 单例注册点
└── Services/Tenancy.php     # 租户上下文服务（必须 singleton！）

bootstrap/app.php             # 中间件链、路由注册
database/migrations/          # 所有迁移
database/seeders/             # 演示数据填充
routes/web.php                # Web 路由
```

## 架构一句话

**单库 + `tenant_id` 列 + 全局 `TenantScope` + `BelongsToTenant` Trait + `Tenancy` 单例服务。**

登录时中间件把当前用户注入 Tenancy，Tenancy 单例持有当前租户 ID，TenantScope 在每次查询自动附加 `WHERE tenant_id = ?`，业务模型通过 Trait 在创建时自动填 tenant_id。Seeder/Tinker 等 console 环境下 Tenancy::id() 为 null → Scope 逃生舱自动关闭 → 正常工作。

详细设计见 [docs/architecture.md](docs/architecture.md)。

## 更多文档

- [架构详解](docs/architecture.md)
- [数据库设计](docs/database.md)
- [本地环境搭建](docs/setup.md)

## License

MIT
