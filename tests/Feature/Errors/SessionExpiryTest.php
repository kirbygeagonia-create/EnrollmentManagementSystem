<?php

namespace Tests\Feature\Errors;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * What a desk sees when their session went away while the tab stayed open.
 *
 * The framework default is a dead-end 419 page: it does not say the save did not happen, and it
 * drops the person on a page with no way back to the record they were on. The renderer registered
 * in bootstrap/app.php sends them back instead, with a warning that names both facts — nothing was
 * saved, and nothing already saved was lost.
 *
 * CSRF cannot be exercised through the middleware in a test (VerifyCsrfToken steps aside when the
 * app is running unit tests), so these register a route that throws the same exception the
 * middleware throws, which reaches the same renderer.
 */
class SessionExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RouteFacade::middleware('web')->post('__test/session-expired', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        RouteFacade::middleware('web')->get('__test/session-expired', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
    }

    public function test_a_desk_that_lost_its_session_is_sent_back_with_an_explanation(): void
    {
        $this->post('/__test/session-expired', [], ['Referer' => '/registrar/517'])
            ->assertRedirect('/registrar/517');

        // Read the flash the same way the next request will, rather than through assertSessionHas:
        // after a redirect assertion the test store has already been replaced, and the helper then
        // reports a key that is provably there (the next test reads it).
        $this->assertNotNull(session('warning'));
    }

    public function test_the_explanation_names_both_facts_the_desk_needs(): void
    {
        $this->post('/__test/session-expired', [], ['Referer' => '/registrar']);

        $warning = session('warning');

        // "Your request failed" tells a person nothing. The message has to say the save did not
        // happen, and that what they had already saved is intact — the second half is what stops
        // someone re-entering a whole form out of worry.
        $this->assertStringContainsString('was not saved', $warning);
        $this->assertStringContainsString('Nothing you had already saved was lost', $warning);
        $this->assertStringContainsString('sign in again', $warning);
    }

    public function test_a_request_with_no_page_to_return_to_falls_back_to_the_dashboard(): void
    {
        $this->post('/__test/session-expired')
            ->assertRedirect('/dashboard');
    }

    public function test_a_json_caller_still_gets_a_status_code_rather_than_a_redirect(): void
    {
        $this->postJson('/__test/session-expired')
            ->assertStatus(419)
            ->assertJsonPath('message', 'Your session expired, so this was not saved. Sign in again and try once more.');
    }

    public function test_a_browsing_visit_is_left_on_the_explanation_page(): void
    {
        // Nothing was submitted, so nothing was lost. Sending a GET back with a warning would tell
        // the person about a save that never happened.
        $this->get('/__test/session-expired')
            ->assertStatus(419);

        $this->assertArrayNotHasKey('warning', session()->all());
    }
}
