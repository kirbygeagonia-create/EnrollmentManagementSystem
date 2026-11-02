<?php

namespace App\Http\Middleware;

use App\Support\AuditAct;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Name the act for every audit row one desk request writes (§28.3 G-37, prompt §9).
 *
 * `AuditLogObserver` watches every model, so left alone it records `created`, `updated` or `deleted` — a fact
 * about a row, not about what an officer did. One desk decision writes several rows at once: the record, the
 * workflow box behind it, the status history, the notice. Naming the act here, at the request that performs it,
 * stamps all of them with one word, and a plain edit that belongs to no named act keeps the event word.
 *
 * The scope is bracketed by `AuditAct::run()`, which restores the previous label in a `finally` — so a nested
 * act (an enrollment issued inside an intake approval) cannot leak its name outward, and an exception thrown
 * mid-act cannot leave the next request wearing it.
 *
 * It is `append`ed to the web group rather than attached route-by-route: the alternative is sixteen annotations
 * in `routes/web.php`, and an act route added later would simply be unnamed — invisible, not refused. Here a new
 * act is one line in `AuditAct::ROUTE_ACTS`, and `AuditActNamingTest` refuses a map entry whose route name no
 * longer resolves.
 *
 * Queued work runs in a worker with no request, so a notice written by a delayed job keeps the event word. That
 * is stated rather than engineered around: the act that caused it is on the same enrollment's audit rows.
 */
class NameAuditAct
{
    public function handle(Request $request, Closure $next): Response
    {
        $act = AuditAct::forRoute($request->route()?->getName());

        if ($act === null) {
            return $next($request);
        }

        return AuditAct::run($act, fn () => $next($request));
    }
}
