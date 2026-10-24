<?php
/**
 * Lane 69 probe 3: how many questions does §28.5 actually hold today, and how many carry a verdict?
 *
 * §1's overview sentence was found by the open-items walk to read "28.5 asks nineteen questions, fourteen of
 * them answered…", while pass 183 moved §29.4 to twenty open decisions when P-19 was registered. That is the
 * lane 64 class exactly — a count that stopped growing when the register did — so before answering the
 * owner's "is everything done?" the section has to be counted from its own bytes, not from the sentence.
 *
 * The section runs from its heading ("28.5 Consolidated list of decisions…") to the next heading (§28.6).
 * Rows are counted, and each row is classified by whether any cell carries a verdict word, because the
 * register writes its answer in the last cell and its question in the first.
 *
 * Read-only.
 */

$root = dirname(__DIR__, 3);
$t = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');

$start = strpos($t, '28.5 Consolidated list of decisions');
// The next section, found by its heading rather than by its title: the first guess ("28.6 Feasibility")
// was a heading that does not exist, and the probe refused rather than counting an empty segment.
preg_match('/P \d+: 28\.(6|7|8)\b/u', substr($t, $start + 10), $m, PREG_OFFSET_CAPTURE);
$end = $m === [] ? false : $start + 10 + $m[0][1];
if ($start === false || $end === false) {
    exit("REFUSED: cannot bound §28.5 — found start=".var_export($start, true)." end=".var_export($end, true)."\n");
}
$seg = substr($t, $start, $end - $start);
preg_match_all('/ROW (\d+): (.*)/u', $seg, $rows, PREG_SET_ORDER);

$verdict = '/\b(Answered|Ruled|Closed|Fixed|Implemented|Adopted|Confirmed|not yet deployed)\b/iu';
$answered = [];
$waiting = [];
foreach ($rows as $r) {
    $body = preg_replace('/\s+/u', ' ', trim($r[2]));
    $cells = array_map('trim', explode('|', $body));
    // §28.5's rows begin with the register id (C-4, P-13, G-4…) or with a question; the verdict is the
    // cell that carries the date, so judge the row by that cell rather than by the first.
    $asked = (string) ($cells[1] ?? $cells[0]);
    if (preg_match($verdict, $body)) {
        $answered[] = ['ROW '.$r[1], mb_substr($asked, 0, 112), mb_substr($body, -110)];
    } else {
        $waiting[] = ['ROW '.$r[1], mb_substr($asked, 0, 112)];
    }
}

printf("§28.5 rows read from its own bytes: %d\n  carrying a verdict word: %d\n  still an open question: %d\n\n",
    count($rows), count($answered), count($waiting));

echo "=== STILL AN OPEN QUESTION ===\n";
foreach ($waiting as [$n, $q]) {
    echo "  $n  $q\n";
}
echo "\n=== ANSWERED (for the count to add up) ===\n";
foreach ($answered as [$n, $q, $v]) {
    echo "  $n  $q\n        …$v\n";
}

echo "\n=== the two sentences that state these counts ===\n";
foreach (['asks nineteen questions', 'twenty open decisions', 'the fifteen that wait on an office', 'nineteen questions'] as $needle) {
    $p = strpos($t, $needle);
    printf("  %-38s %s\n", $needle, $p === false ? '(absent)' : '…'.mb_substr(preg_replace('/\s+/u', ' ', substr($t, max(0, $p - 60), 260)), 0, 250));
}
