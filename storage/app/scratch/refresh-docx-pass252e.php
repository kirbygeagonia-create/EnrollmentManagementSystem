<?php

/**
 * Docx pass 252e — the two pointers into ExamController.php that no instrument could see.
 *
 * Both were found by the method lane 76 established for a moved file: the docx's cited window is
 * compared with the WORKING file's text at the same numbers, and each remaining window is read
 * against the claim in its own sentence. Seventeen windows carried their claim; two did not, and
 * both are the shape the bounds sweep is blind to — the number is inside the right file and lands
 * on a live line of a neighbouring construct.
 *
 *   §1.1's example row cites `ExamController.php:308` for "the Course-Specific examination
 *   refusal". Line 308 is inside `entranceApplicants()` — the transferee roster query the C-4
 *   ruling grew — and the refusal whose words the document quotes is :384-385. This is the
 *   sentence the document uses to explain what a citation IS, so it must be a live example.
 *   §26's repeat-proofing row cites `ExamController.php:242` for "re-running a desk action
 *   reports the existing result instead of creating a second row". Line 242 is
 *   `AdmissionStatus::Approved->value,` inside that same roster filter; the construct that makes
 *   the general stage repeat-proof is the `firstOrNew` upsert at :333-335, whose comment states
 *   the row's claim almost verbatim. (The course-specific stage is deliberately NOT repeat-proof —
 *   §13's correction-semantics row says so, citing :428-437.)
 *
 * Each needle was probed first: both full spellings occur exactly once in `word/document.xml`, and
 * neither replacement occurs at all. Usage: php storage/app/scratch/refresh-docx-pass252e.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass252e-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

// Stage 1 — every NEW number is asserted against the line it will claim, before anything is written.
$assertions = [
    ['app/Http/Controllers/Exam/ExamController.php', 384, 'requiresCourseSpecificExam'],
    ['app/Http/Controllers/Exam/ExamController.php', 385, 'administers no entrance examination'],
    ['app/Http/Controllers/Exam/ExamController.php', 333, 'firstOrNew'],
    ['app/Http/Controllers/Exam/ExamController.php', 334, 'duplicate'],
    ['app/Http/Controllers/Exam/ExamController.php', 335, 'firstOrNew'],
];

$failures = [];
foreach ($assertions as [$file, $line, $needle]) {
    $lines = file($root.'/'.$file);
    $text = rtrim($lines[$line - 1] ?? '');
    if (! str_contains($text, $needle)) {
        $failures[] = basename($file).":{$line} does not carry '{$needle}' (it reads: ".trim($text).')';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "REFUSED: a cited line does not carry the claim this pass would attach to it.\n");
    foreach ($failures as $f) {
        fwrite(STDERR, '  '.$f."\n");
    }
    exit(1);
}

docxApply(
    $docx,
    $backup,
    [
        ['ExamController.php:308', 'ExamController.php:384-385', null, 1],
        ['ExamController.php:242', 'ExamController.php:333-335', null, 1],
    ],
    [],
    $apply
);
