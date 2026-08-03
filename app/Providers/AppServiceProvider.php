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
