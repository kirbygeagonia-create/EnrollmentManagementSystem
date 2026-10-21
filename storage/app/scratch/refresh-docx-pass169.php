<?php

/**
 * Pass 169 — §21's "what reduces the bill" row reads the columns the server actually holds, and §28.3 gains G-28.
 *
 * Lane 60's probe bound the documents' ratios to the queries that answer them, and one reading caught something no
 * citation check can see: the row answering "What reduces the bill" names `scholarshiptypes` columns `typeKind`,
 * `fixedAmount` and `coveragePercentage`. Live `ems` holds scholarshipTypeId, scholarshipName, coverageType and
 * coveragePercent; the other three appear in no migration, model, controller or view, and `fixedAmount` names a column
 * this schema has never carried. The same row cites `AssessmentController.php:139-144`, which is the fee-pricing loop
 * the row above cites at :129-141 — the reduction is computed at :232-237 and stored at :160-168, and :157 sets coverage
 * to zero at compute time, which is what §14's auto-award sentence states with the same wrong pointer.
 *
 * Two stages, deliberately:
 *
 *  1. the register row. docxApply() opens the file itself, so a row cloned into a string here would never reach disk —
 *     the first draft of this pass had exactly that bug and lost the row. The clone is therefore written by this file,
 *     in the way pass 164 proved: template row located by its own id cell, text swapped cell by cell, container counts
 *     compared against the template, and the §28.3 table recounted from the bytes about to be written.
 *  2. the §21 row and §14's pointer, through docxApply so the engine's own guards run. The row is addressed by its label
 *     cell, which stays unique because G-28 quotes the question in lower case; `typeKind` cannot be the anchor, since the
 *     register row quotes the invented names on purpose, and a plain search for "scholarshiptypes (" lands on a different
 *     table entirely — both found by measuring byte offsets, not by reading the dump, which pads every run boundary.
 *
 * Both stages move the register's size in the two places §29.4 states it, because a row that grows never edits its
 * neighbour.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$readPath = $root.'/storage/app/scratch/lane60-readings.json';
if (! is_file($readPath)) {
    exit("REFUSED: no lane60-readings.json — the replacement column list must come from the server, not from memory.\n");
}
$R = json_decode((string) file_get_contents($readPath), true);
$cols = $R['perTableColumnNames']['scholarshiptypes'] ?? null;
if (! is_array($cols) || $cols === []) {
    exit("REFUSED: the readings carry no scholarshiptypes column list.\n");
}
$live = array_map('strtolower', $cols);
foreach (['typeKind', 'fixedAmount', 'coveragePercentage'] as $n) {
    if (in_array(strtolower($n), $live, true)) {
        exit("REFUSED: {$n} is a real column after all — this pass's premise is wrong.\n");
    }
}
// information_schema is case-free on this server, so each display spelling is checked against the lowercased set
// rather than taken from it: the document must print the name the code uses.
$display = ['scholarshipName', 'coverageType', 'coveragePercent'];
$keep = array_values(array_filter($display, static fn (string $c): bool => in_array(strtolower($c), $live, true)));
if (count($keep) !== 3) {
    exit('REFUSED: only '.count($keep).' of the three intended columns exist, so the rewritten row would name a column the server does not hold.'."\n");
}

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$assertPlain = function (string $text, string $what) use (&$fails): void {
    $bad = [];
    foreach (['&', '<', '>'] as $c) {
        if (str_contains($text, $c)) {
            $bad[] = $c;
        }
    }
    if ($bad !== []) {
        printf("  FAIL %s carries %s — it would be escaped twice or corrupt the part\n", $what, implode(' ', $bad));
        $fails++;
    }
};

/** Replace the text of the first <w:t> in a cloned cell, the way pass 160 and pass 164 do. */
$setText = function (string $cell, string $text): string {
    if (preg_match('/(<w:t(?:\s[^>]*)?>)(.*?)(<\/w:t>)/s', $cell, $one) !== 1) {
        throw new RuntimeException('the cell carries no text run to replace');
    }

    return preg_replace_callback('/<w:t(?:\s[^>]*)?>.*?<\/w:t>/s',
        fn (array $x): string => $x[0] === $one[0]
            ? str_replace($one[2], htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8'), $x[0])
            : $x[0],
        $cell, 1);
};

/** Write one document.xml over the whole archive, guarding the part count and the bytes that land on disk. */
$rewrite = function (string $path, string $body, int $partsExpected, string $backupTo) use ($root, &$fails): void {
    $out = dirname($path).'/.ems-docx-stage.tmp';
    $w = new ZipArchive();
    if ($w->open($out, ZipArchive::CREATE) !== true) {
        exit("REFUSED: cannot build the replacement archive.\n");
    }
    $r = new ZipArchive();
    $r->open($path);
    $n = $r->numFiles;
    for ($i = 0; $i < $n; $i++) {
        $name = $r->getNameIndex($i);
        $w->addFromString($name, $name === 'word/document.xml' ? $body : $r->getFromIndex($i));
    }
    $w->close();
    $r->close();
    if ($n !== $partsExpected) {
        printf("  FAIL the archive now holds %d parts, expected %d\n", $n, $partsExpected);
        $fails++;

        return;
    }
    if (! rename($out, $path)) {
        exit("REFUSED: could not move the new archive into place; backup at {$backupTo}\n");
    }
    $check = new ZipArchive();
    $check->open($path);
    $round = (string) $check->getFromName('word/document.xml');
    $parts = $check->numFiles;
    $check->close();
    if (hash('sha256', $round) !== hash('sha256', $body)) {
        exit("REFUSED: what is on disk is not the bytes this pass built.\n");
    }
    printf("  ok   wrote %d parts, %d bytes of document.xml\n", $parts, strlen($body));
};

// ---------------------------------------------------------------- stage 1: the register row and the two counts

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$parts = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

$row = [
    'G-28',
    'A dictionary row named three `scholarshiptypes` columns the schema never had, and cited the fee loop for the reduction',
    'The §21 row that answers “what reduces the bill” listed `scholarshiptypes` with `typeKind`, `fixedAmount` and '
        .'`coveragePercentage`. Live `ems` holds scholarshipTypeId, scholarshipName, coverageType and coveragePercent, and no '
        .'migration, model, controller or view mentions the other three — `fixedAmount` names a column this schema has never '
        .'carried, so a reader who added one would be following the document rather than the database. The same row cited '
        .'lines 139-144 of `AssessmentController` for the reduction, and those lines build each charge from '
        .'`feetypes.defaultAmount` and the enrolled units, which is what the row directly above cites at :129-141. A grant '
        .'reduces the bill at :232-237, where `coverageType` decides between the remaining balance and `totalAssessedAmount` '
        .'times `coveragePercent` over 100, and the result is stored at :160-168; at compute time :157 sets coverage to zero '
        .'on purpose, which is the fact §14’s auto-award sentence states — carrying the same wrong pointer. Both halves '
        .'passed every citation check that exists: the file is real, the lines are real, and a sweep verifies that a '
        .'citation lands on a live line, never that it lands on the line the sentence is about. Found by lane 60 on '
        .'2026-10-21 while binding the documents’ ratios to the queries that answer them, when the probe asked '
        .'information_schema which columns the named table actually holds.',
    'Corrected 2026-10-21: the row names the columns the server holds, its citation moved to :232-237 and :160-168, and '
        .'§14’s pointer to :152-158. Read by storage/app/scratch/lane60-ratio-claims.php, which binds each census figure, '
        .'per-table column count and ratio in these documents to the reading that answers it.',
    '§21 (what reduces the bill), §14 (BR19 auto-award), §28.3 G-21; found 2026-10-21 by '
        .'storage/app/scratch/lane60-probe-ratios.php, proved by storage/app/scratch/mutate-lane60-ratio-claims.php',
];

$anchor = '>G-27<';
$guard(substr_count($new, $anchor) === 1, 'the last register row is findable by its own id cell');
$at = strpos($new, $anchor);
$rowStart = $at === false ? false : strrpos(substr($new, 0, (int) $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($new, '</w:tr>', (int) $at) + 7;
$template = substr($new, (int) $rowStart, (int) $rowEnd - (int) $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cells);
$guard(count($cells[0]) === 5, 'the template row has five cells (read '.count($cells[0]).')');

$clone = $template;
foreach ($cells[0] as $i => $cell) {
    $assertPlain($row[$i], 'G-28 cell '.($i + 1));
    $clone = str_replace($cell, $setText($cell, $row[$i]), $clone);
}
$guard($clone !== $template, 'G-28 carries new text');
$guard(substr_count($clone, '<w:tr') === substr_count($template, '<w:tr'), 'G-28 is one row and no more');
foreach (['</w:tr>', '<w:tc>', '</w:p>', '</w:t>'] as $n) {
    $guard(substr_count($clone, $n) === substr_count($template, $n), 'G-28 container '.$n.': one row’s worth');
}
$guard(substr_count($clone, '>G-28<') === 1, 'the cloned row is addressed as G-28');
$new = substr_replace($new, $clone, (int) $rowEnd, 0);

// The size in the two places §29.4 states it: the arithmetic sentence and the family table's own cell.
$sizeNeedle = 'lane 57 added G-26 and G-27, so §28.3 holds 44 data rows';
$sizeNew = 'lane 57 added G-26 and G-27, and lane 60 added G-28 from a dictionary row that named columns the schema '
    .'never had, so §28.3 holds 45 data rows';
$guard(substr_count($new, $sizeNeedle) === 1, 'the size sentence is present once');
$new = str_replace($sizeNeedle, $sizeNew, $new, $cSize);
$guard($cSize === 1, 'the size sentence was rewritten ('.$cSize.')');

$labelAt = strpos($new, '>G / D / U / X / N<');
$guard($labelAt !== false, 'the family row is findable by its own label cell');
$famStart = $labelAt === false ? 0 : (int) strrpos(substr($new, 0, (int) $labelAt), '<w:tr');
$famEnd = (int) strpos($new, '</w:tr>', (int) $labelAt) + 7;
$famText = substr($new, $famStart, $famEnd - $famStart);
$guard(substr_count($famText, '>44<') === 1, 'exactly one 44 sits inside that family row');
$new = substr_replace($new, str_replace('>44<', '>45<', $famText), $famStart, $famEnd - $famStart);
$guard(substr_count($new, '>44<') === 0, 'no count cell still says 44');

// Recount §28.3 from the bytes that are about to be written, so the row count is a measurement rather than an intention.
$lastRow = strpos($new, '>G-28<');
$tblStart = $lastRow === false ? 0 : strrpos(substr($new, 0, (int) $lastRow), '<w:tbl>');
$tblEnd = (int) strpos($new, '</w:tbl>', (int) $lastRow) + 7;
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', substr($new, (int) $tblStart, $tblEnd - (int) $tblStart), $all);
$dataRows = count($all[0]) - 1;
$guard($dataRows === 45, '§28.3 recounted from the edited bytes holds 45 data rows (read '.$dataRows.')');
$guard(str_contains($new, $sizeNew), 'the size sentence carries the new count');
// The two citations this pass re-points must still be the pre-edit strings, or stage 2 would resolve nothing.
$guard(substr_count($new, 'AssessmentController.php:139-144') === 2,
    'the wrong pointer is still in both places ('.substr_count($new, 'AssessmentController.php:139-144').')');
$guard(substr_count($new, 'What reduces the bill') === 1,
    'the §21 row stays addressable by its label cell ('.substr_count($new, 'What reduces the bill').' copies) — G-28 quotes the question in lower case on purpose');
$dom = new DOMDocument;
$guard(@$dom->loadXML($new) !== false, 'the staged document.xml parses');

if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nStage 1 would add G-28, restate the size sentence and move the family cell 44 → 45.\n";
    echo 'Stage 2 would rewrite the §21 row cells and §14’s pointer.'."\n\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass169.docx');
$rewrite($src, $new, $parts, $root.'/storage/app/scratch/ems-docx-pre-pass169.docx');
echo "     stage 1 written\n";

// ---------------------------------------------------------------- stage 2: the §21 row and §14's pointer

$textEdits = [
    ['AssessmentController.php:139-144', 'AssessmentController.php:152-158', 'auto-award (BR19)'],
];
$cellEdits = [
    ['What reduces the bill', 'scholarshiptypes', 'scholarshiptypes ('.implode(', ', $keep).')'],
    ['What reduces the bill', 'AssessmentController.php:139-144', 'AssessmentController.php:232-237 and :160-168, '
        .'where a grant’s coverage is computed and the balance is stored less it'],
];

$zip2 = new ZipArchive();
$zip2->open($src);
$after = (string) $zip2->getFromName('word/document.xml');
$zip2->close();
$guard2 = static function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};
$guard2(substr_count($after, '>G-28<') === 1, 'stage 1 landed: G-28 is in the document being edited now');
$guard2(substr_count($after, 'AssessmentController.php:139-144') === 2, 'both wrong pointers are still present');
$guard2(substr_count($after, 'What reduces the bill') === 1, 'the §21 label cell is still unique, so it can address that row');
if ($fails > 0) {
    exit("REFUSED: stage 2's anchors do not resolve against the written file; backup at storage/app/scratch/ems-docx-pre-pass169.docx\n");
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass169b.docx', $textEdits, $cellEdits, true);

$z3 = new ZipArchive();
$z3->open($src);
$final = (string) $z3->getFromName('word/document.xml');
$z3->close();
$flat = html_entity_decode((string) preg_replace('/<[^>]+>/', '', $final), ENT_QUOTES | ENT_XML1);
// Judge the §21 row by its own span, not by a whole-document count: G-28 quotes all three invented names on purpose
// (`fixedAmount` twice), and a count that ignores that would report a correct document as damaged.
$at2 = strpos($final, 'What reduces the bill');
$rStart = (int) strrpos(substr($final, 0, (int) $at2), '<w:tr');
$rEnd = (int) strpos($final, '</w:tr>', (int) $at2) + 7;
$span = html_entity_decode((string) preg_replace('/<[^>]+>/', '', substr($final, $rStart, $rEnd - $rStart)), ENT_QUOTES | ENT_XML1);
foreach (['typeKind', 'fixedAmount', 'coveragePercentage'] as $n) {
    $guard(! str_contains($span, $n), 'the §21 row no longer names '.$n);
}
$guard(str_contains($span, 'scholarshipName, coverageType, coveragePercent'), 'the §21 row names the real columns in its own span');
$guard(substr_count($flat, 'AssessmentController.php:139-144') === 0, 'no citation still points at the fee loop for the reduction');
$guard(str_contains($flat, 'AssessmentController.php:232-237 and :160-168'), 'the §21 row points at the reduction');
$guard(str_contains($flat, 'AssessmentController.php:152-158'), '§14 points at the deliberate zero');
$guard(substr_count($flat, 'G-28') === 2, 'G-28 exists as its own row and in the sentence that counts the register ('.substr_count($flat, 'G-28').' copies)');
if ($fails > 0) {
    echo "\nGuard(s) failed after writing; restore from storage/app/scratch/ems-docx-pre-pass169.docx\n";
    exit(1);
}
echo "\nPass 169 written in two stages; backup at storage/app/scratch/ems-docx-pre-pass169.docx\n";
exit(0);
