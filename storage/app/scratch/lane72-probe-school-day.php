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
require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payments;
use App\Support\SchoolTime;

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
