<?php

/**
 * Pass 299: the §1.1 revision row still called the pagination half "untouched" after pass 297 measured it.
 *
 * Lane 61's rule in a new place: a ruling word must reach the document's own tally, not only the row it decides. The
 * register row (§28.3 G-40) was re-written with the census, and the living revision row copied forward the older
 * premise — two sentences about one gap, one measured and one not. The clause "35 of the 36 pages … still offer no
 * order" stays: that half is still true, and it is the sorting gap the row remains open for.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass299.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

$from = 'and the pagination half of this row is untouched';
$to = 'and the pagination half was weighed the same day rather than repeated: twenty-nine of those thirty-six pages '
    .'receive a paginator from their own action, three receive a plain collection (Accounting’s daily report, one '
    .'curriculum’s subject list and the settings table) and four map their rows in the browser over the prop they '
    .'were given, none of the three being a work queue';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$n = substr_count($xml, $from);
printf("needle occurs %d time(s) (want 1)\n", $n);
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: nothing written.\n");

    exit(1);
}
if (substr_count($xml, $to) !== 0) {
    fwrite(STDERR, "REFUSED: the replacement text already occurs, so this pass would half-apply.\n");

    exit(1);
}

docxApply($docx, $root.'/storage/app/scratch/pass299-backup-'.date('Ymd-His').'.docx', [[$from, $to, null, 1]], [], $apply);
