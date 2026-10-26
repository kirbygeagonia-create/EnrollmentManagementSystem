<?php

/**
 * Docx pass 251 — the authority catches up with the G-3 crediting decision.
 *
 * Companion to `refresh-docx-pass251b.php`, which inserts the twelve rows this batch adds. This
 * half carries the sentences and the figures: the schema census moved with seven new columns and
 * one new foreign key, the SET NULL count moved with it, §24's two per-table headings moved with
 * the server, §13.1's "four actions" became five, and two claims that credited subjects simply
 * cover a load had to acquire the condition the build now enforces.
 *
 * The two citation repairs are the reason this half exists at all. `EvaluationController.php:58`
 * named a blank line in HEAD and still does — it is the first half of the comma list `:58,190`,
 * which no instrument can read as two citations (AGENTS names that shape). `:959-1000` was a bare
 * continuation offered for `confirmClearance()` while those lines sit inside `store()` and
 * `approvedPassSlip()` — false by meaning before this batch, and now re-pointed to the method the
 * sentence actually names. Both were measured against the live file, not shifted.
 *
 * Stage 1 is the engine's own: a needle whose count disagrees with the measurement refuses the
 * run. Usage: php storage/app/scratch/refresh-docx-pass251.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass251-backup-'.date('Ymd-His').'.docx';
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

// One-shot: pass 251b writes the §1.1 row; this pass refuses until it is present, so the two
// halves cannot be applied out of order and leave a register with rows but no record.
if (! str_contains($xml, 'Per-row crediting decided (development prompt')) {
    fwrite(STDERR, "REFUSED: pass 251b's rows are not in the document yet — run refresh-docx-pass251b.php first.\n");
    exit(1);
}

$textEdits = [
    // Two citations that were false BEFORE this batch, repaired by reading the live file.
    ['EvaluationController.php:58,190', 'EvaluationController.php:882-914', null, 1],
    ['959-1000', '1105-1146', null, 2],

    // The schema census, measured on live ems on 2026-10-26: 459 columns, 101 foreign keys,
    // 130 indexes, 176 table constraints, 27 SET NULL keys, 4 CASCADE, 1 RESTRICT, 69 NO ACTION.
    ['452 columns, 100 foreign-key constraints, 129 indexes, and 175 table constraints',
        '459 columns, 101 foreign-key constraints, 130 indexes, and 176 table constraints', null, 1],
    ['100 constraints:', '101 constraints:', null, 1],
    ['), 26 null the child key (', '), 27 null the child key (', null, 1],
    ['These 26', 'These 27', null, 1],

    // §24's per-table headings, each re-counted from information_schema.
    ['creditedsubjects  —  7 columns', 'creditedsubjects  —  11 columns', null, 1],
    ['transferacademicrecords  —  7 columns', 'transferacademicrecords  —  10 columns', null, 1],

    // The rule itself: a credit covers a subject only while its decision is approval.
    ['the only prerequisite escape hatch in the build',
        'the only prerequisite escape hatch in the build, and it is drawn only from the rows whose decision is approval (§7.3, G-3)',
        null, 2],
    ['honouring elective groups, prerequisites, and credited subjects',
        'honouring elective groups, prerequisites, and the credited subjects the department has approved', null, 1],
    ['works an enrollment through four actions, then signs',
        'works an enrollment through five actions, then signs', null, 1],
    ['and source institution.',
        'and source institution. The equivalency it proposes is decided on the next row, not here.', null, 1],
    ['gets to skip because an equivalent was already completed',
        'gets to skip because the department approved an equivalent — creditDecision is approved; a declined row stays on the record and covers nothing',
        null, 1],

    // The row pass 251b wrote carries a prose pipe, and `lane69-open-inventory.php` reads a table
    // row by splitting on pipes: a §1.1 row with two cells and a `|` inside one of them arrives as
    // three and is printed as UNRESOLVED. AGENTS names this shape; the wording goes to words.
    ['creditDecision (approved | rejected)', 'creditDecision (approved or rejected)', null, 1],
];

$cellEdits = [];
$paraEdits = [];
$inserts = [];

echo 'pass 251 — text edits ('.count($textEdits)."), inserts (".count($inserts)."), cells (".count($cellEdits)."), paragraphs (".count($paraEdits).")\n";
docxApply($docx, $backup, $textEdits, $cellEdits, $apply, $paraEdits, $inserts);

echo $apply ? "written.\n" : "DRY RUN — nothing written.\n";
