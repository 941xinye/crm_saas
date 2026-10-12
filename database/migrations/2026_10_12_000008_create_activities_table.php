<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreign('tenant_id', 'activities_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->morphs('activitable'); // activitable_type + activitable_id
            $table->foreignId('user_id');
            $table->foreign('user_id', 'activities_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('type', ['call', 'email', 'meeting', 'note', 'task']);
            $table->string('subject');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
