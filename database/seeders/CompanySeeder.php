<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Company>
     */
    public function run(array $params = [])
    {
        $tenant = $params['tenant'] ?? null;
        $users  = $params['users'] ?? collect();

        if (! $tenant) {
            $this->command?->error('CompanySeeder requires a tenant parameter');
            return collect();
        }

        $companies = collect();

        for ($i = 0; $i < 10; $i++) {
            $companies->push(Company::factory()->create([
                'tenant_id' => $tenant->id,
                'owner_id'  => $users->random()->id,
            ]));
        }

        $this->command?->info("Created {$companies->count()} companies for tenant [{$tenant->name}]");

        return $companies;
    }
}
