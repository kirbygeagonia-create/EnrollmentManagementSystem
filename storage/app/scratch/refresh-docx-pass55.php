<?php
/**
 * Pass 55 — the seeder citations, and the three passages that still report the seeder’s old facts.
 *
 * DevReferenceDataSeeder has grown to 850 lines since §21.5 read it at 702, and the retirement
 * block, the office list, the requirement loop and the admission-requirement list all moved.
 * In RbacSeeder the contradictory role comment and the console line D-5 recorded are gone — but
 * §22.3 still quoted them, which contradicts §28.3. Measured: offices :47-58, the office-8
 * retirement :62-104, fee types :701, admission requirements :711-722, clearance requirement
 * lines :734-740, the office-head account loop :796, dean_academic :826-835; RbacSeeder’s 17
 * Role::updateOrCreate calls run :226-477 and it prints no role count at all.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['(702 lines)', '(850 lines)'],
    ['DevReferenceDataSeeder.php:639-644', 'DevReferenceDataSeeder.php:711-722'],
    ['DevReferenceDataSeeder.php:638-644', 'DevReferenceDataSeeder.php:711-722'],
];

$cellEdits = [
    // §21.1 — the academic-year rows the seeder creates.
    ['A new school year must be inserted by SQL', 'Yes', 'Yes ( :137 , :141 , :148 )', 1],

    // §21.1 — the one seeded room.
    ['Capacity is enforced when assigning students', 'One row', 'One row — Room 101, capacity 40 ( :688 )', 1],

    // §25.4 P-9 — the evidence and the printed consequence of a retired office.
    ['DevReferenceDataSeeder.php:652-655', 'DevReferenceDataSeeder.php:652-655',
     'DevReferenceDataSeeder.php:47-58 seeds exactly the nine offices App\\Enums\\OfficeId names, :734-740 builds one named requirement line per office from ProvisionalClearanceRequirements::NAMES, and :62-104 retires any leftover office 8 in foreign-key order. Measured on live ems: 9 offices, 9 requirement lines, 0 unnamed.', 1],
    ['DevReferenceDataSeeder.php:652-655', 'Every slip',
     'Was: every slip printed ten lines, one of them “Clearance”, with no office able to sign it. Now: nine named lines, each belonging to an office that exists and can approve its own line (§25.6).', 1],
    ['DevReferenceDataSeeder.php:652-655', 'Data + policy question',
     'Closed in data 2026-10-04 by ruling 12', 1],
];

$paraEdits = [
    // §1.4 — the standing correction that opened the clearance chapter, re-read from the seeder.
    ['DevReferenceDataSeeder.php:44-56',
     'There is no Clearance Office, and since 2026-10-04 there is no record of one either. A tenth row, office ID 8, used to be seeded with the name “Clearance” (the block that created it now lives at DevReferenceDataSeeder.php:47-58 and holds nine offices) while App\\Enums\\OfficeId named no such case. Clearance is a *process* that designated offices sign off on; the issuing and receiving function belongs under the Registrar Office — receipt can only be recorded by a user whose officeId is Registrar ( ClearancePolicy.php:68-94 ). Ruling 12 retired the row in foreign-key order ( :62-104 : its staff, the approvals pointing at its requirement, the requirement, then the office), so the table, the enum and §12 now agree on nine offices and the slip prints nine named lines (§12.1, §21.1, §25 P-9, §28.3 D-1).'],

    // §22.3 — the role-count contradiction D-5 already removed.
    ['RbacSeeder.php:184',
     'The description column above is the seeder’s own role description, quoted from live roles.description — and since 2026-10-06 it is quoted with updateOrCreate, so an installed database receives the corrected text rather than keeping whatever a year-old insert wrote (§28.3 D-5). The contradictory pair this paragraph used to quote is gone: the comment that read “CREATE ALL 13 DESK ROLES” and the console line that printed “Created all 14 functional desk and faculty roles” were both removed, so the seeder prints no role figure at all and no count can be read from its output. What is verifiable: seventeen Role::updateOrCreate calls run from RbacSeeder.php:226 to :477, seventeen rows sit in the live roles table, and ninety-five permissions sit in the catalogue §22.2 lists — the figures this section now generates rather than remembers.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass55.docx', $textEdits, $cellEdits, $apply, $paraEdits);
