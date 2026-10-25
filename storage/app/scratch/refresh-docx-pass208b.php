<?php

/**
 * Pass 208b — four pointers the first pass could not reach as phrases, taken as bare ranges instead.
 *
 * The flat dump joins `<w:t>` runs, so a sentence reads continuously on screen while the file holds it in pieces;
 * `lane70-probe-needles.php` prints each candidate's byte count before anything is written. Four of the five
 * pointers pass 208 left over turn out to be addressable when the needle is the range alone:
 *
 *   `:676-682` (1 copy) — the staff row's pointer into DevReferenceDataSeeder. Reading the file said the range is
 *       the CURRICULUM block (`'majorId' => null` at :677) while the staff seeding the sentence describes is
 *       :782-827. A wrong target, not a stale one: the cited lines are live code belonging to something else.
 *   `:195-225` (1 copy) — attachPhoto() in IDController.php, which stores the capture and sets cardPhotoPath.
 *   `:208` after the anchor `and its two gates` (1 copy) — signStep() in WorkflowService.php.
 *   `:28-31` after the anchor `passes a real array` (1 copy) — NotificationSeeder.php, whose second array at
 *       :39-43 then falls inside the 160-character window and is judged too.
 *
 * One pointer is deliberately left: the returnReason range in a §28 register row. `:288-312` appears twice, the
 * other copy already prefixed with its file, and `returnReason` occurs six times, so no anchor here is safe —
 * an anchor that could pick the wrong copy is how a pass corrupts a citation it never read. It goes through the
 * cell kind.
 *
 *   php storage/app/scratch/refresh-docx-pass208b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass208b.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$code = [
    'devseed' => 'database/seeders/DevReferenceDataSeeder.php',
    'id' => 'app/Http/Controllers/ID/IDController.php',
    'wf' => 'app/Services/WorkflowService.php',
    'notif' => 'database/seeders/NotificationSeeder.php',
];
$L = [];
foreach ($code as $k => $rel) {
    $L[$k] = (array) file($root.'/'.$rel);
}
$claims = [
    ['devseed', 782, 'Staff (staff8 = admin'],
    ['devseed', 810, 'office{$officeId}_head'],
    ['devseed', 827, '}'],
    ['devseed', 677, "'majorId' => null"],          // what the document cites TODAY — the wrong target
    ['id', 195, 'public function attachPhoto('],
    ['id', 214, "'cardPhotoPath' =>"],
    ['wf', 208, 'public function signStep('],
    ['notif', 28, "'data' => ["],
    ['notif', 31, '],'],
    ['notif', 39, "'message' => 'A student has completed"],
    ['notif', 43, '],'],
];
$fails = 0;
foreach ($claims as [$k, $n, $needle]) {
    $t = trim($L[$k][$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s %-8s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $k, $n, mb_substr($t, 0, 58));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    printf("\nREFUSED: %d claimed line(s) do not carry the claim.\n", $fails);
    exit(1);
}

$edits = [
    [':676-682', 'DevReferenceDataSeeder.php:782-827'],
    [':195-225', 'IDController.php:195-225'],
    [':208', 'WorkflowService.php:208', 'and its two gates'],
    [':28-31', 'NotificationSeeder.php:28-31', 'passes a real array'],
];

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as $i => $e) {
    [$from, $to] = [$e[0], $e[1]];
    $anchor = $e[2] ?? null;
    $cnt = substr_count($xml, $from);
    $acnt = $anchor === null ? null : substr_count($xml, $anchor);
    printf("  needle %-12s count %-2d  anchor [%s] count %s\n", $from, $cnt,
        $anchor ?? '—', $acnt === null ? '—' : (string) $acnt);
    if ($anchor === null && $cnt !== 1) {
        printf("REFUSED: needle %d is not unique and has no anchor.\n", $i + 1);
        exit(1);
    }
    if ($anchor !== null && $acnt !== 1) {
        printf("REFUSED: anchor for edit %d is seen %d time(s); a positional edit needs one owner.\n", $i + 1, $acnt);
        exit(1);
    }
    if (str_contains($to, '<') || str_contains($to, '>')) {
        echo "REFUSED: a replacement carries markup.\n";
        exit(1);
    }
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass208b.docx', $edits, [], $apply);
