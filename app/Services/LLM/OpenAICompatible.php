<?php

namespace App\Services\LLM;

use Illuminate\Support\Facades\Http;

/**
 * OpenAI 兼容 LLM Provider。
 * 支持官方 OpenAI + 所有兼容 OpenAI API 的服务（阿里云百炼 / Azure / DeepSeek / 硅基流动）。
 */
class OpenAICompatible implements LLMProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout = 20,
    ) {
    }

    public function configured(): bool
    {
        return filled($this->apiKey);
    }

    public function generate(string $prompt, string $systemRole = '', int $maxTokens = 400): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        $messages = [];
        if ($systemRole !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemRole];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => 0.7,
            ]);

            if (! $response->successful()) {
                logger()->warning('LLM 请求失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return data_get($response->json(), 'choices.0.message.content');
        } catch (\Throwable $e) {
            logger()->warning('LLM 连接异常', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
