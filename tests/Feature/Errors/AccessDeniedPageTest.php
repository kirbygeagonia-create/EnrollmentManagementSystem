<?php

namespace Tests\Feature\Errors;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * What a desk reads when the system refuses it — and the difference between the two refusals.
 *
 * A bare policy denial says everything there is to say: the account has no right to this area, and the
 * 403 page's own copy carries that. A boundary that refused *one named record* holds the thing the desk
 * can act on ("the program you picked belongs to another college"), and until this batch it was thrown
 * away: errors/403.blade.php interpolated nothing, so every `abort(403, 'why')` in the application
 * arrived as a headless "Access denied". These tests pin both directions, and pin that the fix did not
 * quietly change a refusal that has no reason to give.
 */
class AccessDeniedPageTest extends TestCase
{
    private const REASON = 'Program “BSCE” belongs to another college; a department records its own.';

    protected function setUp(): void
    {
        parent::setUp();

        RouteFacade::middleware('web')->get('__test/denied-with-reason', function () {
            abort(403, self::REASON);
        });

        // What every `$this->authorize(...)` failure in the app actually throws.
        RouteFacade::middleware('web')->get('__test/denied-by-policy', function () {
            throw new AuthorizationException;
        });

        RouteFacade::middleware('web')->get('__test/denied-with-markup', function () {
            abort(403, 'Record <script>alert(1)</script> is not yours to open');
        });
    }

    public function test_a_refusal_that_named_the_reason_prints_it(): void
    {
        $this->get('/__test/denied-with-reason')
            ->assertForbidden()
            ->assertSee(self::REASON, false)
            ->assertSee('You don\'t have access to this page', false);
    }

    public function test_the_page_still_sends_the_desk_back_to_work(): void
    {
        // The reason is the new part, not the whole page: a desk that cannot open this area still needs
        // the way out and the name of who to ask, or it just sits on a dead end.
        $this->get('/__test/denied-with-reason')
            ->assertSee('Back to Dashboard')
            ->assertSee('contact your system administrator or your office head', false);
    }

    public function test_a_bare_policy_denial_keeps_the_generic_copy_and_prints_no_framework_placeholder(): void
    {
        $page = $this->get('/__test/denied-by-policy')
            ->assertForbidden()
            ->assertSee('Your account isn\'t authorized to view this area', false);

        // Laravel's own string is not a reason, and printing it would put developer text in front of an
        // officer who can do nothing with it.
        $page->assertDontSee('This action is unauthorized', false);
    }

    public function test_a_reason_is_escaped_not_injected(): void
    {
        // A reason is built from a record's own text (`$course?->courseCode`), so whatever a stored name
        // contains goes through this page. It must arrive as words.
        $this->get('/__test/denied-with-markup')
            ->assertForbidden()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_a_json_caller_that_asked_for_data_gets_the_reason_as_a_message(): void
    {
        // Exam/Create.jsx loads its roster over /exam/students, and the screen renders `message` where
        // the roster would have been. Without this the desk gets the HTML 403 page inside a fetch, the
        // JSON parse throws, and the refusal is hidden behind "no candidates yet".
        $this->getJson('/__test/denied-with-reason')
            ->assertForbidden()
            ->assertJsonPath('message', self::REASON);
    }

    public function test_a_json_caller_facing_a_reasonless_denial_is_left_exactly_as_it_was(): void
    {
        // The guard is deliberate: only a refusal that said why changes shape. A policy denial still
        // answers with the page, so no existing 403 response in the suite or at the desks moves.
        $this->getJson('/__test/denied-by-policy')
            ->assertForbidden()
            ->assertSee('You don\'t have access to this page', false);
    }
}
