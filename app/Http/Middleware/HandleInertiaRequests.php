<?php

namespace App\Http\Middleware;

use App\Models\Students;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? $request->user()->loadMissing(['office', 'unit', 'roles']) : null,
            ],
            // Frontend authorization flags — keeps the UI from offering
            // links/routes the current user cannot actually use (audit §2.2).
            'can' => [
                'studentsView' => $request->user()?->can('viewAny', Students::class) ?? false,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
