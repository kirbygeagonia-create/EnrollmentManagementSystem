<?php

/**
 * Pass 181 — the three print rulings of 2026-10-23, written where they were asked.
 *
 * The owner answered P-16 (the letterhead name), P-2 (build the form's identity block from the designs
 * the school's own papers already show) and P-17 (a clearance is issued when the semester ends, and the
 * system keeps every student who has completed one). Each half of that is already in the build: the
 * settings row and the six template fallbacks carry the ruled name, the enrollment form prints five
 * identification fields, and Studentclearances::termEnrollment() names nothing outside its own term.
 * This pass moves the document to the same state — §25.4's four rows, then the nine places that count,
 * quote or cross-reference them.
 *
 * Measured before being written, not copied from the register:
 *   - config('settings.schoolName') on a booted app against live ems answers the sheet's Official Name;
 *   - all eight PDFs ems:print-fidelity renders carry SOUTH EAST ASIAN INSTITUTE OF TECHNOLOGY, INC.;
 *   - enrollment-form.pdf now prints DEMO-2026-017, its course, Year 1, 2nd Semester and 2025-2026;
 *   - live ems keys studentclearances on uq_student_clearanceperiod, its four foreign keys are NO ACTION,
 *     and no file under app/ or routes/ deletes a clearance row.
 * The school's own stationery in the gitignored Images folder was read for two of those claims: its
 * letterhead carries the two-word South East form, its Class Card carries no student number at all, and
 * its printed landline is the second of the two numbers SEAIT_Official_Information.md §1 records.
 *
 *   php storage/app/scratch/refresh-docx-pass181.php          # dry run: resolve every target
 *   php storage/app/scratch/refresh-docx-pass181.php --apply  # write
 */

$root = dirname(__DIR__, 3);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// ---------------------------------------------------------------- cell edits
// Each is [row anchor, prefix of the target cell's text, new cell text].

