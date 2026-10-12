<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 自定义字段（Phase 6 / P1）：
 * - tenants.custom_fields：字段定义，按实体分组 {"company":[{key,label,type,required,options}],...}
 * - companies/contacts/opportunities.custom_fields：字段值 {"key": value}
 * 定义存租户表（TenantScope 隔离天然生效），值存业务表 JSON 列。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('settings');
        });

        foreach (['companies', 'contacts', 'opportunities'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('custom_fields')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });

        foreach (['companies', 'contacts', 'opportunities'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('custom_fields');
            });
        }
    }
};
