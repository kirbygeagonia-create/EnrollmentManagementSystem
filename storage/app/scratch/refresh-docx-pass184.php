<?php

/**
 * Pass 184 — P-19 is ruled and built, and every register that asked it now says so.
 *
 * Ruled 2026-10-23 by the project owner: keep the nine-office table — the desks sign against it — and add the
 * copy designation and the school's course-code wording to the face of the slip. Built the same day on both
 * print surfaces, and read back off the re-rendered PDF rather than off the template.
 *
 * One half of the school's paper was deliberately NOT adopted, and the row says so: the school writes the level
 * as "2nd Year", while resources/js/Components/ui/statusLabel.js:50-56 records that six pages once printed four
 * vocabularies for one number and the team settled on "Year 2" to match the evaluator's picker. Re-spelling it on
 * one paper would restart that. The deviation is recorded as a consequence, not buried.
 *
 * Two things this pass had to learn the hard way, both written into its guards. The cells are rewritten in raw
 * document.xml, so a needle whose text contains an ampersand must carry "&amp;" — the flattened reading the
 * dump shows is decoded, and a plain-& needle matches nothing. And the build moved the lines §25.6 cites:
 * clearance-slip.blade.php:38-74 and PrintSlip.jsx:25-38 are re-pointed here, not left describing the file as
 * it stood before the ruling was built.
 *
 *   php storage/app/scratch/refresh-docx-pass184.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass184.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

/**
 * Each entry: [what it is, current bytes, replacement bytes, expected occurrences]. The current texts were read
 * out of this file by storage/app/scratch/probe-p19-cells.php rather than recalled — a needle written from
 * memory is a needle that silently matches nothing.
 */
