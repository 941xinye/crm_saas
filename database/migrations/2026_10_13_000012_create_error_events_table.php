<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 错误事件聚合表（P1）：5xx 异常按签名（异常类+文件+行号的 md5）聚合，
 * 供平台错误监控面板展示。平台级表，不做租户隔离（tenant_id 仅作来源标注）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_events', function (Blueprint $table) {
            $table->id();
            $table->string('signature')->unique();          // md5(类|文件|行)
            $table->string('type');                          // 异常类名
            $table->string('file');                          // 异常文件
            $table->unsignedInteger('line');                 // 异常行号
            $table->string('message', 1000);                 // 最新一次错误信息
            $table->unsignedBigInteger('tenant_id')->nullable()->index(); // 来源租户（console/queue 为 null）
            $table->json('context')->nullable();             // 首次上报的上下文
            $table->unsignedInteger('count')->default(1);    // 累计发生次数
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_events');
    }
};
