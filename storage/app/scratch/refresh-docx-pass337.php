<?php

/**
 * Docx pass 337 — the three Accounting pointers `lane76-shift-cites.php` deferred, written by their own words.
 *
 * The batch moved `AccountingController.php` by four lines (one import, three lines in `index()`), so every pointer
 * below the change aged. The shift tool wrote the ones it could resolve and refused the rest: `:298` and `:345` each
 * occur more than once in `document.xml` and `:302`/`:349` already occur elsewhere, so a bare number needle would
 * have written someone else's citation — the "a needle can be another citation's tail" defect, met a fourth time.
 *
 * Each edit therefore carries the words the sentence itself uses as its anchor, and every anchor is asserted unique
 * BEFORE the write:
 *   `box at :298`       -> `box at :302`   ("a collected payment signs the same box at :298")
 *   `signature at :345` -> `signature at :349`  ("settleNoBalance() (…:319-356, the signature at :345)")
 * Both targets were verified by reading HEAD's line and the working line: 298/345 and 302/349 hold the identical
 * `signStepByOffice(… OfficeId::Accounting …)` statement, byte for byte.
 *
 * One item is reported and NOT written: a second `:345` and the bare `:227-287` pair live in runs split so that no
 * contiguous text needle reaches them; they land on live lines of the right file (bounds-clean) and are stale by
 * four, so the gate cannot see them. They are left as a named queue item for a `cell`-kind pass rather than forced
 * through a needle that would weld unrelated runs.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass337.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass337-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$headPath = $root.'/storage/app/scratch/lane110-head-AccountingController.php';
if (! is_file($headPath) || filesize($headPath) === 0) {
    fwrite(STDERR, "REFUSED: no HEAD export at ".basename($headPath).".\n");

    exit(1);
}
$head = (string) file_get_contents($headPath);
$work = (string) file_get_contents($root.'/app/Http/Controllers/Accounting/AccountingController.php');
$line = static fn (string $s, int $n): string => trim(explode("\n", $s)[$n - 1] ?? '(absent)');
$bad = 0;
foreach ([[':298', 298, 302], [':345', 345, 349]] as [$tok, $old, $new]) {
    $a = $line($head, $old);
    $b = $line($work, $new);
    if ($a === '' || $a !== $b) {
        fwrite(STDERR, "REFUSED: {$tok} -> :{$new} is unverified. HEAD {$old}: `{$a}` / WORK {$new}: `{$b}`\n");
        $bad++;

        continue;
    }
    echo "verified {$tok} -> :{$new} (both lines identical: ".substr($a, 0, 46)."…)\n";
}

$zip = new ZipArchive;
$zip->open($docx);
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    ['box at :298', 'box at :302'],
    ['signature at :345', 'signature at :349'],
];
foreach ($textEdits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    printf('edit %d: anchor %s occurs %d time(s)%s', $i, $from, $n, PHP_EOL);
    if ($n !== 1) {
        $bad++;
    }
}
if ($bad > 0) {
    $already = true;
    foreach ($textEdits as [, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $already = false;
        }
    }
    if ($already) {
        echo "ALREADY APPLIED.\n";

        exit(0);
    }
    fwrite(STDERR, "REFUSED — nothing written.\n");

    exit(1);
}

docxApply($docx, $backup, array_map(static fn ($e) => [$e[0], $e[1]], $textEdits), [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$zip = new ZipArchive;
$zip->open($docx);
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();
$fail = 0;
foreach ($textEdits as $i => [$from, $to]) {
    printf('  edit %d: %s now %d (want 1), %s now %d (want 0)%s', $i, $to, substr_count($after, $to), $from, substr_count($after, $from), PHP_EOL);
    if (substr_count($after, $to) !== 1 || substr_count($after, $from) !== 0) {
        $fail++;
    }
}
if ($fail > 0) {
    fwrite(STDERR, "REFUSED: read-back failed; restoring.\n");
    if (is_file($backup)) {
        copy($backup, $docx);
    }

    exit(1);
}
echo "written, re-read. NOT written (run-split, bounds-clean, stale by four): a second `:345` and the bare\n"
    ."`:227-287` pair (correct window `:231-291`, endpoints verified) — these need the engine's cell kind.\n";
