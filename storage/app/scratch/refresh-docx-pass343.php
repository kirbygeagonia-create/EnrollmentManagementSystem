<?php

/**
 * Docx pass 343 — re-take the three bare pointers `lane76-shift-cites.php` deferred, each by reading both endpoints.
 *
 * The shift tool moved 77 tokens for the two controllers this batch edited (+2 in RegistrarController, +3 in
 * ClearanceController) and refused to write anything until its queue was resolved; the queue items were a GONE window
 * (mapped after reading to 407-433, since the insertion sits inside that action and its signature did not move) and
 * three BARE tails the tool will not attribute by distance. A half-moved pair — head shifted, tail not — looks MORE
 * valid than a missing pointer, so these three are written here with the claim each new number carries asserted
 * against the live line first.
 *
 *   ClearanceController.php:407 and :435            — the slip's print action and its PDF sibling; live 438 is
 *                                                    `public function downloadSlip(...)`.
 *   downloadSlip() serves the PDF ( :435 )          — the same action in §25.1's print-route table; 428 (its own
 *                                                    head) already moved in the shift run.
 *   RegistrarController.php:216-221 , :219          — the seven-gate checklist array and the key inside it; live 221
 *                                                    is `'registrarApprovalPending' => ...`. This tail is its own
 *                                                    Consolas run, so the needle is the run boundary `>:219<`, which
 *                                                    occurs once document-wide — `, :219` does NOT, because that
 *                                                    spelling belongs to IDController.php's bloodType comma list,
 *                                                    a pointer this batch must not touch.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass343.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass343-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

/** The live line, or null when the file or the line is not there. */
$line = static function (string $rel, int $n) use ($root): ?string {
    $lines = explode("\n", (string) file_get_contents($root.'/'.ltrim($rel, '/')));

    return $lines[$n - 1] ?? null;
};

$assertions = [
    ['app/Http/Controllers/Clearance/ClearanceController.php', 438, 'downloadSlip'],
    ['app/Http/Controllers/Clearance/ClearanceController.php', 428, "Inertia::render('Clearance/PrintSlip'"],
    ['app/Http/Controllers/Registrar/RegistrarController.php', 221, 'registrarApprovalPending'],
    ['app/Http/Controllers/Registrar/RegistrarController.php', 216, 'return ['],
    ['app/Http/Controllers/Clearance/ClearanceController.php', 407, 'public function printSlip'],
];

$bad = 0;
foreach ($assertions as [$rel, $n, $needle]) {
    $src = $line($rel, $n);
    $ok = $src !== null && str_contains($src, $needle);
    printf('  %s %s:%d carries %s%s', $ok ? 'ok  ' : 'BAD ', basename($rel), $n, $needle, PHP_EOL);
    if (! $ok) {
        echo '       the line reads: '.trim((string) $src)."\n";
        $bad++;
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: a pointer's claim did not match the line it is offered for; nothing written.\n");

    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    ['ClearanceController.php:407 and :435', 'ClearanceController.php:407 and :438', null, 1],
    ['serves the PDF ( :435 )', 'serves the PDF ( :438 )', null, 1],
];
// The third pointer cannot travel the engine: the digits are their own Consolas run, so the only contiguous needle
// is the run boundary `>:219<`, and `docxApply` refuses a replacement carrying a tag character (correctly — a text
// edit writes plain characters into a run). It is written below by its own guarded zip round-trip, after the engine
// has written, because the engine re-reads the file from disk and an earlier in-memory edit would be discarded.
$runEdit = ['>:219<', '>:221<'];

foreach ($textEdits as $i => [$from, $to, , $want]) {
    $n = substr_count($xml, $from);
    printf('edit %d: anchor occurs %d time(s), want %d — %s%s', $i, $n, $want, substr($from, 0, 40), PHP_EOL);
    if ($n !== $want) {
        $bad++;
    }
    if (str_contains($to, '<') || str_contains($to, '>')) {
        $bad++;
        echo "  edit {$i} carries a raw tag character the engine would refuse\n";
    }
}
$runHits = substr_count($xml, $runEdit[0]);
printf('run edit: anchor occurs %d time(s), want 1 — %s%s', $runHits, $runEdit[0], PHP_EOL);
if ($runHits !== 1) {
    $bad++;
}
if ($bad > 0) {
    $applied = substr_count($xml, $runEdit[1]) === 1;
    foreach ($textEdits as [, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $applied = false;
        }
    }
    echo $applied ? "ALREADY APPLIED.\n" : "REFUSED — nothing written.\n";

    exit($applied ? 0 : 1);
}

docxApply($docx, $backup, $textEdits, [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

// --- the run-boundary edit, on the bytes the engine actually wrote ------------------------------------------
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open for the run edit.\n");
}
$before = (string) $zip->getFromName('word/document.xml');
$zip->close();
if (substr_count($before, $runEdit[0]) !== 1) {
    fwrite(STDERR, 'REFUSED: the run needle matched '.substr_count($before, $runEdit[0]).' time(s) after the engine'
        ." wrote; nothing more was changed.\n");

    exit(1);
}
$patched = str_replace($runEdit[0], $runEdit[1], $before);
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open for the run write.\n");
}
$zip->addFromString('word/document.xml', $patched);
$zip->close();

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the written docx did not open as a zip.\n");
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$fail = 0;
$checks = array_merge($textEdits, [$runEdit]);
foreach ($checks as $i => [$from, $to]) {
    $newN = substr_count($after, $to);
    $oldN = substr_count($after, $from);
    printf('  check %d: new form %d (want 1), retired form %d (want 0)%s', $i, $newN, $oldN, PHP_EOL);
    if ($newN !== 1 || $oldN !== 0) {
        $fail++;
    }
}
if ($fail > 0) {
    fwrite(STDERR, "REFUSED: read-back failed; restoring the pass's own backup.\n");
    if (is_file($backup)) {
        copy($backup, $docx);
    }

    exit(1);
}

echo "written, re-read: the three deferred tails name the lines their own sentences claim.\n";
