<?php

/**
 * Pass 225 — the fourteenth and fifteenth wrong targets, both inside one Accounting row.
 *
 * §28's row 8 answers "Sign the Accounting box by hand" with:
 *   "an account that owes nothing is signed out by settleNoBalance() ( AccountingController.php:260-297 , the
 *    signature at :274 ), and a collected payment signs the same box at :227 ."
 *
 * Both ranges land on the wrong statement, and both in the same file, so the bounds sweep clears them:
 *   - `:274` is `return redirect()->route('accounting.show', …)` — the refusal for an account that still owes.
 *     The signature `settleNoBalance()` actually writes is at **:290**, inside the paid transition at :283-292.
 *   - `:227` is `if ($newBalance > 0) { … paymentStatus = Partial }` — the receipt's own status word, which is the
 *     *opposite* branch. A collected payment signs the box at **:243**, inside `if ($newBalance <= 0 …)` at :237.
 *
 * The old lines are asserted too, so the drift is proved rather than claimed: this pass refuses unless :227 really
 * is the `> 0` branch and :274 really is the redirect. Both needles are contiguous in `document.xml` and unique, so
 * they travel as two `text` edits; `:227` alone occurs twice in the file, which is why neither needle is a bare
 * number.
 *
 *   php storage/app/scratch/refresh-docx-pass225.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass225.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$ac = (array) file($root.'/app/Http/Controllers/Accounting/AccountingController.php');
$fails = 0;
foreach ([
    [$ac, 227, 'if ($newBalance > 0) {'],
    [$ac, 228, "paymentStatus' => PaymentStatus::Partial"],
    [$ac, 237, 'if ($newBalance <= 0'],
    [$ac, 243, 'signStepByOffice($workflow, OfficeId::Accounting->value'],
    [$ac, 260, 'public function settleNoBalance('],
    [$ac, 274, "return redirect()->route('accounting.show'"],
    [$ac, 283, 'if ($enrollment) {'],
    [$ac, 290, 'signStepByOffice($workflow, OfficeId::Accounting->value'],
    [$ac, 296, '}'],
] as [$l, $n, $needle]) {
    $t = trim($l[$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 62));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "REFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    ['signs the same box at :227', 'signs the same box at :243'],
    ['the signature at :274', 'the signature at :290'],
];
$textEdits = [];
foreach ($edits as [$needle, $replacement]) {
    $n = substr_count($xml, $needle);
    printf("\n  needle [%s] occurs %d time(s) in document.xml (want 1)\n", $needle, $n);
    if ($n !== 1) {
        echo "REFUSED: a needle is not unique, so this pass will not guess which copy to move.\n";
        exit(1);
    }
    $textEdits[] = [$needle, $replacement, null, 1];
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass225.docx', $textEdits, [], $apply);
