<?php

/**
 * Proof that lane 61's checker can fail — and can refuse.
 *
 * The class this checker owns is a STATUS WORD, not a figure: §28.7, §28.8 and §30 all presented ids as undecided
 * whose own register rows begin "Closed 2026-10-0…". Every citation sweep cleared those sentences, because each
 * pointer lands on a live line and each §-reference resolves. So a checker that reads nothing here would report a
 * clean document, and the only way to know it reads something is to plant the defect and watch it complain.
 *
 * Each leg runs the checker over a synthetic dump, never over the docx, so a mutant cannot reach the authority
 * file. The one exception is the last leg, which runs it over the real dump and requires the verdict the document
 * earned today. Every mutation leg is paired with its clean control, and a leg refuses when its own planted text
 * is not present — a needle that matches nothing reports the checker's silence as a pass.
 *
 * Usage: php storage/app/scratch/mutate-lane61-open-claims.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$checker = $root.'/storage/app/scratch/lane61-open-claims.php';
$dir = $root.'/storage/app/scratch/.lane61-fixtures';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$fails = 0;
$say = static function (string $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok === 'ok' ? 'ok  ' : 'BAD ', $what);
    if ($ok !== 'ok') {
        $fails++;
    }
};

// cmd.exe, not sh: `2>/dev/null` and `; echo EXIT=$?` do not work here, and without the real exit code a
// surviving mutant looks exactly like a killed one.
$run = static function (string $cmd): array {
    $out = (string) shell_exec($cmd.' 2>&1 & if errorlevel 2 (echo EXIT=2) else if errorlevel 1 (echo EXIT=1) else (echo EXIT=0)');
    preg_match('/EXIT=([012])\s*$/', $out, $m);

    return ['code' => isset($m[1]) ? (int) $m[1] : -1, 'out' => $out];
};

/** A register block §28.3-shaped: id, gap, build-does-today, who-must-act, raised-in. */
$row = static function (string $id, string $verdict, string $actor = 'Closed in code'): string {
    return 'ROW 12: '.$id.' | The gap stated once | '.$verdict.' | '.$actor.' | §13.2';
};

$BASE_ROWS = implode("\n", [
    $row('G-90', 'Closed 2026-10-01 by the flag and its test'),
    $row('G-91', 'Ruled 2026-10-02 by the owner and built'),
    $row('G-92', 'Open, and it is a data question the Registrar owes'),
    $row('C-90', 'Answered 2026-10-07: per-program, default off', 'Registrar'),
]);

$write = static function (string $name, string $body) use ($dir): string {
    $p = $dir.'/'.$name.'.txt';
    file_put_contents($p, $body."\n");

    return $p;
};

/**
 * @param array<int,string> $wantIn   strings the output must carry
 * @param array<int,string> $wantNot  strings the output must not carry
 */
$leg = static function (string $name, string $body, int $wantCode, array $wantIn, array $wantNot, string $why)
use ($php, $checker, $run, $say, $write): void {
    $flat = $write($name, $body);
    $r = $run($php.' '.escapeshellarg($checker).' --flat='.escapeshellarg($flat));
    $ok = $r['code'] === $wantCode;
    foreach ($wantIn as $w) {
        $ok = $ok && str_contains($r['out'], $w);
    }
    foreach ($wantNot as $w) {
        $ok = $ok && ! str_contains($r['out'], $w);
    }
    $say($ok ? 'ok' : 'BAD', sprintf('%-26s exit %d (want %d) — %s', $name, $r['code'], $wantCode, $why));
    if (! $ok) {
        foreach (array_slice(preg_split('/\n/', trim($r['out'])), 0, 6) as $line) {
            echo '        | '.$line."\n";
        }
    }
};

echo "-- the checker must see a planted contradiction --\n";
$leg('clean-control', $BASE_ROWS."\nP 30: The rule is stated in §13 and the desk applies it.",
    0, ['no sentence in the document asks a question'], [],
    'a document that asks nothing answers nothing');
$leg('planted-open-claim', $BASE_ROWS."\nP 30: G-90 remains open, and the build has not been told.",
    1, ['G-90', 'VERDICT: 1 contradiction'], [],
    'the planted sentence must be refused');
$leg('planted-spelled-number', $BASE_ROWS."\nP 30: Three open items — G-90, G-91 and G-92 — decide what this section describes.",
    1, ['VERDICT: 2 contradiction(s) over 2 ids'], [],
    'the two answered ids are flagged and the genuinely open one is not');

echo "\n-- and must stand down for the four shapes that are not claims --\n";
$leg('self-answering', $BASE_ROWS."\nP 30: Ruled 2026-10-06 (G-90), a student coming in is issued irregular rather than being left undecided for a desk.",
    0, ['no sentence in the document asks a question'], [],
    'a sentence carrying its own answer is a record');
$leg('clause-attribution', $BASE_ROWS."\nP 30: A request still open under a box its own office already signed is the audit rule. Nine rows later, §28.3 G-90 closes in code.",
    0, ['no sentence in the document asks a question'], [],
    'open-words in one sentence must not borrow an id from the next');
$leg('dated-revision-row', $BASE_ROWS."\nROW 5: 2026-10-05–06 | Its one open item was G-90, and that report was false as committed.",
    0, ['no sentence in the document asks a question'], [],
    'a §1.1 row whose first cell is a date is that day’s record');
$leg('genuinely-open-row', $BASE_ROWS."\nP 30: G-92 remains open, and it is the Registrar’s to supply.",
    0, ['no sentence in the document asks a question'], [],
    'asking what is truly open is not a contradiction');

echo "\n-- the verdict must be the cell that LEADS, never a title that contains the word --\n";
$leg('title-not-a-verdict', 'ROW 12: G-93 | Open a second signature on a signed box | Closed 2026-10-01 by the guard | Closed in code | §16.4'
    ."\nP 30: G-93 is not yet answered.",
    1, ['G-93'], [],
    'the row answers; the title’s word “Open” must not be read as its verdict');

echo "\n-- and it must refuse rather than report a clean run it did not perform --\n";
$leg('no-verdicts-parsed', "ROW 12: G-95 | A gap with no status cell at all | because nobody wrote one\nP 30: Nothing here is judged.",
    1, ['REFUSED'], [],
    'zero verdicts is a broken instrument, not a clean document');
$leg('blind-spot-printed', $BASE_ROWS."\nP 30: X-77 remains open and this run holds no row for it.",
    0, ['BLIND SPOTS', 'X-77'], ['VERDICT: 1 contradiction'],
    'an id with no verdict is reported unverifiable, never counted clean — and its own row is printed, because a '
    .'header that tallies blind spots without showing them asks the reader to trust a claim the run never made');

echo "\n-- the real document, read today --\n";
$real = $run($php.' '.escapeshellarg($checker));
if ($real['code'] !== 0 || ! str_contains($real['out'], 'no sentence in the document asks a question')) {
    $say('BAD', 'real dump: exit '.$real['code'].' — the document still asks something its register answers');
    foreach (array_slice(preg_split('/\n/', trim($real['out'])), 0, 10) as $line) {
        echo '        | '.$line."\n";
    }
} else {
    $say('ok', 'real dump: the corrected document asks nothing its registers answer');
}

if ($fails > 0) {
    printf("\n%d lane 61 case(s) misbehaved.\n", $fails);
    exit(1);
}
echo "\nAll lane 61 cases behaved.\n";
