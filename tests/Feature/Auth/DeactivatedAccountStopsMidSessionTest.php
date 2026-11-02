<?php

namespace Tests\Feature\Auth;

use App\Enums\StaffStatus;
use App\Models\Staffusers;
use App\Support\DenialReason;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A deactivated account stops at its next request (§28.3 G-42's second half, prompt §9's user accounts).
 *
 * The login refusal was built first: an inactive account cannot start a session. What it left open is the desk that
 * was already signed in when an administrator switched it off — nothing else in the tree read `staffusers.status`
 * (measured by grepping every policy, the auth path and the providers), so that browser kept acting for the life of
 * its cookie. `RejectDeactivatedAccount` closes that, and these cases are directional on purpose: each one asks the
 * SAME account and the SAME route on both sides of the flip, because a case that only ever shows the refusal would
 * also pass if the middleware refused every request, which is the same failure as not refusing any.
 *
 * The status is read from the account, never from its role: prompt §9 asks that the two be kept distinct, and an
 * account may hold every right in the catalogue and still be closed.
 */
class DeactivatedAccountStopsMidSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    private function staff(string $role = 'Staff'): Staffusers
    {
        $account = Staffusers::factory()->make([
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'user'.uniqid(),
            'email' => 'user'.uniqid().'@example.com',
            'role' => 'staff',
            'officeId' => null,
        ]);
        unset($account->remember_token);
        $account->save();

        $account->syncRoles([$role]);

        return $account;
    }

    #[Test]
    public function an_account_signed_in_while_active_is_refused_at_its_next_request_once_it_is_deactivated(): void
    {
        $actor = $this->staff('SysAdmin');
        $target = $this->staff();

        // Before: the identical request is carried out. Without this half the case below could not tell a working
        // guard from one that refuses everything.
        $this->actingAs($actor)
            ->post(route('admin.users.status.toggle', $target))
            ->assertRedirect();
        $this->assertSame(StaffStatus::Inactive, $target->refresh()->status,
            'the pre-flip request did not reach the controller, so the movement this case measures never happened');

        $actor->update(['status' => StaffStatus::Inactive]);

        $response = $this->actingAs($actor)
            ->post(route('admin.users.status.toggle', $target));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('username');

        // Nothing was carried out: the target still reads Inactive because the FIRST request put it there, and the
        // second would have toggled it back to Active. That is the assertion that makes this a test of the stop.
        $this->assertSame(StaffStatus::Inactive, $target->refresh()->status,
            'a deactivated account still performed the act — the request was carried out, not refused');
    }

    #[Test]
    public function the_refusal_signs_the_account_out_of_its_session(): void
    {
        $actor = $this->staff('RegistrarDesk');

        $this->actingAs($actor)->get(route('dashboard'))->assertOk();

        $actor->update(['status' => StaffStatus::Inactive]);

        $this->actingAs($actor)->get(route('dashboard'))->assertRedirect(route('login'));

        // What this case can prove is that the signed-in state ends. The store is invalidated as well — see the
        // middleware — but that half is hygiene rather than the guard: a later request carrying the old cookie is
        // re-read against the account and refused again, so a leg planted on `invalidate()` could not fail and is
        // withheld in the harness with that reason written down.
        $this->assertGuest('web',
            'the refusal left the account authenticated, so the next click from the same browser would act');
    }

    #[Test]
    public function the_refusal_names_the_rule_and_the_remedy_rather_than_the_mechanism(): void
    {
        $actor = $this->staff();
        $actor->update(['status' => StaffStatus::Inactive]);

        // The refusal has two surfaces, and this one is the typed one: a caller that asks for JSON gets the
        // sentence in {"message"}, the same pair DenialReason established for a policy denial. Reading the wording
        // here rather than out of the flashed form bag also refuses the case if the browser half ever loses it.
        $response = $this->actingAs($actor)->getJson(route('dashboard'));

        $response->assertStatus(403);
        $reason = (string) $response->json('message');
        $this->assertSame((string) $response->json('message'), DenialReason::deactivated(),
            'the mid-session refusal invented its own sentence instead of the one the login screen prints');
        $this->assertStringContainsString('deactivated', $reason,
            'the sentence a desk reads must name the state it is in, not the mechanism that refused');
    }

    #[Test]
    public function an_active_account_with_a_limited_role_still_reaches_its_own_desk(): void
    {
        // The mirror of the case above: the guard reads status, not role. An Instructor-scope account that is active
        // must not be swept up by a rule written for accounts that were closed.
        $actor = $this->staff('RegistrarDesk');

        $this->actingAs($actor)->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($actor);
    }

    #[Test]
    public function a_deactivated_account_can_still_sign_out_of_its_own_session(): void
    {
        $actor = $this->staff();
        $actor->update(['status' => StaffStatus::Inactive]);

        // The account must be able to leave cleanly. A refusal that also swallows logout would strand the session and
        // read as a broken application rather than as a closed account.
        $this->actingAs($actor)->post(route('logout'));

        $this->assertGuest('web');
    }

    #[Test]
    public function a_guest_is_untouched_by_the_guard(): void
    {
        // No status to ask. If this redirected, the login screen itself would be unreachable.
        $this->get(route('login'))->assertOk();
    }

    #[Test]
    public function one_sentence_answers_a_closed_account_at_both_doors(): void
    {
        // The login screen has carried this refusal since the gap was first worked, and the mid-session guard was
        // added later — which is exactly how two explanations of one state appear. There is no JavaScript or HTTP
        // way to compare them at runtime for a message that is only ever shown on a refusal, so the sources are
        // read: the login path must call the shared holder, and must not keep a literal copy of the sentence.
        $login = (string) file_get_contents(base_path('app/Http/Requests/Auth/LoginRequest.php'));

        $this->assertStringContainsString('DenialReason::deactivated()', $login,
            'the login screen spells the refusal again, so the two surfaces can drift apart');
        $this->assertStringNotContainsString(
            'Your staff account is currently deactivated',
            $login,
            'a literal copy of the sentence sits in the login path beside the shared one'
        );
        $this->assertStringContainsString(
            'Your staff account is currently deactivated',
            (string) file_get_contents(base_path('app/Support/DenialReason.php')),
            'the shared holder no longer holds it, so both assertions above pass over an absence'
        );
    }

    #[Test]
    public function the_guard_is_registered_in_the_web_group(): void
    {
        // Every case above is behavioural, and a middleware that exists but is never appended would pass all of them
        // only if the refusal had another author. This reads the wiring, which is where an unregistered guard hides.
        $kernel = (string) file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString('RejectDeactivatedAccount::class,', $kernel,
            'the class is in the tree but no request runs it, which is the state that reads as built');
    }
}
