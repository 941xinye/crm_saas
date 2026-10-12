<?php

namespace Database\Seeders;

use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Contact>
     */
    public function run(array $params = [])
    {
        $tenant    = $params['tenant'] ?? null;
        $companies = $params['companies'] ?? collect();

        if (! $tenant) {
            $this->command?->error('ContactSeeder requires a tenant parameter');
            return collect();
        }

        $contacts = collect();

        foreach ($companies as $company) {
            $count = fake()->numberBetween(2, 3);
            for ($i = 0; $i < $count; $i++) {
                $contacts->push(Contact::factory()->create([
                    'tenant_id'  => $tenant->id,
                    'company_id' => $company->id,
                ]));
            }
        }

        $this->command?->info("Created {$contacts->count()} contacts for tenant [{$tenant->name}]");

        return $contacts;
    }
}
