<?php

/**
 * Phase 4 — LLM 集成配置。
 *
 * 支持所有 OpenAI 兼容服务，只需修改 base_url / api_key / model：
 * - OpenAI:      https://api.openai.com/v1, model=gpt-4o-mini
 * - 阿里云百炼:  https://dashscope.aliyuncs.com/compatible-mode/v1, model=qwen-plus
 * - Azure:       https://{resource}.openai.azure.com/openai/deployments/{deployment}/chat/completions (需换 provider)
 * - DeepSeek:    https://api.deepseek.com/v1, model=deepseek-chat
 * - 硅基流动:    https://api.siliconflow.cn/v1, model=Qwen/Qwen2.5-7B-Instruct
 *
 * 安全降级：未配置 API Key 时，FollowUpSuggestionService::suggest() 返回 null，
 * 前端 Livewire 组件自动显示"AI 功能未启用"提示，不崩溃。
 */
return [
    'api_key' => env('LLM_API_KEY', ''),
    'base_url' => env('LLM_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('LLM_MODEL', 'gpt-4o-mini'),
    'timeout' => (int) env('LLM_TIMEOUT', 20),
];
