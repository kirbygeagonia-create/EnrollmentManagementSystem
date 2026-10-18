<?php

/**
 * Pass 142b — the document's thirty-two ExamController.php citations move by the two lines index() grew.
 *
 * Lane 46 deleted a dead `stage` branch and added a comment in index(), and rewrote results() the same way.
 * Nothing in the document cites results(), so every citation in the file lands in the one region that moved,
 * and it moved uniformly by two.
 *
 * That is not arithmetic taken on trust. storage/app/scratch/lane46-probe-exam-cites.php compares the committed
 * file with the working one and, for each cited range, searches the shifts the edits allow (0, +2, +5) and keeps
 * only the one under which EVERY cited line is byte-identical to what the document saw. All of them resolve at
 * +2; none needed re-reading. Bare continuations are included — the text writes
 * "ExamController.php:165-231 , :225 , :237-248", and a checker that only reads `File.php:NN` never sees the
 * last two. `:225` appears twice in the document and the other one belongs to a §25 print citation, so it is
 * moved by position, with a guard that exactly one of its two occurrences sits after an ExamController citation.
 *
 * This runs BEFORE pass 142, which writes new citations at the post-shift line numbers; had it run after, this
 * pass would have moved the new ones twice.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open the docx\n");
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

$beforePrefixed = preg_match_all('/ExamController\.php:\d+/', $xml);
$guard($beforePrefixed === 32, "the file carries 32 prefixed ExamController.php citations (found {$beforePrefixed})");

// 1. The lone `:225` that belongs to ExamController, chosen by position and guarded by its neighbour.
$needle = 'ExamController.php:165-231';
$at = strpos($xml, $needle);
$bareAt = $at === false ? false : strpos($xml, '>:225<', $at);
$bareAt = $bareAt === false ? false : $bareAt + 1;
$others = 0;
$scan = 0;
while (($p = strpos($xml, ':225', $scan)) !== false) {
    if ($p !== $bareAt) {
        $others++;
    }
    $scan = $p + 1;
}
$guard($bareAt !== false, 'the ExamController-owned `:225` was found after its file name');
$guard($others === 1, 'exactly one other `:225` exists and is left alone (the §25 print citation)');
$xml = $bareAt === false ? $xml : substr_replace($xml, ':227', $bareAt, strlen(':225'));

// 2. The bare continuations, each of which occurs only as an ExamController companion.
foreach ([':237-248' => ':239-250', ':353-360' => ':355-362', ':309-312' => ':311-314', ':309-341' => ':311-343'] as $old => $newer) {
    $n = substr_count($xml, $old);
    $guard($n >= 1, "bare {$old} occurs {$n} time(s)");
    $xml = str_replace($old, $newer, $xml);
}

// 3. Every prefixed citation, one pass so no token is seen twice and no substring collision can occur.
$moved = 0;
$xml = preg_replace_callback('/ExamController\.php:(\d+)(?:-(\d+))?/', function (array $m) use (&$moved): string {
    $moved++;
    $out = 'ExamController.php:'.((int) $m[1] + 2);
    if (isset($m[2]) && $m[2] !== '') {
        $out .= '-'.((int) $m[2] + 2);
    }

    return $out;
}, $xml);
$guard($moved === 32, "all 32 prefixed citations were rewritten ({$moved})");

$guard(substr_count($xml, 'ExamController.php:165-231') === 0 && substr_count($xml, 'ExamController.php:144-389') === 0
    && substr_count($xml, 'ExamController.php:342-369') === 0, 'no old citation survives anywhere');
$guard(substr_count($xml, 'ExamController.php:167-233') === 6 && substr_count($xml, 'ExamController.php:344-371') === 4,
    'the two most-cited ranges now read 167-233 (6) and 344-371 (4), as measured');

// 2b. One continuation drops the colon entirely: "(ExamController.php:234 , 309-341)". After 2 above, the only
// bare 309-341 left is that one, and the guard is what proves it.
$bare341 = substr_count($xml, '309-341');
$guard($bare341 === 1, "exactly one colon-less 309-341 remains after the colonned one moved (found {$bare341})");
$xml = str_replace(' 309-341)', ' 311-343)', $xml);
$guard(substr_count($xml, '309-341') === 0, 'no 309-341 is left citing the old numbering');

$needleNew = substr($xml, strpos($xml, 'ExamController.php:167-233'), 26);
$guard($needleNew === 'ExamController.php:167-233', 'the first rewritten citation reads exactly right: '.$needleNew);

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($xml) === true, 'document.xml parses after the rewrite');
libxml_clear_errors();

printf("\n%s\n", $fails > 0 ? "{$fails} guard(s) failed — nothing written" : 'all guards green');
if ($fails > 0) {
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-17-pass142b.docx');

$z0 = new ZipArchive();
if ($z0->open($src) !== true) {
    fwrite(STDERR, "cannot open the docx to count its parts — nothing written\n");
    exit(1);
}
$partsBefore = count($z0);
$z0->close();

// ZipArchive has no create() and OVERWRITE truncates the whole archive; CREATE plus the part-count check below
// is what makes either mistake impossible to commit.
$zip = new ZipArchive();
if ($zip->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");
    exit(1);
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

$check = new ZipArchive();
$check->open($src);
$onDisk = $check->getFromName('word/document.xml');
$partsAfter = count($check);
$check->close();
if ($onDisk !== $xml) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");
    exit(1);
}
if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — parts were lost, restore the backup\n");
    exit(1);
}

printf("WROTE %s — document.xml byte-verified, all %d parts still present\n", $src, $partsAfter);
