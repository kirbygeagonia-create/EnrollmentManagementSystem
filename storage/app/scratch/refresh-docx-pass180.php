<?php

/**
 * Lane 64 / docx pass 180 — a section's MEMBERSHIP re-counted against COUNT(*).
 *
 * §24.17 is titled "Tables that are empty in the demo database". Every FIGURE beside it has been
 * re-read by lanes 58-61; its SETS never had been. On live `ems` COUNT(*) answers ten empty tables,
 * six of them Laravel framework tables and four domain, while the section's split sentence said
 * "the last six of them framework tables" of an alphabetical list whose last six include two domain
 * tables, §24.18's population row counted 8 domain empties, `shiftingrequests` was named in the
 * sentence and given no row, and §28.1's index still said the G family runs G-1 to G-8.
 *
 * Stage 1 splices §29.4's count cell and clones the two rows; stage 2 rewrites the seven sentences
 * that count the registers. DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};
$assertPlain = function (string $text, string $what) use (&$fails): void {
    $bad = [];
    foreach (['&', '<', '>'] as $c) {
        if (str_contains($text, $c)) {
            $bad[] = $c;
        }
    }
    if ($bad !== []) {
        printf("  FAIL %s carries %s — it would be escaped twice or corrupt the part\n", $what, implode(' ', $bad));
        $fails++;
    }
};
$setCellText = function (string $cell, string $text) use ($guard): string {
    $encoded = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $first = true;
    $out = preg_replace_callback('/<w:t(?:\s[^>]*)?>.*?<\/w:t>/s', function (array $x) use (&$first, $encoded): string {
        if ($first) {
            $first = false;

            return preg_replace('/>.*?<\/w:t>/s', '>'.$encoded.'</w:t>', $x[0], 1);
        }

        return preg_replace('/>.*?<\/w:t>/s', '></w:t>', $x[0], 1);
    }, $cell);
    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $out, $t);
    $flat = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $guard($flat === $text, 'the written cell reads exactly the pass’s own text, not merely contains it');

    return $out;
};
$flatten = function (string $xml): string {
    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $xml, $t);

    return html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$parts = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;
echo 'document.xml: '.strlen($xml)." bytes\n\n=== stage 1: measurements the new rows rest on ===\n";

// What COUNT(*) actually answers, read before the sentence that states it is written (lane 62's rule).
require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$counts = [];
foreach (array_map(fn (stdClass $r): string => $r->TABLE_NAME,
    Illuminate\Support\Facades\DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")) as $t) {
    $counts[$t] = (int) Illuminate\Support\Facades\DB::select("SELECT COUNT(*) c FROM `{$t}`")[0]->c;
}
$empty = array_keys(array_filter($counts, fn (int $c): bool => $c === 0));
sort($empty);
$guard(count($counts) === 63, 'live schema holds 63 base tables (read '.count($counts).')');
$guard(count($empty) === 10, 'ten of them answer COUNT(*) = 0 (read '.count($empty).')');
$guard(($counts['shiftingrequests'] ?? null) === 0, 'shiftingrequests holds no row, which is why the section owes it one');

$rows = [];
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

$emptyRow = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'model_has_permissions' && ($c[1] ?? '') === 'Users, Offices, Roles and Security');
$anchorRow = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'creditedsubjects');
$g29 = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'G-29');
$g30existing = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'G-30');
$fam = $byCells(fn (array $c): bool => ($c[2] ?? '') === 'Enforcement, reference-data, screen, authorization and notification gaps');
$guard(count($emptyRow) === 1, 'exactly one §24.17 row is addressable as the clone template ('.count($emptyRow).')');
$guard(count($anchorRow) === 1, 'exactly one creditedsubjects row ('.count($anchorRow).')');
$guard(count($g29) === 1 && count($g30existing) === 0, 'the register ends at G-29 and G-30 is not already there');
$guard(count($fam) === 1, 'exactly one §29.4 family row names the §28.3 gap families ('.count($fam).')');
$guard(($emptyRow[0]['n'] ?? 0) === 4, 'the §24.17 template row has four cells');
$guard(($g29[0]['n'] ?? 0) === 5, 'the §28.3 template row has five cells');
if ($fails > 0) {
    printf("\n%d guard(s) failed before any splice — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

$shifting = ['shiftingrequests', 'Transfer and Prior Academic Records',
    'A student files a shift request and a Dean or Program Head signs it, then Guidance grants or refuses it',
    'The table is the 63rd in the schema and holds no row on live `ems`, so the whole docket §11 describes is read from code rather than from a record: no desk has been shown a request, and the one RESTRICT foreign key in the database sits on its requestedBy column, so the delete that key refuses has never fired here. The flow was built for §28.3 G-7 under the owner’s ruling 11 of 2026-10-11, and the demo dataset still exercises none of it.'];
$g30 = ['G-30',
    'A section titled after a set never had that set re-counted. §24.17 is titled “Tables that are empty in the demo database”, yet five of its eight rows named tables that hold rows, `shiftingrequests` was named in its own intro and given no row, §24.18’s population row counted 8 domain empties, §28.1’s index said the G family runs G-1 to G-8 eleven rows short of its end, and the split sentence called “the last six” of an alphabetical list the framework tables — the last six of that list are two domain tables.',
    'Every figure beside those sentences is re-read by a checker: lane 60 counts tables and columns, lane 59 binds a number to the tool named in its sentence, lane 61 reads the verdict word. None of them asks whether a list the document calls a set equals the set. COUNT(*) over the 63 base tables of live `ems` on 2026-10-23 answers ten empty — six Laravel framework tables (cache, cache_locks, failed_jobs, job_batches, jobs, sessions) and four domain (creditedsubjects, model_has_permissions, shiftingrequests, transferacademicrecords). `information_schema.TABLES.TABLE_ROWS` is an InnoDB estimate and reports clearanceperiods as 0 while every clearance row joins to it, so a checker that read the estimate would have confirmed the old list.',
    'Closed 2026-10-23. §24.17 now names its framework six instead of pointing at their position and carries the `shiftingrequests` row its own title promised, §24.18 counts 4, §28.1 reads G-1 … G-30, and storage/app/scratch/lane64-empty-table-inventory.php --verify judges those sets, the section’s rows and every other domain-empty count in the document against COUNT(*) on live `ems`. mutate-lane64-empty-tables.php plants six ways the sets could disagree and requires each to refuse. Not a CI step: an emptiness set belongs to whichever demo dataset stated it — lane 60’s rule for row counts — and CI’s `ems_test` is seeded differently.',
    '§24.17, §24.18, §28.1; lane 64, 2026-10-23'];

foreach ([['§24.17 shiftingrequests', $shifting], ['§28.3 G-30', $g30]] as [$what, $texts]) {
    foreach ($texts as $t) {
        $assertPlain($t, $what.' cell');
    }
}

/** Clone a located row, writing every cell, and return the bytes plus the offset to insert after. */
$clone = function (array $row, array $texts, string $what) use ($setCellText, $guard, $flatten): array {
    $preg = '/<w:tc>.*?<\/w:tc>/s';
    preg_match_all($preg, $row['xml'], $cm);
    $guard(count($cm[0]) === count($texts), $what.': '.$texts[0].' gets one cell for each of the template’s '.count($cm[0]).' cells');
    $clone = $row['xml'];
    foreach ($cm[0] as $i => $cell) {
        $clone = str_replace($cell, $setCellText($cell, $texts[$i]), $clone);
    }
    foreach (['<w:tr', '</w:tr>', '<w:tc>', '</w:p>', '<w:r', '</w:r>'] as $n) {
        $guard(substr_count($clone, $n) === substr_count($row['xml'], $n), $what.' container '.$n.': one row’s worth');
    }
    $flat = $flatten($clone);
    foreach ($texts as $i => $t) {
        if ($i > 0) {
            $guard(! str_contains($flat, 'PLACEHOLDER'), $what.': no placeholder text survived the clone');
        }
    }
    $guard(str_contains('>'.$clone.'>', '>'.htmlspecialchars($texts[0], ENT_QUOTES | ENT_XML1, 'UTF-8').'<'),
        $what.' is addressed by its own first cell');

    return [$clone, (int) $row['at'] + strlen($row['xml'])];
};

