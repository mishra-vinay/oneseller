<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local/demo data only. Production starts empty: create the admin with
     * `php artisan oneseller:create-admin`.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
