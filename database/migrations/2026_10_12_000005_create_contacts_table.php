<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreign('tenant_id', 'contacts_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable();
            $table->foreign('company_id', 'contacts_company_id_foreign')->references('id')->on('companies')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
