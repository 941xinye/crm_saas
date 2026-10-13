<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 错误事件聚合（P1）：同一签名（异常类+文件+行号）只保留一行，
 * count 累计发生次数、message 保留最近一次错误信息。
 * 平台级表，不挂 BelongsToTenant（无租户隔离，仅 tenant_id 标注来源）。
 */
class ErrorEvent extends Model
{
    protected $fillable = [
        'signature', 'type', 'file', 'line', 'message',
        'tenant_id', 'context', 'count', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'context' => 'array',
        'count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /** 签名：md5(异常类|文件|行号)，与 ErrorReporter 告警冷却共用同一口径 */
    public static function signatureFor(\Throwable $e): string
    {
        return md5($e::class.'|'.$e->getFile().'|'.$e->getLine());
    }

    /** 来源租户（仅标注用途，无外键约束；console/queue 来源为 null） */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