$edits = [
    // --- §25.4 P-19, the finding cell ------------------------------------------------------------
    [
        '§25.4 P-19 finding',
        'Open since 2026-10-23, and the school owns the answer. It came from reading the school’s own paper for the '
            .'first time while working the decision sheet. A real clearance slip — the student’s copy, dated 08 MAY 2026 '
            .'for the 2nd Semester of 2025-2026 — carries four fields and nothing else: Course, written “(BSIT) Bachelor '
            .'of Science in Information Technology - 2nd Year”; Full Name; Date; and Received by over “Registrar '
            .'In-charge (Signature over Printed Name) &amp; Date”, beneath the title CLEARANCE 2nd Semester, 2025-2026 '
            .'(Student’s Copy). It has no requirements table, no student number, no overall-status word, no date to be '
            .'signed, no document number and no fee line. The system prints every one of those and never prints the copy '
            .'designation. So the nine-office block §25 has audited line by line — and whose wording §25.11 already marks '
            .'provisional — may be the content of an internal working sheet rather than of the paper a student keeps.',
        'Closed 2026-10-23 by the ruling given the same day the row was opened. Reading the school’s own paper for '
            .'the first time — the student’s copy, dated 08 MAY 2026 for the 2nd Semester of 2025-2026 — found two '
            .'documents where §25 had described one. The school’s carries four fields and nothing else: Course written '
            .'“(BSIT) Bachelor of Science in Information Technology - 2nd Year”; Full Name; Date; and Received by over '
            .'“Registrar In-charge (Signature over Printed Name) &amp; Date”, beneath a title that marks itself (Student’s '
            .'Copy). This system’s carried a document number, a student number, an overall-status word, a date to be '
            .'signed, a fee line and the nine-office table, and marked nothing. Ruled: keep the office table, because '
            .'the desks sign against it, and add the copy designation and the school’s course-code wording to the face. '
            .'Built the same day on both print surfaces.',
        1,
    ],
    // --- §25.4 P-19, the evidence cell: the prose around the ranges --------------------------------
    [
        '§25.4 P-19 evidence',
        'with pdftotext — which returns Semester, Academic Year, Student Name, Student ID, Overall Status, Course '
            .'and Year, Date to be Signed, then nine numbered rows of Office, Requirement, Status, Approved By and Date. '
            .'A grep for a copy designation across every print view and desk page finds none.',
        'with pdftotext. Before the build that file returned Semester, Academic Year, Student Name, Student ID, '
            .'Overall Status, Course and Year, Date to be Signed, then nine numbered rows of Office, Requirement, Status, '
            .'Approved By and Date, with no copy mark anywhere; a grep across every print view, desk page and shared '
            .'component found none. After it the same file reads CLEARANCE SLIP (STUDENT’S COPY) over Course &amp; Year: '
            .'(BSIT) Bachelor of Science in Information Technology - Year 2, and the office table is unchanged at nine '
            .'rows. The ranges cited above are the post-build file, re-measured rather than shifted by arithmetic: the '
            .'identity block grew by the eight lines that build the label.',
        1,
    ],
    // --- §25.4 P-19, the consequence cell ---------------------------------------------------------
    [
        '§25.4 P-19 consequence',
        'The paper a student carries and the paper this section describes are different documents, so every fidelity '
            .'claim about the slip is a claim about structure only. Until the Registrar says which the system is to '
            .'produce, §25.6’s line-by-line account stands as an account of the working docket the code builds. Two '
            .'readings are open: match the student copy and move the office table to an internal sheet, or keep the '
            .'table and add the copy designation and the school’s Course wording to the face. The question is registered '
            .'here and in §25.11 and §28.5, and items 1, 2, 4, 5 and 16 of Documentation/School-Decision-Sheet-2026-10-23.md '
            .'carry the reading of the paper against it — item 16 asks this question itself, and was added after the '
            .'sheet was issued.',
        'The office table stays, so §25.6’s line-by-line account is now an account of the paper a student is handed '
            .'as well as of the docket. What the ruling did not adopt is the school’s spelling of the year level: the '
            .'paper writes “2nd Year”, and this build prints “Year 2”, because resources/js/Components/ui/statusLabel.js:50-56 '
            .'records that six pages once carried four vocabularies for one number and the shared formatter exists to '
            .'stop that. Re-spelling the level on one surface only would restart the defect the formatter closed, so if '
            .'the Registrar wants the ordinal on paper it is one formatter’s change and every §25 paper moves with it. '
            .'The school’s other absences — no student number, no status word, no fee line, no document number on the '
            .'student’s copy — were not part of the ruling and are not removed, and items 1, 2, 4 and 5 of '
            .'Documentation/School-Decision-Sheet-2026-10-23.md still put them to the Registrar.',
        1,
    ],
    // --- §25.11's question row --------------------------------------------------------------------
    [
        '§25.11 question row',
        'It arrived on 2026-10-23, from reading the school’s own paper for the first time while working the decision '
            .'sheet, and nothing in the repository settles which paper is the paper. The student’s copy of a real '
            .'clearance carries Course, written “(BSIT) Bachelor of Science in Information Technology - 2nd Year”, Full '
            .'Name, Date and Received by over “Registrar In-charge (Signature over Printed Name) &amp; Date”, beneath a '
            .'title that marks itself (Student’s Copy) — and nothing else: no requirements table, no student number, no '
            .'overall-status word, no replacement fee and no document number. The system prints a block of identity rows '
            .'over a nine-line office table (§25.6) — eight rows on the desk paper, seven on the file ems:print-fidelity '
            .'renders, because that command passes no document number — and no print view marks a copy at all. Which of '
            .'the two SEAIT accepts at a window, and whether the office block belongs on an internal sheet rather than on '
            .'the paper a student keeps, is the Registrar’s. §25.4 P-19 records the comparison and stays open until it is '
            .'answered; the wording of the nine lines is already marked provisional above.',
        'Answered 2026-10-23, the same day it was asked: keep the office table and put the copy designation and the '
            .'school’s course-code wording on the face. The question came from reading the school’s own paper for the '
            .'first time — the student’s copy carries Course written “(BSIT) Bachelor of Science in Information '
            .'Technology - 2nd Year”, Full Name, Date and Received by over “Registrar In-charge (Signature over Printed '
            .'Name) &amp; Date”, beneath a title marking itself (Student’s Copy), and nothing else. Built the same day: '
            .'the slip now titles itself Clearance Slip (Student’s Copy) and leads the program with its code, on the '
            .'desk page and on the generated PDF together, and the nine-office block is unchanged. The one half not '
            .'adopted is the school’s spelling of the level — “2nd Year” against this build’s “Year 2”, which '
            .'resources/js/Components/ui/statusLabel.js exists to keep single — and the wording of the nine lines is '
            .'still provisional above.',
        1,
    ],
    // --- §28.5's decision row ---------------------------------------------------------------------
    [
        '§28.5 decision row',
        'Both readings are within the build’s reach, so this is a decision and not a defect: match the student copy '
            .'and move the office block to an internal working sheet, or keep the block and add the school’s Course '
            .'wording and a copy designation to the face. The code prints the table because §12.1 seeds one requirement '
            .'line for each of the nine offices and §25.6 audits that block line by line; the school’s own paper carries '
            .'neither the table nor the student number. Recorded as §25.4 P-19, and items 1, 2, 4 and 5 of the sheet put '
            .'the reading of the paper to the Registrar with it.',
        'Answered 2026-10-23, the day it was registered, and built the same sitting: the office block stays because '
            .'the desks sign against it, and the face gained the copy designation and the school’s course-code wording. '
            .'The two readings had been within the build’s reach — match the student copy and move the block to an '
            .'internal sheet, or keep it and mark the copy — and the second was chosen. §25.4 P-19 carries the '
            .'comparison, the render it was read off, and the one half of the school’s wording that was not adopted; '
            .'items 1, 2, 4 and 5 of the sheet stay open on their own questions.',
        1,
    ],
    // --- §28.4's group row verdict ----------------------------------------------------------------
    [
        '§28.4 group row verdict',
        'A sixth id joins the row the same day: whether the printed clearance slip is the school’s student copy or '
            .'the working docket the code builds (P-19), which the Registrar has to say before any of this row’s five '
            .'decisions reaches the paper.',
        'A sixth id joined the row the same day and was answered the same day: whether the printed clearance slip is '
            .'the school’s student copy or the working docket the code builds (P-19). It was ruled to be both at once — '
            .'the office block stays and the face now says whose copy it is — so all six of this row’s decisions are in '
            .'the build.',
        1,
    ],
    // --- §28.8's tally and the list it introduces --------------------------------------------------
    [
        '§28.8 print tally',
        'Six print findings sit outside this table because it counts only 28.2 and 28.3; five are answered and the '
            .'sixth, P-19, is the school’s to settle:',
        'Six print findings sit outside this table because it counts only 28.2 and 28.3, and every one of them is '
            .'now answered:',
        1,
    ],
    [
        '§28.8 P-19 clause',
        'found by reviewing it line by line the day after; and P-19, opened by reading the school’s own student copy '
            .'of the slip against the paper the system prints, which is not a finding the code can close. The sentence '
            .'that stood here before pass 181',
        'found by reviewing it line by line the day after; and P-19, opened by reading the school’s own student copy '
            .'of the slip against the paper the system prints, ruled the same day and built the same sitting — the '
            .'office table stays and the face now marks the copy. The sentence that stood here before pass 181',
        1,
    ],
    // --- §30.6's opening, which pass 183 re-opened --------------------------------------------------
    [
        '§30.6 opening',
        'One institutional question is owed here, and it arrived on 2026-10-23 rather than being left over: whether '
            .'the printed clearance slip is the school’s student copy — four fields, one signatory, no requirements '
            .'table — or the working docket the code builds (P-19). The question this paragraph used to carry, the '
            .'spelling of the institution’s name, was ruled the same day and P-16 records the answer with the paper it '
            .'was read off.',
        'No institutional question is owed here, and the one that arrived on 2026-10-23 is why that sentence has now '
            .'been written twice: whether the printed clearance slip is the school’s student copy or the working docket '
            .'the code builds was asked, ruled and built the same day (P-19). The question this paragraph used to carry, '
            .'the spelling of the institution’s name, was ruled the same day and P-16 records the answer with the paper '
            .'it was read off.',
        1,
    ],
    // --- the four ranges the build moved, fixed as tokens because a citation sits in its own run ----
    [
        'range clearance-slip identity',
        'clearance-slip.blade.php:38-74',
        'clearance-slip.blade.php:38-82',
        2,
    ],
    [
        'range clearance-slip table',
        ':76-99 (the requirements table)',
        ':84-107 (the requirements table)',
        1,
    ],
    [
        'range desk page four fields',
        'PrintSlip.jsx:25-42',
        'PrintSlip.jsx:31-49',
        1,
    ],
    [
        'range desk page three fields',
        'PrintSlip.jsx:25-38',
        'PrintSlip.jsx:31-44',
        1,
    ],
    // --- §25.6's identity paragraph, which pass 183 amended -----------------------------------------
    [
        '§25.6 identity paragraph',
        'Read against the school’s own student copy on 2026-10-23, though, this is the half of the paper the school '
            .'does not have: its slip carries Course, Full Name, Date and Received by only, writes the level as “2nd '
            .'Year” rather than “Year 2”, and marks itself (Student’s Copy) — which no print view here emits (P-19, open).',
        'Read against the school’s own student copy on 2026-10-23, the two papers differ in what they carry, not in '
            .'which copy they are: the school’s holds Course, Full Name, Date and Received by only, and this one adds '
            .'the student number, the status word, the document number, the fee and the nine office lines. Its title now '
            .'marks the copy — Clearance Slip (Student’s Copy) — and its program line leads with the code, which is what '
            .'the ruling asked for (P-19 closed); the level stays “Year 2” against the school’s “2nd Year”, for the '
            .'reason §25.4 records.',
        1,
    ],
    // --- §1.1's living row ---------------------------------------------------------------------------
    [
        '§1.1 living row',
        '§29.4 counts twenty open decisions where it had counted nineteen and Documentation/School-Decision-Sheet-2026-10-23.md '
            .'now carries sixteen items where it carried fifteen;',
        '§29.4 counts twenty open decisions where it had counted nineteen and Documentation/School-Decision-Sheet-2026-10-23.md '
            .'now carries sixteen items where it carried fifteen; the row was then ruled and built in the same sitting — '
            .'the office table stays, both print surfaces title themselves (Student’s Copy) and lead the program with '
            .'its code, and the school’s “2nd Year” is the one half deliberately not adopted, because '
            .'statusLabel.js exists to keep the level spelled one way across every paper;',
        1,
    ],
];

