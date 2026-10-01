<?php

namespace App\Providers;

use App\Models\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Hydrate `config('settings.*')` from the `settings` table.
     *
     * The Admin → User Management → Settings screen writes that table and the
     * print templates read `config('settings.schoolName')`, so without this
     * bridge a Registrar edit silently changes nothing on a printed document.
     */
    public function boot(): void
    {
        foreach ($this->rows() as $key => $value) {
            config(["settings.{$key}" => $value]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function rows(): array
    {
        // The first deploy and the test suites run before the table exists.
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Settings::pluck('settingValue', 'settingKey')->all();
        } catch (QueryException) {
            return [];
        }
    }
}
