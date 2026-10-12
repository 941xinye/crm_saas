<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create tenants
        $tenants = app(TenantSeeder::class)->run();

        // 2. Create platform super admin (tenant_id = null)
        User::factory()->create([
            'name'              => 'Platform Admin',
            'email'             => 'admin@platform.test',
            'password'          => Hash::make('password'),
            'tenant_id'         => null,
            'role'              => 'admin',
            'is_platform_admin' => true,
        ]);
        $this->command?->info('Created platform admin (admin@platform.test / password)');

        // 3. Per-tenant business data
        foreach ($tenants as $tenant) {
            $this->command?->newLine();
            $this->command?->info("=== Seeding tenant: {$tenant->name} ===");

            // Stages (no dependencies other than tenant)
            $stages = app(StageSeeder::class)->run(['tenant' => $tenant]);

            // Users (no dependencies other than tenant)
            $users = app(UserSeeder::class)->run(['tenant' => $tenant]);

            // Companies depend on users (owner)
            $companies = app(CompanySeeder::class)->run([
                'tenant' => $tenant,
                'users'  => $users,
            ]);

            // Contacts depend on companies
            $contacts = app(ContactSeeder::class)->run([
                'tenant'    => $tenant,
                'companies' => $companies,
            ]);

            // Leads depend on users (owner)
            app(LeadSeeder::class)->run([
                'tenant' => $tenant,
                'users'  => $users,
            ]);

            // Opportunities depend on stages + users + companies + contacts
            app(OpportunitySeeder::class)->run([
                'tenant'    => $tenant,
                'stages'    => $stages,
                'users'     => $users,
                'companies' => $companies,
                'contacts'  => $contacts,
            ]);

            // Activities depend on users + morph targets (queried directly)
            app(ActivitySeeder::class)->run([
                'tenant' => $tenant,
                'users'  => $users,
            ]);
        }

        $this->command?->newLine();
        $this->command?->info('🎉 Database seeding complete!');
    }
}
