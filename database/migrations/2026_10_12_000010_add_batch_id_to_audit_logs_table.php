<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 给 audit_logs 表加 batch_id (uuid)，把同一次批量操作产生的多条日志关联起来。
     * 批量汇总日志也会用同一个 batch_id，action 为 batch_* 前缀。
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('auditable_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
    }
};
