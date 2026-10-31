<?php

/**
 * Docx pass 326c — the comma-list citation `lane76-shift-cites.php` deliberately refused to half-move.
 *
 * `( AccountingController.php:228 , :212-272 )` is ONE citation carrying TWO numbers under one file name, and the
 * shift tool resolves heads only: it moved `228` nowhere (I skipped it) and deferred `:212-272` as BARE-DEFERRED
 * because its nearest preceding name in the 160-byte attribution window is `PaymentPolicy.php`. Moving the head and
 * leaving the tail is worse than leaving both — the pair would read as a valid citation pointing at a retired
 * layout — so both numbers are taken here, in one write, after reading both endpoints in both versions of the file.
 *
 * What was read (not assumed), HEAD vs live AccountingController.php at +15 throughout:
 *   HEAD 228 `'amount' => 'required|numeric|min:0.01',`   = live 243, byte-identical — the ₱0 rule three sentences cite
 *   HEAD 212 `public function record(`                    = live 227, byte-identical — the cited method's signature
 *   HEAD 272 `'remainingBalance' => $newBalance,`         = live 287, byte-identical — the balance write that ends it
 * The needle `AccountingController.php:228` occurs three times in document.xml and all three make the SAME claim
 * (the validator refusing ₱0), so a blanket replace with expect=3 is correct here rather than the usual hazard; the
 * count is asserted, not trusted, and the retired spellings are asserted gone.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass326c.php [--apply]
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

$textEdits = [
    // null anchor + expect>1 tells the engine "this needle must occur exactly N times, and every copy is mine".
    ['AccountingController.php:228', 'AccountingController.php:243', null, 3],
    [':212-272', ':227-287', null, 2],
];

docxApply($docx, $root.'/storage/app/scratch/pass326c-dry.docx', $textEdits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the written archive (an error CODE is truthy).\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$checks = [
    ['AccountingController.php:243', 3, 'the min-0.01 rule, three sentences'],
    ['AccountingController.php:228', 0, 'the retired head must be gone'],
    [':227-287', 2, 'record() body, both copies'],
    [':212-272', 0, 'the retired tail must be gone'],
];

$bad = 0;
foreach ($checks as [$needle, $want, $why]) {
    $n = substr_count($after, $needle);
    printf("%-30s %d (want %d)  %s\n", $needle, $n, $want, $why);
    if ($n !== $want) {
        $bad++;
    }
}

if ($bad > 0) {
    fwrite(STDERR, "REFUSED: the write reported success and the read-back disagrees — restore the newest pass326c backup by hand if one was written.\n");

    exit(1);
}

echo 'written, re-read, head and tail moved together.'."\n";
