<?php

namespace Database\Seeders;

use App\Models\Opportunity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OpportunitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Opportunity>
     */
    public function run(array $params = [])
    {
        $tenant    = $params['tenant'] ?? null;
        $stages    = $params['stages'] ?? collect();
        $users     = $params['users'] ?? collect();
        $companies = $params['companies'] ?? collect();
        $contacts  = $params['contacts'] ?? collect();

        if (! $tenant) {
            $this->command?->error('OpportunitySeeder requires a tenant parameter');
            return collect();
        }

        $opportunities = collect();

        // Sort stages by order, then distribute 12 opportunities across all 6 stages
        // Prioritize middle stages (more realistic pipeline distribution)
        $sortedStages = $stages->sortBy('order')->values();

        // Distribution: 2 New, 3 Qualified, 3 Proposal, 2 Negotiation, 1 Won, 1 Lost
        $distributions = [2, 3, 3, 2, 1, 1];

        foreach ($distributions as $stageIndex => $count) {
            $stage = $sortedStages[$stageIndex];

            for ($i = 0; $i < $count; $i++) {
                $opportunities->push(Opportunity::factory()->create([
                    'tenant_id'          => $tenant->id,
                    'stage_id'           => $stage->id,
                    'owner_id'           => $users->random()->id,
                    'company_id'         => $companies->random()->id,
                    'contact_id'         => $contacts->random()->id,
                    'probability'        => $stage->probability,
                    'value'              => fake()->randomFloat(2, 5000, 250000),
                    'expected_close_date'=> Carbon::now()->addDays(fake()->randomElement([15, 30, 45, 60, 90, 120, 180])),
                ]));
            }
        }

        $this->command?->info("Created {$opportunities->count()} opportunities for tenant [{$tenant->name}]");

        return $opportunities;
    }
}
