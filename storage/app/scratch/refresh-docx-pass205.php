<?php

/**
 * Pass 205 — the §1.1 record cited a file, and so moved the citation census it had just stated.
 *
 * Pass 204 wrote "689 over 154 files" into the living revision row and, in the same sentence, the pointer
 * `(EnrollmentReadiness.php:61-63)` that proves the claim it records. The sweep counts that pointer, so the
 * document now answers 690. This is lane 61's defect in a new surface — the record of a change is itself part of
 * what the change counts — and the fix is the same: re-measure after the last edit and correct the figure in the
 * same batch that makes it false, rather than leaving a number standing that only ever described the moment
 * before the sentence containing it was written.
 *
 *   php storage/app/scratch/refresh-docx-pass205.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass205.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass205.docx', [
    ['689 over 154 files and the blind queue from 70 to 43',
        '690 over 154 files and the blind queue from 70 to 43'],
], [], $apply);
