<?php

/**
 * Lane 47 probe — does a summary cloned from an already-paginated builder inherit the page offset?
 *
 * AdmissionController, AssessmentController and ClearanceController all write the same sentence in a comment:
 * "the summary tiles must describe the full queue, not just the current page (audit 2026-09-25)". Then they take
 * that summary from `(clone $query)` — but the clone happens *after* `$query->paginate(20)`, and Laravel's
 * paginate() calls forPage(), which sets limit/offset on $this in place
 * (vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php:3208). AccountingController is the one
 * desk that clones before paginating.
 *
 * This script asks the real server for both shapes and prints the SQL, so the claim is measured rather than
 * recalled. Nothing is written; live ems is only read.
 *
 * Usage: php storage/app/scratch/lane47-probe-offset-inheritance.php
 */

require __DIR__.'/../../../vendor/autoload.php';

$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admissions;
use App\Models\Enrollments;
use App\Models\Studentassessments;
use App\Models\Studentclearances;

$report = function (string $name, object $base, string $column, callable $aggregate): void {
    foreach ([1, 2, 3] as $page) {
        // The exact call each controller makes, with the page the desk is on.
        $query = (clone $base)->orderByDesc($column);
        $beforePaginate = $aggregate(clone $query);
        $query->paginate(20, ['*'], 'page', $page);
        $afterPaginate = $aggregate(clone $query);

        printf("%-11s page %d — %s\n", $name, $page, $afterPaginate['sql']);
        printf("          cloned BEFORE  paginate(): %s\n", json_encode($beforePaginate['value']));
        printf("          cloned AFTER   paginate(): %s   %s\n\n", json_encode($afterPaginate['value']),
            $afterPaginate['value'] == $beforePaginate['value']
                ? '(same)'
                : '<-- THE TILES ANSWER TO THE PAGE, NOT THE QUEUE');
    }
};

$report('clearance', Studentclearances::query(), 'studentClearanceId', function ($q) {
    $withLimit = (clone $q)->reorder()->selectRaw('overallStatus, count(*) as aggregate')->groupBy('overallStatus');

    return ['sql' => $withLimit->toRawSql(), 'value' => $withLimit->pluck('aggregate', 'overallStatus')->all()];
});

$report('admission', Admissions::query(), 'admissionId', function ($q) {
    $withLimit = (clone $q)->reorder()->selectRaw('admissionStatus, count(*) as aggregate')->groupBy('admissionStatus');

    return ['sql' => $withLimit->toRawSql(), 'value' => $withLimit->pluck('aggregate', 'admissionStatus')->all()];
});

$report('assessment', Studentassessments::query(), 'assessmentId', function ($q) {
    $withLimit = (clone $q)->reorder()->selectRaw('coalesce(sum(totalAssessedAmount),0) as total');

    return ['sql' => $withLimit->toRawSql(), 'value' => $withLimit->first()?->total];
});

printf("enrollments on ems: %d, clearances: %d, admissions: %d, assessments: %d — page 2 exists only where the set exceeds 20.\n",
    Enrollments::count(), Studentclearances::count(), Admissions::count(), Studentassessments::count());
