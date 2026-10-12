<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreign('tenant_id', 'leads_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable();
            $table->foreign('owner_id', 'leads_owner_id_foreign')->references('id')->on('users')->nullOnDelete();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->enum('status', ['new', 'contacted', 'qualified', 'converted', 'lost'])->default('new');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'owner_id', 'status']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
