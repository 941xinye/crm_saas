<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('probability')->comment('智能评分 0-100');
            $table->timestamp('scored_at')->nullable()->after('score')->comment('评分时间');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn(['score', 'scored_at']);
        });
    }
};
