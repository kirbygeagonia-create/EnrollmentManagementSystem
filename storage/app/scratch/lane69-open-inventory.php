<?php

/**
 * Lane 69 probe: what do the registers actually leave open today?
 *
 * The owner's question was "is everything done, nothing left to address?", and the honest answer has to be
 * measured, not recalled — six prose families of stale claims have already been found in this document by
 * tools, three of them in sentences that were written to say what was outstanding. This walks the flat dump
 * once and separates four things that get confused:
 *
 *   OPEN     a register row whose own verdict cell still leads with an open verb (a real, live gap)
 *   OFFICE   a row that names a person or office who must act, which is owed work whether or not the row
 *            also carries a verdict ("Who must act" is a column, not a status)
 *   ANSWERED a row whose register verdict closes it — listed here only so the count adds up
 *   SHEET    the decision sheet's own items, read from the file rather than from memory
 *
 * Read-only: it writes nothing and changes no state.
 */
$root = dirname(__DIR__, 3);
$dump = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');

// Rows in the dump are "ROW n: cell | cell | …" on one line, §-headed by the nearest preceding P marker.
preg_match_all('/ROW (\d+): (.*)/u', $dump, $rows, PREG_SET_ORDER);
// Delimiter-free alternations: the first version embedded the slashes and then wrapped them again, so every
// preg_match() warning-failed to a false and the OPEN count printed a trustworthy-looking zero.
$openWords = '\b(Open[ed]?|still owed|Owed|waits? on|Waiting|To be (?:confirmed|decided|supplied|answered)|not yet (?:deployed|answered|ruled)|undecided|Ask the|Remains|outstanding|Provisional)\b';
$closedWords = '\b(Closed|Answered|Ruled|Fixed|Resolved|Implemented|Done|Adopted|Retired|Documented)\b';

$openRows = [];
$actorRows = [];
foreach ($rows as $r) {
    $n = (int) $r[1];
    $body = trim($r[2]);
    $cells = array_map('trim', explode('|', $body));
    $leading = $cells[0] ?? '';
    $flat = preg_replace('/\s+/u', ' ', $body);

    // A gap's TITLE can begin "Open a second signature…", so a verdict is the cell that LEADS with the
    // open verb — and when any cell also carries a date, that one is the verdict cell (lane 61's rule).
    $leadsOpen = false;
    foreach ($cells as $cell) {
        if (preg_match('/^\s*(?:'.$openWords.')/iu', $cell) && ! preg_match('/(?:'.$closedWords.')/iu', $cell)) {
            $leadsOpen = true;
        }
    }
    if ($leadsOpen && ! preg_match('/(?:'.$closedWords.')/iu', $body)) {
        $openRows[] = [$n, mb_substr($flat, 0, 190)];
    }

    // §28.8's question is different: which rows still name an actor.
    if (preg_match('/Registrar|Accounting|Scholarship|Guidance|Admission|Academic|Blocking|Office|department|the school/iu', $leading) && preg_match('/(?:'.$openWords.')/iu', $body) && ! preg_match('/(?:'.$closedWords.')/iu', $body)) {
        $actorRows[] = [$n, mb_substr($flat, 0, 150)];
    }
}

printf("%d rows parsed from the dump.\n\n", count($rows));

echo "=== OPEN: register rows whose own verdict cell still leads with an open verb ===\n";
foreach ($openRows as [$n, $t]) {
    echo "  ROW {$n}: {$t}\n";
}
echo '  count: '.count($openRows)."\n\n";

echo "=== OFFICE: rows that name an actor AND still carry open words (owed work) ===\n";
foreach (array_slice($actorRows, 0, 25) as [$n, $t]) {
    echo "  ROW {$n}: {$t}\n";
}
echo '  count: '.count($actorRows)."\n";

