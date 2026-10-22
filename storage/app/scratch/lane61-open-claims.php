<?php
/**
 * Lane 61: does the document still ASK a question its own register ANSWERED?
 *
 * Found by reading §28.7, whose nine rows put E-1, G-1, G-3 … X-1 in the order "these should
 * be taken in", while every one of those rows' own register line begins "Closed 2026-10-0…".
 * §30.6 then names four "open items" that §28.2 and §28.3 each answer with a date, and §25.5
 * describes a settings→config bridge that landed 2026-10-01 and a test pins.
 *
 * Why no existing sweep saw it: every citation lands on a live line (the line sweep), every
 * §-reference resolves (lane 37), every named construct exists in the cited span (lane 32),
 * and no figure is wrong (lanes 58/59/60). Only the STATUS WORD two sentences apart disagrees.
 *
 * Three rules keep it honest, and each was learned the expensive way in an earlier lane:
 *  - a register's verdict verb is read only when it LEADS a cell. §25.4 rows put it in cell 1
 *    ("P-4 | Closed 2026-10-02: …"), §28.3 rows in cell 3 ("G-3 | title | Closed 2026-10-03 …").
 *    A cell that merely contains the word "open" is a title, not a verdict.
 *  - an id is judged only inside the clause that carries the open-words, so "a request still
 *    open under a box its office signed" (an audit rule) cannot be attributed to G-2, which
 *    appears eleven sentences later in the same flat-dump line.
 *  - a sentence that states its own answer is history, not a live claim: "ruled 2026-10-06
 *    (C-2), a student … is issued irregular rather than being left undecided" records the fix.
 *
 * Usage: php storage/app/scratch/lane61-open-claims.php [--flat=path] [--verbose]
 * Exits non-zero on any contradiction, and refuses when it parsed no verdicts at all.
 */

$flat = 'storage/app/scratch/docx-flat.txt';
$verbose = in_array('--verbose', $argv, true);
foreach ($argv as $a) {
    if (str_starts_with((string) $a, '--flat=')) {
        $flat = substr((string) $a, 7);
    }
}
if (! is_file($flat)) {
    exit("REFUSED: no dump at {$flat}. Run dump-docx-flat.php first.\n");
}

$lines = file($flat, FILE_IGNORE_NEW_LINES);
if ($lines === false || $lines === []) {
    exit("REFUSED: the dump is empty.\n");
}

/** Verbs that settle a register row, and which way they settle it. */
$ANSWERED = ['Closed', 'Ruled', 'Contained', 'Built', 'Answered', 'Implemented', 'Verified', 'Partly closed'];
$OPEN = ['Open', 'Unanswered', 'Deferred', 'Not closed', 'Still open'];

// ---------------------------------------------------------------- 1. the verdicts

$verdict = [];      // id => ['line'=>int,'status'=>string,'cell'=>int]
$openVerdict = [];  // ids whose OWN row says they are open — the honest live list
$liveWork = [];     // ids whose LEADING verdict is answered but a later cell opens work
$registerRows = 0;    // rows that look like register rows, judged or not — the empty-scope guard
foreach ($lines as $i => $l) {
    if (! preg_match('/^ROW \d+: ([A-Z]{1,3}-\d{1,2}) \|/', $l, $m)) {
        continue;
    }
    $id = $m[1];
    $registerRows++;
    // Cell boundaries cannot be trusted (§28.3's G-26 splits on the `|` inside
    // `required|numeric|min:0.01`), so the verdict is "the first cell that LEADS with a
    // verb" and the open scan is a separate pass over every cell.
    $cells = preg_split('/\s*\|\s*/', $l);
    $lead = null;
    // §25.4 puts its verdict in cell 1 ("P-4 | Closed 2026-10-02: …") and §28.3 in cell 3
    // ("G-3 | the gap | Closed 2026-10-03 … | Closed in code"), so no column index works. Take the cell that
    // leads with a verb AND carries a date when one does — a gap's TITLE can begin with a verb word too
    // ("Open a second signature on a signed box"), and reading that as the verdict would hide the row's answer.
    $candidates = [];
    foreach (array_slice($cells, 1) as $c => $cell) {
        foreach (array_merge($ANSWERED, $OPEN) as $v) {
            if (preg_match('/^' . preg_quote($v, '/') . '\b/', $cell)) {
                $candidates[] = ['line' => $i + 1, 'status' => $v, 'cell' => $c + 1, 'dated' => preg_match('/20\d\d-\d\d-\d\d/', $cell) === 1];
                break;
            }
        }
    }
    foreach ($candidates as $cand) {
        if ($cand['dated']) {
            $lead = $cand;
            break;
        }
    }
    if ($lead === null && $candidates !== []) {
        $lead = $candidates[0];
    }
    if ($lead === null) {
        continue;
    }
    $verdict[$id] = $lead;
    if (in_array($lead['status'], $OPEN, true)) {
        $openVerdict[$id] = $lead;
    }
    if (in_array($lead['status'], $ANSWERED, true)) {
        foreach (array_slice($cells, 1) as $c => $cell) {
            if ($c + 1 === $lead['cell']) {
                continue;
            }
            foreach ($OPEN as $v) {
                if (preg_match('/^' . preg_quote($v, '/') . '\b/', $cell)) {
                    // "Partly closed … | Open, and it is a data question" — the row answers its
                    // own headline and still owes the rest. §28.7 has to be rebuilt from these.
                    $liveWork[$id] = ['line' => $i + 1, 'cell' => $c + 1, 'text' => $cell];
                    break 2;
                }
            }
        }
    }
}

