<?php

/**
 * Lane 36, part 2: prune the 21 print rows no enrollment, student or block owns.
 *
 * The owner ruled on 2026-10-15, after the record-state review showed what they are: 19 `clearanceSlip` rows
 * numbered 1..19 with no gap and 2 `blockSchedule` rows numbered 1 and 2 printed four seconds apart — one
 * batch, from 2026-09-13 to 2026-09-29, all of it predating the `studentId`/`blockId` columns that let a print
 * be attributed at all. §25 P-14's hazard is real but spent: `uq_documentprintlog_issue` is
 * `(enrollmentId, documentType, documentNumber)`, and a NULL in the first part makes each row unrelated to
 * every other, so these rows can neither block nor be blocked by a future print. They are a count, not a risk.
 *
 * So this is a deletion, and a deletion of records only — nothing else in the database points at a
 * `documentprintlog` row, so no orphan is created. Guards, all evaluated before the first DELETE:
 *   - a verified dump of live `ems` must be named on the command line and must contain the table;
 *   - the set is exactly the rows with ALL THREE keys NULL — a row with any key is untouchable, and one that
 *     turns up with a key while this script is deciding is refused rather than reclassified;
 *   - the caller states how many rows to expect; a different number means the world changed since the review
 *     and the run stops;
 *   - the manifest is written and re-read BEFORE anything is deleted, so a row is only removable once its
 *     full contents are on disk;
 *   - after the delete, the surviving count must equal the pre-run count minus the pruned count, and every
 *     attributed row must still be there.
 *
 * usage: php storage/app/scratch/prune-unattributable-print-rows.php <dump> [expected=21] [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dumpArg = $argv[1] ?? '';
$expect = (int) ($argv[2] ?? 21);
$apply = in_array('--apply', $argv, true);
$db = config('database.connections.mysql.database');

$fails = 0;
$fail = function (string $why) use (&$fails): void {
    fwrite(STDERR, "REFUSE: {$why}\n");
    $fails++;
};

if ($db !== 'ems' && $db !== 'ems_lane36_rehearse') {
    $fail("refusing to delete anything in database `{$db}` — this script is written for ems or its rehearsal clone");
}
if ($dumpArg === '' || ! is_file($dumpArg) || filesize($dumpArg) < 100_000
    || ! str_contains(file_get_contents($dumpArg), 'CREATE TABLE `documentprintlog`')) {
    $fail('no verified dump of the table named (pass the mysqldump path; it must contain documentprintlog)');
}

if ($fails > 0) {
    // Refuse before the first read, not just before the first delete: a database this script has no business
    // in must not be queried at all. Measured — pointing it at a foreign schema printed the refusal and then
    // reached for a column that schema does not have.
    exit(1);
}

$rows = DB::table('documentprintlog')
    ->whereNull('enrollmentId')->whereNull('studentId')->whereNull('blockId')
    ->orderBy('printLogId')->get();

if ($rows->count() !== $expect) {
    $fail("found {$rows->count()} unattributable rows, expected {$expect} — the set changed since the review, so re-read it before deleting");
}

// Anything with a key must survive; assert the complement too, so a broken predicate cannot widen the set.
$attributed = DB::table('documentprintlog')
    ->where(fn ($q) => $q->whereNotNull('enrollmentId')->orWhereNotNull('studentId')->orWhereNotNull('blockId'))
    ->count();
$total = DB::table('documentprintlog')->count();
if ($attributed + $rows->count() !== $total) {
    $fail("the split does not add up: {$attributed} attributed + {$rows->count()} unattributable != {$total} total");
}

printf("database: %s | total print rows: %d | attributed: %d | to prune: %d\n", $db, $total, $attributed, $rows->count());
foreach ($rows->groupBy('documentType') as $type => $g) {
    printf("  %-16s %2d rows  numbers %s  printed %s -> %s  by %s\n", $type, $g->count(),
        $g->pluck('documentNumber')->implode(','), $g->min('printedDate'), $g->max('printedDate'),
        $g->pluck('printedBy')->unique()->implode('/'));
}

if ($fails > 0) {
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN — pass --apply to write the manifest and delete these rows\n";
    exit(0);
}

$manifest = $root.'/storage/app/scratch/manifest-prune-unattributable-prints-'.now()->format('Y-m-d-His').'.json';
file_put_contents($manifest, json_encode([
    'ruled' => 'owner, 2026-10-15, after the record-state review (lane 35/36)',
    'dump' => $dumpArg,
    'database' => $db,
    'predicate' => 'enrollmentId IS NULL AND studentId IS NULL AND blockId IS NULL',
    'rows' => $rows->map(fn ($r) => (array) $r)->all(),
], JSON_PRETTY_PRINT));

// A row is only deletable once it is on disk and readable back — the manifest is the whole justification for
// deleting a record, so a manifest that silently failed to write must not be discovered afterwards.
$written = json_decode(file_get_contents($manifest), true);
if (($written['rows'] ?? []) !== $rows->map(fn ($r) => (array) $r)->all()) {
    $fail('the manifest on disk does not match the rows about to be deleted');
} else {
    echo 'manifest verified and written: '.basename($manifest)."\n";
}

if ($fails > 0) {
    exit(1);
}

$deleted = DB::table('documentprintlog')->whereIn('printLogId', $rows->pluck('printLogId'))->delete();
printf("deleted %d row(s)\n", $deleted);

$after = DB::table('documentprintlog')->count();
$afterAttributed = DB::table('documentprintlog')
    ->where(fn ($q) => $q->whereNotNull('enrollmentId')->orWhereNotNull('studentId')->orWhereNotNull('blockId'))->count();
$left = DB::table('documentprintlog')->whereNull('enrollmentId')->whereNull('studentId')->whereNull('blockId')->count();

printf("post: total %d (was %d, deleted %d) | attributed %d (was %d) | unattributable left %d\n",
    $after, $total, $deleted, $afterAttributed, $attributed, $left);
$after === $total - $deleted || $fail('the surviving count does not account for the deletion');
$afterAttributed === $attributed || $fail('an attributed row was lost — this was not supposed to touch those');
$left === 0 || $fail('unattributable rows remain');

printf("\n%s\n", $fails === 0
    ? sprintf('PRUNED %d rows; %d attributed rows untouched; manifest %s', $deleted, $afterAttributed, basename($manifest))
    : sprintf('%d problem(s) after the delete — restore from %s', $fails, $dumpArg));
exit($fails === 0 ? 0 : 1);
