<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreign('tenant_id', 'companies_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable();
            $table->foreign('owner_id', 'companies_owner_id_foreign')->references('id')->on('users')->nullOnDelete();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
