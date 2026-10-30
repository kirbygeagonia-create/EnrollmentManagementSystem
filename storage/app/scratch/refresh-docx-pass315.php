<?php

/**
 * Pass 315: the two pointers into the clearance page that this batch's own edits aged.
 *
 * Wiring the queue's sort head added lines near the top of `Clearance/Index.jsx`, so every citation below them
 * slid down. One of them (`:60-61`) had been a `useMemo` closing brace before this batch and is now a blank line
 * two rows past it; the other (`:503`) lands on the page subtitle while the sentence quotes the phase rule four
 * lines below. The bounds sweep cannot see either: a live line of the right file is all it asks.
 *
 * Each new number is asserted against the line it names before anything is written.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass315.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$page = $root.'/resources/js/Pages/Clearance/Index.jsx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$lines = explode("\n", (string) file_get_contents($page));

$assert = [
    272 => 'label="Pending Obligation',
    273 => 'value={stats.pending}',
    526 => 'Permits the student to proceed to Phase 2',
];

$failed = [];
foreach ($assert as $n => $needle) {
    $text = trim($lines[$n - 1] ?? '');
    if (! str_contains($text, $needle)) {
        $failed[] = "line {$n} does not carry [{$needle}]: [".mb_substr($text, 0, 70).']';
    }
}
if ($failed !== []) {
    fwrite(STDERR, "REFUSED: the page does not hold what this pass would assert.\n  ".implode("\n  ", $failed)."\n");

    exit(1);
}
echo 'asserted '.count($assert)." line(s) against the claims they carry\n";

$edits = [
    ['Clearance/Index.jsx:60-61', 'Clearance/Index.jsx:272-273'],
    ['Clearance/Index.jsx:503', 'Clearance/Index.jsx:526'],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as $i => [$from, $to]) {
    if (($h = substr_count($xml, $from)) !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i} needle [{$from}] occurs {$h}, want 1.\n");

        exit(1);
    }
}

printf("%s: two clearance page pointers re-taken\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass315-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass315-dry.docx', $edits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([['Clearance/Index.jsx:60-61', 0], ['Clearance/Index.jsx:272-273', 1], ['Clearance/Index.jsx:503', 0], ['Clearance/Index.jsx:526', 1]] as [$needle, $want]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED.\n";
