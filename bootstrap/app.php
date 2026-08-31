<?php

use App\Exceptions\InvalidStateTransitionException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
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
    })->create();
