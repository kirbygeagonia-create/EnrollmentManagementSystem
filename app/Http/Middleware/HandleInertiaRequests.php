<?php

namespace App\Http\Middleware;

use App\Enums\OfficeId;
use App\Models\Academicterms;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\WorkflowService;
use App\Support\ReferenceDataSections;
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

        $staff = $request->user() instanceof Staffusers ? $request->user() : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? $request->user()->loadMissing(['office', 'unit', 'roles']) : null,
            ],
            'currentTerm' => $currentTerm
                ? "AY {$currentTerm->academicYear?->yearLabel} · {$currentTerm->semester->value} Semester"
                : null,
            // The workflow's own vocabulary, keyed by the office that signs each
            // box. The stepper prints these instead of offices.officeName, because
            // the phase a student is in is 'Department Evaluation' — the office
            // that signs it happens to be Guidance's box.
            'workflowPhases' => WorkflowService::stepLabels(),
            // A Registrar return always sends the record back to the Department
            // Evaluation box, so the tracker can mark that step as returned
            // instead of leaving it reading as an ordinary completed one.
            'workflowReturnOfficeId' => OfficeId::Guidance->value,
            // Frontend authorization flags — keeps the UI from offering
            // links/routes the current user cannot actually use (audit §2.2).
            'can' => [
                'studentsView' => $request->user()?->can('viewAny', Students::class) ?? false,
                // Reference-data catalogs are maintained by more than one desk now
                // (the Registrar owns the grade scale), so the launcher and the hub
                // ask these two flags instead of assuming the admin role.
                'refdataHub' => ReferenceDataSections::hubVisible($staff),
                'gradeScaleManage' => $staff?->checkPermissionTo('refdata.gradeScale.manage') ?? false,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
                // Three desk guards explain themselves under `info`; sharing it
                // keeps a refusal from reaching the screen as silence.
                'info' => $request->session()->get('info'),
            ],
        ];
    }
}
