<?php

/**
 * Pass 301: §24's double-encoding sentence describes a mechanism the build no longer has.
 *
 * The sentence read "both listeners pass an already-encoded string (json_encode([...]) at SendEnrollmentNotification.php:38,
 * SendWorkflowNotification.php:45, :57), so Laravel encodes it a second time." `json_encode` occurs ZERO times in either
 * listener: both write `'data' => array_merge([...])`. Every number in that parenthetical lands on a live line of the
 * right file, which is why the citation sweep has been silent about it for weeks — the §12 Gate-4 shape (a stale claim
 * under a live pointer) reached from the architecture side, and the reason a bounds check is not a meaning check.
 *
 * The pass re-takes the claim AND the numbers, asserting each new line really carries the array write before writing.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass301.php [--apply]
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

$assert = [
    ['app/Listeners/SendWorkflowNotification.php', 95, "'data' => array_merge(["],
    ['app/Listeners/SendEnrollmentNotification.php', 72, "'data' => \$data,"],
];
$bad = [];
foreach ($assert as [$f, $n, $needle]) {
    $lines = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/'.$f)));
    if (! str_contains($lines[$n - 1] ?? '', $needle)) {
        $bad[] = basename($f).":{$n} does not carry '{$needle}' — reads: ".trim($lines[$n - 1] ?? '(past end)');
    }
}
if ($bad !== []) {
    fwrite(STDERR, "REFUSED: the asserted lines do not carry the claim.\n  ".implode("\n  ", $bad)."\n");

    exit(1);
}

$from = 'but both listeners pass an already-encoded string (json_encode([...]) at SendEnrollmentNotification.php:38, SendWorkflowNotification.php:45, :57), so Laravel encodes it a second time.';
$to = 'and neither pre-encodes anything: SendEnrollmentNotification.php:72 stores \'data\' => $data and SendWorkflowNotification.php:95 stores \'data\' => array_merge([...]), so each is a plain array that the model casts exactly once.';

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
    fwrite(STDERR, "REFUSED: nothing written — the sentence is run-split or already re-taken.\n");

    exit(1);
}
docxApply($docx, $root.'/storage/app/scratch/pass301-backup-'.date('Ymd-His').'.docx', [[$from, $to, null, 1]], [], $apply);
