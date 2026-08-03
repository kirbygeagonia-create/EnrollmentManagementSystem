<?php

namespace Tests\Feature;

use App\Models\Staffusers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = Staffusers::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = Staffusers::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'firstName' => 'Test',
                'middleName' => 'M',
                'lastName' => 'User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test', $user->firstName);
        $this->assertSame('M', $user->middleName);
        $this->assertSame('User', $user->lastName);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_user_can_deactivate_their_account(): void
    {
        $user = Staffusers::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSame('inactive', $user->fresh()->status->value);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = Staffusers::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
