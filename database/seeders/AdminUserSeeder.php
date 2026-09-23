<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Creates the first /admin login. Reads ADMIN_EMAIL from the
     * environment if set; otherwise defaults to admin@campbuddy.test.
     * The password is always freshly generated and printed once — never
     * a hardcoded default credential.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@campbuddy.test');

        if (User::where('email', $email)->exists()) {
            $this->command->warn("Admin user {$email} already exists — skipping.");

            return;
        }

        $password = Str::password(20);

        User::create([
            'name' => 'CampBuddy Admin',
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin user created:');
        $this->command->line("  Email:    {$email}");
        $this->command->line("  Password: {$password}");
        $this->command->warn('Save this password now — it is not stored anywhere and will not be shown again.');
    }
}
