<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds roles and the initial content. It never creates an admin account:
 * run `php artisan app:create-super-admin` for that.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesSeeder::class, ContentSeeder::class]);
    }
}
