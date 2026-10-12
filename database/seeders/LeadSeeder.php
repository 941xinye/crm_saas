<?php

namespace Database\Seeders;

use App\Models\Lead;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Lead>
     */
    public function run(array $params = [])
    {
        $tenant = $params['tenant'] ?? null;
        $users  = $params['users'] ?? collect();

        if (! $tenant) {
            $this->command?->error('LeadSeeder requires a tenant parameter');
            return collect();
        }

        $leads = collect();

        // Distribute 15 leads across 5 statuses (3 each for a reasonable distribution)
        $statuses = ['new', 'contacted', 'qualified', 'converted', 'lost'];
        $statusIndex = 0;

        for ($i = 0; $i < 15; $i++) {
            // 3 leads per status
            if ($i % 3 === 0 && $statusIndex < count($statuses)) {
                $currentStatus = $statuses[$statusIndex++];
            }

            $leads->push(Lead::factory()->create([
                'tenant_id' => $tenant->id,
                'owner_id'  => $users->random()->id,
                'status'    => $currentStatus,
            ]));
        }

        $this->command?->info("Created {$leads->count()} leads for tenant [{$tenant->name}]");

        return $leads;
    }
}
