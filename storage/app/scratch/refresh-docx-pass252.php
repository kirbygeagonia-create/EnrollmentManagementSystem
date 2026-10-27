<?php

/**
 * Docx pass 252 — the sentences and the figures the §7.2 confirmation moves.
 *
 * Companion to `refresh-docx-pass252b.php`, which inserts the seven rows this batch needs. This
 * half carries the numbers and the promises:
 *
 *   §24's `examresults` heading counts the table's columns, and the server now holds 11 of them.
 *   The digit is rewritten inside the located heading paragraph rather than by a bare needle,
 *   because `— 8 columns` is not unique across sixty-three headings and a global replace would
 *   move a neighbour's count too.
 *   §23.3's foreign-key census, §23.4's SET NULL list and the schema census sentence all move
 *   with the one key and three columns: measured on live `ems` after the migrate — 462 columns,
 *   102 foreign keys, 131 indexes, 177 table constraints, 28 SET NULL keys, 4 CASCADE, 1 RESTRICT,
 *   69 NO ACTION, 94 permissions.
 *   §22.2's `exam` module row and §22.3's `RegistrarApprover` row are hand-written from the
 *   seeder (no generator reads it — the tool that printed them for pass 74 was a one-off), so
 *   both gain the new right and both counts move.
 *   The examination sentences that promised a recorded result decides a gate stop promising it:
 *   §6.4's stored-as row, §11.2 and §13.1's stage rows, the fail-effect row, and the §10.4
 *   two-stage row.
 *   Three pointers the citation shifter could not attribute are re-taken here against the live
 *   files: the bare `:306-308` in the §10.4 row (the refusal that names a program administering no
 *   general paper, `ExamController.php` lines 329 to 331), the second copy of `:358-366`, and the
 *   two copies of `:1105-1146` — `confirmClearance()`, which this batch moved to lines 1120 to 1161.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass252.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass252-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// One-shot: pass 252b's rows must already be in, or the heading would claim 11 columns that the
// listing does not hold — half-applied is exactly the state this pass exists to refuse.
if (! str_contains($xml, 'Examination results adopted by the Registrar (development prompt')) {
    fwrite(STDERR, "REFUSED: pass 252b's rows are not in the document yet — run refresh-docx-pass252b.php first.\n");
    exit(1);
}

$flattenRuns = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
};

/**
 * Rewrite the digit of a §24 per-table heading, located by its own flattened text.
 *
 * A heading is one paragraph and the number sits in its own run beside the count word; the
 * paragraph as a whole is unique (`examresults — 8 columns`), while the number alone is not.
 */
$headingCount = static function (string $xml, string $table, int $from, int $to) use ($flattenRuns): string {
    $found = 0;
    $out = preg_replace_callback('/<w:p>.*?<\/w:p>/s', function (array $m) use ($table, $from, $to, &$found, $flattenRuns): string {
        $flat = trim(preg_replace('/\s+/', ' ', $m[0] === '' ? '' : $flattenRuns($m[0])));
        if (! preg_match('/^'.preg_quote($table, '/').'\s*—\s*'.$from.' columns$/', $flat, $x)) {
            return $m[0];
        }
        $found++;
        // The heading is ONE run and the house spelling puts two spaces around the em dash
        // (`examresults  —  8 columns`), so match on whitespace rather than on a literal.
        $row = preg_replace('/\s*—\s*'.$from.'\s+columns/u', '  —  '.$to.' columns', $m[0], 1);
        if ($row === $m[0]) {
            // Split runs: the digits sit in their own <w:t>.
            $row = preg_replace('/<w:t([^>]*)>'.preg_quote((string) $from, '/').'<\/w:t>/',
                '<w:t$1>'.$to.'</w:t>', $m[0], 1);
        }
        if (trim(preg_replace('/\s+/', ' ', $flattenRuns($row))) === $flat) {
            fwrite(STDERR, "REFUSED: the rewrite of the {$table} heading changed nothing.\n");
            exit(1);
        }

        return $row;
    }, $xml);

    if ($found !== 1) {
        fwrite(STDERR, "REFUSED: the {$table} heading matched {$found} paragraph(s), expected exactly 1.\n");
        exit(1);
    }

    return $out;
};

$xml = $headingCount($xml, 'examresults', 8, 11);

$textEdits = [
    // §23.3 and §23.4's own counts, measured on live ems after the migrate. Each needle is a
    // single-run fragment: the sentences sit inside styled paragraphs, so a long needle crosses a
    // run boundary and answers 0 occurrences rather than nothing to do.
    ['101 constraints:', '102 constraints:', null, 1],
    [', 27 null the child key (', ', 28 null the child key (', null, 1],
    ['These 27', 'These 28', null, 1],
    ['459 columns, 101 foreign-key constraints, 130 indexes, and 176 table constraints',
        '462 columns, 102 foreign-key constraints, 131 indexes, and 177 table constraints', null, 1],

    // §22.2's module row lists the exam permissions alphabetically.
    ['exam.record.courseSpecific, exam.record.general, exam.record.retention, exam.view',
        'exam.record.courseSpecific, exam.record.general, exam.record.retention, exam.verify, exam.view', null, 1],
    // §22.3's RegistrarApprover list, and its count.
    ['enrollment.studentdata.record, evaluation.view, payment.view',
        'enrollment.studentdata.record, evaluation.view, exam.verify, exam.view, payment.view', null, 1],

    // The rule itself: a recorded result is the scoring desk's opinion until the Registrar adopts it.
    // The stored-as row of §6.4 and the stage-1 paragraph of §11.2 both list what a recorded
    // result is; `examType = general` occurs exactly twice, in those two, and both must gain the
    // state — one copy left behind is the half-applied register this file refuses elsewhere.
    ['examType = general', 'examType = general, resultStatus = provisional until the Registrar confirms it', null, 2],
    ['because the prerequisite refuses a non-pass general row',
        'because the prerequisite refuses a general row that is not a confirmed pass', null, 1],
    ['and read pass before', 'and read pass, and be confirmed official by the Registrar, before', null, 1],
    ['the separate exam.verify.general right listed here until 2026-10-08 was retired with the policy method that was its only reader.',
        'the separate exam.verify.general right listed here until 2026-10-08 was retired with the policy method that was its only reader. The exam.verify that exists since 2026-10-26 is a different key and shares only a prefix with it: ExamPolicy::verify is called by the route that confirms a recorded result official, which is the act §7.2 asks the Registrar to perform.',
        null, 1],

    // The three pointers the citation shifter could not attribute, re-taken against the live files.
    [':306-308', 'ExamController.php:329-331', null, 1],
    [':358-366', ':388-396', null, 2],
    [':1105-1146', ':1120-1161', null, 2],
];

$cellEdits = [
    // §22.2: the exam module now declares five rights.
    ['exam.record.courseSpecific, exam.record.general, exam.record.retention, exam.view', '4', '5'],
    // §22.3: RegistrarApprover holds exam.view and exam.verify, so seventeen rights.
    ['Registrar officer for final enrollment approval, subject confirmation', '15', '17'],
];

docxApply($docx, $backup, $textEdits, $cellEdits, $apply);
