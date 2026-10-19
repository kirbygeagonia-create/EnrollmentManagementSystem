<?php

/**
 * Pass 151 — re-point the citations pass 150 left behind, and the two pointers in the same rows that were
 * already wrong before this batch.
 *
 * Lane 51 added an import line at the top of eight JSX files and moved code in four PHP files. Pass 150 moved
 * the PHP pointers and the two JSX ones whose shift its probe happened to find; the probe scanned -14..+14 and
 * took the FIRST offset that made a cited line byte-identical, so a one-line citation that coincidentally
 * matched a `});` twelve rows earlier reported a confident wrong move and the rest reported "unchanged".
 * Nothing downstream complained: a pointer that is one row short still lands inside the same method, and the
 * sweep only refuses a line past the end or a blank one. Reading HEAD's own document against `git diff -U0`
 * (lane51-reconcile-cites.php) is what settled it.
 *
 * Every move here is a whole string, asserted unique before it is written, and confirmed against the working
 * copy's own bytes — not arithmetic. Two pointers are pre-existing drift, not lane 51's: §25.6 pointed at
 * PrintSlip.jsx:41 for a line that was at 46 in HEAD, and at :83-85 for the slip's receipt rows, which live at
 * 103-105 of the Blade template. They are NOT edited in this pass: the sentence holding them also claims the PDF never reads overallStatus and stamps SEAIT CLEARED unconditionally, which the Blade stopped doing on 2026-10-02, when it began stamping HELD unless a box is open. A true number under a false claim is the worse half-fix, so that row waits for section 25.6 of its own re-read -- see lane51-endstate.txt.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

/** each: [ needle, replacement, expected occurrences, proof that the new number is the cited code ] */
$edits = [
    ['AuthenticatedLayout.jsx:19', 'AuthenticatedLayout.jsx:20', 1,
        "resources/js/Layouts/AuthenticatedLayout.jsx", 20, "blocking:"],
    ['AuthenticatedLayout.jsx:60-82, :84-112', 'AuthenticatedLayout.jsx:61-83, :85-113', 1,
        "resources/js/Layouts/AuthenticatedLayout.jsx", 61, "Fetch notifications"],
    ['PrintLayout.jsx:28', 'PrintLayout.jsx:30', 1,
        "resources/js/Components/ui/PrintLayout.jsx", 30, "seait-logo"],
    ['PrintSlip.jsx:24-37', 'PrintSlip.jsx:25-38', 1,
        "resources/js/Pages/Clearance/PrintSlip.jsx", 25, "headerContent"],
    ['PrintSlip.jsx:29-35', 'PrintSlip.jsx:30-36', 1,
        "resources/js/Pages/Clearance/PrintSlip.jsx", 32, "Course:"],
    ['PrintSlip.jsx:58-59', 'PrintSlip.jsx:59-60', 1,
        "resources/js/Pages/Clearance/PrintSlip.jsx", 59, "return ("],
    ['PrintSlip.jsx:88-93', 'PrintSlip.jsx:89-94', 1,
        "resources/js/Pages/Clearance/PrintSlip.jsx", 89, "Overall Status"],
    ['WorkflowStepper.jsx:12', 'WorkflowStepper.jsx:13', 1,
        "resources/js/Components/ui/WorkflowStepper.jsx", 13, "skipped"],
    ['the signature at :273', 'the signature at :274', 1,
        "app/Http/Controllers/Accounting/AccountingController.php", 274, "signStepByOffice"],
    ['again at :273', 'again at :274', 1,
        "app/Http/Controllers/Accounting/AccountingController.php", 274, "signStepByOffice"],
    ['box at :226', 'box at :227', 1,
        "app/Http/Controllers/Accounting/AccountingController.php", 227, "signStepByOffice"],
    ['( :187 , :171-231 )', '( :188 , :172-232 )', 2,
        "app/Http/Controllers/Accounting/AccountingController.php", 188, "min:0.01"],
];

$zip = new ZipArchive();

if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);

    if (! $ok) {
        $fails++;
    }
};

// ---- prove each new number names the code the sentence means -----------------------

$linesOf = function (string $p): array {
    return explode(chr(10), str_replace("\r\n", chr(10), (string) file_get_contents($p)));
};

foreach ($edits as $e) {
    [$from, $to, $count, $file, $line, $token] = $e;
    $work = $linesOf($root.'/'.$file);
    $at = $line <= count($work) ? $work[$line - 1] : 'PAST END of a '.count($work).'-line file';

    $guard(str_contains($at, $token), sprintf('%s -> %s :%d holds %s — "%s"',
        mb_substr($from, 0, 34), basename($file), $line, $token, trim(mb_substr($at, 0, 48))));
}

// ---- apply -----------------------------------------------------------------------

$new = $xml;

foreach ($edits as $e) {
    [$from, $to, $count] = $e;
    $seen = substr_count($new, $from);
    $guard($seen === $count, sprintf('needle "%s" occurs %d time(s) as expected', mb_substr($from, 0, 44), $seen));

    if ($seen !== $count) {
        continue;
    }

    $new = str_replace($from, $to, $new);
}

$guard($fails === 0, 'no needle was unexpected — nothing was applied twice or skipped');

foreach ($edits as $e) {
    [$from, $to, $count] = $e;
    $guard(substr_count($new, $from) === 0, sprintf('the old number "%s" is gone', mb_substr($from, 0, 40)));
    $guard(substr_count($new, $to) >= $count, sprintf('the new number "%s" is on the page', mb_substr($to, 0, 40)));
}

// ---- the revision row records that the first pass was re-measured -----------------

$anchor = 'arriving as a real report for 2027-02-13.';
$append = ' The pointers themselves were then re-measured against the document as it stood in HEAD, and that '
    .'second look found sixteen the first pass had left behind: eight into JSX files that gained one line at the '
    .'top, and eight bare continuations — `the signature at :273` — which name no file, so a checker keyed to '
    .'`File.php:NN` cannot see them at all. A pointer one row short still lands on a live line, which is exactly '
    .'why the bounds sweep cleared it and a reading of the bytes was needed. Two pointers in §25.6 were already '
    .'wrong before this batch and are left for that section’s own re-read, because the sentence around them needs '
    .'re-measuring too, not only its numbers.';

$guard(substr_count($new, $anchor) === 1, 'the revision row closes with the lane-51 sentence exactly once');
$new = str_replace($anchor, $anchor.$append, $new, $applied);
$guard($applied === 1, 'the re-measurement sentence was appended');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses after the sixteen moves and the appended sentence');
libxml_clear_errors();

printf("\n%d needle(s) rewritten (sixteen citation pointers), %d byte(s) added to document.xml\n", count($edits), strlen($new) - strlen($xml));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");

    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";

    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass151.docx');

$z0 = new ZipArchive();
$z0->open($src);
$partsBefore = count($z0);
$z0->close();

$z1 = new ZipArchive();

if ($z1->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");

    exit(1);
}

$z1->addFromString('word/document.xml', $new);
$z1->close();

$check = new ZipArchive();
$check->open($src);
$onDisk = $check->getFromName('word/document.xml');
$partsAfter = count($check);
$check->close();

if ($onDisk !== $new) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");

    exit(1);
}

if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — restore the backup\n");

    exit(1);
}

printf("WROTE %s — byte-verified, all %d parts present\n", $src, $partsAfter);
