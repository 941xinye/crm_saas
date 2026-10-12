<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 审计日志服务：统一记录 create/update/delete 操作，支持批量操作的聚合。
 *
 * 设计原则（经验回溯教训）：
 * - 审计写入必须旁路化：try-catch 吞异常，绝不阻断主流程
 * - tenant_id 从被操作对象身上取，而非当前登录用户（兼容超管跨租户操作）
 * - 字段语义清晰：action 用枚举字符串，before/after 只存模型 fillable 字段
 * - 批量操作通过 withBatchId() 设置上下文，期间所有 record() 自动带同一个 batch_id
 */
class AuditService
{
    /**
     * 当前请求内的批量操作上下文 ID。
     * null 表示不在批量操作中。
     */
    private static ?string $currentBatchId = null;

    /**
     * 记录一条审计日志，任何异常被吞掉只写 Laravel log。
     *
     * 自动读取当前批量操作上下文（如果有），把 batch_id 带上。
     */
    public static function record(
        Model $auditable,
        string $action,
        ?array $before = null,
        ?array $after = null,
    ): void {
        try {
            $tenantId = static::resolveTenantId($auditable);

            AuditLog::forceCreate([
                'tenant_id' => $tenantId,
                'user_id' => static::currentUserId(),
                'action' => $action,
                'auditable_type' => get_class($auditable),
                'auditable_id' => $auditable->getKey(),
                'batch_id' => static::$currentBatchId,
                'before' => $before,
                'after' => $after,
                'ip' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                'route' => request()->route()?->getName(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('审计日志写入失败', [
                'action' => $action,
                'model' => get_class($auditable),
                'id' => $auditable->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 进入批量操作上下文：生成一个 batch_id，执行闭包，退出后清除。
     *
     * 闭包期间所有调用 record() 产生的日志都会带上这个 batch_id。
     * 同时会额外写一条汇总日志（action = "batch_{$action}"）。
     *
     * 用法：
     *   $summary = AuditService::withBatch('deleted', Company::class, function () use ($ids) {
     *       foreach ($ids as $id) {
     *           Company::find($id)?->delete();
     *       }
     *       return count($ids);
     *   });
     *
     * @param  string   $action       单条操作动作（deleted / updated / ...）
     * @param  string   $modelClass   被操作的模型类（用于汇总日志）
     * @param  callable $callback     批量操作闭包，返回处理的条数（int）或 null
     * @return mixed                  闭包的返回值
     */
    public static function withBatch(string $action, string $modelClass, callable $callback): mixed
    {
        $batchId = (string) Str::uuid();
        $previousBatchId = static::$currentBatchId;
        static::$currentBatchId = $batchId;

        $count = 0;
        $exception = null;

        try {
            $result = $callback();
            $count = is_int($result) || is_numeric($result) ? (int) $result : 0;
        } catch (\Throwable $e) {
            $exception = $e;
            // 即使业务逻辑抛异常，也尽量写汇总日志（记录部分成功的情况）
        } finally {
            // 恢复外层上下文（支持嵌套批量）
            static::$currentBatchId = $previousBatchId;
        }

        // 写汇总日志
        static::recordBatchSummary($batchId, $action, $modelClass, $count, $exception);

        if ($exception) {
            throw $exception;
        }

        return $result ?? $count;
    }

    /**
     * 手动设置/清除当前 batch_id 上下文（withBatch 已覆盖大部分场景，留作底层 API）。
     */
    public static function setCurrentBatchId(?string $batchId): void
    {
        static::$currentBatchId = $batchId;
    }

    public static function getCurrentBatchId(): ?string
    {
        return static::$currentBatchId;
    }

    /**
     * 写一条批量汇总日志。
     * 用 auditable_type 存模型类，auditable_id 存 0，action 为 batch_* 前缀。
     * before / after 存 JSON 摘要（操作条数、涉及的 ID 列表截断等）。
     */
    private static function recordBatchSummary(
        string $batchId,
        string $action,
        string $modelClass,
        int $count,
        ?\Throwable $exception = null,
    ): void {
        try {
            // 尝试取 tenant_id（如果模型有 tenant_id 字段，构造一个空实例取默认值不可靠；
            // 这里 fallback 到当前登录用户）
            $tenantId = static::resolveTenantIdFromClass($modelClass);

            $summary = [
                'count' => $count,
                'action' => $action,
                'status' => $exception ? 'partial' : 'success',
            ];
            if ($exception) {
                $summary['error'] = $exception->getMessage();
            }

            AuditLog::forceCreate([
                'tenant_id' => $tenantId,
                'user_id' => static::currentUserId(),
                'action' => 'batch_' . $action,
                'auditable_type' => $modelClass,
                'auditable_id' => 0,
                'batch_id' => $batchId,
                'before' => null,
                'after' => $summary,
                'ip' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                'route' => request()->route()?->getName(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('批量审计汇总日志写入失败', [
                'batch_id' => $batchId,
                'action' => $action,
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 从被操作对象取 tenant_id。
     * 超管跨租户操作时，当前用户 tenant_id 为 null/0，必须从目标对象取。
     */
    private static function resolveTenantId(Model $auditable): int
    {
        if (isset($auditable->tenant_id)) {
            return (int) $auditable->tenant_id;
        }

        // fallback：当前登录用户的 tenant
        $user = auth()->user();
        if ($user && ! empty($user->tenant_id)) {
            return (int) $user->tenant_id;
        }

        // 实在没有（比如系统内部操作），用平台 tenant 的 id 或抛异常
        throw new \RuntimeException('无法解析审计日志的 tenant_id');
    }

    /**
     * 从模型类名解析 tenant_id（用于批量汇总日志，此时没有具体模型实例）。
     */
    private static function resolveTenantIdFromClass(string $modelClass): int
    {
        $user = auth()->user();
        if ($user && ! empty($user->tenant_id)) {
            return (int) $user->tenant_id;
        }

        // 超管场景：没有当前 tenant，只能抛异常
        throw new \RuntimeException('无法解析批量审计日志的 tenant_id');
    }

    private static function currentUserId(): ?int
    {
        $user = auth()->user();
        return $user?->id;
    }
}
