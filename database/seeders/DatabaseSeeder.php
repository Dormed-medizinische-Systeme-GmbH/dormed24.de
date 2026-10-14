<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
        ]);

        if (! app()->isProduction()) {
            // Admin-Zugang kommt aus der Env (ADMIN_EMAIL / ADMIN_PASSWORD), nicht aus dem Seeder.
            $this->call([
                CustomerSeeder::class,
                RatingSeeder::class,
            ]);
        }
    }
}
