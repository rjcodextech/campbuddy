<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * There is deliberately no public sign-up: admin accounts are created with
 * `php artisan db:seed --class=AdminUserSeeder`. This guards against the
 * Breeze registration routes ever being switched back on.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_registration_screen(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_self_signup_cannot_create_an_account(): void
    {
        $this->post('/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'password', 'password_confirmation' => 'password']);
        $this->post('/admin/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'password', 'password_confirmation' => 'password']);

        $this->assertDatabaseMissing('users', ['email' => 'eve@example.com']);
        $this->assertGuest();
    }
}
