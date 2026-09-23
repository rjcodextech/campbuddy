<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The first admin login is created separately and deliberately, via
     * `php artisan db:seed --class=AdminUserSeeder` — never as a
     * side effect of a general `db:seed` run.
     */
    public function run(): void {}
}
