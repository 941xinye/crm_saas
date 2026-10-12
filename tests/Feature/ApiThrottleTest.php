<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API 限流测试（P3）：
 * 认证端点 10 次/分钟/IP 超限返回 429。
 */
class ApiThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_ten_attempts(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // 第 11 次触发限流
        $this->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
