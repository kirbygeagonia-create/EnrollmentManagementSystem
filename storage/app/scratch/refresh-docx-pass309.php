<?php

/**
 * Pass 309: the day's §1.1 row records the register write.
 *
 * Pass 308 added G-44 … G-50 to §28.3 and pass 308b moved the two places that count them, but the revision record
 * still described only the censuses. A register that grew without a line in §1.1 is the same half-applied state this
 * document keeps refusing: the rows exist, the index says they do, and the record of the day says nothing happened.
 *
 * The sentence is appended to the row that already stands rather than added as a second row for the same day — the
 * reason pass 306 gave for the same shape.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass309.php [--apply]
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

require __DIR__.'/docx-edit-engine.php';

$from = 'read_at is what the test asserts.';
$to = $from.' §28.3 also gained the seven rows the coverage census produced — G-44 through G-50, each stating what the'
    .' build does today and which half waits on the school — and §28.1’s index range with §29.4’s family count were'
    .' re-taken from the written rows, the count and the id order both asserted against the document after the write.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([[$from, 1], ['also gained the seven rows', 0, 'this pass has already run']] as $guard) {
    [$needle, $want, $why] = [$guard[0], $guard[1], $guard[2] ?? 'the anchor must sit exactly once'];
    $hits = substr_count($xml, $needle);
    if ($hits !== $want) {
        fwrite(STDERR, "REFUSED ({$why}): [{$needle}] occurs {$hits}, want {$want}.\n");

        exit(1);
    }
}

printf("%s: the day's §1.1 row records the seven new register rows\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass309-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass309-dry.docx', [[$from, $to]], [], $apply);

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

foreach ([['also gained the seven rows', 1], [$from, 1]] as [$needle, $want]) {
    if (($hits = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$hits}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED: the revision record now names the register write.\n";
