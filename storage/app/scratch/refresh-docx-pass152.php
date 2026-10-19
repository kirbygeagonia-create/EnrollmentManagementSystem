<?php

/**
 * Pass 152 — re-point the four citations that lane 51's follow-through invalidated.
 *
 * The demo pipeline dated its "today" rows with the UTC date while the desk reports now read the school's day,
 * so between 16:00Z and midnight a freshly seeded demonstration receipt does not appear on the screen it exists
 * to demonstrate. Fixing that added one import line to `tools/seed_full_demo_pipeline.php` and to
 * `DemoDatasetRecordStatesTest.php` — and every citation below an import moves by one, including two bare
 * continuations inside the same sentence (`the ruled exception at :146-166`, `the two base rows at :169-235`).
 *
 * Each move is checked against the file's own bytes first: the cited line must hold the construct the sentence
 * names. DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

/** [ needle, replacement, occurrences, file, line, token the line must hold ] */
$edits = [
    ['seed_full_demo_pipeline.php:1701-1704', 'seed_full_demo_pipeline.php:1702-1705', 1,
        'tools/seed_full_demo_pipeline.php', 1702, "yearLevel', '>=', 2"],
    ['DemoDatasetRecordStatesTest.php:112-143', 'DemoDatasetRecordStatesTest.php:113-144', 1,
        'tests/Feature/E2E/DemoDatasetRecordStatesTest.php', 113, 'the_record_state_audit_passes'],
    ['at :146-166', 'at :147-167', 1,
        'tests/Feature/E2E/DemoDatasetRecordStatesTest.php', 147, 'a_signed_box_with_no_record_behind_it'],
    ['at :169-235', 'at :170-236', 1,
        'tests/Feature/E2E/DemoDatasetRecordStatesTest.php', 170, 'the_two_base_rows_the_pipeline_creates'],
];

$zip = new ZipArchive();

if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);

    if (! $ok) {
        $fails++;
    }
};

foreach ($edits as [$from, $to, $count, $file, $line, $token]) {
    $work = explode(chr(10), str_replace("\r\n", chr(10), (string) file_get_contents($root.'/'.$file)));
    $at = $work[$line - 1] ?? 'PAST END of a '.count($work).'-line file';
    $guard(str_contains($at, $token), sprintf('%s :%d holds %s — "%s"', basename($file), $line, $token, trim(mb_substr($at, 0, 44))));
}

$new = $xml;

foreach ($edits as [$from, $to, $count]) {
    $seen = substr_count($new, $from);
    $guard($seen === $count, sprintf('needle "%s" occurs %d time(s) as expected', $from, $seen));

    if ($seen !== $count) {
        continue;
    }

    $new = str_replace($from, $to, $new);
}

foreach ($edits as [$from, $to, $count]) {
    $guard(substr_count($new, $from) === 0, "the old number \"$from\" is gone");
    $guard(substr_count($new, $to) === 1, "the new number \"$to\" is on the page once");
}

// The revision row already says the pointers were re-measured; this pass is that sentence becoming true for
// the two files the pipeline fix touched.
$anchor = 'Two pointers in §25.6 were already wrong before this batch and are left for that section’s own re-read, because the sentence around them needs re-measuring too, not only its numbers.';
$append = ' The demo pipeline and its end-to-end test then moved to the school’s own day as well, which added a line '
    .'to each of them, and the four citations that sit below those lines — two of them bare continuations inside '
    .'one sentence — were re-pointed against the files’ own bytes.';
$guard(substr_count($new, $anchor) === 1, 'the revision row still closes with the re-measurement sentence exactly once');
$new = str_replace($anchor, $anchor.$append, $new, $applied);
$guard($applied === 1, 'the pipeline-move sentence was appended');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses after the four moves and the appended sentence');
libxml_clear_errors();

printf("\n%d needle(s) rewritten, %d byte(s) changed\n", count($edits), strlen($new) - strlen($xml));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");

    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";

    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass152.docx');

$z0 = new ZipArchive();
$z0->open($src);
$partsBefore = count($z0);
$z0->close();

$z1 = new ZipArchive();

if ($z1->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");

    exit(1);
}

$z1->addFromString('word/document.xml', $new);
$z1->close();

$check = new ZipArchive();
$check->open($src);
$onDisk = $check->getFromName('word/document.xml');
$partsAfter = count($check);
$check->close();

if ($onDisk !== $new) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");

    exit(1);
}

if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — restore the backup\n");

    exit(1);
}

printf("WROTE %s — byte-verified, all %d parts present\n", $src, $partsAfter);
