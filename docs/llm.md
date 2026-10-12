# LLM 集成（Phase 4）

CRM 通过通用 LLM 适配器接入大语言模型服务，当前用于 **AI 跟进建议**（为商机生成跟进邮件草稿）。

---

## 一、架构

```
业务层（FollowUpSuggestionService）
        │  依赖注入
        ▼
LLMProvider 接口（app/Services/LLM/LLMProvider.php）
        │  实现
        ▼
OpenAICompatible（app/Services/LLM/OpenAICompatible.php）
        │  HTTP POST {base_url}/chat/completions
        ▼
OpenAI / 阿里云百炼 / DeepSeek / 硅基流动 / Ollama 等兼容服务
```

**设计要点**：

| 要点 | 说明 |
|------|------|
| 接口解耦 | 业务层只依赖 `LLMProvider` 接口，换 provider 无需改业务代码 |
| 优雅降级 | 所有方法失败返回 `null` 而非抛异常，不阻塞主业务 |
| 单一能力 | `generate(prompt, systemRole, maxTokens)` 收 prompt 返字符串 |

---

## 二、配置

`config/llm.php`：

```php
return [
    'api_key' => env('LLM_API_KEY', ''),
    'base_url' => env('LLM_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('LLM_MODEL', 'gpt-4o-mini'),
    'timeout' => (int) env('LLM_TIMEOUT', 20),
];
```

### .env 示例（各服务商）

```ini
# OpenAI
LLM_BASE_URL=https://api.openai.com/v1
LLM_MODEL=gpt-4o-mini

# 阿里云百炼（DashScope 兼容模式）
LLM_BASE_URL=https://dashscope.aliyuncs.com/compatible-mode/v1
LLM_MODEL=qwen-plus

# DeepSeek
LLM_BASE_URL=https://api.deepseek.com/v1
LLM_MODEL=deepseek-chat

# 硅基流动
LLM_BASE_URL=https://api.siliconflow.cn/v1
LLM_MODEL=Qwen/Qwen2.5-7B-Instruct

# 本地 Ollama（OpenAI 兼容端点）
LLM_BASE_URL=http://127.0.0.1:11434/v1
LLM_MODEL=qwen2.5:7b
LLM_API_KEY=ollama   # Ollama 不校验但需非空

# 通用
LLM_API_KEY=sk-xxxxxxxx
LLM_TIMEOUT=20
```

---

## 三、降级策略

`configured()` 检测 `LLM_API_KEY` 是否非空：

| 场景 | 行为 |
|------|------|
| 未配置 Key | `FollowUpSuggestionService::suggest()` 返回 `null`，前端显示「AI 功能未启用」提示 |
| HTTP 非 2xx | 记录 warning 日志（含 status/body），返回 `null` |
| 网络异常 / 超时 | try-catch 捕获，记录 warning 日志，返回 `null` |
| 响应格式异常 | `data_get` 取不到 `choices.0.message.content`，返回 `null` |

**本地开发 / CI 无需 LLM 账户**，所有 AI 功能安全跳过。

---

## 四、AI 跟进建议

### 入口

商机详情页「AI 生成跟进建议」按钮（[app/Livewire/Opportunities/Show.php](../app/Livewire/Opportunities/Show.php) `generateFollowUp()`）。

### 套餐限制

功能 key：`ai_followup`，**专业版及以上**开放（见 [config/plans.php](../config/plans.php)），免费版点击显示升级提示。

### 安全保障

| 机制 | 实现 |
|------|------|
| 限流 | `RateLimiter` 每用户 10 次/分钟，超限返回 `null` 并记录 warning |
| prompt 截断 | 商机标题/活动内容取前 120 字符，活动列表仅取最近 5 条 |
| 静默异常 | 所有 LLM 异常不冒泡，不影响商机页面正常渲染 |

### Prompt 结构

发给模型的内容包含：商机标题、公司名、联系人、当前阶段、金额、预计成交日、最近 5 条活动摘要。System role 要求输出纯文本邮件（第一行主题、空行后正文），无 markdown。

### 响应解析

第一行 → `subject`，其余 → `body`。用户可一键「保存到跟进时间线」（写入 Activity，type=email）。

---

## 五、AI 活动时间线摘要（Phase 6 / P2）

### 入口

公司 / 联系人 / 商机详情页「AI 活动摘要」卡片（[app/Livewire/ActivitySummary.php](../app/Livewire/ActivitySummary.php) `generate()`），
点击生成 ≤5 条要点的中文总结，服务层为 [ActivitySummaryService](../app/Services/ActivitySummaryService.php)。

### 安全保障

| 机制 | 实现 |
|------|------|
| 限流 | `RateLimiter` 每用户 10 次/分钟（`ai-summary-{uid}`），**缓存命中不消耗额度** |
| 缓存 | key = `crm:ai-summary:{实体}:{id}:{最新活动ID}`，新增活动自动失效（1 小时兜底 TTL） |
| prompt 截断 | 最近 20 条活动、单条 150 字符 |
| 静默降级 | 未配置 / 无活动 / 限流 / 失败均返回 `null`，卡片提示不可用 |
| 白名单 | 组件仅接受 Company / Contact / Opportunity 三类实体，find 走 TenantScope |

---

## 六、扩展新 Provider

1. 新建类实现 `App\Services\LLM\LLMProvider` 接口
2. 在 `AppServiceProvider` 中按配置绑定：

```php
$this->app->bind(LLMProvider::class, function () {
    // 例如根据 config('llm.provider') 分发到不同实现
    return new OpenAICompatible(
        config('llm.api_key'),
        config('llm.base_url'),
        config('llm.model'),
        config('llm.timeout'),
    );
});
```

当前默认绑定 `OpenAICompatible`（覆盖所有 OpenAI 兼容服务）。
