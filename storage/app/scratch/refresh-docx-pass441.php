<?php

/**
 * Pass 441 — the badge sentences pass 440 planned, on top of the row it already wrote.
 *
 * Pass 440 wrote its §1.1 row and then the engine refused the sentences: it resolves every
 * needle against the archive as it stands, so a needle that CONTAINS another needle's text (the
 * §16 paragraph quoted the same badge string the phrase edit was about to rewrite) and a short
 * needle whose long twin was meant to retire first (bare `Phase 0.5` inside `Phase 0.5 · BR9`)
 * both came back unresolved. A half-applied register is the defect this repository refuses, and
 * the refusal was the correct outcome — the row landed, the sentences did not.
 *
 * So the two spans that overlap another needle are written on the raw archive bytes first, each
 * asserted to occur exactly once, and the engine then sees the counts its own resolution model
 * expects. The one quoted occurrence (`is "Phase 0.5"`) is raw-only as well, because in
 * `document.xml` the quotation marks are `&quot;` and a plain-text needle can never reach them.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass441.php [--apply]
 */

$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$readPart = static function (string $path, string $part): string {
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        fwrite(STDERR, "REFUSED: the docx is not a readable archive.\n");
        exit(1);
    }
    $xml = $zip->getFromName($part);
    $zip->close();
    if (! is_string($xml) || $xml === '') {
        fwrite(STDERR, "REFUSED: {$part} could not be read out of the archive.\n");
        exit(1);
    }

    return $xml;
};

$xml = $readPart($docx, 'word/document.xml');

// The row pass 440 wrote must be there — this pass is its second half, not a fresh clone.
if (substr_count($xml, $escape('Three nav surfaces and thirteen desk pages')) !== 1) {
    $fail('the §1.1 row from pass 440 is not in the document exactly once, so this pass would be finishing a run that never landed.');
}

// ------------------------------------------------- raw-byte edits: the spans that overlap
$para = 'its summary control reads';
$paraEnd = 'the Registrar to be sixth.';
$start = strpos($xml, $para);
$end = strpos($xml, $paraEnd, (int) $start);

if ($start === false || $end === false) {
    $fail('the §16 paragraph could not be bounded by its own first and last words.');
}
if (substr_count($xml, $para) !== 1 || substr_count($xml, $paraEnd) !== 1) {
    $fail('a §16 boundary word occurs more than once, so the span is not the paragraph this pass means.');
}
$oldSpan = substr($xml, $start, $end + strlen($paraEnd) - $start);
$decoded = html_entity_decode($oldSpan, ENT_QUOTES | ENT_XML1);

foreach (['Workflow Phase — Phase 5', 'Phase 5 · Registrar Central Desk', 'Registrar/Index.jsx:150-151', 'DeskSubNav.jsx:74', 'MegaAppLauncher.jsx:139', '§28.3 U-4 closed on 2026-10-01'] as $mustHold) {
    if (! str_contains($decoded, $mustHold)) {
        $fail("the bounded §16 span does not hold [{$mustHold}] — the span is not the sentence this pass re-takes.");
    }
}

$newSpan = 'its summary control reads “Workflow Phase — Phase 4 of 7” and its page badge reads “Phase 4 of 7 · Registrar Central Desk” ( Registrar/Index.jsx:152-153 , :111 ). Both numbers are derived, not typed: WorkflowService::boxOrder() declares the seven boxes in signing order, HandleInertiaRequests.php shares that declaration as an ordered list, and the page names the box it works and lets the shared list number it. That is what §28.3 U-4 needed to be truly closed, because the two labels did agree on 2026-10-01 — on the wrong number. The tracker under the header numbers the boxes the RECORD holds, so a continuing student whose form omits the Assessment box reads “Phase 3 of 6” there while the header still says “Phase 4 of 7”, and the two answer different questions rather than disagreeing. The navigation surfaces that carried their own zero-through-eight numbering now name the same boxes ( MegaAppLauncher.jsx:155 , AuthenticatedLayout.jsx:20 ), and no React file spells a phase number at all: a case walks every source under the JavaScript tree and refuses the build when one does.';
$newSpan = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $newSpan);

$quotedOld = 'is &quot;Phase 0.5&quot;.';
$quotedNew = 'is &quot;Entrance Examination&quot;.';
if (substr_count($xml, $quotedOld) !== 1) {
    $fail('the quoted badge sentence occurs '.substr_count($xml, $quotedOld).' time(s), expected 1 — read how the quotes are stored before writing.');
}

$xml2 = str_replace($oldSpan, $newSpan, $xml);
$xml2 = str_replace($quotedOld, $quotedNew, $xml2);

