<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 给 stages 表加 is_won / is_lost 标记。
 *
 * 替代 ReportService 里"最后阶段视为赢单"的简化假设，
 * 让赢率统计可由租户自定义阶段精确控制。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->boolean('is_won')->default(false)->after('probability');
            $table->boolean('is_lost')->default(false)->after('is_won');
        });

        // 给现有数据回填：name 为 Won/Lost 的阶段设对应标记
        // 仅对已存在的阶段生效，Seeder 之后会显式设置
        \Illuminate\Support\Facades\DB::table('stages')
            ->where('name', 'Won')
            ->update(['is_won' => true]);
        \Illuminate\Support\Facades\DB::table('stages')
            ->where('name', 'Lost')
            ->update(['is_lost' => true]);
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropColumn(['is_won', 'is_lost']);
        });
    }
};
