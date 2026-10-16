<?php

/**
 * Pass 12 — the §28 gap register: the rows build order steps 1-6 and G-2 closed.
 *
 * Every edit is a whole-cell rewrite keyed by (the row's own title text, the current
 * prefix of the cell). The engine resolves every target before writing anything, prints
 * what each cell currently holds, and refuses on any count that is not exactly one, so a
 * wrong assumption fails on screen instead of landing in the authority document.
 *
 * Run with --apply to write; without it this is a dry run.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    // ---- G-1: returning students can now be enrolled inside the system (ruling 2).
    ['No code path creates an enrollment for a returning student', 'No code path creates an enrollment',
        'Closed 2026-10-03 by ruling 2. Department Evaluation issues the returning student’s enrollment (evaluation.store, on the evaluation.create right that had been written and unused for months), and every desk-created enrollment now goes through one service — App\Services\EnrollmentIssuer — so the seat rule and the workflow form are built the same way whether the paper starts at Admission, at Evaluation, or as a granted shift request. Stages 1-3 remain the path for a first-year or transferee applicant; a continuing student no longer needs a row made by hand.'],
    ['No code path creates an enrollment for a returning student', 'Developer (functional gap',
        'Closed in code'],

    // ---- G-2: the level is derived from the record at issue (owner ruling 2026-10-04).
    ['yearLevel is captured by hand and still never promoted', 'yearLevel is captured by hand',
        'yearLevel was asserted at creation and promoted by nothing'],
    ['yearLevel is captured by hand and still never promoted', 'Nothing promotes the level from semesters',
        'Closed 2026-10-04. Enrollments::derivedYearLevel() answers the question from the student’s own history: one plus the number of academic years that CLOSED before the year being entered opened, counting only enrollments that reached enrolled, because a term still being worked is not a year finished and a dropped one never happened. Admission approval stamps it, the issue form opens on it, and the semester half of the question was already automatic — a term row carries its own semester, so no screen ever asked a person for it. Two things the rule deliberately does not do: it does not count a student through the 2nd semester of the year they are already in (measured on the live dataset, that misread two records), and it does not override a desk — decideStanding still takes a placement, because a transferee’s or a retake’s year is an academic judgement the record does not hold. The demo tool now refuses to seat a year it cannot evidence and prints “Evaluation — level the record does not support” on every run.'],
    ['yearLevel is captured by hand and still never promoted', 'Developer (automatic promotion)',
        'Closed in code; the desk may still place a student elsewhere'],

    // ---- G-3: clearance is read by one rule, and vacuous truth is gone (rulings 4 and 5).
    ['The clearance prerequisite is a controller check, not a schema rule', 'Only the Registrar tests clearance',
        'Closed 2026-10-03 by rulings 4 and 5. One method answers the question — EnrollmentReadiness::clearanceVerdict() — and it is read by the Registrar checklist, by RegistrarPolicy (which therefore refuses with the reason on the record page, not a session error), and by the Department Evaluation desk that confirms the pass slip the student hands it. Clearance season is asked of Clearanceperiods::accepting(), so an extension counts and “no window open” is a refusal rather than a silent pass; the Registrar gate now stops when no window is accepting at all.'],
    ['The clearance prerequisite is a controller check, not a schema rule', 'Developer; the Registrar sets the policy',
        'Closed in code'],

    // ---- G-4: one active enrollment per student per term, at both creation points.
    ['One active enrollment per student per term is not enforced', 'No unique index on',
        'Closed 2026-10-03 by ruling 3, in code and not in the schema: EnrollmentIssuer::seatHolder() is consulted at every creation point (the desk issue, the shift grant, and Admission approval, which used to look up by admissionId only), so a second active record for one seat is refused by name. dropped releases the seat, which is what lets a student the Registrar dropped be enrolled in the same term again. The registrar chose the guard over a unique index, so nothing here rejects a row written directly to the table.'],
    ['One active enrollment per student per term is not enforced', 'Developer, after the Registrar confirms',
        'Closed in code; the Registrar ruled against a unique index'],

    // ---- G-7: the program change is a paper with three signatures.
    ['There is no program-change action anywhere', 'A shift appears only as a',
        'Closed 2026-10-03 by ruling 11. Shiftingrequests is an application-shaped paper with its own status (pending → endorsed → granted | rejected) and three signature pairs: the department that endorses it, the Guidance councillor who grants it, and the enrollment the grant opens. Order is enforced by the status, not by trust — decide requires endorsed, endorse requires pending. The grant issues the receiving enrollment through the same issuer, retires the record being left when it sits in the same term (so a block seat is not held twice), and refuses when any other active enrollment already holds that seat. The paper carrying both enrollment ids is the history row; and because a program change is now a named act, an ordinary issue that simply moves to a different course is refused with the shift flow named.'],
    ['There is no program-change action anywhere', 'Developer with the Registrar',
        'Closed in code'],

    // ---- G-8: examinations no longer decide the application.
    ['An approved application with no enrollment row is invisible to the next desk', 'The Evaluation queue selects on enrollmentStatus',
        'Closed 2026-10-03. Recording a course-specific result no longer writes admissionStatus = approved: the examination records the department’s opinion and stops, and approval by the Admission office is the single act that creates the enrollment (ruling 1’s boundary, the same one the general stage keeps). An approved application with no enrollment row is therefore not a state the desks can reach; a fail at either stage rejects the paper, and ApprovalReadinessTest pins both readings.'],
    ['An approved application with no enrollment row is invisible to the next desk', 'Developer',
        'Closed in code'],

    // ---- G-9: the demo sits a term the curriculum covers, with curriculum loads.
    ['The demo term offers no curriculum subject at the level and semester most active enrollments sit', 'Measured against ems on 2026-10-02',
        'Closed 2026-10-04 by ruling 1. The dataset moved off the Summer term onto AY 2025-2026 2nd semester (termId 11), which the pinned curriculum does cover, and the clearance window moved with it; every load the demo tool seats is now resolved through the desk’s own lookup, so the subjects printed are subjects that program offers at that level and semester. Measured, not claimed: the counter that printed 17 prints 0, the five “offers no subject at that level and semester” warnings are gone, and the twelve-ish rows that were placeholder codes are retired and reseeded. Two counter-checks stay in the report so the condition cannot silently return: “enrolled at a level/term the curriculum offers nothing for” and “level the record does not support”. Which subjects a genuine special term offers is still the Registrar’s to state — the calendar now reaches AY 2026-2027, and the demo simply no longer lives in a Summer term.'],
    ['The demo term offers no curriculum subject at the level and semester most active enrollments sit', 'Registrar (which subjects a special term offers)',
        'Closed by the demo owner’s ruling; the Registrar still owns any real special term'],
];

$writes = [];
$failures = 0;

foreach ($edits as $i => [$rowNeedle, $cellPrefix, $to]) {
    $rowPos = strpos($xml, $rowNeedle);
    if ($rowPos === false) {
        echo sprintf("FAIL edit %d: row needle not found [%s]\n", $i, substr($rowNeedle, 0, 60));
        $failures++;

        continue;
    }
    if (strpos($xml, $rowNeedle, $rowPos + 1) !== false) {
        echo sprintf("FAIL edit %d: row needle is not unique [%s]\n", $i, substr($rowNeedle, 0, 60));
        $failures++;

        continue;
    }

    $start = strrpos(substr($xml, 0, $rowPos), '<w:tr');
    $end = strpos($xml, '</w:tr>', $rowPos) + 7;
    if ($start === false || strpos(substr($xml, $start, $rowPos - $start), '</w:tr>') !== false) {
        echo sprintf("FAIL edit %d: row needle is not inside one table row\n", $i);
        $failures++;

        continue;
    }

    $row = substr($xml, $start, $end - $start);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);

    $hits = [];
    foreach ($cm[0] as $j => $cell) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell[0], $tm);
        $flat = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
        if (strncmp(ltrim($flat), $cellPrefix, min(strlen($cellPrefix), 60)) === 0) {
            $hits[] = $j;
        }
    }

    if (count($hits) !== 1) {
        echo sprintf("FAIL edit %d: cell prefix matched %d cells [%s]\n", $i, count($hits), substr($cellPrefix, 0, 45));
        $failures++;

        continue;
    }

    $cell = $cm[0][$hits[0]][0];
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
    $current = preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1));

    if (substr_count($cell, '<w:p>') !== 1) {
        echo sprintf("FAIL edit %d: target cell holds %d paragraphs\n", $i, substr_count($cell, '<w:p>'));
        $failures++;

        continue;
    }

    printf("ok   edit %2d  cell %d of row @%d\n       was: %s\n       now: %s\n", $i, $hits[0], $start, substr($current, 0, 110), substr($to, 0, 110));

    $writes[] = ['start' => $start, 'end' => $end, 'at' => $cm[0][$hits[0]][1], 'len' => strlen($cell), 'to' => $to];
}

if ($failures > 0) {
    echo "\n{$failures} edit(s) unresolved — nothing written\n";
    exit(1);
}

echo "\nall ".count($writes)." targets resolved uniquely\n";

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

// Rewrite from the last row position backwards so earlier offsets stay valid, and
// rewrite cells inside a row from the last one backwards for the same reason.
file_put_contents(dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass12.docx', file_get_contents($src));

$groups = [];
foreach ($writes as $w) {
    $groups[$w['start'].'-'.$w['end']][] = $w;
}
ksort($groups);
$groups = array_reverse($groups, true);

foreach ($groups as $span => $list) {
    [$start, $end] = explode('-', $span);
    $row = substr($xml, $start, $end - $start);

    usort($list, fn ($a, $b) => $b['at'] <=> $a['at']);

    foreach ($list as $w) {
        $cell = substr($row, $w['at'] - $start, $w['len']);
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $newCell = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $newCell = str_replace('</w:p>', '<w:r>'.$pr[0].'<w:t xml:space="preserve">'
            .str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $w['to']).'</w:t></w:r></w:p>', $newCell);
        $row = substr_replace($row, $newCell, $w['at'] - $start, $w['len']);
    }

    $xml = substr($xml, 0, $start).$row.substr($xml, $end);
}

// Rebuild the package in a temp file (only word/document.xml differs), then rename it
// over the original so the document is never half-written.
$tmp = sys_get_temp_dir().'/ems-docx12-'.uniqid().'.docx';
copy($src, $tmp);

$zip3 = new ZipArchive();
if ($zip3->open($tmp) !== true) {
    fwrite(STDERR, "cannot open temp zip\n");
    exit(1);
}
$zip3->addFromString('word/document.xml', $xml);
$zip3->close();

rename($tmp, $src);
echo "WROTE {$src} (backup at storage/app/scratch/docx-backup-2026-10-04-pass12.docx)\n";
