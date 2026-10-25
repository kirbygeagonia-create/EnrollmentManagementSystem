<?php

/**
 * Pass 206 — the slip's Date-to-be-Signed pointer had drifted, and six blind numbers name no file.
 *
 * Reading `clearance-slip.blade.php` line by line (the bounds sweep only refuses a line past the end or a blank
 * one) found the drift: the §25 paragraph cites `:72-73` for the Date to be Signed, but P-19 added the
 * course-code block above it, so :72-73 is now the `$slipCourseLabel` ternary and the date row is :80-81. Both
 * ranges are live code, so every instrument called it clean.
 *
 * The rest of the pass names the owner of a pointer the sweep prints as blind because the file sits further than
 * 160 characters away: the room-capacity range in `BlockingController.php`.
 *
 * Stage 1 (this file, no --apply) PROVES each anchor is contiguous in document.xml before anything is written:
 * the flat dump joins `<w:t>` runs with a space, so a phrase that reads `write ( :495-507 )` in the dump may be
 * five runs in the file, and a needle built from the dump's spacing silently matches nothing.
 *
 *   php storage/app/scratch/refresh-docx-pass206.php            # probe the code lines and every anchor
 *   php storage/app/scratch/refresh-docx-pass206.php --apply    # write the ones that proved out
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// ------------------------------------------------------------------ 1. the code claims
$files = [
    'slip' => 'resources/views/prints/clearance-slip.blade.php',
    'block' => 'app/Http/Controllers/Blocking/BlockingController.php',
    'acct' => 'app/Http/Controllers/Accounting/AccountingController.php',
    'form' => 'resources/views/prints/enrollment-form.blade.php',
];
$L = [];
foreach ($files as $k => $rel) {
    $L[$k] = (array) file($root.'/'.$rel);
}
$claims = [
    ['slip', 46, 'term->semester->value'],
    ['slip', 50, 'term->academicYear->yearLabel'],
    ['slip', 65, '$termEnrollment = $clearance->termEnrollment();'],
    // what the document cites TODAY — asserted so the pass proves the drift rather than just claiming it
    ['slip', 72, 'courseCode'],
    ['slip', 73, 'courseName'],
    ['slip', 80, 'Date to be Signed'],
    ['slip', 81, 'clearanceEndDate'],
    ['block', 483, 'Block capacity enforcement'],
    ['block', 489, '> $block->maxStudents'],
    ['block', 495, 'Room capacity check'],
    ['block', 502, '> $room->capacity'],
    ['acct', 172, 'public function record('],
    ['acct', 188, 'min:0.01'],
    ['form', 71, '@isset($documentNumber)'],
    ['form', 76, '@endisset'],
];
$fails = 0;
foreach ($claims as [$k, $n, $needle]) {
    $t = trim($L[$k][$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s %-6s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $k, $n, mb_substr($t, 0, 62));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    printf("\nREFUSED: %d claimed line(s) do not carry the claim.\n", $fails);
    exit(1);
}

// ------------------------------------------------------------------ 2. prove every anchor is contiguous
$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

// [anchor, expected count, replacement] — the replacement repeats the anchor and appends the owner, so a count
// that is wrong cannot silently drop the sentence it was meant to keep.
$anchors = [
    ['the Date to be Signed at :72-73', 1, 'the Date to be Signed at :80-81'],
    ['is refused: the room total is weighed before the write', 1, 'is refused: the room total is weighed before the write ( BlockingController.php:495-507 )'],
];
// Two more were probed and are NOT addressable as text needles: the §25 slip sentence splits `$clearance->…` at
// the `>` and `(:46,50` opens a run of its own, so neither phrase is contiguous in document.xml. They need the
// para kind with a run-boundary anchor and go in a later pass; keeping them here would only make the guard below
// refuse a write that has already proved its two real edits.
$bad = 0;
foreach ($anchors as $i => [$a, $want, $_]) {
    $n = substr_count($xml, $a);
    printf("  anchor %d count %d (want %d) — %s\n", $i + 1, $n, $want, mb_substr($a, 0, 60));
    $bad += $n === $want ? 0 : 1;
}
if ($bad > 0) {
    echo "\nPROBE: some anchor is not contiguous. Those edits are listed in the notes and go in through the\n"
        ."cell or para kind instead of a text needle; nothing is written until every count above matches.\n";
    if ($apply) {
        exit(1);
    }
}
printf("  document.xml is %d bytes\n", strlen($xml));

$usable = [];
foreach ($anchors as [$a, $want, $to]) {
    if (substr_count($xml, $a) === $want) {
        $usable[] = [$a, $to, null, $want];
    }
}
if ($usable === []) {
    echo "\nREFUSED: no anchor is addressable on these bytes, so this pass would write nothing while reporting success.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass206.docx', $usable, [], $apply);
