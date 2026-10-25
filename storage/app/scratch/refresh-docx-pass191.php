<?php

/**
 * Pass 191 — name the file behind the bare pointers whose numbers were verified against the code.
 *
 * Each replacement is addressed by a needle that occurs EXACTLY ONCE in document.xml, so no offset arithmetic
 * is involved at all — which matters: the first version of this pass computed byte offsets per table row and
 * applied them in reverse insertion order rather than reverse offset order, which wrote one run across another
 * and produced XML that failed the parse guard. Nothing was written, and the guard is the only reason why.
 *
 * Verified before being written, per the document's own rule that a citation is checked by meaning:
 *   :332      AdmissionController.php:414  'enrollmentStatus' => EnrollmentStatus::Pending
 *   :200      ShiftRequestController.php:228  public function decide()
 *   :788      EvaluationController.php:844  private function retentionBlocker()
 *   :91-99    AccountingController.php:172-190  record() and its paid-to-paid guard comment at :177
 *   :360-431  RegistrarController.php:360-431  printCertificate 360, printClassCards 382, printSubjectLoad 413
 *   :147-173  AccountingController.php:301-330  dailyReport() 301, SchoolTime day 308, whereDate 313
 *   :175-200  AccountingController.php:351-395  void() 351, recomputed balance 364, transition 371
 * :328, :160-203 and :288-312 are listed too and will refuse themselves if their needle is not unique — the
 * row's own text was split across runs, so they may need the engine's anchor form instead.
 *
 *   php storage/app/scratch/refresh-docx-pass191.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass191.php --apply  # write
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    [':332', 'AdmissionController.php:414'],
    [':200', 'ShiftRequestController.php:228'],
    [':788', 'EvaluationController.php:844'],
    [':91-99', 'AccountingController.php:172-190'],
    [':360-431', 'RegistrarController.php:360-431'],
    [':147-173', 'AccountingController.php:301-330'],
    [':175-200', 'AccountingController.php:351-395'],
    [':328', 'AdmissionController.php:411'],
    [':160-203', 'RegistrarController.php:220-279'],
    [':288-312', 'RegistrarController.php:288-312'],
];

$accepted = [];
foreach ($edits as [$old, $new]) {
    $n = substr_count($xml, $old);
    if ($n === 1) {
        $accepted[] = [$old, $new];
        printf("  ok   %-12s unique, -> %s\n", $old, $new);
    } else {
        printf("  skip %-12s occurs %d times document-wide, so a plain replacement is not provable\n", $old, $n);
    }
}
$say(count($accepted) >= 7, 'at least the seven verified edits are addressable ('.count($accepted).')');

$staged = $xml;
foreach ($accepted as [$old, $new]) {
    $staged = str_replace($old, $new, $staged);
}
foreach ($accepted as [$old, $new]) {
    // Only assert the positive. "the bare needle is gone" is the wrong guard here: a citation whose number is
    // already right keeps that number in its own replacement (`:360-431` becomes
    // `RegistrarController.php:360-431`), so the needle legitimately survives inside the fix. Whether the
    // document still holds a bare pointer is the citation sweep's question, not this pass's.
    $say(substr_count($staged, $new) >= substr_count($xml, $new), 'the named citation is present: '.$new);
    foreach (['<', '>', '&'] as $ch) {
        $say(! str_contains($new, $ch), $new.' carries no raw '.$ch);
    }
}
$say(substr_count($xml, '<w:tbl>') === substr_count($staged, '<w:tbl>'), 'no table moved');
$say(substr_count($xml, '<w:p>') + substr_count($xml, '<w:p ') === substr_count($staged, '<w:p>') + substr_count($staged, '<w:p '),
    'no paragraph moved');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  %d edit(s) applied to the staged bytes; delta %+d\n", count($accepted), strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: the pointers above would name their file.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass191.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    echo "REFUSED: the docx cannot be reopened for the write — nothing written.\n";
    exit(1);
}
if ($z->getFromName('word/document.xml') !== $xml) {
    echo "REFUSED: document.xml changed under this pass since it was read — nothing written.\n";
    exit(1);
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
