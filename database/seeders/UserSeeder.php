<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function run(array $params = [])
    {
        $tenant = $params['tenant'] ?? null;

        if (! $tenant) {
            $this->command?->error('UserSeeder requires a tenant parameter');
            return collect();
        }

        $users = collect();
        $tenantSlug = $tenant->slug;

        // 1 admin
        $users->push(User::factory()->create([
            'name'     => "{$tenant->name} Admin",
            'email'    => "admin@{$tenantSlug}.test",
            'password' => Hash::make('password'),
            'tenant_id'=> $tenant->id,
            'role'     => 'admin',
        ]));

        // 1 manager
        $users->push(User::factory()->create([
            'name'     => "{$tenant->name} Manager",
            'email'    => "manager@{$tenantSlug}.test",
            'password' => Hash::make('password'),
            'tenant_id'=> $tenant->id,
            'role'     => 'manager',
        ]));

        // 2 sales
        $users->push(User::factory()->create([
            'name'     => "{$tenant->name} Sales One",
            'email'    => "sales1@{$tenantSlug}.test",
            'password' => Hash::make('password'),
            'tenant_id'=> $tenant->id,
            'role'     => 'sales',
        ]));

        $users->push(User::factory()->create([
            'name'     => "{$tenant->name} Sales Two",
            'email'    => "sales2@{$tenantSlug}.test",
            'password' => Hash::make('password'),
            'tenant_id'=> $tenant->id,
            'role'     => 'sales',
        ]));

        $this->command?->info("Created {$users->count()} users for tenant [{$tenant->name}]");

        return $users;
    }
}
