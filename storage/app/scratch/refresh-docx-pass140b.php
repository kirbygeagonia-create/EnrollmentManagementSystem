<?php

/**
 * Pass 140b — the three sentences that counted §23.6.1's keys, moved with the row pass 140 added.
 *
 * §23.6.1 itself says "Eight keys in seven tables"; §28's contradiction row says the six UNIQUE constraints the
 * validation edition promised "are eight unique keys in the domain tables"; and pass 139's risk row inherited
 * the same eight when it corrected the foreign-key total beside it. Nine is what the server holds now that the
 * print-log issuance key is listed. The table count was already right — nine keys still sit across seven tables.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass140b.docx',
    [
        ['Eight keys in seven tables.', 'Nine keys in seven tables.'],
        ['are eight unique keys in the domain tables', 'are nine unique keys in the domain tables'],
        ['100 foreign keys, 8 domain unique keys, 55 domain tables',
            '100 foreign keys, 9 domain unique keys, 55 domain tables'],
    ],
    [],
    $apply
);
