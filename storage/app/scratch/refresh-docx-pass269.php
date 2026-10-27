<?php

/**
 * Two figures the resume case overtook, and the pass range that says when G-32 closed.
 *
 * `EnrollmentReturnTest` grew a 27th case when the parked-record edge was built, and §24's `enrollmentreturns`
 * row still said 26 — the same sentence gained a case while its count stood still, which is lane 64's
 * membership defect arrived at from a test file. §28.3's G-32 row closed itself with "docx passes 258-267";
 * pass 268 wrote that row and this pass moves the count it left behind, so a range that stopped growing would
 * read as though neither happened.
 *
 * The case count is taken from the test file rather than typed: the pass counts its own `#[Test]` markers and
 * refuses when the document's figure is anything but one less than that, so the edit cannot land on a file
 * that has since grown again.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass269.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$test = (string) file_get_contents($root.'/tests/Feature/Enrollment/EnrollmentReturnTest.php');
if ($test === '') {
    fwrite(STDERR, "REFUSED: cannot read tests/Feature/Enrollment/EnrollmentReturnTest.php.\n");

    exit(1);
}

$cases = substr_count($test, '#[Test]');
if ($cases < 1) {
    fwrite(STDERR, "REFUSED: the test file declares no #[Test] method, so its count cannot be measured.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    [', 26 cases: the status trail', ", {$cases} cases: the status trail"],
    ['Closed 2026-10-27 by docx passes 258-267: each pointer re-taken', 'Closed 2026-10-27 by docx passes 258-269: each pointer re-taken'],
    [
        'which is what makes that qualification honoured rather than silently resolved.',
        'which is what makes that qualification honoured rather than silently resolved. Pass 268 wrote this row, and pass 269 moved the §24 case count that the resume case overtook.',
    ],
];

if (substr_count($xml, ", {$cases} cases: the status trail") === 1) {
    fwrite(STDERR, "REFUSED: this pass's own §24 row is already in the document — the count is {$cases} and was written here before.\n");

    exit(1);
}

// Pre-flight: every needle resolves to exactly one place, and the document does not already hold the copy that
// is about to be written. A half-applied register is the worst docx defect, so nothing is substituted until all
// three resolve.
foreach ($edits as [$from, $to]) {
    $n = substr_count($xml, $from);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSED: needle [".substr($from, 0, 60).'] matched '.$n.' place(s), expected exactly 1.'."\n");

        exit(1);
    }
    if ($from === $to) {
        fwrite(STDERR, "REFUSED: a needle and its replacement are the same text.\n");

        exit(1);
    }
}

printf("test file declares %d cases; the document says 26 -> %d\n", $cases, $cases);

$updated = $xml;
foreach ($edits as [$from, $to]) {
    $updated = str_replace($from, $to, $updated);
}

// After-check on the bytes, before they reach the archive. An appended sentence *contains* its own needle, so
// requiring the needle to vanish would refuse a correct edit — the count is what has to move, by one, and the
// replacement has to sit exactly once.
foreach ($edits as $i => [$from, $to]) {
    $before = substr_count($xml, $from);
    $after = substr_count($updated, $from);
    $expected = str_contains($to, $from) ? $before : $before - 1;
    if ($after !== $expected) {
        fwrite(STDERR, "REFUSED: edit {$i}'s needle occurred {$before} time(s) and now occurs {$after}; "
            .(str_contains($to, $from) ? 'the replacement carries the needle, so the count must hold.'
                : 'the count must fall by one.')."\n");

        exit(1);
    }
    if (substr_count($updated, $to) !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i}'s replacement occurs ".substr_count($updated, $to)." times, expected 1.\n");

        exit(1);
    }
}

// Tags are counted by their OPEN form, not by a literal: `<w:t xml:space="preserve">` and `<w:p w:rsidR=…>` are
// the same elements as `<w:t>` and `<w:p>`, and a balance test written against the bare spelling reports a
// healthy document as corrupt.
foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $updated);
    $b = substr_count($updated, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($updated) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%sgrew the §24 case count and widened G-32's pass range\n", $apply ? '' : 'DRY RUN — ');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass269-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $updated);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $updated) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 269 applied: the §24 row counts the resume case and G-32's range reaches the pass that wrote it.\n";
