<?php

/**
 * Pass 139b — two citations this very batch moved, re-pointed by meaning rather than by arithmetic.
 *
 * Lane 43 added seven lines above each of these two blocks (the course guard's shift-request count, and the
 * officer check), so every line below them moved by seven. The whole-document sweep caught both: the range that
 * began on the SETTINGS banner now begins seven lines later, and the prerequisite rule line is no longer 337.
 *
 * Each new number was read off the construct the prose names, not computed:
 *   UserManagementController.php:463-483 → :470-490  settings() opens at 470, updateSetting() closes at 490,
 *                                              and 491 is the AUDIT LOGS banner, which is not this claim.
 *   ReferenceDataController.php:337      → :348      the `prerequisiteSubjectId => nullable|exists:…` rule.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass139b.docx',
    [
        ['UserManagementController.php:463-483', 'UserManagementController.php:470-490'],
        ['ReferenceDataController.php:337', 'ReferenceDataController.php:348'],
    ],
    [],
    $apply
);
