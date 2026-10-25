<?php

/**
 * Pass 227 — the twenty-third wrong target, and the first one found by READING the queue the anchor checker
 * had been tallying but never printing.
 *
 * §28's row 9 and §27's deadlock row both name a checklist key `registrar_ready`. No such key exists anywhere in
 * `app/`, `resources/`, `routes/`, `tools/`, `tests/`, the markdown guides or CI — the array returned at
 * `RegistrarController.php:151-162` holds exactly seven keys, and the one these sentences mean is
 * `registrarApprovalPending` at :156, which is false precisely when the next pending box is some other office.
 * So the claim was right and the identifier it quoted was invented — the shape a bounds sweep cannot see, because
 * `:145-156` and `:156` are both live lines of the file the row names.
 *
 * The row's own "seven-gate" figure is asserted too, so the count is measured rather than kept.
 *
 *   php storage/app/scratch/refresh-docx-pass227.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass227.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$rc = (array) file($root.'/app/Http/Controllers/Registrar/RegistrarController.php');
$fails = 0;
$keys = [];
foreach ($rc as $i => $row) {
    if ($i + 1 >= 151 && $i + 1 <= 175 && preg_match("/^\s+'([a-zA-Z_]+)' =>/u", (string) $row, $m) === 1) {
        $keys[] = ($i + 1).':'.$m[1];
    }
}
foreach ([[156, "'registrarApprovalPending' => \$nextPendingOffice === OfficeId::Registrar->value"],
    [152, "'evaluation_signed'"],
    [162, "'prerequisites_met'"],
] as [$n, $needle]) {
    $t = trim((string) ($rc[$n - 1] ?? ''));
    $ok = str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 66));
    $fails += $ok ? 0 : 1;
}
printf("  checklist keys found between :151 and :175: %d  [%s]\n", count($keys), implode(', ', $keys));
if (count($keys) !== 7) {
    echo "BAD    the row says seven gates; the array answers a different number.\n";
    $fails++;
}
if (str_contains(implode('', $rc), 'registrar_ready')) {
    echo "BAD    the controller does define registrar_ready, so the document was right.\n";
    $fails++;
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

$n = substr_count($xml, 'registrar_ready');
printf("\n  needle [registrar_ready] occurs %d time(s) in document.xml (want 2)\n", $n);
if ($n !== 2) {
    echo "REFUSED: the count is not what this pass measured.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass227.docx',
    [['registrar_ready', 'registrarApprovalPending', null, 2]], [], $apply);
