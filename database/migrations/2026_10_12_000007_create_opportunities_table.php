<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreign('tenant_id', 'opportunities_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable();
            $table->foreign('contact_id', 'opportunities_contact_id_foreign')->references('id')->on('contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable();
            $table->foreign('company_id', 'opportunities_company_id_foreign')->references('id')->on('companies')->nullOnDelete();
            $table->foreignId('stage_id');
            $table->foreign('stage_id', 'opportunities_stage_id_foreign')->references('id')->on('stages')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable();
            $table->foreign('owner_id', 'opportunities_owner_id_foreign')->references('id')->on('users')->nullOnDelete();
            $table->string('title');
            $table->decimal('value', 14, 2)->default(0);
            $table->date('expected_close_date')->nullable();
            $table->unsignedTinyInteger('probability')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'stage_id', 'owner_id']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
