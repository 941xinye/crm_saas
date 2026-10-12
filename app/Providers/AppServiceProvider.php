<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Observers\WebhookObserver;
use App\Observers\WorkflowObserver;
use App\Services\LLM\LLMProvider;
use App\Services\LLM\OpenAICompatible;
use App\Services\Tenancy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tenancy::class);

        // LLM Provider：默认 OpenAI 兼容实现（安全降级）
        $this->app->bind(LLMProvider::class, function () {
            return new OpenAICompatible(
                apiKey: config('llm.api_key', ''),
                baseUrl: config('llm.base_url', 'https://api.openai.com/v1'),
                model: config('llm.model', 'gpt-4o-mini'),
                timeout: config('llm.timeout', 20),
            );
        });
    }

    public function boot(): void
    {
        // Webhook 观察者：5 个业务模型的 CRUD 事件自动推送
        Company::observe(WebhookObserver::class);
        Contact::observe(WebhookObserver::class);
        Lead::observe(WebhookObserver::class);
        Opportunity::observe(WebhookObserver::class);
        Activity::observe(WebhookObserver::class);

        // 工作流观察者：商机事件 → WorkflowEngine（created/updated/entered_stage）
        Opportunity::observe(WorkflowObserver::class);
    }
}