if ($registerRows === 0) {
    exit("REFUSED: the dump holds no register row at all — an empty scope is not a clean document.\n");
}
if (count($verdict) === 0) {
    printf("REFUSED: %d register rows parsed and not one carries a verdict verb; the parser is broken, not the document.\n",
        $registerRows);
    exit(1);
}

// ---------------------------------------------------------------- 2. the prose that asks

/** Words that present an id as not yet decided. */
$OPENWORDS = '\b(?:open items?|open questions?|still open|remains open|is not yet answered|'
    .'(?:waits?|wait) on an answer|awaits? an answer|undecided|decision (?:the school )?has not (?:been )?made|'
    .'(?:the school|the registrar) has not made|has yet to be answered|to be decided|not settled)\b';
/** A sentence that carries its own answer is a record, not a live claim. */
$SELFANSWER = '/\b(?:ruled|closed|contained|answered|resolved|decided)\b[^.]{0,40}\b(?:on |by |20\d\d)/i';
/** A §1.1 revision row whose first cell is a date is that day's record. */
$DATEDROW = '/^ROW \d+: \d{4}-\d{2}-\d{2}/u';

$hits = [];
$blind = [];
foreach ($lines as $i => $l) {
    if (! preg_match('/^(?:P|ROW) \d+: /', $l)) {
        continue;
    }
    if (preg_match($DATEDROW, $l)) {
        continue;
    }
    $body = preg_replace('/^(?:P|ROW) \d+: /', '', $l);
    // Sentences, not the whole line: a flat-dump row is one line carrying many.
    foreach (preg_split('/(?<=[.!?])\s+/', $body) as $sent) {
        if (! preg_match('/' . $OPENWORDS . '/i', $sent)) {
            continue;
        }
        if (preg_match($SELFANSWER, $sent)) {
            continue;
        }
        if (! preg_match_all('/\b([A-Z]{1,3}-\d{1,2})\b/', $sent, $mm)) {
            continue;
        }
        foreach (array_unique($mm[1]) as $id) {
            if (! isset($verdict[$id])) {
                $blind[] = ['line' => $i + 1, 'id' => $id, 'sent' => $sent];
                continue;
            }
            if (in_array($verdict[$id]['status'], $ANSWERED, true)) {
                $hits[] = [
                    'id' => $id,
                    'line' => $i + 1,
                    'row' => $verdict[$id]['line'],
                    'status' => $verdict[$id]['status'],
                    'sent' => trim($sent),
                ];
            }
        }
    }
}

// ---------------------------------------------------------------- 3. report

printf("%d register rows carry a verdict verb; %d sentences elsewhere present an answered id as undecided.\n\n",
    count($verdict), count($hits));

echo "--- what the registers actually leave open today ---\n";
printf("leading an open verb: %s\n", $openVerdict === [] ? '(none)' : implode(', ', array_keys($openVerdict)));
printf("answered headline, open consequence: %s\n", $liveWork === [] ? '(none)' : implode(', ', array_keys($liveWork)));
foreach ($liveWork as $id => $w) {
    printf("    %-5s row %-5d cell %d: %s\n", $id, $w['line'], $w['cell'],
        mb_strlen($w['text']) > 200 ? mb_substr($w['text'], 0, 200) . '…' : $w['text']);
}
echo "\n";

$byId = [];
foreach ($hits as $h) {
    $byId[$h['id']][] = $h;
}
ksort($byId);
foreach ($byId as $id => $list) {
    printf("%s — its own row %d says \"%s\", but %d sentence(s) still ask:\n",
        $id, $list[0]['row'], $list[0]['status'], count($list));
    foreach ($list as $h) {
        $s = $h['sent'];
        if (mb_strlen($s) > 260) {
            $s = mb_substr($s, 0, 260) . '…';
        }
        printf("    line %-5d %s\n", $h['line'], $s);
    }
    echo "\n";
}

if ($blind !== []) {
    printf("BLIND SPOTS: %d sentence(s) name an id this run holds no verdict for — read them, they are not cleared:\n",
        count($blind));
    if ($verbose) {
        foreach ($blind as $b) {
            $s = trim($b['sent']);
            printf("    line %-5d %-6s %s\n", $b['line'], $b['id'], mb_strlen($s) > 200 ? mb_substr($s, 0, 200) . '…' : $s);
        }
        echo "\n";
    }
}

if ($hits === []) {
    echo "VERDICT: no sentence in the document asks a question its own register has answered.\n";
    exit(0);
}
printf("VERDICT: %d contradiction(s) over %d ids. The document still asks what its registers answer.\n",
    count($hits), count($byId));
exit(1);
