<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Activity>
     */
    public function run(array $params = [])
    {
        $tenant = $params['tenant'] ?? null;
        $users  = $params['users'] ?? collect();

        if (! $tenant) {
            $this->command?->error('ActivitySeeder requires a tenant parameter');
            return collect();
        }

        $activities = collect();

        // Get random morphed targets
        $leads       = Lead::withoutTenant()->where('tenant_id', $tenant->id)->get();
        $opportunities = Opportunity::withoutTenant()->where('tenant_id', $tenant->id)->get();
        $contacts    = Contact::withoutTenant()->where('tenant_id', $tenant->id)->get();

        $targetPool = [];

        $leads->shuffle()->take(7)->each(function ($item) use (&$targetPool) {
            $targetPool[] = ['type' => Lead::class, 'id' => $item->id];
        });
        $opportunities->shuffle()->take(7)->each(function ($item) use (&$targetPool) {
            $targetPool[] = ['type' => Opportunity::class, 'id' => $item->id];
        });
        $contacts->shuffle()->take(6)->each(function ($item) use (&$targetPool) {
            $targetPool[] = ['type' => Contact::class, 'id' => $item->id];
        });

        shuffle($targetPool);
        $targetPool = array_slice($targetPool, 0, 20);

        $types = ['call', 'email', 'meeting', 'note', 'task'];

        foreach ($targetPool as $target) {
            $type = fake()->randomElement($types);
            $activities->push(Activity::factory()->create([
                'tenant_id'        => $tenant->id,
                'activitable_type' => $target['type'],
                'activitable_id'   => $target['id'],
                'user_id'          => $users->random()->id,
                'type'             => $type,
                'scheduled_at'     => Carbon::now()->subDays(fake()->randomElement([-7, -30, 0, 7, 14, 21])),
                'completed_at'     => fake()->optional(0.6)->dateTime(),
            ]));
        }

        $this->command?->info("Created {$activities->count()} activities for tenant [{$tenant->name}]");

        return $activities;
    }
}
