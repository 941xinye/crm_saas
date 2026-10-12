<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-2 性能评估补充索引（详见 docs/performance.md「索引评估」）：
 * - activities.scheduled_at：提醒扫描（ReminderService）按时间窗全表扫描
 * - activities 多态列：活动时间线 / 待办按主体聚合
 * - opportunities (tenant_id, expected_close_date) / (tenant_id, created_at)：
 *   成交日提醒、报表月度聚合、列表默认排序
 * - audit_logs (tenant_id, created_at)：审计页按租户倒序分页的复合索引
 *
 * 部分环境可能已手工补建同名索引，用 hasIndex 保证幂等。
 */
return new class extends Migration
{
    private const INDEXES = [
        'activities' => [
            ['scheduled_at'],
            ['activitable_type', 'activitable_id'],
        ],
        'opportunities' => [
            ['tenant_id', 'expected_close_date'],
            ['tenant_id', 'created_at'],
        ],
        'audit_logs' => [
            ['tenant_id', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($table, $indexes) {
                foreach ($indexes as $columns) {
                    if (! Schema::hasIndex($table, $columns)) {
                        $t->index($columns);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($table, $indexes) {
                foreach ($indexes as $columns) {
                    if (Schema::hasIndex($table, $columns)) {
                        $t->dropIndex($columns);
                    }
                }
            });
        }
    }
};
