<?php

namespace App\Providers;

use App\Models\Auditlogs;
use App\Observers\AuditLogObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Audit §2.3: refuse to serve production traffic with debug mode on.
        // A production deploy that forgets APP_DEBUG=false would otherwise
        // render stack traces, file paths, and config to end users.
        if (app()->environment('production') && config('app.debug')) {
            throw new \RuntimeException(
                'APP_DEBUG must be false in production. Set APP_DEBUG=false in your .env before deploying.'
            );
        }

        // Attach the audit observer to every model (skip Auditlogs itself to avoid recursion).
        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! is_subclass_of($class, Model::class) || $class === Auditlogs::class) {
                continue;
            }

            $class::observe(AuditLogObserver::class);
        }
    }
}