echo '=== P-19’s closure: ', count($edits), " sentences, each read out of the file it edits ===\n";
foreach ($edits as [$what, $from, $to, $want]) {
    foreach (['<', '>'] as $c) {
        $say(! str_contains($to, $c), $what.': replacement carries no '.$c);
    }
    $say(! preg_match('/&(?!amp;|lt;|gt;|quot;|#)/', $to), $what.': every ampersand in the replacement is an entity');
    $c = substr_count($xml, $from);
    $say($c === $want, $what.': needle occurs '.$c.' time(s), expected '.$want);
}

$say(substr_count($xml, 'Open since 2026-10-23') === 1, 'exactly one row still leads with the open verb, until these edits apply');
$say(substr_count($xml, '>P-19<') === 1, 'P-19 is still addressed once as a bare text node');
$say(substr_count($xml, 'clearance-slip.blade.php:38-74') === 2, 'the pre-build identity range is cited twice (§25.4 and §25.6), and both move here');
$say(substr_count($xml, 'PrintSlip.jsx:25-38') === 1 && substr_count($xml, 'PrintSlip.jsx:25-42') === 1,
    'the two pre-build desk ranges are each cited once');
if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

$staged = $xml;
foreach ($edits as [$what, $from, $to, $want]) {
    $staged = str_replace($from, $to, $staged);
    $say(substr_count($staged, $to) === $want, $what.': the replacement lands once');
}
$say(substr_count($staged, 'Open since 2026-10-23') === 0, 'no print row leads with an open verb any more');
$say(substr_count($staged, '(P-19, open)') === 0, 'nothing still presents P-19 as an open finding');
foreach (['clearance-slip.blade.php:38-74', ':76-99', 'PrintSlip.jsx:25-38', 'PrintSlip.jsx:25-42'] as $stale) {
    $say(! str_contains($staged, $stale), 'no pointer still cites the pre-build '.$stale);
}
$say(substr_count($staged, '<w:tbl>') === substr_count($xml, '<w:tbl>'), 'no table element was added or lost');
$say(substr_count($staged, '</w:tr>') === substr_count($xml, '</w:tr>'), 'no row was added or lost — this pass closes, it does not open');
$start = (int) strpos($staged, '>P-1<');
$end = (int) strpos($staged, '>P-19<');
preg_match_all('/>P-(\d+)</', substr($staged, $start, $end - $start + 7), $pm);
$say($pm[1] === array_map('strval', range(1, 19)), 'the print register still runs P-1 … P-19 in order, once each');
$say((string) $staged !== $xml, 'the staged bytes differ from the file, so something was actually written');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
if ($fails > 0) {
    printf("\n%d guard(s) failed on the staged bytes — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "\nDRY RUN: ", count($edits), " sentences and ranges would be rewritten — P-19's three cells, the two\n"
        ."school-question registers, §28.4's group verdict, §28.8's tally and list, §30.6's opening, §25.6's\n"
        ."paragraph, the four ranges the build moved, and §1.1's living row. Re-run with --apply.\n";
    exit(0);
}

$new = $staged;
copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass184.docx');
$z = new ZipArchive();
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $new);
$z->close();
echo "WROTE {$src}\n";
