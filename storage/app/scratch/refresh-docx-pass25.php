<?php

/**
 * Pass 25 — the two signature rights ruling 7 moved are now enforced, and the passages that
 * described them as held-but-uncalled are corrected. Generated from the build of 2026-10-05:
 * ClinicPolicy::record asks clinic.sign beside clinic.record; IDPolicy::validate asks id.sign
 * beside id.validate; both dead signWorkflow() methods are gone with their caller-less bodies.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    // §18.1 — the Clinic narrative, and the line citation the +15-line docblock moved.
    ['Signing the Clinic box happens in the same transaction.',
        'Signing the Clinic box happens in the same transaction, which is why the act is guarded by both halves of the split ruling 7 drew — the counter’s clinic.record and the signature’s clinic.sign (ClinicPolicy.php:47-53, wired 2026-10-05). Amending or reopening a record is not a signature, so those acts still ask only clinic.update and clinic.reopen.'],
    ['ClinicPolicy.php:58', 'ClinicPolicy.php:73'],

    // §18.3 — the ID desk’s terminal act.
    ['the desk confirms identity data and the photo.',
        'the desk confirms identity data and the photo, and holds both the counter’s id.validate and the signature’s id.sign (IDPolicy.php:117-125, wired 2026-10-05).'],

    // §22.7 — the row that named clinic.sign as the office’s own permission.
    ['only SysAdmin may sign across offices',
        'only SysAdmin may sign across offices. At the two desks where the affirmative act IS the box’s signature, the policy now asks for the signature before the act: ClinicPolicy::record requires clinic.sign beside clinic.record, and IDPolicy::validate requires id.sign beside id.validate (both 2026-10-05). Before that, those two rights were held by a role and consulted by no caller — the only place in the matrix where a ruling had been recorded and not enforced.'],

    // §22.9 — the OfficeHead row ruling 7 rewrote.
    ['no desk role holds clearance.view.',
        'no desk role holds clearance.view. The two rights this ruling moved at the Clinic and ID desks gate their acts as of 2026-10-05: a head seated at office 11 or 22 who is not also ClinicStaff or IdOfficer can record nothing and validate nothing, though the counter work stays theirs.'],

    // §22.9 — the IdOfficer description is read verbatim from roles.description, which still
    // names a step the 2026-09-30 ruling removed; the note keeps the reading honest.
    ['face-photo validation, and card release',
        'face-photo validation, and card release — the last phrase is the live roles.description text, written before the 2026-09-30 ruling removed card production; §18.3 states that no release action exists'],

    // §28.2 X-4 — the register row that closed ruling 7.
    ['because the seeder pairs an office head with their desk role.',
        'because the seeder pairs an office head with their desk role. Two of the five rights sat at that writing held by a role and consulted by nothing: on 2026-10-05 clinic.sign became a precondition of recording a health assessment and id.sign of validating a card, so the right the matrix grants is now the right the desk meets.'],

    // §1.1 — the revision record. The clause about §24 was overtaken by pass 24.
    ['2026-10-04 (this issue)', '2026-10-04–05 (this issue)'],
    ['and §24 states which column listings are not yet re-generated.',
        'and §24’s column listings are re-read from the server — 451 documented columns across 57 of the 63 tables, the shift request given its own entry. The last two rights ruling 7 moved are then wired into the code that had been describing them: clinic.sign gates the recording of a health assessment, id.sign the validation of an ID card, and the two unreachable signWorkflow() policy methods that held them are deleted.'],
];

$cellEdits = [];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-05-pass25.docx', $textEdits, $cellEdits, $apply);
