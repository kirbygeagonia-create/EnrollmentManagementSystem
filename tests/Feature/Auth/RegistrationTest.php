<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_is_disabled(): void
    {
        // Audit §2.1: public self-registration must not exist. Staff accounts
        // are created exclusively via Admin → User Management.
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseCount('staffusers', 0);
    }
}
