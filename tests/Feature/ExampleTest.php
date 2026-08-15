<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Guests hitting the root are redirected to the branded login page
     * (the legacy Welcome landing page was removed).
     */
    public function test_guests_are_redirected_to_login_from_the_root(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
