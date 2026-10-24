<?php

/**
 * Pass 183 — §25.4 P-19, and the two school-question registers it must also appear in.
 *
 * Tackling Documentation/School-Decision-Sheet-2026-10-23.md meant reading the school's own paper for the
 * first time. A real clearance slip — the student's copy — carries four fields and one signatory; the system
 * prints seven identity rows and a nine-line office table and never marks which copy it is. That is not a
 * rendering defect the print checkers can see: every pointer in §25.6 lands on a live line and the fidelity
 * run renders 8/8. So it becomes an open finding (P-19) and an open question, and §1.1's own record of pass 42
 * states the convention — a school question "exists twice — in §25.11, §26.7 or §27.7 and again here [§28.5]"
 * — which is why this pass writes three rows, not one.
 *
 * Measured, not recalled: Images/Clearance Slip.jpg (the school's paper, never committed — the scans are real
 * signed forms) against the rendered storage/app/prints/fidelity/clearance-slip.pdf read with pdftotext, and
 * against clearance-slip.blade.php:38-74 and :76-99, PrintSlip.jsx:25-42, and the two registers' own row counts
 * read out of this docx (7 questions in §25.11, 19 in §28.5, §29.4's two cells stating 19 and 18).
 *
 *   php storage/app/scratch/refresh-docx-pass183.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass183.php --apply  # write
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
$assertPlain = function (string $text, string $what) use ($say): void {
    foreach (['<', '>'] as $c) {
        if (str_contains($text, $c)) {
            $say(false, $what.' carries '.$c.' — plain characters only');

            return;
        }
    }
    $say(! str_contains($text, '&amp;'), $what.' carries no entities');
};
$flatten = function (string $xml): string {
    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $xml, $t);

    return html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
};
$setCellText = function (string $cell, string $text) use ($say, $flatten): string {
    $encoded = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $first = true;
    $out = preg_replace_callback('/<w:t(?:\s[^>]*)?>.*?<\/w:t>/s', function (array $x) use (&$first, $encoded): string {
        if ($first) {
            $first = false;

            return '<w:t xml:space="preserve">'.$encoded.'</w:t>';
        }

        return '<w:t xml:space="preserve"></w:t>';
    }, $cell);
    $say($flatten($out) === $text, 'the written cell reads exactly the pass’s own text');

    return $out;
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $new, $rows, PREG_OFFSET_CAPTURE);
$byCells = function (callable $pred) use ($rows, $flatten): array {
    $hit = [];
    foreach ($rows[0] as $r) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r[0], $cm);
        $cells = array_map(fn (string $c): string => trim($flatten($c)), $cm[0]);
        if ($pred($cells)) {
            $hit[] = ['xml' => $r[0], 'at' => $r[1], 'cells' => $cells, 'n' => count($cm[0])];
        }
    }

    return $hit;
};
/** Clone a located row into a new row carrying these cell texts, and return the insertion op. */
$cloneOp = function (array $row, array $texts, string $label) use ($say, $setCellText): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row['xml'], $cm);
    $say(count($cm[0]) === count($texts), "{$label}: one new cell for each of the template’s ".count($cm[0]).' cells');
    $clone = $row['xml'];
    foreach ($cm[0] as $i => $cell) {
        $clone = str_replace($cell, $setCellText($cell, $texts[$i]), $clone);
    }
    foreach (['<w:tr', '</w:tr>', '<w:tc>', '</w:p>', '<w:r', '</w:r>'] as $n) {
        $say(substr_count($clone, $n) === substr_count($row['xml'], $n), "{$label}: clone’s container {$n} is one row’s worth");
    }

    return ['at' => (int) $row['at'] + strlen($row['xml']), 'len' => 0, 'text' => $clone, 'label' => $label];
};

$question = 'Is the printed clearance slip the school’s student copy, or the internal docket the system builds?';
$p19Why = 'It arrived on 2026-10-23, from reading the school’s own paper for the first time while working the decision '
    .'sheet, and nothing in the repository settles which paper is the paper. The student’s copy of a real clearance '
    .'carries Course, written “(BSIT) Bachelor of Science in Information Technology - 2nd Year”, Full Name, Date and '
    .'Received by over “Registrar In-charge (Signature over Printed Name) & Date”, beneath a title that marks itself '
    .'(Student’s Copy) — and nothing else: no requirements table, no student number, no overall-status word, no '
    .'replacement fee and no document number. The system prints seven identity rows and a nine-line office table '
    .'(§25.6) and no print view marks a copy at all. Which of the two SEAIT accepts at a window, and whether the '
    .'office block belongs on an internal sheet rather than on the paper a student keeps, is the Registrar’s. '
    .'§25.4 P-19 records the comparison and stays open until it is answered; the wording of the nine lines is '
    .'already marked provisional above.';
