<?php

/**
 * Docx pass 348 — §28.3 G-50 closed: the private folder is no longer served at all.
 *
 * The row was written on 2026-10-30 describing a state the build kept: `documents` shared
 * `storage_path('app/private')` with the `local` disk, and that disk asked Laravel to serve it, so every uploaded
 * paper and face photo sat behind an HMAC of the path keyed by `APP_KEY` — a signature the application never mints.
 * The fix took the flag off rather than moving either root (which would have left every stored path valid but the
 * door intact): the two routes are not registered, `ServedUploadPathTest` pins that absence, and
 * `mutate-g50-served-folder-tests.php` proves the cases fail if the flag comes back.
 *
 * Four text edits re-take the row's own tense — it described today's build and now describes the one it replaced —
 * and one moves the verdict cell's leading verb, which is the cell lane 61 and lane 69 read. The day row gains the
 * sixth half, including the preflight sentence that was still promising the retired door: a mechanism claim the
 * build overtook is the lane-70 family, reached here from a command message.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass348.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass348-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$titleShared = ['shares its root with the disk that is served', 'shared its root with the disk that was served', null, 1];
$titleSat = ['sits behind nothing but the application-key HMAC', 'sat behind nothing but the application-key HMAC', null, 1];
$titleKey = ['refuses a blank key precisely because that is the only gate',
    'refused a blank key precisely because, for those two routes, that was the only gate', null, 1];
$verdict = ['Open 2026-10-30, and measured rather than reasoned', 'Closed 2026-10-31, and measured rather than reasoned', null, 1];
$closure = ['that is why this row is open and not urgent',
    'that is why the row was open and not urgent, and why it took a configuration line rather than a migration: '
    .'the flag came off the `local` disk, so the routes are not registered at all — a stronger claim than the HMAC '
    .'they used to answer to. `tests/Feature/Files/ServedUploadPathTest.php` pins the absence (no local disk asks to '
    .'serve, neither route name resolves, no storage path is served, and both verbs answer 404), and '
    .'`storage/app/scratch/mutate-g50-served-folder-tests.php` puts the flag back and requires each of those cases to '
    .'go red. The preflight’s Served folder row now WARNs any disk served from the uploads’ root and keeps its FAIL '
    .'for one declared public, and DEPLOYMENT.md §2 carries the operator’s half: never put the flag back on the disk '
    .'the uploads live in, and never declare a served disk public', null, 1];
$day = ['case can see it',
    'case can see it. The day’s sixth half closed §28.3 G-50 by taking the serve flag off the `local` disk rather '
    .'than moving either root: the read and upload routes at /storage/{path} are now not registered at all, which is '
    .'a stronger claim than the HMAC they used to answer to, and `tests/Feature/Files/ServedUploadPathTest.php` pins '
    .'the absence while `storage/app/scratch/mutate-g50-served-folder-tests.php` puts the flag back and requires those '
    .'cases to go red. The preflight’s Served folder row gained a WARN for any disk served from the uploads’ root and '
    .'keeps its FAIL for one declared public; its Application key sentence was re-taken because it still promised the '
    .'retired door — the lane-70 shape, reached from a command message — and DEPLOYMENT.md §2 now says what an '
    .'operator must not re-enable', null, 1];

$textEdits = [$titleShared, $titleSat, $titleKey, $verdict, $closure, $day];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$bad = 0;
foreach ($textEdits as $i => [$from, $to, , $want]) {
    $n = substr_count($xml, $from);
    printf('edit %d: needle occurs %d time(s), want %d — %s%s', $i, $n, $want, substr($from, 0, 46), PHP_EOL);
    if ($n !== $want) {
        $bad++;
    }
    if (str_contains($to, '<') || str_contains($to, '>')) {
        $bad++;
        echo "  edit {$i} carries a tag character\n";
    }
}
if ($bad > 0) {
    // Either every edit has already landed (its needle gone, or surviving only as the head of its own append) or
    // this run is looking at a half-written document. Decided per edit, never with one constant for the loop.
    $applied = true;
    foreach ($textEdits as [$from, $to]) {
        $toCount = substr_count($xml, $to);
        $fromCount = substr_count($xml, $from);
        if ($toCount < 1) {
            $applied = false;

            continue;
        }
        $residualOk = str_starts_with($to, $from) ? $fromCount === $toCount : $fromCount === 0;
        if (! $residualOk) {
            $applied = false;
        }
    }
    echo $applied ? "ALREADY APPLIED.\n" : "REFUSED — nothing written.\n";

    exit($applied ? 0 : 1);
}

docxApply($docx, $backup, $textEdits, [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the written docx did not open.\n");
}
$after = html_entity_decode((string) $zip->getFromName('word/document.xml'), ENT_QUOTES | ENT_HTML5);
$zip->close();

// Each edit states its own residual: an append keeps its needle as the head of the new sentence, a rewrite does not
// (pass 341's per-edit rule — one constant for the loop refuses a correct write).
$expect = [
    ['shares its root with the disk that is served', 0],
    ['sits behind nothing but the application-key HMAC', 0],
    ['refuses a blank key precisely because that is the only gate', 0],
    ['Open 2026-10-30, and measured rather than reasoned', 0],
    ['that is why this row is open and not urgent', 0],
    ['shared its root with the disk that was served', 1],
    ['sat behind nothing but the application-key HMAC', 1],
    ['Closed 2026-10-31, and measured rather than reasoned', 1],
    ['The day’s sixth half closed', 1],
    ['case can see it', 1],
];
$bad = 0;
foreach ($expect as [$needle, $want]) {
    $n = substr_count($after, $needle);
    if ($n !== $want) {
        $bad++;
        printf('  %s occurs %d time(s), want %d%s', $needle, $n, $want, PHP_EOL);
    }
}
if ($bad > 0) {
    copy($backup, $docx);
    exit("REFUSED: the written document does not read back as intended; restored from {$backup}.\n");
}
echo "written, re-read: the G-50 row leads Closed, its title is in the tense of the build it replaced, and the day's row carries the sixth half.\n";
