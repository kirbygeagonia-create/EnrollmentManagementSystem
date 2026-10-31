<?php

/**
 * Lane 108: make the admission queue's status tile replace its column list rather than inherit one.
 *
 * The tile is cloned AFTER `ListSort::apply()` has run, so it inherits two things the order brought: the join to
 * `students`, and the select pin `admissions.*` that closes G-51. `selectRaw()` APPENDS, so the aggregate went out
 * as `select `admissions`.*, admissionStatus, count(*) …  group by admissionStatus`, which is MySQL 8
 * `ONLY_FULL_GROUP_BY` error 1055 — a 500 at the desk for clicking the surname header. SQLite answers the same
 * query, which is why the whole local suite was green and CI's MySQL job was not.
 *
 * Two edits, each byte-count-preserving on its own line, so no citation into this handler ages:
 *   1. `->select('admissionStatus')` before `->selectRaw(...)` — Eloquent's `select()` REPLACES the column list, so
 *      the aggregate carries the two columns it groups by and nothing else. The join stays and is harmless: it is
 *      many-to-one through a NOT NULL key, so no admission is counted twice.
 *   2. the comment rewritten in place, same number of lines, so no citation into this handler ages. Its wording was
 *      re-taken twice: first to say a late clone inherits the order as well as the page, then — once the clone was
 *      left where it is — to state the one reason that is safe, which is that the hop is many-to-one through a NOT
 *      NULL `admissions.studentId`. The handler carries that sentence; this file's read-back keys on the CODE line,
 *      because a verifier keyed on prose reports its own age as a defect.
 *
 * Usage: php storage/app/scratch/lane108-tile-replaces-its-columns.php [--apply] [--file=<path>]
 *   A plain run on the fixed tree reports ALREADY APPLIED. `--file=` points at a copy, which is how a harness puts
 *   the retired shape back and requires this file to stop agreeing.
 */
$root = dirname(__DIR__, 3);
$file = 'app/Http/Controllers/Admission/AdmissionController.php';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        continue;
    }
    if (str_starts_with($arg, '--file=')) {
        $file = substr($arg, strlen('--file='));

        continue;
    }
    fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this script reads --apply and --file=.\n");

    exit(1);
}
if (! is_file(tilePath($root, $file))) {
    fwrite(STDERR, "REFUSED: no handler at {$file} — an unread target is never a clean check.\n");

    exit(1);
}

/** A `--file=` may be project-relative or absolute, so a harness can point this at a copy outside the tree. */
function tilePath(string $root, string $file): string
{
    return (str_starts_with($file, '/') || preg_match('#^[A-Za-z]:#', $file) === 1) ? $file : $root.'/'.$file;
}

$edits = [
    [
        "            ->selectRaw('admissionStatus, count(*) as aggregate')",
        "            ->select('admissionStatus')->selectRaw('count(*) as aggregate')",
    ],
    [
        '        // Taken before paginate(), not after: paginate() calls forPage(), which sets limit and offset on the',
        '        // Taken before the ORDER as well as before paginate(): the order joins `students` and pins the select',
    ],
    [
        '        // builder it is called on, so a clone made afterwards carries "LIMIT 20 OFFSET 20" and answers with',
        '        // to `admissions.*`, and `selectRaw()` appends — so a late clone used to aggregate over the star and',
    ],
    [
        '        // nothing once the desk leaves page 1. AccountingController has always cloned first; these three did',
        '        // MySQL 8 answered it 1055, a 500 at the desk for clicking a header, while SQLite answered it. This',
    ],
    [
        '        // not, and page 1 is not evidence either way.',
        '        // tile replaces its own columns; `select()` overwrites, and the join is many-to-one on a NOT NULL key.',
    ],
];

$path = tilePath($root, $file);
$bytes = (string) file_get_contents($path);
$linesBefore = substr_count($bytes, "\n");

// APPLIED, so a re-run is the read-back rather than a confusing "needle matched 0". The signal is the CODE line, not
// the comment beside it: this file's own wording was re-taken later in the batch to state why a late clone is safe,
// and a verifier that keyed on prose would report its own age as a defect. A one-shot patcher that refuses cleanly
// on a tree it already fixed is still a patcher that lies about the tree.
$appliedShape = "->select('admissionStatus')->selectRaw('count(*) as aggregate')";
$oldShape = "->selectRaw('admissionStatus, count(*) as aggregate')";
if (substr_count($bytes, $appliedShape) === 1 && substr_count($bytes, $oldShape) === 0) {
    echo "ALREADY APPLIED: the admission tile replaces its own column list, so the select the order pinned does not"
        ." ride into the aggregate.\n";
    echo "why the clone may still sit AFTER the order: the hop is many-to-one through a NOT NULL `admissions.studentId`"
        ." — read that sentence in the handler, because it is the reason and not this file.\n";
    echo "the driver that sees the defect this removes: MySQL 8 with ONLY_FULL_GROUP_BY answers 1055 for the old shape;"
        ." SQLite answers it.\n";

    exit(0);
}

$failures = [];
foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($bytes, $from);
    if ($n !== 1) {
        $failures[] = sprintf('edit %d: needle matched %d time(s), wanted exactly 1 — %s', $i, $n, trim($from));
    }
    if (substr_count($from, "\n") !== substr_count($to, "\n")) {
        $failures[] = "edit {$i}: the replacement is a different number of lines";
    }
}
if ($failures !== []) {
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

$out = $bytes;
foreach ($edits as [$from, $to]) {
    $out = str_replace($from, $to, $out);
}
if (substr_count($out, "\n") !== $linesBefore) {
    fwrite(STDERR, "REFUSED: the file's line count would move; citations would age.\n");

    exit(1);
}
printf('%d edits, %d lines before and after.%s', count($edits), $linesBefore, PHP_EOL);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

file_put_contents($path, $out);

$after = (string) file_get_contents($path);
if (! str_contains($after, "->select('admissionStatus')->selectRaw('count(*) as aggregate')")) {
    fwrite(STDERR, "REFUSED: written, but the tile does not replace its own columns.\n");

    exit(1);
}
if (str_contains($after, "->selectRaw('admissionStatus, count(*) as aggregate')")) {
    fwrite(STDERR, "REFUSED: the old aggregate call is still there.\n");

    exit(1);
}
echo 'written, re-read: the aggregate carries the two columns it groups by, and nothing the order brought.'."\n";
