<?php

/**
 * Lane 70, pass 239: open §28.3 G-31 for the mechanism-claim class, and move every place that counts the register.
 *
 * The owner ruled on 2026-10-25 that the N-1 narrative (four sections describing a gap §28.3 closed on 2026-10-01 —
 * corrected by passes 235-238) is worth a register row, because no instrument can see that class of sentence:
 * lane61 reacts to a status word, lane60 to a figure, lane59 to a count a tool prints, the sweeps to line numbers.
 *
 * Pass 180's proven shape is reused verbatim: clone a row of the same table, write EVERY cell through the same
 * helper (a cloned cell keeps all of the template's runs — pass 173's §1.1 clone carried the previous row's closing
 * paragraph for a whole batch), guard that each container count moves by exactly one row's worth, splice the count
 * cells in place, insert right-to-left, then re-read the ids in order before anything is written.
 *
 * Every number this pass writes is READ from the staged bytes, never recalled: the §28.3 data-row count, §28.8's
 * register tally, and the G family's own range. If a sentence does not carry the expected shape the pass refuses.
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
    $guard($flat === $text, 'the written cell reads exactly the pass’s own text');

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

$rows = [];
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $new, $rows, PREG_OFFSET_CAPTURE);
$byCells = function (callable $pred) use ($rows, $flatten): array {
    $hit = [];
    foreach ($rows[0] as $r) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r[0], $cm);
        $cells = array_map(fn (string $c): string => trim($flatten($c)), $cm[0]);
        if ($pred($cells)) {
            $hit[] = ['xml' => $r[0], 'at' => $r[1], 'cells' => $cells, 'n' => count($cm[0]), 'cellXml' => $cm[0]];
        }
    }

    return $hit;
};

$tpl = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'G-30');
$idx = $byCells(fn (array $c): bool => ($c[0] ?? '') === 'G-1 … G-30');
$fam = $byCells(fn (array $c): bool => ($c[2] ?? '') === 'Enforcement, reference-data, screen, authorization and notification gaps');
$guard(count($tpl) === 1, 'exactly one G-30 row to clone ('.count($tpl).')');
$guard(count($idx) === 1, 'exactly one §28.1 index row reading G-1 … G-30 ('.count($idx).')');
$guard(count($fam) === 1, 'exactly one §29.4 family row for the §28.3 gap families ('.count($fam).')');
$guard(($tpl[0]['n'] ?? 0) === 5, 'the §28.3 template row has five cells');

// The counts the new row moves, read out of the document rather than remembered.
$flatDoc = $flatten($xml);
preg_match('/§28\.3 holds (\d+) data rows/', $flatDoc, $m1);
$guard(($m1[1] ?? '') !== '', 'the §28.3 data-row count is findable in the prose (read '.($m1[1] ?? 'nothing').')');
preg_match('/of the (\d+) register rows (\d+) still name an actor while the other (\d+) name none — (\d+) of those state the answer/', $flatDoc, $m2);
$guard(count($m2) === 5, 'the §28.8 tally sentence is findable and carries four numbers (read '.json_encode($m2).')');
$familyCount = trim($flatten((string) ($fam[0]['cellXml'][1] ?? '')));
$guard($familyCount === (string) ((int) ($m1[1] ?? -1) + (int) ($m2[1] ?? -1) - (int) ($m2[1] ?? 0)) || $familyCount !== '',
    'the §29.4 family count cell reads ['.$familyCount.']');
if ($fails > 0) {
    printf("\n%d guard(s) failed before any splice — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

$g31 = ['G-31',
    'A sentence that says how the code behaves today is checked by nothing. Four sections narrated gap N-1 as the live state after §28.3 had closed it on 2026-10-01: §19.4’s writer table said both listeners address `App\Models\Students`, §19.4’s consequence said no notification is ever displayed, §20’s foreign-key purpose row said “Broken today: writers store `App\Models\Students` rows that no reader queries”, and §24.14 stated the same as *this edition’s* correction. Both listeners write `Staffusers::class` with the recipient’s userId (`app/Listeners/SendEnrollmentNotification.php:62-63`, `app/Listeners/SendWorkflowNotification.php:88-89`), `App\Support\WorkflowInbox::actors()` answers the office’s staff userIds (:93-95), and the signature listener’s own docblock says students are not notifiable.',
    'Every judge in this repository reads a different surface and none of them reads behaviour: `lane61-open-claims` reacts to a status word, `lane60-ratio-claims` to a figure, `lane59-instrument-counts` to a count a named tool prints, `sweep-docx-citations.php` to a line number landing on a live non-blank line. A present-tense mechanism sentence carries no number, no status word and no broken pointer, so the build overtakes it and every gate stays green — the §12 Gate 4 shape reached from the architecture side. The class is also wide: the same false claim sat in four places, and three of them were outside the section that owns the finding, so correcting one section never reaches the others.',
    'Closed 2026-10-25 by docx passes 235-238, which rewrote all four sentences to the measured state and re-cited the blocks that actually write the columns (`SendEnrollmentNotification.php:59-66`, `SendWorkflowNotification.php:85-96`). `storage/app/scratch/refresh-docx-pass238.php` measures live `ems` inside itself and refuses to write unless `notifications` answers exactly one `notifiable_type` — `App\Models\Staffusers`, 14 rows — so the corrected sentences cannot be committed against a database that disagrees. What remains owed is the instrument: a checker that binds “the document states X does today” to a probe of X.',
    '§19.4, §20, §24.14, §28.3 N-1; lane 61, 70, 2026-10-25'];

foreach ($g31 as $t) {
    $assertPlain($t, 'G-31 cell');
}
if ($fails > 0) {
    printf("\nReplacement text carries markup — NOTHING WRITTEN.\n");
    exit(1);
}

$preg = '/<w:tc>.*?<\/w:tc>/s';
preg_match_all($preg, $tpl[0]['xml'], $cm);
$guard(count($cm[0]) === count($g31), 'one cell of the template for each of the five texts');
$clone = $tpl[0]['xml'];
foreach ($cm[0] as $i => $cell) {
    $clone = str_replace($cell, $setCellText($cell, $g31[$i]), $clone);
}
foreach (['<w:tr', '</w:tr>', '<w:tc>', '</w:p>', '<w:r', '</w:r>'] as $n) {
    $guard(substr_count($clone, $n) === substr_count($tpl[0]['xml'], $n), 'clone container '.$n.': one row’s worth');
}
$guard(trim($flatten($clone)) !== '', 'the clone is not empty');
$guard(str_contains('>'.$clone.'>', '>'.htmlspecialchars('G-31', ENT_QUOTES | ENT_XML1, 'UTF-8').'<'), 'the clone is addressed by its own id cell');

// §29.4's count cell moves in place (a digit-only run is markup-facing, so no text needle is safe).
$famRow = $fam[0];
$guard(substr_count($famRow['xml'], '>'.$familyCount.'<') === 1, 'the family row holds exactly one '.$familyCount.' cell');
$newFam = str_replace('>'.$familyCount.'<', '>'.((int) $familyCount + 1).'<', $famRow['xml']);
$guard(strlen($newFam) === strlen($famRow['xml']) && substr_count($newFam, '>'.((int) $familyCount + 1).'<') === 1,
    'the count cell moved in place, same length');
$new = substr_replace($new, $newFam, $famRow['at'], strlen($famRow['xml']));

// §28.1's index row: the range cell is rewritten through the same cell helper.
$idxRow = $idx[0];
$newIdx = $idxRow['xml'];
$newIdx = str_replace($idxRow['cellXml'][0], $setCellText($idxRow['cellXml'][0], 'G-1 … G-31'), $newIdx);
$new = substr_replace($new, $newIdx, $idxRow['at'], strlen($idxRow['xml']));

// The clone goes after the G-30 row; offsets were taken from the same snapshot, so splice the row last and
// right-to-left (the §29.4 and §28.1 edits are in place and change no offset after their own end).
$new = substr_replace($new, $clone, (int) $tpl[0]['at'] + strlen($tpl[0]['xml']), 0);

$guard(substr_count($new, '</w:tr>') === substr_count($xml, '</w:tr>') + 1, 'one row added, none lost');
$guard(substr_count($new, '<w:tbl>') === substr_count($xml, '<w:tbl>')
    && substr_count($new, '</w:tbl>') === substr_count($xml, '</w:tbl>'), 'no table element added or lost');
$guard(substr_count($new, '>G-31<') === 1, 'G-31 exists once');
$gFrom = (int) strpos($new, '>G-1<');
$gTo = (int) strpos($new, '>G-31<');
preg_match_all('/>G-(\d+)</', substr($new, $gFrom, $gTo - $gFrom + 8), $gm);
$seen = array_values(array_unique($gm[1]));
$want = array_map('strval', range(1, 31));
sort($seen);
$guard($seen === $want, 'the G family runs G-1 … G-31 once each in order (read '.count($seen).' distinct: '.implode(',', $seen).')');
$staged = $flatten($new);
if (preg_match('/of the (\d+) register rows (\d+) still name an actor while the other (\d+) name none — (\d+) of those state the answer/', $staged, $now) === 1) {
    // The splice cannot change a prose count: stage 2 is what re-ages it. If the numbers here already moved,
    // something rewrote the sentence twice and the arithmetic below would be wrong.
    $guard($now[1] === $m2[1] && $now[4] === $m2[4], 'the §28.8 tally sentence survived the splice unchanged ('.$now[1].' rows, '.$now[4].' answering), so stage 2 can age it once');
} else {
    $guard(false, 'the §28.8 tally sentence vanished from the staged bytes');
}
$guard(@(new DOMDocument)->loadXML($new) !== false, 'the staged document.xml parses');
if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

$needles = [
    ['§28.3 holds '.$m1[1].' data rows', '§28.3 holds '.((int) $m1[1] + 1).' data rows'],
    ['of the '.$m2[1].' register rows '.$m2[2].' still name an actor while the other '.$m2[3].' name none — '.$m2[4].' of those state the answer',
        'of the '.((int) $m2[1] + 1).' register rows '.$m2[2].' still name an actor while the other '.((int) $m2[3] + 1).' name none — '.((int) $m2[4] + 1).' of those state the answer',
    ],
    ['and lane 64 added G-30 from a section whose membership had never been re-counted, so',
        'and lane 64 added G-30 from a section whose membership had never been re-counted, and lane 70 added G-31 from four sections that narrated a closed gap in the present tense, so',
    ],
];
$stage2 = [];
foreach ($needles as $i => [$from, $to]) {
    $c = substr_count($staged, $from);
    $guard($c === 1, 'needle '.($i + 1).' contiguous and unique in the staged bytes (seen '.$c.'): ['.mb_substr($from, 0, 48).']');
    $assertPlain($to, 'needle '.($i + 1).' replacement');
    $stage2[] = [$from, $to];
}
// §28.1's own range statement and any surviving "46/47 data rows" phrasing are printed, not guessed.
foreach (['G-1 … G-30', 'G-1 … G-8'] as $n) {
    printf("  note: [%s] still occurs %d time(s) in the staged bytes\n", $n, substr_count($staged, $n));
}
if ($fails > 0) {
    printf("\n%d guard(s) failed before any write — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: stage 1 would clone G-31 into §28.3, move §29.4’s count cell ".$familyCount.' → '.((int) $familyCount + 1)
        ." and rewrite §28.1's index cell; stage 2 would rewrite ".count($stage2)." counting sentences. Re-run with --apply.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass239.docx');
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
$guard(substr_count($round, '>G-31<') === 1, 'the row is on disk');
if ($fails > 0) {
    printf("\n%d guard(s) failed after the stage 1 write — restore from storage/app/scratch/ems-docx-pre-pass239.docx.\n", $fails);
    exit(1);
}
echo "     stage 1 written and re-read\n";
docxApply($src, $root.'/storage/app/scratch/pass239-backup.docx', $stage2, [], $apply);
echo "pass 239 applied.\n";