$cellEdits = [
    // ---- §25.4 P-2: closed on the paper, built from the school's own designs.
    [
        'read off the rendered file on 2026-10-21, where not one identity token appears',
        'Open, and it is a paper question rather than a code one.',
        'Closed 2026-10-23, on the owner’s ruling and on the paper. The form now prints the five facts its face '
            .'used to omit — Student ID, Course, Year Level, School Year and Semester — in an identification block '
            .'directly under its title, and the labels are the Registrar’s own words: they are the fields its '
            .'Subject Load paper already carries. The ruling was to build it from the designs the school has '
            .'supplied, not to wait again — the four scans in the gitignored Images folder are a Subject Load, a '
            .'Class Card, a Clearance Slip and a Class Block and Schedule, and no blank enrollment form is among '
            .'them. Measured on the paper rather than on the blade: php artisan ems:print-fidelity rendered the set '
            .'and storage/app/scratch/lane62-print-paper-probe.php read the text out of the PDF, which now carries '
            .'DEMO-2026-017, the course, Year 1, 2nd Semester and 2025-2026 where the 2026-10-21 reading found no '
            .'identity token of any kind.',
    ],
    [
        'read off the rendered file on 2026-10-21, where not one identity token appears',
        'enrollment-form.blade.php has no courseName',
        'enrollment-form.blade.php:59-80 is the block, and its five values are the expressions '
            .'subject-load.blade.php already prints. tests/Feature/Print/PrintEnrollmentFormPayloadTest.php pins '
            .'three things: the desk’s eager-load list, that reading the block costs no further query, and that the '
            .'five labels with their values are in the rendered body. That last case reads the body alone on '
            .'purpose — the document’s title element has carried the student number all along and never reached the '
            .'printed page. storage/app/scratch/mutate-p2-payload-tests.php plants five defects, two of them in the '
            .'blade, and refuses its whole run when the control is not green.',
    ],
    [
        'read off the rendered file on 2026-10-21, where not one identity token appears',
        'The form is the one document with student type',
        'Nothing for the data, which this row already recorded as present. What the ruling did not settle is the '
            .'rest of the sheet’s layout: the block sits under the title in the Subject Load’s order, and only the '
            .'Registrar can say whether the enrollment form’s own paper puts those five fields elsewhere. That is a '
            .'layout review against a form this repository has never been given, not a gap in what prints.',
    ],

    // ---- §25.4 P-10: both branches now ruled.
    [
        'narrower than it was written: the slip takes course',
        'Closed 2026-10-02, code landed 2026-10-01',
        'Closed 2026-10-02, code landed 2026-10-01, and narrower than it was written: the slip takes course, year '
            .'level and the log’s enrollment from the enrollment of the clearance period’s own term WHEN THE STUDENT '
            .'HOLDS ONE in that term. The other branch — a slip raised after its term closed — was P-17 and was '
            .'ruled on 2026-10-23: it names no enrollment. Both halves of the derivation are now answered, and both '
            .'are pinned by tests/Feature/Print/ClearanceSlipTermEnrollmentTest.php.',
    ],
    [
        'narrower than it was written: the slip takes course',
        'Nothing for the term-matched half.',
        'Nothing. The window-versus-term question that remains is the acceptance script’s §18 item 1 and is a ruling '
            .'about which window a term owns, not a code defect; the enrollment a slip names when its period’s term '
            .'holds none was P-17, and the answer the school gave — none — is built and pinned.',
    ],

    // ---- §25.4 P-16: the name, ruled and read off the paper.
    [
        'the name row is the one this repository disagrees with itself about',
        'Open, and the school owns the answer, not the code.',
        'Closed 2026-10-23, on the owner’s ruling: the paper carries South East Asian Institute of Technology, Inc. — '
            .'the two-word South East and the corporate suffix, exactly as SEAIT_Official_Information.md §1 records '
            .'the Official Name. The settings row holds that value, each of the six print views passes its '
            .'upper-case form as the fallback a fresh install prints, and the login screen already spelled it the '
            .'sheet’s way, so screen and document name the school alike. Measured on paper after the change: all '
            .'eight documents php artisan ems:print-fidelity renders carry SOUTH EAST ASIAN INSTITUTE OF TECHNOLOGY, '
            .'INC. The school’s own stationery carries the same two-word form, which makes the ruling’s evidence the '
            .'paper rather than a comment.',
    ],
    [
        'the name row is the one this repository disagrees with itself about',
        'SettingsSeeder.php:28 seeds the one-word form',
        'SettingsSeeder.php seeds the ruled value and its description names the sheet it comes from; the six print '
            .'views under resources/views/prints/ pass its upper-case form as their fallback; PrintLayout.jsx:36, '
            .'PrintCertificate.jsx:74, GuestLayout.jsx:50 and GuestLayout.jsx:68 all read the sheet’s spelling. '
            .'tests/Feature/Print/PrintTemplateSelfContainmentTest.php no longer exempts the name: it parses the '
            .'Official Name out of SEAIT_Official_Information.md §1, requires the seeded value to equal it, requires '
            .'every letterhead key’s value to be traceable to the sheet, and refuses a print view whose fallback is '
            .'not that name upper-cased. storage/app/scratch/mutate-lane62-letterhead-tests.php plants six defects, '
            .'two of them the drift this row was about.',
    ],
    [
        'the name row is the one this repository disagrees with itself about',
        'Nothing printed is misaddressed',
        'Nothing printed is misaddressed, and nothing now disagrees with the school’s own record of its name. One '
            .'letterhead value still has two answers in the sheet it comes from: SEAIT_Official_Information.md §1 '
            .'lists the Registrar landline as (083) 226-1602 / 226-1203, the settings row carries the first, and the '
            .'stationery in the scan the owner supplied carries the second. The build prints a number the school '
            .'publishes either way; which one the letterhead should carry is the school’s to say, and no code '
            .'disagrees with that answer.',
    ],

    // ---- §25.4 P-17: ruled, built, and the retention half measured.
    [
        'found by reading the model rather than by probing the print',
        'Open since 2026-10-23, and found by reading the model',
        'Closed 2026-10-23, on the owner’s ruling: a clearance is issued when the semester ends, as a requirement for '
            .'the clearance pass an enrollment brings, and the enrollment system keeps every student who has '
            .'completed one. So the slip names the period’s own enrollment, and when that term holds none it names '
            .'nothing — the paper prints N/A for course and year level rather than borrowing a later enrollment’s. '
            .'The remedy for such a slip is the right clearance period, not a different enrollment. The retention '
            .'half was measured before it was written down: live ems keys studentclearances on '
            .'uq_student_clearanceperiod (studentId, clearancePeriodId), the four foreign keys around the table are '
            .'NO ACTION, no file under app/ or routes/ deletes a clearance row, and generateSlip refuses to re-issue '
            .'over a clearance a desk has already answered.',
    ],
    [
        'found by reading the model rather than by probing the print',
        'The fallback at Studentclearances.php:94',
        'The method at Studentclearances.php:77 and its ruled branch at :87-93; all three slip-print paths call it '
            .'(ClearanceController.php:386, PrintService.php:77, clearance-slip.blade.php:65) and each renders the '
            .'absence its own way — clearance-slip.blade.php:69 prints N/A, PrintSlip.jsx:33 prints an em dash, and '
            .'both print-log calls key the issuance on the student rather than on an enrollment. '
            .'tests/Feature/Print/ClearanceSlipTermEnrollmentTest.php pins both branches, the three call sites, that '
            .'no delete path exists, and that a completed row survives the period that follows it — the last on both '
            .'drivers, since the unique-key message names the constraint differently on each. '
            .'storage/app/scratch/mutate-p17-term-enrollment-tests.php plants seven defects, including the fallback '
            .'re-appearing and a delete written across two lines.',
    ],
    [
        'found by reading the model rather than by probing the print',
        'A slip printed after its term closed states a course',
        'A slip printed after its term closed states no course and no year level, and says so on its face. That is '
            .'the ruling’s own consequence: the alternative — naming the newest enrollment the student holds — '
            .'printed a program and a level from a term the clearance was never about, which is what this row was '
            .'opened for. Item 14 of Documentation/School-Decision-Sheet-2026-10-23.md is answered by the ruling and '
            .'is no longer owed.',
    ],

    // ---- §28.4's group row: the five ids it files under "a decision before any code".
    [
        'A decision before any code',
        'Three of the five are settled',
        'Five of the five are settled. The fee is Reference Data’s alone (ruling 8), office 8 is retired in data '
            .'(ruling 12), and the subject load has its own template, so the three this row counted as taken were '
            .'built; the other two were answered on 2026-10-23 — the spelling of the institution’s name the paper '
            .'carries (P-16) and which enrollment a clearance slip names when its own period’s term holds none '
            .'(P-17, and the answer is none). This row waited on a decision, the decision arrived, and both are in '
            .'the build.',
    ],

    // ---- §28.6 row 5: the claim about the paper, read twice.
    [
        'The student ID is not on the paper (P-1, P-2)',
        'Re-read on the paper itself on 2026-10-21',
        'Re-read on the paper itself, twice, by extracting the text of the set php artisan ems:print-fidelity renders '
            .'from live ems (storage/app/scratch/lane62-print-paper-probe.php). On 2026-10-21: the slip held up, its '
            .'face carrying its own subject’s School ID (DEMO-2026-018 in that set) as the certificate and the '
            .'subject load did, while the enrollment form carried no ID token of any kind and no school year either — '
            .'so the half of this sentence about the form had been written without reading it. On 2026-10-23, after '
            .'the form was built: enrollment-form.pdf prints DEMO-2026-017, its course, its year level and '
            .'2025-2026, which closes P-2 on paper. What the probe still answers NO for is the class card and the '
            .'block schedule, and that is not a defect: the school’s own Class Card carries no student number at all '
            .'— it names the student in words, then Course and Year — and a block schedule is a document about a '
            .'block, not about a student.',
    ],

    // ---- §30's consistency row that quotes this requirement.
    [
        'Every printed document names the student, the program, the year level and the term',
        'Not met, and recorded as unmet',
        'Met for the four papers a student carries away, and recorded rather than tidied for the two that are not '
            .'about one student: the slip prints its ID on screen and on the PDF (P-1 closed), the certificate and '
            .'the subject load print theirs, and the enrollment form has printed its identification block since '
            .'2026-10-23 (P-2 closed). The class card carries no student number and the block schedule names no '
            .'student — which is what the school’s own papers do, read off the scans, so the claim is true of the '
            .'documents it was written about and not of the two that describe a section rather than a person. The '
            .'slip’s program and level now come from its own period’s enrollment, or say N/A (P-10, P-17).',
    ],

    // ---- §30.5's settings row.
    [
        'The settings table, whose three rows all carry a value',
        'It stays because the letterhead is the school’s',
        'It stays because the letterhead is the school’s rather than the template’s: the table is populated — 3 rows, '
            .'one of them the name — and a provider carries every row into config(‘settings.*’) at boot, so the paper '
            .'reads what an admin maintains. Two of those rows stood blank until 2026-10-21 and P-7 records where '
            .'their values were found; the spelling of the name they print was ruled on 2026-10-23 and P-16 carries '
            .'the ruling, with a test that reads the Official Name out of the sheet and refuses any value that '
            .'disagrees with it.',
    ],
];

