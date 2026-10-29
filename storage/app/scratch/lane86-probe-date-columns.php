<?php

/**
 * Which of the columns the app feeds with `now()` are DATE rather than DATETIME?
 *
 * A DATE column written from `now()` stores the machine's civil day, because the app clock is UTC and
 * Eloquent formats a Carbon for a `date` cast with the app timezone. Lane 50's PHP half reads the
 * *shape* written (`now()->toDateString()`, `whereDate()`), so `'receivedDate' => now()` is invisible
 * to it while the column type is the whole question. This asks the server, not the code.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// The import block has to sit above the bootstrap: PHP resolves an unqualified name at compile time,
// in statement order, and Pint's fully_qualified_strict_types rule is what put this here once before.
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$targets = [
    'admissions' => ['submittedDate', 'evaluatedDate', 'draftedDate'],
    'studentrequirementsubmissions' => ['uploadedDate', 'submittedDate', 'validatedDate'],
    'studentassessments' => ['assessmentDate'],
    'clearanceapprovals' => ['receivedDate', 'approvalDate'],
    'studentclearances' => ['receivedDate'],
    'payments' => ['paymentDate'],
    'idrequests' => ['requestDate', 'validatedDate', 'printedDate', 'releasedDate'],
    'clinicrecords' => ['recordedDate'],
    'enrollments' => ['formIssuedDate'],
];

$db = DB::selectOne('SELECT DATABASE() AS d')->d;
echo "schema: {$db}\n";

foreach ($targets as $table => $cols) {
    $rows = DB::select(
        'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS'
        .' WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
        [$db, $table]
    );
    if ($rows === []) {
        echo str_pad($table, 34)." (no such table)\n";

        continue;
    }
    $byCol = [];
    foreach ($rows as $r) {
        $byCol[$r->COLUMN_NAME] = $r->DATA_TYPE;
    }
    $parts = [];
    foreach ($cols as $c) {
        $parts[] = $c.'='.($byCol[$c] ?? 'ABSENT');
    }
    echo str_pad($table, 34).implode('  ', $parts)."\n";
}
