<?php

/**
 * Read-only: how long is the clinic queue on the database this box calls `ems`?
 *
 * Lane 47's rule, applied to a sort: a desk cannot demonstrate an order over a queue of one row, and the number of
 * rows the live dataset holds is the difference between "the screen was read in a browser" and "the sequence was
 * observed". This probe answers that before anyone claims the second.
 *
 * Usage: php storage/app/scratch/lane102-probe-clinic-queue.php
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\DeskQueues;

$driver = DB::connection()->getDriverName();
$database = DB::connection()->getDatabaseName();
if ($database === 'ems' && ! str_contains((string) ($argv[1] ?? ''), '--allow-live')) {
    fwrite(STDERR, "REFUSED: pass --allow-live to read the live queue deliberately.\n");

    exit(1);
}

$rows = DeskQueues::clinic()
    ->with(['student', 'course'])
    ->orderByDesc('enrollments.enrollmentId')
    ->get();

printf("connection: %s / %s\n", $driver, $database);
printf("the clinic queue holds %d row(s)\n", $rows->count());

foreach ($rows as $row) {
    printf(
        "  enrollment %-5s %-10s %-28s %s\n",
        $row->enrollmentId,
        $row->student?->schoolIdNumber ?? '—',
        $row->course?->courseName ?? '—',
        $row->student?->lastName ?? '—'
    );
}

$distinct = $rows->pluck('student.lastName')->filter()->unique()->count();
printf("\n%d distinct surname(s): a browser read here %s\n", $distinct, $distinct > 1
    ? 'can show a sequence change'
    : 'can only show that the header sends the request and the arrow survives it — the sequence itself is proved by the suite, which writes four rows');