echo "\n=== SHEET: Documentation/School-Decision-Sheet-2026-10-23.md, item by item ===\n";
$sheet = (string) file_get_contents($root.'/Documentation/School-Decision-Sheet-2026-10-23.md');
$items = preg_split('/\n\*\*(\d+)\. /', $sheet, -1, PREG_SPLIT_DELIM_CAPTURE);
$sheetOpen = [];
$answeredCount = 0;
// Judged on the two places the sheet writes a ruling — the item's own title and its "Answer:" line — and
// never on its prose. The first version grepped the whole block case-insensitively, so "answered" inside
// the next section's heading (the blocks run up to the next item's marker) read as that item being closed,
// and an "Answered as indefinite" narrative — a measurement the school has not ticked — read as a ruling.
for ($i = 1; $i < count($items); $i += 2) {
    $num = $items[$i];
    $block = $items[$i + 1] ?? '';
    $title = (string) strtok($block, "\n");
    $answerLine = '';
    if (preg_match('/^- Answer:.*$(?:\n {2,}.*)*/m', $block, $am) === 1) {
        $answerLine = $am[0];
    }
    // A ruling word counts only in DECISION position — after a dash that closes the struck-out question, or
    // after the emphasis opener that starts the answer value. Item 2 taught this the hard way on 2026-10-24:
    // its tick line says "(the copy designation and the course code are already built, ruled 2026-10-23)",
    // which reports another row's ruling inside an UNticked answer, and a substring test for `ruled` marked
    // the row closed — the instrument inherited exactly the defect class it was written to catch.
    $marked = str_contains($title, 'ANSWERED')
        || str_contains($answerLine, '☑')
        || preg_match('/(?:—|-)\s*(?:\*\*)?(answered|ruled)\b|\*\*\s*(answered|ruled)\b/i', $answerLine) === 1;
    printf("  item %-3s %-16s %s\n", $num, $marked ? '[answered/ruled]' : '[OPEN]', mb_substr($title, 0, 92));
    if (! $marked) {
        $sheetOpen[] = (int) $num;
    } else {
        $answeredCount++;
    }
}
echo '  open on the sheet: '.count($sheetOpen).' → items '.implode(', ', $sheetOpen)."\n";

// Cross-check the classifier against the claim the sheet makes about itself, because that claim is what a
// reader takes to the meeting. The header spells its numbers as words ("twelve answered, four left"), so the
// words are mapped before anything is compared. A tally that disagrees with the document's own sentence is a
// defect in one of them, and this run cannot tell which — so it says so instead of answering quietly.
$words = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7,
    'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13,
    'fourteen' => 14, 'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19,
    'twenty' => 20];
$total = $num = null;
if (preg_match('/(\d+|'.implode('|', array_keys($words)).')\s+items?\b/i', $sheet, $tm) === 1) {
    $total = ctype_digit($tm[1]) ? (int) $tm[1] : ($words[strtolower($tm[1])] ?? null);
}
if (preg_match('/(\d+|'.implode('|', array_keys($words)).')\s+answered,?\s*(\d+|'.implode('|', array_keys($words)).')\s+left/i', $sheet, $hm) === 1) {
    $claimsAnswered = ctype_digit($hm[1]) ? (int) $hm[1] : ($words[strtolower($hm[1])] ?? null);
    $claimsLeft = ctype_digit($hm[2]) ? (int) $hm[2] : ($words[strtolower($hm[2])] ?? null);
    $itemsRead = count($sheetOpen) + $answeredCount;
    if ($claimsAnswered === null || $claimsLeft === null) {
        echo "  the sheet's header could not be read as two counts, so nothing was cross-checked\n";
    } else {
        printf("  the sheet's header says %d answered, %d left of %s → %s\n", $claimsAnswered, $claimsLeft,
            $total === null ? 'an unstated total' : $total,
            ($claimsLeft === count($sheetOpen) && $claimsAnswered + $claimsLeft === $itemsRead
                && ($total === null || $total === $itemsRead)) ? 'AGREES' : 'DISAGREES — read both, neither is proof');
        if (! ($claimsLeft === count($sheetOpen) && $claimsAnswered + $claimsLeft === $itemsRead
            && ($total === null || $total === $itemsRead))) {
            $fails = ($fails ?? 0) + 1;
        }
    }
} else {
    echo "  the sheet's header states no answered/left pair, so this run cannot cross-check itself\n";
}
