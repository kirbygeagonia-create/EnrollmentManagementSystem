<?php

/**
 * Pass 188 — §28.8's "Who owes what" table still tallies 46 owed slots taken from a column that no
 * longer names actors for closed items.
 *
 * The table reads Front-end developer 4, Developer 19, Registrar 13, SysAdmin 3, Project owner 4,
 * Department 3 = 46 slots over six actors. But §28.3's rows now answer that column with their closure
 * ("Closed in code", "Closed in data; SysAdmin maintains the list"), and lane61-who-owes counts exactly
 * **two** register rows whose column still names someone who owes a decision after the 2026-10-24
 * rulings: C-5 "Department with Registrar" (left provisional on 2026-10-23 until the departments speak)
 * and G-13 "Owner / whoever provisions the server" (an install that does not exist). AGENTS.md already
 * recorded the 46-vs-8 discrepancy as a lane 61 finding; it was never corrected, and today it is 46-vs-2.
 *
 * Rewritten as whole-row regions, never as loose needles: "4", "3", "Registrar" and "Department" each
 * occur elsewhere, which is the defect that produced the EvaluationController `:588-584` corruption.
 * Spliced right-to-left so no earlier edit moves a later offset.
 *
 *   php storage/app/scratch/refresh-docx-pass188.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass188.php --apply  # write
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

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$none = 'None — every item this row once listed now states its own answer';

// Anchored on each row's identifier list, which is unique in the document, rather than on its actor
// label — "Department", "Registrar" and "Developer" all occur elsewhere, so a label needle could land in
// the wrong table. Key: the identifier cell as it stands. Value: [expected count, new count, new ids].
$oldIds = [
    'G-5, U-1, U-2, U-4' => ['4', '0', $none],
    'C-11, G-1, G-2, G-3, G-4, G-6, G-7, G-8, D-1, D-4, D-5, D-6, U-3, X-1, X-3, N-1, N-2, N-3, N-4' => ['19', '0', $none],
    'C-1, C-2, C-4, C-5, C-7, C-8, G-2, G-3, G-4, G-7, D-1, D-6, X-4' => ['13', '0', $none],
    'D-2, D-3, X-3' => ['3', '0', $none],
    'C-9, C-10, C-11, X-4' => ['4', '1', 'G-13 (install-time, DEPLOYMENT.md §5)'],
    'C-2, C-5, C-6' => ['3', '1', 'C-5'],
];
$labels = [];

$regions = [];
foreach ($oldIds as $ids => [$expectCount, $newCount, $newIds]) {
    $at = strpos($xml, '>'.$ids.'<');
    if ($at === false) {
        $say(false, 'identifier cell not found: '.substr($ids, 0, 40));
        continue;
    }
    $start = strrpos(substr($xml, 0, $at), '<w:tr');
    $end = strpos($xml, '</w:tr>', $at);
    $row = substr($xml, $start, $end - $start);
    $label = 'row@'.(int) $start;
    $say(substr_count($row, '>'.$ids.'<') === 1, $label.': identifier cell unique inside its row');

    $new = str_replace('>'.$ids.'<', '>'.$newIds.'<', $row);
    $idPos = strpos($new, '>'.$newIds.'<');
    // The count is the last short numeric <w:t> before the identifier cell. Scanning back for a bare ">"
    // is wrong here: run properties (<w:rPr>…) close between the cells, so the previous ">" is a tag end.
    $prefix = substr($new, 0, $idPos);
    $n = preg_match_all('/<w:t[^>]*>(\d{1,3})<\/w:t>/u', $prefix, $mm, PREG_OFFSET_CAPTURE);
    $say($n >= 1, $label.': found a numeric text cell before the identifiers (count '.$n.')');
    if ($n < 1) {
        continue;
    }
    $valPos = $mm[1][count($mm[1]) - 1][1];
    $valLen = strlen($mm[1][count($mm[1]) - 1][0]);
    $said = '>'.$mm[1][count($mm[1]) - 1][0].'<';
    $say($said === '>'.$expectCount.'<', $label.': count cell reads '.$said.' (expected >'.$expectCount.'<)');
    $new = substr_replace($new, $newCount, $valPos, $valLen);
    $say($new !== $row, $label.': its row was modified');
    $regions[] = [$start, $end - $start, $new, $label];
}

usort($regions, fn ($a, $b) => $b[0] <=> $a[0]);
$staged = $xml;
foreach ($regions as [$start, $len, $new, $label]) {
    $staged = substr_replace($staged, $new, $start, $len);
}

$say(count($regions) === 6, 'all six table rows rebuilt (got '.count($regions).')');
$say(substr_count($staged, '>46<') === substr_count($xml, '>46<'), 'no new 46 appeared anywhere');
$say(substr_count($staged, '>'.$none.'<') === 4, 'four rows now read none');
$say(substr_count($staged, '>C-1, C-2, C-4, C-5, C-7, C-8, G-2, G-3, G-4, G-7, D-1, D-6, X-4<') === 0,
    'the 13-slot Registrar row is gone');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §28.8 would show 2 owed slots (C-5, G-13) where it shows 46.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass188.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write — nothing written.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
