# PWA 支持

应用具备基本 PWA 能力：可安装到桌面/手机主屏、独立窗口运行、静态资源缓存加速、离线时显示友好提示页。

## 组成

| 文件 | 说明 |
|------|------|
| `public/manifest.webmanifest` | 应用清单：名称、图标、`start_url=/dashboard`、`display=standalone`、主题色 |
| `public/sw.js` | Service Worker（版本 `crm-static-v1`） |
| `public/pwa/icon.svg` / `icon-maskable.svg` | 应用图标（any / maskable 双用途） |
| `public/pwa/offline.html` | 离线回退提示页 |

布局 `layouts/app.blade.php` 已注入 `<link rel="manifest">`、`theme-color`、apple-touch-icon 与 SW 注册脚本（`navigator.register('/sw.js')`，失败静默）。

## 离线体验（Phase 10）

- **全局离线横幅**：布局内常驻 `#offline-banner`（amber 色，`hidden` 默认隐藏），JS 监听浏览器 `online` / `offline` 事件实时切换；断网时所有页面顶部显示「网络连接已断开」，恢复联网自动消失。文案走 `misc.offline_banner` 语言键
- **离线页**：页面导航请求断网时由 SW 回退 `public/pwa/offline.html`（见下方缓存策略）
- **决策说明——为什么不做完整离线写队列**：CRM 的写操作全部经 Livewire/API 落服务端并触发租户隔离校验、配额检查、审计日志与工作流，本地排队重放会破坏这些服务端不变量（重复创建、配额绕过、审计缺失）。因此离线能力收敛为「可读缓存 + 明确的不可用提示」，引导用户联网后再操作，而非在客户端模拟写入

## 缓存策略（sw.js）

- **静态资源**（css/js/svg/png/字体/ico）：cache-first，命中直接返回，首次成功响应写入缓存；
- **页面导航请求**：network-only，断网时回退离线提示页；
- **API / Livewire 数据请求**：完全不走缓存（避免离线看到过期业务数据）；
- **跨域请求**（CDN Tailwind、Sortable.js）：不处理，走浏览器默认行为；
- 旧版本缓存在新 SW activate 时自动清理（`skipWaiting + clients.claim`）。

## 安装使用

1. Chrome / Edge 访问站点，地址栏出现「安装」图标（或菜单 → 安装应用）；
2. 手机浏览器：菜单 → 添加到主屏幕；
3. 安装后以独立窗口启动，进入 `/dashboard`。

## 更新缓存版本

发布新静态资源后，将 `public/sw.js` 顶部的 `CACHE_NAME` 版本号递增（如 `crm-static-v2`），客户端 SW 更新时自动清空旧缓存。

## 测试

`tests/Feature/PwaTest.php`（5 项）：manifest 存在且字段有效、SW 与离线页存在（含关键策略）、图标存在、布局正确注入 PWA 标签、**全局离线横幅渲染**。