$jobs = [];
foreach ([[$emptyRow[0], $shifting, '§24.17'], [$g29[0], $g30, '§28.3 G-30']] as [$tpl, $texts, $what]) {
    $cell = $tpl;
    if ($what === '§24.17') {
        // the new row belongs beside its module pair, not at the end of the table
        $cell = $anchorRow[0];
    }
    [$bytes, $insertAfter] = $clone($tpl, $texts, $what);
    $jobs[] = [$cell['at'] + strlen($cell['xml']), $bytes, $what];
}
// §29.4's count cell: a raw splice, because >46< is markup-facing. It is applied first and in place, so
// the two insertion offsets taken from the same snapshot stay valid (both rows sit earlier in the part).
$famRow = $fam[0];
$guard(substr_count($famRow['xml'], '>46<') === 1, 'the §29.4 family row holds exactly one 46 cell');
$newFam = str_replace('>46<', '>47<', $famRow['xml']);
$guard(strlen($newFam) === strlen($famRow['xml'])
    && substr_count($newFam, '>47<') === substr_count($famRow['xml'], '>47<') + 1, 'the count cell moved in place, same length');
$new = substr_replace($new, $newFam, $famRow['at'], strlen($famRow['xml']));

usort($jobs, fn (array $a, array $b): int => $b[0] <=> $a[0]);
foreach ($jobs as $j) {
    $new = substr_replace($new, $j[1], $j[0], 0);
}
$guard(substr_count($new, '>46<') === substr_count($xml, '>46<') - 1, 'exactly one 46 cell became 47');
$guard(substr_count($new, '>G-30<') === 1, 'G-30 exists once');
$guard(substr_count($new, '>shiftingrequests<') === substr_count($xml, '>shiftingrequests<') + 1, 'the §24.17 row exists once (seen '
    .substr_count($new, '>shiftingrequests<').' as a bare text node, '.substr_count($new, 'shiftingrequests').' in any form)');
