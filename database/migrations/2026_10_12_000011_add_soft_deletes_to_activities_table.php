<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 给 activities 表加软删除字段，与 Company/Contact/Lead/Opportunity 对齐。
 *
 * 活动记录（尤其已完成的通话/会议）属于业务历史，
 * 误删应当可恢复，不应硬删除丢失审计线索。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
