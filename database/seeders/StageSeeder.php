<?php

namespace Database\Seeders;

use App\Models\Stage;
use Illuminate\Database\Seeder;

class StageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Stage>
     */
    public function run(array $params = [])
    {
        $tenant = $params['tenant'] ?? null;

        if (! $tenant) {
            $this->command?->error('StageSeeder requires a tenant parameter');
            return collect();
        }

        $stages = collect();

        $defaultStages = [
            ['name' => 'New',         'order' => 10,  'probability' => 0],
            ['name' => 'Qualified',   'order' => 30,  'probability' => 25],
            ['name' => 'Proposal',    'order' => 50,  'probability' => 50],
            ['name' => 'Negotiation', 'order' => 70,  'probability' => 75],
            ['name' => 'Won',         'order' => 90,  'probability' => 100],
            ['name' => 'Lost',        'order' => 100, 'probability' => 0],
        ];

        foreach ($defaultStages as $index => $data) {
            $stages->push(Stage::create([
                'tenant_id'  => $tenant->id,
                'name'       => $data['name'],
                'order'      => $data['order'],
                'probability'=> $data['probability'],
                'is_default' => $index < 4, // first 4 are default
            ]));
        }

        $this->command?->info("Created {$stages->count()} stages for tenant [{$tenant->name}]");

        return $stages;
    }
}