$guard(substr_count($new, '<w:tbl>') === substr_count($xml, '<w:tbl>')
    && substr_count($new, '</w:tbl>') === substr_count($xml, '</w:tbl>'), 'no table element was added or lost');
$guard(substr_count($new, '</w:tr>') === substr_count($xml, '</w:tr>') + 2, 'two rows added, none lost');
$gFrom = (int) strpos($new, '>G-1<');
$gTo = (int) strpos($new, '>G-30<');
preg_match_all('/>G-(\d+)</', substr($new, $gFrom, $gTo - $gFrom + 6), $gm);
$guard($gm[1] === array_map('strval', range(1, 30)), 'the G family runs G-1 … G-30 in order, once each (read '.count($gm[1]).')');
$guard(@(new DOMDocument)->loadXML($new) !== false, 'the staged document.xml parses');
if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
echo "\n=== stage 2: the sentences that count what stage 1 moved ===\n";

$needles = [
    ['— the last six of them framework tables that §24.18 treats as infrastructure, so',
        '— six of them are Laravel framework tables (cache, cache_locks, failed_jobs, job_batches, jobs, sessions), so'],
    ['and each is rewritten below to say what the demo dataset does not exercise rather than that the table is empty.',
        'and each is rewritten below to say what the demo dataset does not exercise rather than that the table is empty. '
            .'The section’s table now carries four rows that are empty today — `shiftingrequests` among them, which the sentence above named but which had no row of its own — and five that were written as empty and are not.'],
    ['8 domain tables are empty in the seeded database (§24.17)',
        '4 domain tables are empty in the seeded database (§24.17)'],
    ['of the 57 register rows 8 still name an actor while the other 49 name none — 46 of those state the answer in that cell',
        'of the 58 register rows 8 still name an actor while the other 50 name none — 47 of those state the answer in that cell'],
    ['that now names an actor in 8 of the 57 register rows and names none in the other 49',
        'that on 2026-10-21 named an actor in 8 of the 57 register rows then in the two registers, and none in the other 49'],
    ['and lane 61 added G-29 from four sections that still asked what the registers answer, so §28.3 holds 46 data rows',
        'and lane 61 added G-29 from four sections that still asked what the registers answer, and lane 64 added G-30 from a section whose membership had never been re-counted, so §28.3 holds 47 data rows'],
    ['G-1 … G-8', 'G-1 … G-30'],
];
foreach ($needles as $i => [$from, $to]) {
    $c = substr_count($new, $from);
    $guard($c === 1, 'needle '.($i + 1).' is contiguous and unique in the staged bytes (seen '.$c.'): ['.mb_substr($from, 0, 44).']');
    $assertPlain($to, 'needle '.($i + 1).' replacement');
}
if ($fails > 0) {
    printf("\n%d guard(s) failed before any write — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: stage 1 would clone the `shiftingrequests` row into §24.17 and G-30 into §28.3, and move §29.4’s count cell;\n"
        ."stage 2 would rewrite the seven sentences above. Re-run with --apply.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass180.docx');
$z = new ZipArchive();
if ($z->open($src) !== true) {
    exit("REFUSED: cannot reopen for write.\n");
}
$z->addFromString('word/document.xml', $new);
$z->close();
$z = new ZipArchive();
$z->open($src);
$round = (string) $z->getFromName('word/document.xml');
$roundParts = $z->numFiles;
$z->close();
$guard($roundParts === $parts, 'the archive still holds '.$parts.' members');
$guard($round === $new, 'the bytes read back are the bytes staged');
$guard(substr_count($round, '>G-30<') === 1 && substr_count($round, '>shiftingrequests<') === substr_count($xml, '>shiftingrequests<') + 1, 'both rows are on disk');
if ($fails > 0) {
    printf("\n%d guard(s) failed after the stage 1 write — restore the docx from storage/app/scratch/ems-docx-pre-pass180.docx.\n", $fails);
    exit(1);
}
echo "     stage 1 written and re-read\n";
docxApply($src, $root.'/storage/app/scratch/pass180-backup.docx', $needles, [], $apply);
echo "pass 180 applied.\n";
