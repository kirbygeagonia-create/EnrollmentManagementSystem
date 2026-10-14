<?php

use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\PrintRendererMissing;
use App\Exceptions\WorkflowAlreadySignedException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Support\DenialReason;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    // Application::configure() turns on listener discovery by default, and discovery
    // registers every app/Listeners handle() as its own listener. app/Providers/
    // EventServiceProvider already maps the same listeners in $listen, so each event
    // was handled twice — one enrollment status change wrote two identical
    // notifications. Discovery stays off; $listen is the only wiring.
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeaders::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Spatie permission middleware aliases for route-level RBAC (build plan 2.2)
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Domain workflow exceptions (EnrollmentStateMachine / WorkflowService)
        // surface as friendly, actionable feedback instead of a raw 500 page.
        // Custom renderables take precedence over the debug/Ignition view, so
        // users get guided feedback in every environment. The exception is
        // still reported to the log for auditing.
        $exceptions->render(function (InvalidStateTransitionException $e, Request $request) {
            $friendly = 'This action can\'t be completed because the record isn\'t at the '
                .'expected stage of the enrollment workflow — it may have already been '
                .'processed by another office. Refresh the record and try again. '
                .'If the problem persists, contact the Registrar.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $friendly], 422);
            }

            return redirect()
                ->back()
                ->with('error', $friendly);
        });

        // A second signature on a closed box. Named in the card rather than left to the
        // generic stage message above, because the officer needs to know the box is not
        // theirs to change and that nothing they did rewrote the person before them.
        // No desk action re-opens a signed box today, so the card says what to hand over
        // instead of promising a button that does not exist.
        $exceptions->render(function (WorkflowAlreadySignedException $e, Request $request) {
            $d = $e->details;
            $friendly = "{$d['office']} already signed this enrollment — that box was closed by "
                ."{$d['signedBy']} on {$d['signedDate']}. Nothing on the record changed, and your "
                .'action did not replace that signature. If the name or date on that box is wrong, '
                .'note the enrollment number and give it to your office head: a signed box is '
                .'corrected by the Registrar with a system administrator, not at the desk. '
                .'Refresh the record to see the stage it is really at.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $friendly], 422);
            }

            return redirect()
                ->back()
                ->with('error', $friendly);
        });

        // A desk that leaves a tab open and comes back to it hits 419 on its next save. The default
        // is a dead-end page that does not say the save failed and drops the person away from the
        // record they were on, so for anything that was a submission this sends them back with a
        // warning naming both facts. A GET is left alone — nothing was lost, and the page explains
        // itself. Note the exception cannot be caught as TokenMismatchException: the framework's
        // prepareException() turns it into an HttpException(419) before any render callback runs.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->isMethodSafe()) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired, so this was not saved. Sign in again and try once more.',
                ], 419);
            }

            return redirect()
                ->back(fallback: '/dashboard')
                ->with('warning', 'Your session expired while you were away, so that was not saved. '
                    .'Nothing you had already saved was lost — sign in again and repeat the last step.');
        });

        // A refusal that said why. errors/403.blade.php now prints the reason for a desk that was sent to
        // the page, but a screen that pulls its own data has no page to land on: Exam/Create.jsx loads the
        // roster from /exam/students, which refuses another college's program by name (lane 26). Left as
        // it was, that refusal arrived as the 403 *HTML* inside a fetch(), res.json() threw, the catch
        // cleared the spinner and the screen showed its own "no candidates yet" copy — the opposite of the
        // truth. A caller that asked for JSON gets the reason instead and renders it where the roster was.
        // A denial with no reason — a bare policy refusal — returns null here and keeps the 403 page, so
        // nothing about the existing unauthorised responses changes.
        $exceptions->render(function (HttpException $e, Request $request) {
            $reason = $e->getStatusCode() === 403 && $request->expectsJson()
                ? DenialReason::of($e)
                : null;

            return $reason === null ? null : response()->json(['message' => $reason], 403);
        });

        // The last of the raw-database-error paths. Every constraint the application knows about
        // is guarded in code first, so reaching the database with a violation means something the
        // desk did that no rule predicted — a duplicate typed into a screen that never checked for
        // it, a row deleted underneath a form already open. The person cannot act on a SQL message
        // and a 500 page tells them the system broke rather than that the save did not happen, so
        // a submission is sent back with what to do instead. The exception is still logged in full
        // by the handler, and a page that was merely being read keeps the normal 500 screen.
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->isMethodSafe()) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'That could not be saved because it conflicts with information already on file. '
                        .'Reload the record and try again.',
                ], 409);
            }

            return redirect()
                ->back(fallback: '/dashboard')
                ->with('error', 'That could not be saved — it conflicts with information already on file, '
                    .'or the record changed while this screen was open. Nothing was written. Reload the '
                    .'record, repeat the step, and tell your office head if it happens again.');
        });

        // Every printed paper is drawn by shelling out to a real browser. On a server that has
        // none, the desk's download used to end on the generic 500 page, which reads as the
        // system having broken rather than as one dependency an office head can install — and a
        // download is a page navigation, so the refusal has to arrive back on the screen the
        // desk was standing on. This is the same check PrintFidelitySamples already makes on the
        // command line, now answering for the desks.
        $exceptions->render(function (PrintRendererMissing $e, Request $request) {
            $friendly = 'This paper could not be drawn because the server has no browser to draw it with, '
                .'so no file was produced. Nothing on the screen changed — the record is as it was saved. '
                .'Ask your office head to install Chrome or Edge on the server, or to point '
                .'EMS_CHROME_PATH at one, then download it again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $friendly], 503);
            }

            return redirect()
                ->back(fallback: '/dashboard')
                ->with('error', $friendly);
        });
    })->create();