$p19What = 'Both readings are within the build’s reach, so this is a decision and not a defect: match the student copy '
    .'and move the office block to an internal working sheet, or keep the block and add the school’s Course wording '
    .'and a copy designation to the face. The code prints the table because §12.1 seeds one requirement line for each '
    .'of the nine offices and §25.6 audits that block line by line; the school’s own paper carries neither the table '
    .'nor the student number. Recorded as §25.4 P-19, and items 1, 2, 4 and 5 of the sheet put the reading of the '
    .'paper to the Registrar with it.';

echo "document.xml: ".strlen($xml)." bytes\n\n=== stage 1: the three rows, and the two count cells they move ===\n";

$p18 = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'P-18');
$p19existing = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'P-19');
$asked25 = $byCells(fn (array $c): bool => str_starts_with($c[0] ?? '', 'Is an official PDF required') && count($c) === 2);
$asked28 = $byCells(fn (array $c): bool => str_starts_with($c[0] ?? '', 'Is an official PDF required') && count($c) === 3);
$fam = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'P-1 … P-18' && ($c[1] ?? '') === '18');
$open = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'Open decisions' && ($c[1] ?? '') === '19');
$group = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'A decision before any code');

$say(count($p18) === 1, 'exactly one §25.4 row is addressable as the clone template ('.count($p18).')');
$say(count($p19existing) === 0, 'P-19 is not already in the register');
$say(count($asked25) === 1, 'exactly one §25.11 row is addressable as the clone template ('.count($asked25).')');
$say(count($asked28) === 1, 'exactly one §28.5 row is addressable as the clone template ('.count($asked28).')');
$say(count($fam) === 1, 'exactly one §29.4 row pairs the print range with its count ('.count($fam).')');
$say(count($open) === 1, 'exactly one §29.4 row states the open-decision count as 19 ('.count($open).')');
$say(count($group) === 1, 'exactly one §28.4 group row is titled “A decision before any code”');
$say(($p18[0]['n'] ?? 0) === 5, 'the §25.4 template row has five cells (read '.($p18[0]['n'] ?? 0).')');
$say(($asked25[0]['n'] ?? 0) === 2 && ($asked28[0]['n'] ?? 0) === 3, 'the two question templates have two and three cells');

$p19Row = [
    'P-19',
    'Open since 2026-10-23, and the school owns the answer. It came from reading the school’s own paper for the '
        .'first time while working the decision sheet. A real clearance slip — the student’s copy, dated 08 MAY 2026 '
        .'for the 2nd Semester of 2025-2026 — carries four fields and nothing else: Course, written “(BSIT) Bachelor '
        .'of Science in Information Technology - 2nd Year”; Full Name; Date; and Received by over “Registrar '
        .'In-charge (Signature over Printed Name) & Date”, beneath the title CLEARANCE 2nd Semester, 2025-2026 '
        .'(Student’s Copy). It has no requirements table, no student number, no overall-status word, no date to be '
        .'signed, no document number and no fee line. The system prints every one of those and never prints the copy '
        .'designation. So the nine-office block §25 has audited line by line — and whose wording §25.11 already marks '
        .'provisional — may be the content of an internal working sheet rather than of the paper a student keeps.',
    'The school’s paper was supplied by the owner on 2026-10-23 and is not in the repository: the reference scans are '
        .'real signed forms and stay out of git. Compared against clearance-slip.blade.php:38-74 (the identity rows) '
        .'and :76-99 (the requirements table), against PrintSlip.jsx:25-42, and against the rendered '
        .'storage/app/prints/fidelity/clearance-slip.pdf read with pdftotext — which returns Semester, Academic Year, '
        .'Student Name, Student ID, Overall Status, Course and Year, Date to be Signed, then nine numbered rows of '
        .'Office, Requirement, Status, Approved By and Date. A grep for a copy designation across every print view '
        .'and desk page finds none. Live `ems` carries nine clearancerequirements rows, one per office, which is what '
        .'the table prints.',
    'The paper a student carries and the paper this section describes are different documents, so every fidelity claim '
        .'about the slip is a claim about structure only. Until the Registrar says which the system is to produce, '
        .'§25.6’s line-by-line account stands as an account of the working docket the code builds. Two readings are '
        .'open: match the student copy and move the office table to an internal sheet, or keep the table and add the '
        .'copy designation and the school’s Course wording to the face. The question is registered here and in §25.11 '
        .'and §28.5, and items 1, 2, 4 and 5 of Documentation/School-Decision-Sheet-2026-10-23.md carry the reading of '
        .'the paper against it.',
    'Institutional decision',
];
foreach ($p19Row as $t) {
    $assertPlain($t, 'P-19 cell');
}
foreach ([$question, $p19Why, $p19What, '§25.11'] as $t) {
    $assertPlain($t, 'question cell');
}

