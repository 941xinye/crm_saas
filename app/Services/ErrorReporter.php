<?php

namespace App\Services;

use App\Models\ErrorEvent;
use App\Services\Tenancy;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * 错误监控告警（P0 生产化）：应用抛出 5xx 级异常时，向群机器人
 * （钉钉/企业微信/Slack）推送告警文本，并按错误签名做冷却去重；
 * 同时将异常按签名聚合写入 error_events 表（Phase 10），
 * 供平台错误监控面板（/platform/errors）查看历史与累计次数。
 *
 * 降级策略（graceful degradation）：
 * - 未配置 CRM_ROBOT_WEBHOOK_URL 或 CRM_ERROR_ALERTS=false 时静默跳过机器人推送；
 * - DB 聚合与机器人推送相互独立，一方失败不影响另一方；
 * - 4xx 类用户态异常（验证/认证/404 等）不告警不聚合；
 * - 同一签名冷却期内机器人只推送一次（DB 聚合不受冷却限制，每次都计数）；
 * - 任何失败仅记日志，绝不影响主请求流程。
 *
 * 可选升级路径：如需堆栈聚合/issue 管理，接入 Sentry SDK 后在
 * bootstrap/app.php 挂载其 Handler 即可，本服务与之不冲突（见 docs/monitoring.md）。
 */
class ErrorReporter
{
    /**
     * 不应触发告警的用户态异常（4xx 语义）。
     */
    private const IGNORED = [
        ValidationException::class,
        AuthenticationException::class,
        ModelNotFoundException::class,
        HttpResponseException::class,
        UnauthorizedException::class,
    ];

    public function report(\Throwable $e, array $context = []): void
    {
        try {
            if (! config('crm.error_alerts.enabled', true) || $this->isIgnored($e)) {
                return;
            }

            $signature = md5($e::class.'|'.$e->getFile().'|'.$e->getLine());

            // DB 聚合旁路化：写入失败不影响机器人推送（长驻场景 error_events 表可能不可用）
            try {
                $this->recordEvent($e, $signature, $context);
            } catch (\Throwable $recordError) {
                Log::warning('ErrorReporter: 错误聚合写入失败', ['error' => $recordError->getMessage()]);
            }

            $config = config('crm.reminder.robot', []);
            $url = $config['url'] ?? null;

            if (blank($url)) {
                return;
            }

            $cooldown = (int) config('crm.error_alerts.cooldown', 600);

            // 冷却去重：同一错误位置冷却期内机器人只推送一次
            if (! Cache::add("error-alert:{$signature}", true, $cooldown)) {
                return;
            }

            $text = $this->buildText($e, $context);
            $body = $this->buildBody($config['type'] ?? 'dingtalk', $text);

            if ($body === null) {
                return;
            }

            $response = Http::timeout(5)->asJson()->post($url, $body);

            if (! $response->successful()) {
                Log::warning('ErrorReporter: 告警推送失败', ['status' => $response->status()]);
            }
        } catch (\Throwable $reportError) {
            // 旁路化：告警自身失败绝不影响主流程
            Log::warning('ErrorReporter: 告警异常', ['error' => $reportError->getMessage()]);
        }
    }

    /**
     * 按签名聚合写库：已存在则 count+1 并刷新最近错误信息与时间，
     * 首次出现落全量明细（context 仅记录首次上报值）。
     */
    private function recordEvent(\Throwable $e, string $signature, array $context): void
    {
        $now = now();

        $updated = ErrorEvent::query()
            ->where('signature', $signature)
            ->update([
                'count' => DB::raw('count + 1'),
                'message' => mb_substr($e->getMessage(), 0, 1000),
                'last_seen_at' => $now,
            ]);

        if ($updated === 0) {
            ErrorEvent::query()->create([
                'signature' => $signature,
                'type' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => mb_substr($e->getMessage(), 0, 1000),
                'tenant_id' => app(Tenancy::class)->id(),
                'context' => $context ?: null,
                'count' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        }
    }

    private function isIgnored(\Throwable $e): bool
    {
        foreach (self::IGNORED as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        // 4xx HTTP 异常（404/405/419 等）属用户态错误，不告警
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return true;
        }

        return false;
    }

    private function buildText(\Throwable $e, array $context): string
    {
        $lines = [
            '【应用异常告警】',
            sprintf('异常：%s: %s', $e::class, mb_substr($e->getMessage(), 0, 300)),
            sprintf('位置：%s:%d', $e->getFile(), $e->getLine()),
        ];

        if (! app()->runningInConsole() && $request = request()) {
            $lines[] = sprintf('请求：%s %s', $request->method(), mb_substr((string) $request->fullUrl(), 0, 200));
            $lines[] = sprintf('用户：%s', auth()->id() ?? 'guest');
        } else {
            $lines[] = '来源：console/queue';
        }

        foreach ($context as $key => $value) {
            $lines[] = sprintf('%s：%s', $key, mb_substr((string) json_encode($value, JSON_UNESCAPED_UNICODE), 0, 200));
        }

        return implode("\n", $lines);
    }

    private function buildBody(string $type, string $text): ?array
    {
        return match ($type) {
            'dingtalk', 'wecom' => [
                'msgtype' => 'text',
                'text' => ['content' => $text],
            ],
            'slack' => ['text' => $text],
            default => null,
        };
    }
}
