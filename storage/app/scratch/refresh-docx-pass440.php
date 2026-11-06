<?php

/**
 * Pass 440 — the workflow phase vocabulary: what the document said the badges read, and the row.
 *
 * §6's lifecycle table and thirteen "UI badge:" sentences in §8–§18 stated the chrome numbers as
 * present-tense facts, and §16's paragraph claimed the Registrar's two labels agreed. They agreed —
 * on a number the enrollment form does not give that desk. The batch this row records derives every
 * phase number from `WorkflowService::boxOrder()`, so every one of those sentences is re-taken in
 * this pass rather than left describing an older build.
 *
 * Ordering is the lane-69 rule: the long phrase goes before the short one it contains, so
 * "Phase 0.5 · BR9" is retired before bare "Phase 0.5" is asked to mean the same thing. The
 * §1.1 row is written on the archive bytes FIRST and `docxApply()` runs after, because the engine
 * re-reads the file from disk (the pass-252 lesson).
 *
 * Usage: php storage/app/scratch/refresh-docx-pass440.php [--apply]
 *
 * ARTEFACT OF ITS OWN RUN (2026-11-04): this pass wrote its §1.1 row onto the archive and then the
 * engine refused the sixteen sentences — it resolves every needle against the archive as it stands
 * at entry, so the §16 paragraph (which contains the badge string another edit retires) and the bare
 * `Phase 0.5` (contained in `Phase 0.5 · BR9`) both came back unresolved, and the engine wrote
 * nothing it had resolved either. Re-running this file now refuses at its own "the row is already in
 * the document" guard, which is correct and is not a broken instrument: the sentences were finished
 * by `refresh-docx-pass441.php`, which takes the two overlapping spans onto the raw archive bytes
 * first. Do not "fix" 440 by deleting the guard; 441 is the second half of it.
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

$flatten = static function (string $fragment): string {
    preg_match_all('#<w:t(?: [^>]*)?>(.*?)</w:t>#s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};
$rowsForTitle = static function (string $xml, string $title) use ($rowText): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        if (str_contains($rowText(substr($xml, $start, $end - $start)), $title)) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};
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

$schoolToday = trim((string) shell_exec('"'.PHP_BINARY.'" -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();"'));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schoolToday) !== 1) {
    $fail("the school's civil day answered [{$schoolToday}], which is not a date.");
}
$date = $schoolToday;

// -------------------------------------------------- the sentences the batch made false
$edits = [
    // §16's paragraph, whole: it is one contiguous run, so a single edit retires the two labels,
    // the three stale pointers and the claim that agreeing numbers were the same as right ones.
    ['from' => 'its summary control reads “Workflow Phase — Phase 5” and its page badge reads “Phase 5 · Registrar Central Desk” ( Registrar/Index.jsx:150-151 , :109 ). That is §28.3 U-4 closed on 2026-10-01 — the screen no longer contradicts itself about which phase the Registrar sits in. The “Phase 6” that does survive in the chrome belongs to Blocking, the desk after this one ( DeskSubNav.jsx:74 , MegaAppLauncher.jsx:139 , AuthenticatedLayout.jsx:20 ), and a reader who meets it in a navigation label is looking at the right number for the wrong desk only if they expected the Registrar to be sixth.',
        'to' => 'its summary control reads “Workflow Phase — Phase 4 of 7” and its page badge reads “Phase 4 of 7 · Registrar Central Desk” ( Registrar/Index.jsx:152-153 , :111 ). Both numbers are derived, not typed: WorkflowService::boxOrder() declares the seven boxes in signing order, HandleInertiaRequests.php shares that declaration as an ordered list, and the page names the box it works and lets the shared list number it. That is what §28.3 U-4 needed to be truly closed, because the two labels did agree on 2026-10-01 — on the wrong number. The tracker under the header numbers the boxes the RECORD holds, so a continuing student whose form omits the Assessment box reads “Phase 3 of 6” there while the header still says “Phase 4 of 7”, and the two answer different questions rather than disagreeing. The navigation surfaces that carried their own zero-through-eight numbering now name the same boxes ( MegaAppLauncher.jsx:155 , AuthenticatedLayout.jsx:20 , deskPhase.js ), and no React file spells a phase number at all — a case walks every source under resources/js and refuses the build when one does.',
        'expect' => 1],

    // §6's framing sentence and the table's own column head.
    ['from' => 'The codebase uses three independent numbering systems, and screens display the legacy one.',
        'to' => 'The codebase used three independent numbering systems, and the screens displayed the legacy one; since 2026-11-04 the screens display the workflow box numbering itself, so a UI badge and a stepOrder integer answer the same question rather than two.',
        'expect' => 1],
    ['from' => 'Legacy UI badge', 'to' => 'UI badge, which is the workflow box', 'expect' => 1],

    // §28.3's U-4 row: the closure recorded self-agreement, not the right number.
    ['from' => 'Closed 2026-10-01: the summary and the page badge both read Phase 5, so the screen no longer contradicts itself about which phase the Registrar sits in.',
        'to' => 'Closed 2026-10-01 as to the two labels agreeing, and closed again '.$date.' as to the number: both read the Registrar’s box as the fourth of the seven the form declares, taken from the workflow’s own ordered list rather than typed, so the header and the tracker under it answer with one vocabulary.',
        'expect' => 1],

    // The desk badges, longest phrase first.
    ['from' => 'Phase 0 · Admissions Desk', 'to' => 'Admissions Intake', 'expect' => 4],
    ['from' => 'Phase 0.5 · BR9', 'to' => 'Entrance Examination', 'expect' => 4],
    ['from' => 'Phase 0.5', 'to' => 'Entrance Examination', 'expect' => 2],
    ['from' => 'Phase 1 · Campus Clearance', 'to' => 'Campus Clearance', 'expect' => 2],
    ['from' => 'Phase 2 · Department Evaluation', 'to' => 'Phase 1 of 7', 'expect' => 2],
    ['from' => 'Phase 3 · Assessment Desk', 'to' => 'Phase 2 of 7 · Assessment Desk', 'expect' => 2],
    ['from' => 'Phase 4 · Cashier Desk', 'to' => 'Phase 3 of 7 · Cashier Desk', 'expect' => 2],
    ['from' => 'Phase 5 · Registrar Central Desk', 'to' => 'Phase 4 of 7 · Registrar Central Desk', 'expect' => 2],
    ['from' => 'Phase 6 · Section Blocking', 'to' => 'Phase 5 of 7 · Section Blocking', 'expect' => 2],
    ['from' => 'Phase 7 / Phase 8', 'to' => 'Phase 6 of 7 / Phase 7 of 7', 'expect' => 1],
    ['from' => 'Phase 7 · Clinic Desk', 'to' => 'Phase 6 of 7 · Clinic Desk', 'expect' => 1],
    ['from' => 'Phase 8 · ID Office', 'to' => 'Phase 7 of 7 · ID Office', 'expect' => 1],
];

foreach ($edits as $i => $e) {
    if (preg_match('/[<]/', $e['to']) === 1 || preg_match('/[>]/', $e['to']) === 1) {
        $fail("edit {$i} carries markup the engine refuses to write.");
    }
}

$xml = $readPart($docx, 'word/document.xml');

// The new row's own text, so nothing here may repeat a needle above.
$clauses = [
    'The workflow progress is numbered from one declaration now, and the owner found the contradiction at the Registrar desk.',
    'workflowPhases reached the browser as an object keyed by office id, and JavaScript enumerates an object’s integer-like keys in ascending numeric order, so the tracker drew the boxes in the offices table’s numbering: the Registrar’s box, which is the fourth of the seven the enrollment form declares, painted first.',
    'Every desk page’s header chip carried its own typed number from the old module list as well, so a Registrar read one number in its header and another in the tracker under it.',
    'WorkflowService::boxOrder() is now the single statement of the words AND the sequence, shared as an ordered list; the tracker numbers the boxes the record itself holds, so a continuing student whose form omits the Assessment box reads the Registrar as third of six, and every desk page, the launcher and the header pill name their box and take the number from the same list.',
    'Three nav surfaces and thirteen desk pages stopped spelling a number; the badges for the two desks that precede the form and the one that runs beside it now say what they are, because a phase number would claim a place in a sequence they are not part of.',
    'Pinned by four cases in the workflow phase indicator class, which assert the list order on the wire, the record-scoped numbering in the source, that no React file types a phase number, and that every box word a surface names is a box the workflow declares.',
    'Proved leg by leg by the phase-order falsifier, which puts the office-keyed map back and requires the payload case to go red; its first run reported one SURVIVED leg because a label claimed by four surfaces cannot be retired by editing one of them, and the leg now plants all four.',
    'The document’s own framing sentence and its lifecycle table were re-taken the same day: they had described three numbering systems and a legacy badge as the build of today.',
];
$body = implode(' ', $clauses);

if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|blade\.php|json|mjs|md):\d/', $body) === 1) {
    $fail('the new row carries a File.php:NN citation this pass cannot verify — spell the pointer in words.');
}

// The pre-flight mirrors the engine: edits are resolved sequentially against the evolving
// document, so a short needle's count is measured AFTER the long needle that contains it has
// been retired. Counting against the untouched archive would accuse a correct plan.
$sim = $xml;
foreach ($edits as $i => $e) {
    $n = substr_count($sim, $escape($e['from']));
    if ($n !== $e['expect']) {
        $fail("edit {$i} matches {$n} time(s) at its own step, expected {$e['expect']}: [".mb_substr($e['from'], 0, 90).'…]');
    }
    $sim = str_replace($escape($e['from']), $escape($e['to']), $sim);
}

if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}

foreach ($edits as $e) {
    if (str_contains($body, $e['from'])) {
        $fail('the new row repeats a needle this pass retires, so the count would move under itself.');
    }
}

// -------------------------------------------------- the §1.1 row, cloned from the newest row
$rows = $rowsForTitle($xml, 'The timetable import has a desk screen now');
if (count($rows) !== 1) {
    $fail('the newest revision row matched '.count($rows).' row(s) by title, expected exactly 1 — re-read §1.1 before cloning.');
}
[$start, $end] = $rows[0];
$template = substr($xml, $start, $end - $start);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm);
if (count($cm[0]) !== 2) {
    $fail('the revision row this pass clones holds '.count($cm[0]).' cells, expected 2.');
}

$values = [$date, $body];
$clone = $template;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm2, PREG_OFFSET_CAPTURE);
$cellJobs = [];
foreach ($cm2[0] as $j => $x) {
    $cellJobs[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
}
usort($cellJobs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
foreach ($cellJobs as [$j, $at, $len, $src]) {
    $cell = $src;
    preg_match_all('/<w:p(?:\s[^>]*)?>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        $cell = str_replace($extra, '', $cell);
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $cellXml = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($values[$j]).'</w:t></w:r></w:p>', $cellXml);
    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        $fail("the replacement for cell {$j} holds more than one text run — read it before cloning.");
    }
    $clone = substr_replace($clone, $new, $at, $len);
}
if (substr_count($clone, '<w:tc>') !== 2) {
    $fail('the clone lost or gained a cell.');
}
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $cm3);
foreach ($cm3[0] as $j => $cell) {
    if ($flatten($cell) !== $values[$j]) {
        $fail('cell '.($j + 1)." of the clone does not read back as the text supplied — it holds [\n"
            .mb_substr($flatten($cell), 0, 160).'…]');
    }
}
$xml2 = substr_replace($xml, $clone, $end, 0);

printf("stage 1 built: one §1.1 row dated %s and %d sentence(s) to re-take. %s\n",
    $date, count($edits), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    if (substr_count($xml2, $escape($body)) !== 1) {
        $fail('the assembled bytes do not hold the new row exactly once.');
    }
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass440-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for writing the row.');
}
$zip->addFromString('word/document.xml', $xml2);
$zip->close();

docxApply($docx, $backup, array_map(
    static fn (array $e): array => [$e['from'], $e['to'], null, $e['expect']],
    $edits
), [], true);

// ------------------------------------------------- read back
$read = $readPart($docx, 'word/document.xml');
$restored = static function () use ($backup, $docx, $fail): void {
    copy($backup, $docx);
    $fail('the backup was restored.');
};

if (count($rowsForTitle($read, 'Three nav surfaces and thirteen desk pages')) !== 1) {
    $restored();
}
foreach ($edits as $e) {
    if (substr_count($read, $escape($e['from'])) !== 0) {
        copy($backup, $docx);
        $fail('a retired badge spelling is still in the document: ['.mb_substr($e['from'], 0, 70).'…]');
    }
}
foreach (['Phase 0 ·', 'Phase 1 ·', 'Phase 2 ·', 'Phase 3 ·', 'Phase 4 ·', 'Phase 5 ·', 'Phase 6 ·', 'Phase 7 ·', 'Phase 8 ·', 'Phase 0.5'] as $gone) {
    if (substr_count($read, $escape($gone)) !== 0) {
        copy($backup, $docx);
        $fail("the retired chrome spelling [{$gone}] survives the pass — one vocabulary did not land.");
    }
}

echo "WROTE the §1.1 row and ".count($edits)." sentence(s); no badge spelling of the retired numbering survives.\n";
unlink($backup);
exit(0);
