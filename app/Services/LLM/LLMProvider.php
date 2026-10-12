<?php

namespace App\Services\LLM;

/**
 * LLM Provider 接口：所有 AI 后端（OpenAI / 阿里云百炼 / 本地 Ollama）实现此接口。
 *
 * 设计要点：
 * - 与业务解耦：业务层不关心底层 provider
 * - 强制 graceful degradation：所有方法返回 null 而非抛异常
 * - 单一能力：generate() 接收 prompt，返回字符串
 */
interface LLMProvider
{
    /**
     * 是否配置好了密钥（安全降级检测）。
     */
    public function configured(): bool;

    /**
     * 生成回复。
     *
     * @param  string  $systemRole  系统角色提示（可选）
     * @param  string  $prompt      实际 prompt
     * @param  int     $maxTokens   输出上限
     * @return string|null           失败返回 null（不抛异常）
     */
    public function generate(string $prompt, string $systemRole = '', int $maxTokens = 400): ?string;
}
