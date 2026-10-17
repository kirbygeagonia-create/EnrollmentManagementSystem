<?php

/**
 * Pass 139 — the schema arithmetic in §27's risk rows and §28's contradiction table, re-read from the live database.
 *
 * Lane 43 measured the foreign keys to write its checker, and two sentences elsewhere in this document still
 * carry the 2026-09 count. §23.3's own Total row already says 63 tables and 100 keys, and §23.4 tabulates every
 * key with its delete behaviour — so these two cells were not a different scope, they were simply never moved
 * when the shift module and the print-log rescoping added keys. Correcting a total over an uncorrected
 * breakdown would have been the worse choice, so each number below is taken from the table this document
 * already keeps, then confirmed against information_schema:
 *
 *   foreign keys      100  (information_schema.REFERENTIAL_CONSTRAINTS, schema ems; §23.3 Total agrees)
 *   domain tables      55  (63 base tables minus the 8 Laravel framework tables; §23.3's reconciliation agrees)
 *   delete behaviour   69 NO ACTION, 26 SET NULL, 4 CASCADE, 1 RESTRICT (sums to 100)
 *
 * The "8 domain unique keys" figure is deliberately untouched: it was already argued once in §23.6.1, its scope
 * excludes tables this quick count includes (11 UNIQUE constraints show if the RBAC pivots are counted with the
 * domain), and re-litigating it belongs to its own pass, not to a drive-by correction.
 *
 * DRY RUN unless --apply, like every pass since 128.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass139.docx',
    [
        ['86 foreign keys, 8 domain unique keys, 54 domain tables',
            '100 foreign keys, 8 domain unique keys, 55 domain tables'],
        ['86 foreign keys resolve against real parents',
            '100 foreign keys resolve against real parents'],
        ['§23.4 then splits them by delete behaviour',
            '§23.4 then splits them by delete behaviour (69 NO ACTION refuse the delete, 26 SET NULL detach the '
            .'reference, 4 CASCADE, 1 RESTRICT)'],
    ],
    [],
    $apply
);
