<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class OnlineDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Require explicit permission to populate this installation with demo records.
        if (! config('demo.seeding_enabled')) {
            throw new RuntimeException('Enable DEMO_SEEDING_ENABLED before running online demo setup.');
        }

        // Check every required password before starting the database setup.
        foreach ([
            'superadmin.password',
            'auth.initial_password',
            'demo.manager_password',
            'demo.volunteer_password',
        ] as $setting) {
            $password = config($setting);

            // Stop if a password is missing or contains only spaces.
            if (! is_string($password) || trim($password) === '') {
                throw new RuntimeException("Set {$setting} before running online demo setup.");
            }
        }

        // Run the existing seeders after all setup checks have passed.
        $this->call(DatabaseSeeder::class);
    }
}
