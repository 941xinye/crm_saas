<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantWebhookFactory extends Factory
{
    protected $model = TenantWebhook::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'url' => fake()->url() . '/callback',
            'secret' => 'whsec_' . bin2hex(random_bytes(16)),
            'events' => fake()->randomElements([
                'company.created', 'company.updated', 'company.deleted',
                'contact.created', 'lead.created',
                'opportunity.created', 'opportunity.updated',
            ], 2),
            'is_active' => true,
        ];
    }
}
