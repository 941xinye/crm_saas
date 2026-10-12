<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreign('tenant_id', 'stages_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
            $table->unsignedTinyInteger('probability')->default(0); // 0-100
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id']);
            $table->index(['tenant_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
