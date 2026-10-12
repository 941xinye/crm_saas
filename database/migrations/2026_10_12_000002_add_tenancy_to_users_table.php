<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id', 'users_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->enum('role', ['admin', 'manager', 'sales'])->default('sales')->after('tenant_id');
            $table->boolean('is_platform_admin')->default(false)->after('role');
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_tenant_id_foreign');
            $table->dropColumn(['tenant_id', 'role', 'is_platform_admin']);
        });
    }
};
