<?php

/**
 * Pass 312: re-take the two pointers into the Registrar page, and one of them was wrong before this batch.
 *
 * The citation gate flags only a blank line or a number past the end, so a pointer that lands on live code
 * belonging to something else is invisible to it. Two in `Registrar/Index.jsx` were open when this batch edited
 * the page:
 *   - `:82-85` opened on a blank line today; at HEAD it opened on `};`, so it had been one construct short for a
 *     while, and the sentence names the desk's summary tiles — re-taken to the two StatCards that read the
 *     handler's paginator total and the page's own count.
 *   - `:134-135 , :93` names the summary control and the page badge. Those two live at 150-151 and 109 now;
 *     134-135 is tile innards (`}` / `iconBg`) and was already the wrong target BEFORE this batch touched the
 *     page, which is what the sweep cannot see and reading can.
 *
 * `:93` occurs four times document-wide, so it is rewritten only inside a needle that spans both tokens —
 * a global replace of a short number is how another sentence's pointer gets moved.
 *
 * Every new number is asserted against the line it names before anything is written.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass312.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$page = $root.'/resources/js/Pages/Registrar/Index.jsx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$lines = explode("\n", (string) file_get_contents($page));

// Each new number and the claim the document says lives there.
$assert = [
    119 => '<StatCard',
    122 => 'totalCount',
    128 => 'On This Page',
    129 => 'currentCount',
    150 => 'Workflow Phase',
    151 => 'Phase 5',
    109 => 'phaseBadge',
];

$failed = [];
foreach ($assert as $n => $needle) {
    $text = trim($lines[$n - 1] ?? '');
    if (! str_contains($text, $needle)) {
        $failed[] = "line {$n} does not carry [{$needle}]: [".mb_substr($text, 0, 70).']';
    }
}
if ($failed !== []) {
    fwrite(STDERR, "REFUSED: the page does not hold what this pass is about to assert.\n  ".implode("\n  ", $failed)."\n");

    exit(1);
}
echo 'asserted '.count($assert)." line(s) against the claims they carry\n";

$edits = [
    ['Registrar/Index.jsx:82-85', 'Registrar/Index.jsx:119-129'],
    ['134-135 , :93', '150-151 , :109'],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as $i => [$from, $to]) {
    $hits = substr_count($xml, $from);
    if ($hits !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i} needle occurs {$hits} time(s), expected 1: [{$from}]\n");

        exit(1);
    }
}

printf("%s: two Registrar page pointers re-taken\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass312-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass312-dry.docx', $edits, [], $apply);

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

foreach ([['Registrar/Index.jsx:82-85', 0], ['Registrar/Index.jsx:119-129', 1], ['134-135 , :93', 0], ['150-151 , :109', 1]] as [$needle, $want]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}. Restore the pass backup.\n");

        exit(1);
    }
}

echo "APPLIED: both rows now name the lines that carry their claims.\n";
