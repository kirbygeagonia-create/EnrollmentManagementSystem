<?php

/**
 * Pass 76 — the print-reference decision, and the first tool for the real-data migration.
 *
 * 1. §27's retention paragraph enumerates the console commands as a closed list of three. A fourth
 *    now exists, and a sentence that says "contains only A, B, and C" is the kind of claim a panel
 *    checks. Rewritten as a colon enumeration so all four names stay in the code font.
 * 2. The revision row gains what the same day actually settled: the reference photographs were
 *    found outside the repository and deliberately left out of it (they are filled, signed forms,
 *    and the repo is public), the structural comparison is now a document with ten open questions
 *    for the Registrar, and ems:import:students is the migration's dry-run first step.
 *
 * Dry-run by default. Refuses on any anchor that is not exactly once, and on overlapping ranges.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$edits = [];

$refuse = function (string $why) use ($xml, $esc): void {
    fwrite(STDERR, "REFUSE: {$why}\n");
    exit(1);
};

// ---- 1. the command roster --------------------------------------------------------
$lead = ' contains only ';
$leadNew = ' holds these four: ';
if (substr_count($xml, $esc($lead)) !== 1) {
    $refuse("the lead-in '{$lead}' seen ".substr_count($xml, $esc($lead))." times");
}
$edits[] = [strpos($xml, $esc($lead)), strlen($esc($lead)), $esc($leadNew)];

$anchor = 'CreateAdminUser';
if (substr_count($xml, $anchor) !== 1) {
    $edits = [];

    $refuse("the anchor {$anchor} is not unique, so the positional edits below cannot be resolved");
}

$join = ', and ';
$joinAt = strpos($xml, $esc($join), strpos($xml, $anchor) + strlen($anchor));
if ($joinAt === false) {
    $edits = [];

    $refuse("no '{$join}' after {$anchor}");
}
$edits[] = [$joinAt, strlen($esc($join)), $esc(', ')];

$last = 'PrintFidelitySamples';
$lastAt = strpos($xml, $last, $joinAt + strlen($esc($join)));
if ($lastAt === false) {
    $edits = [];

    $refuse("no {$last} after the conjunction");
}
$edits[] = [$lastAt, strlen($last), 'ImportStudents, '.$last];

// ---- 2. revision row append -------------------------------------------------------
$tailOld = 'did not implement those two methods.';
$tailNew = $tailOld
    .' The same day settled where the print reference material lives: the four photographs of the '
    .'physical Registrar forms were found in the superseded EnrollmentSystem folder, outside this '
    .'repository, and they were deliberately not committed — they are filled and signed papers carrying '
    .'a student number and three staff members’ names and signatures, and the repository is public. '
    .'.gitignore now excludes Documentation/Images, and both ems:print-fidelity and walkthrough row 12.10 '
    .'name the paper on the desk instead of a file. A structural comparison of the four printed papers '
    .'against those forms is written up in Documentation/Print-Fidelity-Comparison-2026-10-10.md: page '
    .'size and orientation read from each PDF’s own page box, labels and columns read from the template '
    .'that renders them, ten questions left for the Registrar to answer because they concern the paper, '
    .'not the code — among them that our class card prints the day twice, that the subject load our '
    .'system signs (Student, Class Adviser, Registrar) is signed by different people on the physical '
    .'form (Evaluated by, Processed by), and that the block schedule is a meeting list where the wall '
    .'chart is a weekly grid. The real-data migration also gained its first tool: '
    .'php artisan ems:import:students reports what a Registrar sheet would import and what it would '
    .'refuse, against a column map the command itself prints, and writes nothing — nine tests pin the '
    .'report, each of them re-run under a mutant that makes the command write.';

if (substr_count($xml, $esc($tailOld)) !== 1) {
    $refuse("revision-row tail seen ".substr_count($xml, $esc($tailOld))." times");
}
$edits[] = [strpos($xml, $esc($tailOld)), strlen($esc($tailOld)), $esc($tailNew)];

// ---- apply, descending, refusing any overlap ------------------------------------
usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$nextStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $nextStart) {
        $refuse('overlapping or tied edit ranges');
    }
    $nextStart = $e[0];
}

foreach ($edits as [$at, $len, $repl]) {
    printf("ok   @%-9d len=%-3d → %s\n", $at, $len, substr($repl, 0, 60));
}

$newXml = $xml;
foreach ($edits as [$at, $len, $repl]) {
    $newXml = substr_replace($newXml, $repl, $at, $len);
}

$parseOk = (function () use ($newXml) {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $r = @simplexml_load_string($newXml);
    libxml_clear_errors();

    return $r !== false;
})();

$containersSame = substr_count($xml, '<w:tbl>') === substr_count($newXml, '<w:tbl>')
    && substr_count($xml, '<w:p>') === substr_count($newXml, '<w:p>')
    && substr_count($xml, '</w:p>') === substr_count($newXml, '</w:p>');

$staleGone = substr_count($newXml, $esc($lead)) === 0
    && substr_count($newXml, 'ImportStudents, PrintFidelitySamples') === 1
    && substr_count($newXml, $esc($tailOld)) === 1; // only as the prefix of its own replacement

if (! $parseOk || ! $containersSame || ! $staleGone) {
    $refuse(sprintf(
        'post-write guards (parses=%s containers=%s stale=%s)',
        $parseOk ? 'yes' : 'NO',
        $containersSame ? 'yes' : 'NO',
        $staleGone ? 'yes' : 'NO'
    ));
}

echo "guards  parses; no container moved; roster now names four; revision tail appended\n";

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass76.docx');
$z2 = new ZipArchive();
$z2->open($src);
$z2->addFromString('word/document.xml', $newXml);
$z2->close();

$z3 = new ZipArchive();
$z3->open($src);
$back = $z3->getFromName('word/document.xml');
$z3->close();

if ($back !== $newXml) {
    $refuse('disk does not hold the edited document.xml');
}

echo "Wrote {$src}\n";
