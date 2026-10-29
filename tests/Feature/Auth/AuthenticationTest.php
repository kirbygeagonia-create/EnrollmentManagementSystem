<?php

namespace Tests\Feature\Auth;

use App\Models\Staffusers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = Staffusers::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = Staffusers::factory()->create();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = Staffusers::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_a_deactivated_account_is_refused_at_the_login_screen_though_its_password_is_correct(): void
    {
        $user = Staffusers::factory()->create(['status' => 'inactive']);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString(
            'deactivated',
            (string) session('errors')->first('username'),
            'the refusal must name the cause — an inactive account is not a wrong password'
        );
        $this->assertGuest();
    }

    public function test_the_same_account_authenticates_once_the_school_makes_it_active_again(): void
    {
        $user = Staffusers::factory()->create(['status' => 'inactive']);

        $this->post('/login', ['username' => $user->username, 'password' => 'password']);
        $this->assertGuest();

        $user->update(['status' => 'active']);

        $this->post('/login', ['username' => $user->username, 'password' => 'password']);

        // The login route answers a plain redirect, so the session assertion is the standalone one —
        // TestResponse::assertAuthenticatedAs() does not exist on a RedirectResponse.
        $this->assertAuthenticatedAs($user);
    }
}