$ops = [];
$ops[] = $cloneOp($p18[0], $p19Row, '§25.4 P-19');
$ops[] = $cloneOp($asked25[0], [$question, $p19Why], '§25.11');
$ops[] = $cloneOp($asked28[0], [$question, $p19What, '§25.11'], '§28.5');

foreach ([[$fam[0], '>18<', '>19<'], [$open[0], '>19<', '>20<']] as [$row, $from, $to]) {
    $say(substr_count($row['xml'], $from) === 1, "the {$row['cells'][0]} row holds exactly one {$from} cell");
    $moved = str_replace($from, $to, $row['xml']);
    $say(strlen($moved) === strlen($row['xml']), "its count cell moved in place, same length ({$from} to {$to})");
    $ops[] = ['at' => (int) $row['at'], 'len' => strlen($row['xml']), 'text' => $moved, 'label' => "count {$from}→{$to}"];
}

usort($ops, fn (array $a, array $b): int => $b['at'] <=> $a['at']);
foreach ($ops as $op) {
    $new = substr_replace($new, $op['text'], $op['at'], $op['len']);
}

$say(substr_count($new, '>P-19<') === 1, 'P-19 exists once as a bare text node');
$say(substr_count($new, '>'.htmlspecialchars($question, ENT_QUOTES | ENT_XML1, 'UTF-8').'<') === 2,
    'the new question is the first cell of exactly two rows, §25.11 and §28.5');
$say(substr_count($new, '<w:tbl>') === substr_count($xml, '<w:tbl>'), 'no table element was added or lost');
$say(substr_count($new, '</w:tr>') === substr_count($xml, '</w:tr>') + 3, 'three rows added, none lost');
$say(substr_count($new, '<w:tc>') === substr_count($xml, '<w:tc>') + 5 + 2 + 3, 'ten cells added: five + two + three');
$say(substr_count($new, '>18<') === substr_count($xml, '>18<') - 1, 'exactly one 18 count cell became 19');
$say(substr_count($new, '>19<') === substr_count($xml, '>19<'), 'the 19 count cell moved to 20 while the print count arrived');
$say(substr_count($new, '>20<') === substr_count($xml, '>20<') + 1, 'one 20 count cell added, the open-decision tally');
$from = (int) strpos($new, '>P-1<');
$to = (int) strpos($new, '>P-19<');
preg_match_all('/>P-(\d+)</', substr($new, $from, $to - $from + 7), $pm);
$say($pm[1] === array_map('strval', range(1, 19)), 'the print register runs P-1 … P-19 in order, once each (read '.count($pm[1]).')');
$say(@(new DOMDocument)->loadXML($new) !== false, 'the staged document.xml parses');
if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

echo "\n=== stage 2: the sentences that count, or quote, what stage 1 moved ===\n";

