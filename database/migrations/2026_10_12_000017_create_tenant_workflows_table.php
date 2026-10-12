<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 租户自动化工作流规则表：规则由租户在 UI 自行配置（替代/叠加 config/workflows.php）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('model', 191)->comment('目标模型类 FQCN，如 App\Models\Opportunity');
            $table->string('trigger', 32)->comment('触发事件：created/updated/entered_stage/stalled');
            $table->json('conditions')->nullable()->comment('条件列表 [[field, operator, expected], ...]，AND 逻辑');
            $table->json('actions')->comment('动作列表 [{type, params}, ...]，顺序执行');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0)->comment('越大越先执行');
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_workflows');
    }
};
