<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CSV 后台导入进度表（P1）：
 * 超过 async_threshold 行数的导入文件转入队列处理，本表记录
 * 状态与逐行进度，完成后结果写入并站内信通知发起人。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource');                      // 模型 FQCN
            $table->string('original_name');                 // 用户上传的文件名
            $table->string('path');                          // 私有磁盘相对路径 imports/{id}.csv
            $table->string('status')->default('pending');    // pending / processing / completed / failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();              // [行号 => 错误消息]
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_import_jobs');
    }
};