$needles = [
    // The four range statements: §25's intro, §28.4's index row, §28.4's lead sentence, §29.4's family row.
    ['P-1 … P-18', 'P-1 … P-19', 4],
    // §28.4's group row verdict, then its id list.
    ['Five of the five are settled. The fee is Reference Data’s alone (ruling 8), office 8 is retired in data '
        .'(ruling 12), and the subject load has its own template, so the three this row counted as taken were '
        .'built; the other two were answered on 2026-10-23 — the spelling of the institution’s name the paper '
        .'carries (P-16) and which enrollment a clearance slip names when its own period’s term holds none '
        .'(P-17, and the answer is none). This row waited on a decision, the decision arrived, and both are in '
        .'the build.',
        'Four of the five were settled on 2026-10-23 — the fee is Reference Data’s alone (ruling 8), office 8 is '
            .'retired in data (ruling 12), the subject load has its own template, the spelling of the name the '
            .'paper carries (P-16) and which enrollment a clearance slip names (P-17, and the answer is none) — '
            .'and all five are in the build. A sixth id joins the row the same day: whether the printed clearance '
            .'slip is the school’s student copy or the working docket the code builds (P-19), which the Registrar '
            .'has to say before any of this row’s five decisions reaches the paper.', 1],
    ['P-12, P-4, P-9, P-16, P-17', 'P-12, P-4, P-9, P-16, P-17, P-19', 1],
    // §28.8's tally of print findings outside the actors table, and the list it introduces.
    ['Five print findings sit outside this table because it counts only 28.2 and 28.3, and every one of them '
        .'is now answered:',
        'Six print findings sit outside this table because it counts only 28.2 and 28.3; five are answered and '
            .'the sixth, P-19, is the school’s to settle:', 1],
    ['found by reviewing it line by line the day after. The sentence that stood here before pass 181',
        'found by reviewing it line by line the day after; and P-19, opened by reading the school’s own student '
            .'copy of the slip against the paper the system prints, which is not a finding the code can close. '
            .'The sentence that stood here before pass 181', 1],
    // §28.5's own intro counts the rows stage 1 grew.
    ['Nothing in this table asks for code. The fifteen that do wait on an office',
        'Nothing in this table asks for code. The sixteen that do wait on an office', 1],
    // §30.6's opening: pass 181 declared no institutional question owed; P-19 is one.
    ['No institutional question is owed here any more. The one this paragraph carried — which spelling of the '
        .'institution’s name the paper carries — was ruled on 2026-10-23, and P-16 records the answer with the '
        .'paper it was read off.',
        'One institutional question is owed here, and it arrived on 2026-10-23 rather than being left over: '
            .'whether the printed clearance slip is the school’s student copy — four fields, one signatory, no '
            .'requirements table — or the working docket the code builds (P-19). The question this paragraph used '
            .'to carry, the spelling of the institution’s name, was ruled the same day and P-16 records the answer '
            .'with the paper it was read off.', 1],
    // §25.6's identity paragraph: what the slip prints, against what the school's paper prints.
    ['(P-1 closed).',
        '(P-1 closed). Read against the school’s own student copy on 2026-10-23, though, this is the half of the '
            .'paper the school does not have: its slip carries Course, Full Name, Date and Received by only, writes '
            .'the level as “2nd Year” rather than “Year 2”, and marks itself (Student’s Copy) — which no print view '
            .'here emits (P-19, open).', 1],
    // §1.1's living row.
    ['and the mutant harness grown to eleven legs.',
        'and the mutant harness grown to eleven legs. Tackling the decision sheet then read the school’s own '
            .'clearance slip for the first time and opened §25.4 P-19: the student’s copy carries Course, Full Name, '
            .'Date and Received by (Registrar In-charge, signature over printed name) and nothing else — no '
            .'requirements table, no student number, no fee line, and a copy designation the system never prints — so '
            .'which of the two papers the system is to produce is the Registrar’s to say. §25.11 and §28.5 each gained '
            .'that question, which is why §29.4 now counts sixteen open decisions rather than fifteen, and the sheet '
            .'carries the reading of the paper against items 1, 2, 4 and 5 and §2’s record of the board programs '
            .'against items 6 and 9: BSCrim and BSSW are named there, while the database carries the departmental '
            .'flag on five.', 1],
];
foreach ($needles as $i => [$from, $to, $want]) {
    $c = substr_count($new, $from);
    $say($c === $want, 'needle '.($i + 1).' occurs '.$c.' time(s), expected '.$want.': ['.mb_substr($from, 0, 46).']');
    $assertPlain($to, 'needle '.($i + 1).' replacement');
}
if ($fails > 0) {
    printf("\n%d guard(s) failed before any write — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: stage 1 would clone P-19 into §25.4 and the same question into §25.11 and §28.5, and move two"
        ." §29.4 count cells; stage 2 would rewrite the nine sentences above (the range statement four times).\n"
        ."Re-run with --apply.\n";
    exit(0);
}

foreach ($needles as [$from, $to, $want]) {
    $new = str_replace($from, $to, $new);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass183.docx');
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
