<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 工作流条件匹配模式（Phase 6 / P2）：
 * condition_match = all（全部满足，默认，向后兼容）/ any（任一满足）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_workflows', function (Blueprint $table) {
            $table->string('condition_match', 8)->default('all')->after('conditions');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_workflows', function (Blueprint $table) {
            $table->dropColumn('condition_match');
        });
    }
};
