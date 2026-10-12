<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tenants 表新增 lead_form_key：公共 Web 表单（web-to-lead）的租户标识。
 * UUID 随机不可枚举；置 null 即停用表单，重置后旧链接立即失效。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->uuid('lead_form_key')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('lead_form_key');
        });
    }
};
