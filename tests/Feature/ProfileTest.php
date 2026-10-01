<?php

namespace Tests\Feature;

use App\Models\Staffusers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_password_can_be_changed_from_the_profile_screen(): void
    {
        $user = Staffusers::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'a-new-phrase-2026',
                'password_confirmation' => 'a-new-phrase-2026',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $fresh = $user->fresh();

        $this->assertTrue(Hash::check('a-new-phrase-2026', $fresh->passwordHash));
        $this->assertFalse(Hash::check('password', $fresh->passwordHash));
    }

    public function test_the_current_password_is_required_to_change_it(): void
    {
        $user = Staffusers::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'not-my-password',
                'password' => 'a-new-phrase-2026',
                'password_confirmation' => 'a-new-phrase-2026',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('password', $user->fresh()->passwordHash));
    }

    public function test_a_password_that_is_too_short_or_unconfirmed_is_refused(): void
    {
        $user = Staffusers::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'a-new-phrase-2026',
                'password_confirmation' => 'a-different-phrase',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->passwordHash));
    }
}
