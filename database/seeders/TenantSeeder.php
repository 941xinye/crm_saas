<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Tenant>
     */
    public function run()
    {
        $tenants = collect();

        // 1. Acme Corporation - pro
        $tenants->push(Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme',
            'plan' => 'pro',
            'is_active' => true,
            'settings' => ['currency' => 'USD', 'timezone' => 'America/New_York'],
        ]));

        // 2. Globex Industries - enterprise
        $tenants->push(Tenant::create([
            'name' => 'Globex Industries',
            'slug' => 'globex',
            'plan' => 'enterprise',
            'is_active' => true,
            'settings' => ['currency' => 'EUR', 'timezone' => 'Europe/Berlin'],
        ]));

        // 3. Initech Solutions - free (expired trial)
        $tenants->push(Tenant::create([
            'name' => 'Initech Solutions',
            'slug' => 'initech',
            'plan' => 'free',
            'is_active' => true,
            'trial_ends_at' => '2026-01-01',
            'settings' => ['currency' => 'USD', 'timezone' => 'America/Chicago'],
        ]));

        $this->command?->info("Created {$tenants->count()} tenants");

        return $tenants;
    }
}
