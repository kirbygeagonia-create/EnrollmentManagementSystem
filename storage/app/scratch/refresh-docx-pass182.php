<?php

/**
 * Pass 182 — P-18: the enrollment form reviewed line by line.
 *
 * P-2 closed on 2026-10-23, and the review that followed found three more things the same sheet stated
 * wrongly — none of them visible to a green suite, because a filter dropped from a blade still renders, a
 * dead `@if` still renders nothing, and a print-log row minted after the page still lands in the database.
 * This pass clones the register row, moves the six places that count it, and corrects one §25.3 verdict
 * sentence that pass 181 left behind (it names no finding id with an open word, so lane 61 cannot see it).
 *
 * Measured before being written, all of it on live `ems` the same day:
 *   - enrolledsubjects holds 57 confirmed, 33 proposed and 21 dropped rows;
 *   - 18 enrollments hold a non-confirmed row, and every one of those 18 holds NO confirmed row at all,
 *     so each printed a whole subject table for a load nobody confirmed while its Subject Load printed none;
 *   - `Addresses::$casts` declares addressType as an enum, so `=== 'home'` and `where('addressType', 'current')`
 *     are each false, which is why the home/current line had never printed;
 *   - the two papers now agree for enrollment 92: RH1, ET1, PE2, TOTAL 8 on both, read off the rendered PDFs.
 *
 *   php storage/app/scratch/refresh-docx-pass182.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass182.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};
$assertPlain = function (string $text, string $what) use (&$fails, $guard): void {
    foreach (['<', '>'] as $c) {
        if (str_contains($text, $c)) {
            $guard(false, $what.' carries '.$c.' — this pass writes plain characters only');

            return;
        }
    }
    $guard(! str_contains($text, '&amp;') && ! str_contains($text, '&lt;'), $what.' carries no entities');
};

$flatten = function (string $xml): string {
    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $xml, $t);

    return html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
};
$setCellText = function (string $cell, string $text) use ($guard, $flatten): string {
    $encoded = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $first = true;
    $out = preg_replace_callback('/<w:t(?:\s[^>]*)?>.*?<\/w:t>/s', function (array $x) use (&$first, $encoded): string {
        if ($first) {
            $first = false;

            return '<w:t xml:space="preserve">'.$encoded.'</w:t>';
        }

        return '<w:t xml:space="preserve"></w:t>';
    }, $cell);
    $flat = $flatten($out);
    $guard($flat === $text, 'the written cell reads exactly the pass’s own text, not merely contains it');

    return $out;
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;
echo "document.xml: ".strlen($xml)." bytes\n\n=== stage 1: the register row and the figures it moves ===\n";

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

$p17 = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'P-17');
$p18existing = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'P-18');
$fam = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'P-1 … P-17' && ($c[1] ?? '') === '17');
$guard(count($p17) === 1, 'exactly one §25.4 row is addressable as the clone template ('.count($p17).')');
$guard(count($p18existing) === 0, 'P-18 is not already in the register');
$guard(count($fam) === 1, 'exactly one §29.4 family row pairs the range with its count cell ('.count($fam).')');
$guard(($p17[0]['n'] ?? 0) === 5, 'the §25.4 template row has five cells (read '.($p17[0]['n'] ?? 0).')');

$p18 = [
    'P-18',
    'Closed 2026-10-23, found by reviewing the enrollment form line by line rather than by probing it — the '
        .'review P-2’s closure invited. Three things the sheet stated wrongly, all on the document whose '
        .'identity block had just been built. Part 2 listed every row of `enrolledsubjects` the enrollment '
        .'holds and totalled them, although proposed and dropped rows are workflow state and the Subject '
        .'Load has always filtered to confirmed; the “Same as above (Current Address)” box compared '
        .'`addressType`, which is an enum cast, with === to a string, so it was false for every address and '
        .'the line had never printed at all; and the print’s own issuance number was minted after the page '
        .'was drawn and printed nowhere, so the log row existed with no sheet to match it.',
    'enrollment-form.blade.php:315-326 is the confirmed-only filter and the sentence it prints when nothing '
        .'is confirmed, :164-173 and :220 do the address comparison by reading each enum’s own value rather '
        .'than the object, :71-77 is the Document No. field, and PrintService.php:321-350 now mints before it '
        .'renders. '
        .'Measured first, on live `ems`: `enrolledsubjects` holds 57 confirmed, 33 proposed and 21 dropped '
        .'rows; 18 enrollments hold a non-confirmed row and every one of those 18 holds no confirmed row at '
        .'all, so each printed a full subject table for a load nobody confirmed and disagreed with its own '
        .'Subject Load by the whole table. The student type also printed “FirstYear” where the certificate '
        .'and the Subject Load print “First Year”. After the fix both papers state enrollment 92 as RH1, '
        .'ET1 and PE2, TOTAL 8, read off the rendered PDFs.',
    'Closed in code and pinned: tests/Feature/Print/PrintEnrollmentFormPayloadTest.php now carries six cases '
        .'on this sheet and storage/app/scratch/mutate-p2-payload-tests.php plants eleven defects, six of them '
        .'P-18’s — the filter removed, the empty-load sentence dropped, the enum compared with a string '
        .'again, the number taken off the page, the number minted after the render, and the type spelled by '
        .'ucfirst. Two things the review recorded rather than fixed, because both are letterhead questions '
        .'and not code defects: the form is the only paper that prints Semesters Completed and Years in '
        .'Institution, which intake zeroes and one controller maintains (§30.4), and it carries no OFFICE OF '
        .'THE REGISTRAR line where the class card and the Subject Load do.',
    'Rendering + reachability gap',
];
foreach ($p18 as $t) {
    $assertPlain($t, 'P-18 cell');
}

$cloneRow = function (array $row, array $texts, string $what) use ($setCellText, $guard, $flatten): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row['xml'], $cm);
    $guard(count($cm[0]) === count($texts), $what.': one cell for each of the template’s '.count($cm[0]).' cells');
    $clone = $row['xml'];
    foreach ($cm[0] as $i => $cell) {
        $clone = str_replace($cell, $setCellText($cell, $texts[$i]), $clone);
    }
    foreach (['<w:tr', '</w:tr>', '<w:tc>', '</w:p>', '<w:r', '</w:r>'] as $n) {
        $guard(substr_count($clone, $n) === substr_count($row['xml'], $n), $what.' container '.$n.': one row’s worth');
    }
    $guard(! str_contains($flatten($clone), 'PLACEHOLDER'), $what.': no placeholder text survived the clone');
    $guard(str_contains('>'.$clone.'>', '>'.htmlspecialchars($texts[0], ENT_QUOTES | ENT_XML1, 'UTF-8').'<'),
        $what.' is addressed by its own first cell');

    return [$clone, (int) $row['at'] + strlen($row['xml'])];
};

// §29.4's count cell first, in place and same-length, so the clone's offset stays valid.
$famRow = $fam[0];
$guard(substr_count($famRow['xml'], '>17<') === 1, 'the §29.4 family row holds exactly one 17 cell');
$newFam = str_replace('>17<', '>18<', $famRow['xml']);
$guard(strlen($newFam) === strlen($famRow['xml'])
    && substr_count($newFam, '>18<') === substr_count($famRow['xml'], '>18<') + 1, 'the count cell moved in place, same length');
$new = substr_replace($new, $newFam, $famRow['at'], strlen($famRow['xml']));

[$bytes, $insertAfter] = $cloneRow($p17[0], $p18, '§25.4 P-18');
$new = substr_replace($new, $bytes, $insertAfter, 0);

$guard(substr_count($new, '>17<') === substr_count($xml, '>17<') - 1, 'exactly one 17 cell became 18');
$guard(substr_count($new, '>P-18<') === 1, 'P-18 exists once as a bare text node');
$guard(substr_count($new, '<w:tbl>') === substr_count($xml, '<w:tbl>'), 'no table element was added or lost');
$guard(substr_count($new, '</w:tr>') === substr_count($xml, '</w:tr>') + 1, 'one row added, none lost');
$rangeFrom = (int) strpos($new, '>P-1<');
$rangeTo = (int) strpos($new, '>P-18<');
preg_match_all('/>P-(\d+)</', substr($new, $rangeFrom, $rangeTo - $rangeFrom + 7), $pm);
$guard($pm[1] === array_map('strval', range(1, 18)), 'the print register runs P-1 … P-18 in order, once each (read '.count($pm[1]).')');
$guard(@(new DOMDocument)->loadXML($new) !== false, 'the staged document.xml parses');
if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

echo "\n=== stage 2: the sentences that count, or quote, what stage 1 moved ===\n";

// [needle, replacement, expected occurrences]
$needles = [
    // The four range statements. §25's intro, §28.4's index row, §28.4's lead sentence, §29.4's family row.
    ['P-1 … P-17', 'P-1 … P-18', 4],
    // §25.3's verdict paragraph: stale twice over — it still says the form prints no ID and no period,
    // and that the slip reads the wrong enrollment. Pass 181 moved the register rows around it and left
    // this one, because it names no finding id with an open word, so lane 61 has nothing to index.
    ['and it prints neither student ID nor academic period.',
        'and it prints both since 2026-10-23 (P-2 closed, and P-18 fixed what its Part 2 counted and whose '
            .'issuance number it prints).', 1],
    ['takes program and year level from the wrong enrollment (P-10).',
        'takes program and year level from the enrollment of its own period’s term, or prints N/A when that '
            .'term holds none (P-10, P-17).', 1],
    // §28.8's lead: P-18 is the fifth print finding outside the actors table.
    ['Four print findings sit outside this table because it counts only 28.2 and 28.3, and every one of them is '
        .'now answered: P-7, whose letterhead printed no address and no phone until 2026-10-21; P-16, the '
        .'spelling of the name those rows print, ruled on 2026-10-23; P-2, the enrollment form that carried no '
        .'student identity, course, year level or school year on the paper, built and read back off the PDF the '
        .'same day; and P-17, which enrollment a clearance slip names when its own period’s term holds none, '
        .'ruled the same day to name none. The sentence that stood here before this pass stated a figure of four '
        .'and named three — P-17 had been opened and never added to it.',
        'Five print findings sit outside this table because it counts only 28.2 and 28.3, and every one of them '
            .'is now answered: P-7, whose letterhead printed no address and no phone until 2026-10-21; P-16, the '
            .'spelling of the name those rows print, ruled on 2026-10-23; P-2, the enrollment form that carried '
            .'no student identity, course, year level or school year on the paper, built and read back off the '
            .'PDF the same day; P-17, which enrollment a clearance slip names when its own period’s term holds '
            .'none, ruled the same day to name none; and P-18, three more things the same form stated wrongly, '
            .'found by reviewing it line by line the day after. The sentence that stood here before pass 181 '
            .'stated a figure of four and named three — P-17 had been opened and never added to it, which is '
            .'lane 64’s membership defect in prose.', 1],
    // §1.1's living row.
    ['the unique key, four NO ACTION foreign keys, and no delete path anywhere in app/ or routes/).',
        'the unique key, four NO ACTION foreign keys, and no delete path anywhere in app/ or routes/). '
            .'Reviewing the enrollment form after P-2 closed opened §25.4 P-18: its Part 2 totalled proposed and '
            .'dropped subjects as though they were the term’s load (18 enrollments on live `ems` hold no '
            .'confirmed row at all), its home/current box compared an enum cast with === to a string so it had '
            .'never printed, and its issuance number was minted after the page and printed nowhere — all three '
            .'fixed, six new cases in the payload test, and the mutant harness grown to eleven legs.', 1],
];
foreach ($needles as $i => [$from, $to, $want]) {
    $c = substr_count($new, $from);
    $guard($c === $want, 'needle '.($i + 1).' occurs '.$c.' time(s), expected '.$want.': ['.mb_substr($from, 0, 46).']');
    $assertPlain($to, 'needle '.($i + 1).' replacement');
}
if ($fails > 0) {
    printf("\n%d guard(s) failed before any write — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: stage 1 would clone P-18 into §25.4 and move §29.4's count cell;\n"
        ."stage 2 would rewrite the five sentences above (the range statement four times). Re-run with --apply.\n";
    exit(0);
}

foreach ($needles as [$from, $to, $want]) {
    $new = str_replace($from, $to, $new);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass182.docx');
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
