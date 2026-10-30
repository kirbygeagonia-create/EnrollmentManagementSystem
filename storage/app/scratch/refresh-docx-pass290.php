<?php
/**
 * Pass 290 — the three bare pointers the G43 shift deliberately left behind.
 *
 * lane76-shift-cites.php defers a bare `:NNN` when the 400 bytes before it in document.xml hold a
 * filename that is not the one it is shifting, so two shapes never move with their file:
 *   1. `IDController.php:173, :184` — the clause that begins `Clinicrecords.php:17` sits upstream,
 *      so the comma-tail was charged to Clinicrecords and skipped;
 *   2. the two `ClearanceController.php ... :411` pointers (§23's print-log row and §25.1's
 *      print-route table), which downloadSlip's own signature has left behind by one line.
 *
 * Each NEW number is asserted against the live line it claims before anything is written, and the
 * pass refuses when the OLD number does NOT carry the claim — a shift that would be true either way
 * proves nothing.
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

/** @var array<string, array{0:string,1:int,2:string,3:string}> label => [file, oldLine, newLine-needle, mustContain] */
$assertions = [
    'IDController bloodType write' => [
        'app/Http/Controllers/ID/IDController.php', 184, 185, "'bloodType' =>",
    ],
    'ClearanceController downloadSlip' => [
        'app/Http/Controllers/Clearance/ClearanceController.php', 411, 412, 'public function downloadSlip',
    ],
];

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$failures = 0;
foreach ($assertions as $label => [$rel, $old, $new, $needle]) {
    $lines = explode("\n", (string) file_get_contents($root.'/'.$rel));
    $oldText = trim($lines[$old - 1] ?? '');
    $newText = trim($lines[$new - 1] ?? '');
    $newOk = str_contains($newText, $needle);
    $oldBad = ! str_contains($oldText, $needle);
    printf("assert %-32s %d => %s | old %d = %s\n", $label, $old, $new, $old, mb_substr($oldText, 0, 58));
    printf("       %-32s            new %d = %s\n", '', $new, mb_substr($newText, 0, 58));
    if (! $newOk) {
        echo "  FAIL: the new line does not carry the claim.\n";
        $failures++;
    }
    if (! $oldBad) {
        echo "  FAIL: the old line still carries the claim, so this pointer had not moved.\n";
        $failures++;
    }
}

$edits = [
    ['173, :184', '173, :185', null, 1],
    ['and :411', 'and :412', null, 1],
    ['PDF ( :411', 'PDF ( :412', null, 1],
];

foreach ($edits as $i => $edit) {
    [$from, $to] = $edit;
    $expect = $edit[3];
    $n = substr_count($xml, $from);
    printf("needle %d  %-14s occurs %d (expect %d)\n", $i, $from, $n, $expect);
    if ($n !== $expect) {
        $failures++;
    }
    // The replacement must not already be in the file, or the edit is a no-op wearing a count.
    if (substr_count($xml, $to) > 0 && $to !== $from) {
        printf("  note: %s already occurs %d time(s) elsewhere — checking the delta, not absence\n", $to, substr_count($xml, $to));
    }
}

if ($failures > 0) {
    fwrite(STDERR, "\nREFUSED: {$failures} assertion(s) failed. NOTHING WRITTEN.\n");
    exit(1);
}

docxApply($docx, $root.'/storage/app/scratch/pass290-backup-'.date('Ymd-His').'.docx', $edits, [], $apply);
