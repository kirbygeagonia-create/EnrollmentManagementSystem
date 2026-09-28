<?php

namespace App\Http\Middleware;

use App\Models\Academicterms;
use App\Models\Students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
        // The term active today (by date range) — one shared source for every
        // term chip; replaces per-page hardcoded strings (audit 2026-09-25).
        // Guarded: un-migrated test envs have no tables yet (ExampleTest).
        $currentTerm = Schema::hasTable('academicterms')
            ? Academicterms::with('academicYear')
                ->whereDate('startDate', '<=', now())
                ->whereDate('endDate', '>=', now())
                ->first()
            : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? $request->user()->loadMissing(['office', 'unit', 'roles']) : null,
            ],
            'currentTerm' => $currentTerm
                ? "AY {$currentTerm->academicYear?->yearLabel} · {$currentTerm->semester->value} Semester"
                : null,
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