// §9's own badge sentence is a bare `Phase 0.5` whose run holds nothing else, and whose
// "UI badge: " prefix lives in another run, so no text needle reaches it. The run boundaries are
// the discriminator: the labelled copies read `Phase 0.5 · BR9` inside their run and the quoted
// copy reads `is "Phase 0.5".`, so exactly one run is `>Phase 0.5<`.
$bareOld = '>Phase 0.5<';
$bareNew = '>Entrance Examination<';
if (substr_count($xml2, $bareOld) !== 1) {
    $fail('the bare 0.5 badge reads '.substr_count($xml2, $bareOld).' time(s), expected 1 — the period is not the discriminator this pass believed.');
}
$xml2 = str_replace($bareOld, $bareNew, $xml2);

if ($xml2 === $xml) {
    $fail('the raw edits changed no bytes.');
}

// ------------------------------------------------- engine edits, measured on the raw-edited archive
$edits = [
    ['The codebase uses three independent numbering systems, and screens display the legacy one.',
        'The codebase used three independent numbering systems, and the screens displayed the legacy one; since 2026-11-04 the screens display the workflow box numbering itself, so a UI badge and a stepOrder integer answer the same question rather than two.', 1],
    ['Legacy UI badge', 'UI badge, which is the workflow box', 1],
    ['Closed 2026-10-01: the summary and the page badge both read Phase 5, so the screen no longer contradicts itself about which phase the Registrar sits in.',
        'Closed 2026-10-01 as to the two labels agreeing, and closed again 2026-11-04 as to the number: both read the Registrar’s box as the fourth of the seven the enrollment form declares, taken from the workflow’s own ordered list rather than typed, so the header and the tracker under it answer with one vocabulary.', 1],
    ['Phase 0 · Admissions Desk', 'Admissions Intake', 4],
    ['Phase 0.5 · BR9', 'Entrance Examination', 4],
    ['Phase 1 · Campus Clearance', 'Campus Clearance', 2],
    ['Phase 2 · Department Evaluation', 'Phase 1 of 7', 2],
    ['Phase 3 · Assessment Desk', 'Phase 2 of 7 · Assessment Desk', 2],
    ['Phase 4 · Cashier Desk', 'Phase 3 of 7 · Cashier Desk', 2],
    ['Phase 5 · Registrar Central Desk', 'Phase 4 of 7 · Registrar Central Desk', 2],
    ['Phase 6 · Section Blocking', 'Phase 5 of 7 · Section Blocking', 2],
    ['Phase 7 / Phase 8', 'Phase 6 of 7 / Phase 7 of 7', 1],
    ['Phase 7 · Clinic Desk', 'Phase 6 of 7 · Clinic Desk', 1],
    ['Phase 8 · ID Office', 'Phase 7 of 7 · ID Office', 1],
];

foreach ($edits as $i => $e) {
    $n = substr_count($xml2, $escape($e[0]));
    if ($n !== $e[2]) {
        $fail("edit {$i} matches {$n} time(s) on the raw-edited archive, expected {$e[2]}: [".mb_substr($e[0], 0, 80).'…]');
    }
    if (preg_match('/[<>]/', $e[1]) === 1) {
        $fail("edit {$i} carries markup the engine refuses to write.");
    }
}

printf("stage 1 plans 2 raw edit(s) and %d sentence(s). %s\n", count($edits), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass441-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}

$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for the raw edits.');
}
$zip->addFromString('word/document.xml', $xml2);
$zip->close();

docxApply($docx, $backup, array_map(
    static fn (array $e): array => [$e[0], $e[1], null, $e[2]],
    $edits
), [], true);

// ------------------------------------------------- read back
$read = $readPart($docx, 'word/document.xml');

foreach ($edits as $e) {
    if (substr_count($read, $escape($e[0])) !== 0) {
        copy($backup, $docx);
        $fail('a retired badge spelling is still in the document: ['.mb_substr($e[0], 0, 70).'…] — the backup was restored.');
    }
}

if (substr_count($read, $escape('Phase 0.5')) !== 0) {
    copy($backup, $docx);
    $fail('the retired 0.5 spelling survives somewhere this pass did not reach — the backup was restored.');
}

foreach (['Workflow Phase — Phase 4 of 7', 'UI badge, which is the workflow box', 'Both numbers are derived, not typed'] as $phrase) {
    if (substr_count($read, $escape($phrase)) !== 1) {
        copy($backup, $docx);
        $fail("[{$phrase}] did not come back exactly once — the backup was restored.");
    }
}

echo 'WROTE 2 raw edit(s) and '.count($edits)." sentence(s); the document spells no legacy badge number.\n";
unlink($backup);
exit(0);