// ---------------------------------------------------------------- text edits
// Needles must be contiguous inside one run; replacements carry plain characters.

$textEdits = [
    // §25.5's letterhead reading, measured again after the ruling.
    [
        'answers “Southeast Asian Institute of Technology” — the table\'s title-case spelling, not the upper-case '
            .'literal the call passes as its fallback — and the two rows beside it answer with the campus address '
            .'and the Registrar’s published landline, so no line of the letterhead falls through to the default '
            .'typed into the call.',
        'answers “South East Asian Institute of Technology, Inc.” — the sheet’s Official Name, which the school’s own '
            .'stationery carries in the same two-word form, and not the upper-case literal the call passes as its '
            .'fallback — and the two rows beside it answer with the campus address and the Registrar’s published '
            .'landline, so no line of the letterhead falls through to the default typed into the call. The name is '
            .'what P-16 was about; it was ruled on 2026-10-23, and PrintTemplateSelfContainmentTest now reads the '
            .'Official Name out of the sheet and refuses a seeded value or a template fallback that differs from it.',
    ],

    // §25.6's academic-period paragraph.
    [
        'at :65 (P-10 closed).',
        'at :65 (P-10 closed, and P-17 ruled the other branch on 2026-10-23: when the period’s term holds no '
            .'enrollment the slip prints N/A for course and year level rather than naming a later one).',
    ],

    // §28.8's lead: the print findings that sit outside the actors table.
    [
        'Four print findings sit outside this table because it counts only 28.2 and 28.3: P-7, whose letterhead '
            .'printed no address and no phone until 2026-10-21; P-16, the spelling of the name those rows now print, '
            .'which is the school’s to rule; and P-2, which now states its verdict: the enrollment form carries no '
            .'student identity, program, year level or school year on the paper at all, and is waiting on the blank '
            .'physical form the Registrar has not yet supplied.',
        'Four print findings sit outside this table because it counts only 28.2 and 28.3, and every one of them is '
            .'now answered: P-7, whose letterhead printed no address and no phone until 2026-10-21; P-16, the '
            .'spelling of the name those rows print, ruled on 2026-10-23; P-2, the enrollment form that carried no '
            .'student identity, course, year level or school year on the paper, built and read back off the PDF the '
            .'same day; and P-17, which enrollment a clearance slip names when its own period’s term holds none, '
            .'ruled the same day to name none. The sentence that stood here before this pass stated a figure of four '
            .'and named three — P-17 had been opened and never added to it.',
    ],

    // §30.6's opening: the institutional question this section owed.
    [
        'One institutional question is still owed here, and it is a name rather than a rule: which spelling of the '
            .'institution’s name the paper carries (P-16).',
        'No institutional question is owed here any more. The one this paragraph carried — which spelling of the '
            .'institution’s name the paper carries — was ruled on 2026-10-23, and P-16 records the answer with the '
            .'paper it was read off.',
    ],
    [
        'Of the rest, two are acts rather than answers — the program and term the enrollment form cannot print (P-2, '
            .'which also waits on the Registrar to let the blank form reach this repository), and the proxy an '
            .'operator must name before HSTS means anything (G-13) — and two are places where only the school can '
            .'settle what the paper says — the spelling of the name (P-16), and which enrollment a slip names when '
            .'its own period’s term holds none (P-17).',
        'Of the rest, one is an act rather than an answer: the proxy an operator must name before HSTS means anything '
            .'(G-13), which stays open because the owner has not decided to deploy this build and may not. The three '
            .'this paragraph used to count as owed — the program and term the enrollment form could not print (P-2), '
            .'the spelling of the name (P-16), and which enrollment a clearance slip names when its own period’s term '
            .'holds none (P-17) — were ruled on 2026-10-23 and are in the build.',
    ],

    // §1.1's living row: the same issue, later in the day.
    [
        'with mutate-lane64-empty-tables.php planting seven ways they could disagree.',
        'with mutate-lane64-empty-tables.php planting seven ways they could disagree. The three print items were then '
            .'ruled and applied in one batch: the letterhead name is South East Asian Institute of Technology, Inc. '
            .'(P-16, and a test reads that name out of the school’s own information sheet and refuses any surface '
            .'that disagrees), the enrollment form prints its identification block (P-2, built from the school’s '
            .'Subject Load wording and re-read off the rendered PDF), and a clearance slip names its own period’s '
            .'enrollment or nothing (P-17, with the retention the ruling states measured on the schema: the unique '
            .'key, four NO ACTION foreign keys, and no delete path anywhere in app/ or routes/).',
    ],
];

// The register keeps seventeen rows, so no range or family-count cell moves. This guard is what says
// so: an edit that opened or closed a row would have to move the four P-1 … P-17 statements, §29.4's
// count cell, §28.4's group row and §28.1's index, and this pass deliberately changes none of them.
$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach (['P-1 … P-17' => 4] as $needle => $want) {
    $n = substr_count($xml, $needle);
    printf("register figure  %-12s %d occurrence(s), expected %d\n", $needle, $n, $want);
    if ($n !== $want) {
        exit("REFUSED: the register's own figures are not what this pass assumes — nothing written.\n");
    }
}

docxApply($src, $root.'/storage/app/scratch/pass181-backup.docx', $textEdits, $cellEdits, $apply);

if ($apply) {
    echo "pass 181 applied.\n";
}
