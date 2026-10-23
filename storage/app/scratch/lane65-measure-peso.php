<?php

/**
 * Lane 65 measurement — what the server answers for each peso figure the document states in the present
 * tense. Read-only SELECTs. Column names were read out of information_schema, not recalled: the first
 * draft of this probe asked `feetypes` for `feeAmount` and `studentassessments` for `totalAssessment`,
 * and neither column exists.
 */

// Imports above the bootstrap: see the note in lane65-peso-claims.php — Pint shortens the FQCN and files
// the `use` after the call, which passes `pint --test` and fatals at runtime.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo 'schema: '.DB::connection()->getDatabaseName()."\n\n--- feetypes catalogue (§18.3 quotes each unit/flat amount) ---\n";
foreach (DB::select('SELECT feeName, defaultAmount, unitBasis FROM feetypes ORDER BY feeTypeId') as $f) {
    printf("  %-40s %12s  %s\n", $f->feeName, number_format((float) $f->defaultAmount, 2), $f->unitBasis);
}

echo "\n--- distinct assessed totals on live ems ---\n";
foreach (DB::select('SELECT totalAssessedAmount a, COUNT(*) n FROM studentassessments GROUP BY totalAssessedAmount ORDER BY a') as $r) {
    printf("  totalAssessedAmount %14s  x%d\n", number_format((float) $r->a, 2), $r->n);
}

echo "\n--- receipts held (paid or partial), by amount ---\n";
$h = DB::select("SELECT SUM(amount) s, COUNT(*) n FROM payments WHERE paymentStatus IN ('paid','partial')")[0];
printf("  held total %s over %d receipts\n", number_format((float) $h->s, 2), $h->n);
foreach (DB::select("SELECT amount, COUNT(*) n FROM payments WHERE paymentStatus IN ('paid','partial') GROUP BY amount ORDER BY amount") as $p) {
    printf("  %14s  x%d\n", number_format((float) $p->amount, 2), $p->n);
}

echo "\n--- stored remaining balances ---\n";
foreach (DB::select('SELECT remainingBalance b, COUNT(*) n FROM studentassessments GROUP BY remainingBalance ORDER BY b') as $b) {
    printf("  %14s  x%d\n", number_format((float) $b->b, 2), $b->n);
}

echo "\n--- the worst negative and the largest balance, if any ---\n";
$r = DB::select('SELECT MIN(remainingBalance) lo, MAX(remainingBalance) hi, MIN(totalAssessedAmount) alo FROM studentassessments')[0];
printf("  min balance %s | max balance %s | min assessed %s\n", number_format((float) $r->lo, 2), number_format((float) $r->hi, 2), number_format((float) $r->alo, 2));
