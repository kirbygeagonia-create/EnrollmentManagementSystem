<?php

/**
 * Read-only: has the school's day moved out from under the demo dataset?
 *
 * `tools/seed_full_demo_pipeline.php` dates its "today" rows with SchoolTime::today(), so between 16:00Z and
 * midnight the Accounting drawer asks for a day the data does not carry; after Manila crosses midnight the same
 * dataset is one school-day stale, and the E2E case that pins DEMO-OR-TODAY-01 fails with no code change (the
 * documented 2026-10-20 instance). This probe says which side of that line the box is on BEFORE a suite run is
 * read as a defect — the remedy for the stale case is a re-dump and a pipeline re-run, never softening the test.
 *
 *   php storage/app/scratch/lane72-probe-school-day.php
 */

// Imports belong above the bootstrap: PHP resolves an unqualified class name where it is COMPILED, and Pint's
// fully_qualified_strict_types files its own `use` block below the call, which parses, passes `pint --test`, and
// fatals at runtime. This file was in that state for one formatting pass — see lane73b-probe-import-order.php.
use App\Models\Payments;
use App\Support\SchoolTime;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$today = SchoolTime::today();
$receipt = Payments::where('orNumber', 'DEMO-OR-TODAY-01')->first();

printf("utc now:            %s\n", now()->toDateTimeString());
printf("school today:       %s   (zone %s)\n", (string) $today, SchoolTime::zone());
printf("DEMO-OR-TODAY-01:   %s\n", $receipt === null ? 'ABSENT' : $receipt->paymentDate->toDateString());
printf("held receipts dated the school's today: %d\n",
    Payments::held()->where('paymentDate', '>=', SchoolTime::startOfSchoolDay((string) $today))
        ->where('paymentDate', '<', SchoolTime::startOfNextSchoolDay((string) $today))->count());
printf("%s\n", $receipt !== null && $receipt->paymentDate->toDateString() === (string) $today
    ? "VERDICT: the dataset is dated with the school's CURRENT day; a suite run now measures the build."
    : "VERDICT: the dataset is NOT dated with the school's current day — re-dump live ems and re-run the pipeline\n"
        ."        before reading any E2E failure as a defect, and never soften the case to forgive a stale date.\n");

// Both sides of that sentence are computed from the same machine, so the verdict can only ever be about this box:
// the school's day and the dataset's day are read from one clock and compared to each other. Ask an outside host what
// UTC is before treating "the school's CURRENT day" as the world's — lane74-probe-clock-skew.php measures that, and
// on 2026-10-26 it printed the box 19 h 00 m ahead of api.github.com, which makes the civil day it declares one day
// ahead of the one a correctly-set machine would write.
echo "NOTE: the two dates above both come from this box's clock; run storage/app/scratch/lane74-probe-clock-skew.php\n"
    ."      for the difference between that clock and an outside one, and say which clock you mean when you quote a\n"
    ."      date from this probe.\n";
