<?php

/**
 * Pass 24 — §24.1's totals after the 14 regenerated rows and the new table, plus the enum
 * notation the cloned row inherited from information_schema.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    // information_schema spells enums with quotes; every other row in the chapter uses a|b|c.
    ["enum('pending','endorsed','granted','rejected')", 'enum(pending|endorsed|granted|rejected)'],
    ['56 of the 62 tables documented here', '57 of the 63 tables documented here'],
    // The table census, in the two places it is stated. The count cell beside §24.1's note
    // still reads 62, so the sentence carries the correction rather than contradicting it.
    ['54 EMS domain tables plus 8 Laravel framework tables',
        '54 EMS domain tables plus 8 Laravel framework tables at that reading, 55 and 8 today — the 63rd being shiftingrequests, created 2026-10-03 and given its own column table in §24.6'],
    ['54 EMS domain tables, 8 framework tables, 62 live tables.',
        '55 EMS domain tables, 8 framework tables, 63 live tables.'],
];

$cellEdits = [
    // Columns documented: 418 + the 14 rows pass 22 regenerated + shiftingrequests' 19 = 451,
    // which is exactly what the server reports.
    ['Columns documented', '418', '451'],
    ['Columns documented', 'Generated: no column is listed that the server does not have',
        'Generated: no column is listed that the server does not have, and none that it has is omitted. Read against information_schema on 2026-10-04 and diffed row by row against this chapter: the fourteen columns that had arrived since the previous reading (the refund trio on payments, the withdrawal trio on studentscholarships, enrollments.dropReason and the clearance-confirmation pair, documentprintlog.studentId and blockId, idrequests.mismatchRemark, clearancerequirements.requirementName, courses.requiresCourseSpecificExam) were added as rows in their own tables, and the one table the chapter did not carry at all — shiftingrequests — now has its own entry in §24.6.'],
    ['Foreign keys documented', '86', '86 → 100'],
    ['Foreign keys documented', 'Full inventory in §23.3',
        '§23.3’s inventory was compiled at 86 and has not been re-read; information_schema reports 100 referential edges today. The difference is the references the rulings introduced — payments.refundedBy, studentscholarships.statusChangedBy, enrollments.clearanceConfirmedBy, documentprintlog.studentId and blockId, enrollments.curriculumId — plus shiftingrequests’ own nine (students, courses twice, enrollments twice, academicterms, staffusers three times). Re-inventorying §23.3 is the next reading’s job and is named here rather than left for a reader to discover.'],
    ['Domain tables added by this edition', 'clinicrecords is the Clinic desk',
        'clinicrecords is the Clinic desk’s own record — the previous edition described the Clinic phase with no table behind it. model_has_permissions is the Spatie pivot that grants a permission to one individual user; it is empty here, which is why §22.3 can be read as the whole permission story. shiftingrequests joined this edition on 2026-10-03 (ruling 11): the shift paper, documented at §13.7 and listed at §24.6.'],
    // §23's module census: Enrollment gained a table.
    ['enrollments (9)', '5', '6'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass24.docx', $textEdits, $cellEdits, $apply);
