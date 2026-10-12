# REST API 交互式文档（OpenAPI）

REST API v1 的机器可读规范与在线调试页面，端点定义与 [routes/api.php](../routes/api.php) 同步。

## 访问方式

| 入口 | 地址 | 说明 |
| --- | --- | --- |
| 交互式文档 | `GET /api/docs` | Swagger UI，公开访问，可在页面右上角 Authorize 填入 Token 后在线调试 |
| OpenAPI 规范 | `GET /api/openapi.json` | OpenAPI 3.0.3 JSON，供 SDK 生成器 / Postman / Apifox 导入 |
| 规范源文件 | `public/api/openapi.json` | 随仓库提交，CI 可校验 |

## 规范生成

规范由 Artisan 命令从 [GenerateOpenApiSpec](../app/Console/Commands/GenerateOpenApiSpec.php) 内的资源定义生成：

```bash
php artisan api:generate-docs            # 输出 public/api/openapi.json
php artisan api:generate-docs --output=storage/app/openapi.json   # 自定义输出位置
```

**API 变更后必须重新生成并提交规范文件**（新增端点/字段时同步修改命令中的 `RESOURCES` 映射）。

## 规范覆盖范围

- 认证：`/login`、`/register`（无需 Token）、`/logout`、`/user`
- 标准 CRUD：公司、联系人、线索、商机、活动、Webhook 配置（企业版）
- 只读发现：`/stages`、`/users`（集成方解析 stage_id / owner_id）
- 附件：列表 / multipart 上传 / 详情 / 文件流下载
- 邮件转线索：`POST /inbound-email/{tenantSlug}`（Inbound Parse 回调）

## 认证与多租户约定（规范中已声明）

- `securitySchemes.bearerAuth`：Sanctum Bearer Token，`Authorization: Bearer <token>`
- Token 能力：读操作需 `read`、写操作需 `write`（`token.scope` 中间件按 HTTP 方法校验）
- 所有数据自动限定于 Token 所属租户；跨租户资源一律 404（不暴露存在性）
- 列表响应统一为 `{status, data[], meta{total, per_page, current_page, last_page}}`；单资源为 `{status, data{}}`
- 分页参数：`page`、`per_page`（默认 15，上限 100）、`search`、`sort`、`direction`

## 测试

`tests/Feature/OpenApiDocsTest.php`（5 个用例）：规范文件有效性与关键端点覆盖、创建请求必填字段、`/api/openapi.json` 服务、`/api/docs` 页面渲染、生成命令可重复执行。
