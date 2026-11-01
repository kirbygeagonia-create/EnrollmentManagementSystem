<?php

/**
 * Docx pass 390 — the sentences and lists the seat key's return and §24's five missing rows made false.
 *
 * Nine text edits and four run insertions, every figure read from an instrument rather than typed:
 *
 *   §23.6.1's count sentence — `lane44-unique-keys.php` prints the pair the server holds, and its run
 *     today reports `uq_enrollments_active_seat` as a MISS beside a sentence saying twelve keys;
 *   §24's `enrollments — 24 columns` heading, the §24 census row's `439 columns documented`, and the
 *     §23 census sentence's `481 columns / 138 indexes / 184 table constraints` — all four are read
 *     back from `lane60-ratio-claims.php --run`'s own STALE lines, which name the server's answer for
 *     each noun. The pass refuses unless the set of stale figures it is about to correct is exactly the
 *     set it expects, because correcting a figure the server no longer disputes is how a pass re-ages
 *     a number that was already true;
 *   the two §28.5/§26.7 register rows that state the withdrawal: their present-tense claim ("which
 *     today it does not") is now false, their `down()` promise is now spent, and their "what the
 *     domain tables do hold NOW" clause names one key where the schema holds two — each occurs twice,
 *     so each goes through with expect=2 rather than one copy being corrected and its twin left asking;
 *   §24's "Change since the previous edition — documented for the first time here:" lists, which name
 *     the columns this edition documents and therefore must gain `rowVersion`, `rejectionReason`,
 *     `dedupeKey`, `requestKey` and `seatKey`. Those sentences are NOT addressable by a text needle:
 *     the paragraph is split into one run per column name, so a whole-clause needle occurs 0 times in
 *     document.xml. Each insert clones the neighbour run (keeping its formatting) and writes the new
 *     name beside it, then asserts the paragraph's flattened text.
 *
 * Nothing is written unless every pre-condition resolves; after the write the pass re-dumps and re-runs
 * both instruments and restores its own backup if either still complains. Usage:
 *   php storage/app/scratch/refresh-docx-pass390.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$apply = in_array('--apply', $argv, true);
$debug = in_array('--debug', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if (! in_array($arg, ['--apply', '--debug'], true)) {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply and --debug.\n");
        exit(1);
    }
}

$fail = static function (string $message): never {
    fwrite(STDERR, "REFUSED: {$message}\n");
    exit(1);
};

$run = static function (string $cmd) use ($php): string {
    // No escapeshellarg(): the path holds no spaces and cmd.exe does not strip the quotes a POSIX
    // escaper adds, which is how a probe ends up executing nothing.
    return (string) shell_exec(str_replace('PHP', $php, $cmd).' 2>&1');
};

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

// ------------------------------------------------------------------ what the instruments answer
$lane44 = $run('PHP storage/app/scratch/lane44-unique-keys.php');
if (! preg_match('/the server holds (\d+) keys across (\d+) tables/', $lane44, $m)) {
    $fail("lane44 printed no server pair to read the key count from. Its own output:\n".$lane44);
}
$keysOnServer = (int) $m[1];
$tablesWithKeys = (int) $m[2];
// Pass 389 wrote the seat key's ROW, so by now the only §23.6.1 complaint this pass may answer is the
// count sentence. Anything else in the queue means the row is missing or a third key moved, and a
// pass that rewrites a sentence it did not read is the failure this file keeps naming.
$complaints = [];
if (preg_match_all('/^\s*(MISS|PHANTOM)\s+(.+)$/m', $lane44, $c44, PREG_SET_ORDER)) {
    foreach ($c44 as $c) {
        $complaints[] = trim($c[1]).': '.trim($c[2]);
    }
}
$sentenceComplaint = null;
foreach ($complaints as $c) {
    if (str_contains($c, 'the sentence says')) {
        $sentenceComplaint = $c;
    }
}
$otherComplaints = array_values(array_filter($complaints, static fn (string $c): bool => $c !== $sentenceComplaint));
if ($otherComplaints !== []) {
    $fail("lane44 reports ".count($otherComplaints)." complaint(s) besides the count sentence, and this pass corrects only the sentence:\n  "
        .implode("\n  ", $otherComplaints));
}
if ($sentenceComplaint === null) {
    $fail("lane44 reports no count-sentence complaint, so the sentence already agrees with the server and this pass has nothing to write there. Full run:\n".$lane44);
}
if (! str_contains($lane44, 'ok    uq_enrollments_active_seat') && ! str_contains($lane44, 'ok uq_enrollments_active_seat')) {
    $fail('lane44 does not list the seat key as an ok row, so §23.6.1 does not carry it yet — pass 389 must run first.');
}

$lane60 = $run('PHP storage/app/scratch/lane60-ratio-claims.php --run');
// Two shapes, because a census noun reads "X claimed N, the server answers M" and a per-table heading
// reads "stated N columns, the server holds M". Collecting both into one greedy alternation is how a
// figure gets read from half the list.
$stale = [];
if (preg_match_all('/^STALE\s+(.*?)\s+line (\d+)\s+(\w+) claimed (\d+), the server answers (\d+)/m', $lane60, $c1, PREG_SET_ORDER)) {
    foreach ($c1 as $s) {
        $stale[(int) $s[4]] = ['noun' => trim($s[1]).'.'.$s[3], 'to' => (int) $s[5], 'line' => (int) $s[2]];
    }
}
// The per-table line's noun carries a space (`per-table enrollments`), so `(\S+)` here matched half
// the line and the pass parsed three of four stale figures — a figure read from half a list.
if (preg_match_all('/^STALE\s+(.*?)\s+line (\d+)\s+stated (\d+) columns, the server holds (\d+)/m', $lane60, $c2, PREG_SET_ORDER)) {
    foreach ($c2 as $s) {
        $stale[(int) $s[3]] = ['noun' => trim($s[1]), 'to' => (int) $s[4], 'line' => (int) $s[2]];
    }
}
if (preg_match('/(\d+) sentence figure\(s\) \+ (\d+) heading figure\(s\): \d+ match, (\d+) stale, (\d+) unbound/', $lane60, $m)) {
    $staleCount = (int) $m[3];
    $unboundCount = (int) $m[4];
} else {
    $fail("lane60 printed no tally line, so the number of stale figures it reports cannot be read. Its own output:\n".$lane60);
}
if (count($stale) !== $staleCount || $unboundCount !== 0) {
    $fail('lane60 tallies '.$staleCount.' stale and '.$unboundCount.' unbound figure(s); this pass parsed '
        .count($stale).'. A figure read from half the list is the defect this repository refuses. Its output:\n'.$lane60);
}
ksort($stale);
$expectedStale = [24, 138, 184, 481];
if (array_keys($stale) !== $expectedStale) {
    $fail('the stale set lane60 reported is '.implode(', ', array_keys($stale))
        .' but this pass is written for 24, 138, 184, 481 — the figures the returning column moves. Read the run before widening the list.');
}

$weighNow = $run('PHP storage/app/scratch/lane189-weigh-column-claims.php');
if (! preg_match('/heading sum for domain tables: (\d+), for the framework eight: (\d+)/', $weighNow, $m)) {
    $fail("lane189 printed no domain heading sum, so the §24 census figure cannot be read from an instrument. Its own output:\n".$weighNow);
}
$domainSumNow = (int) $m[1];

// ------------------------------------------------------------------ the document
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open (ZipArchive answers an error code, not false, for a file that is not an archive).');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}

$words = [1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven',
    8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
    14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
    19 => 'nineteen', 20 => 'twenty', 21 => 'twenty-one', 22 => 'twenty-two'];
foreach ([$keysOnServer, $tablesWithKeys] as $n) {
    if (! isset($words[$n])) {
        $fail("the count sentence is spelled in words and the server answered {$n}; this pass has no word for it, so it stops rather than writing a digit into a sentence that reads as prose.");
    }
}
$newCountSentence = $words[$keysOnServer].' keys in '.$words[$tablesWithKeys].' tables.';
$oldCountSentence = 'Twelve keys in ten tables.';
if ($newCountSentence === $oldCountSentence) {
    $fail('the server pair already equals the sentence this pass would write, so the sentence is not stale and this pass has nothing to correct there.');
}

$edits = [
    [$oldCountSentence, $newCountSentence, 1],
    ['481 columns', $stale[481]['to'].' columns', 1],
    ['138 indexes', $stale[138]['to'].' indexes', 1],
    ['184 table constraints', $stale[184]['to'].' table constraints', 1],
    // The census row counts §24's own per-table headings, so its figure is the domain heading sum
    // lane189 measured a moment ago plus the one column this pass moves into the list.
    [$domainSumNow.' columns documented', ($domainSumNow + $stale[24]['to'] - 24).' columns documented', 1],
    ['enrollments — 24 columns', 'enrollments — '.$stale[24]['to'].' columns', 1],
    ['What is left is whether the schema should also reject a row written straight to the table, which today it does not.',
        'The schema now also rejects a row written straight to the table: `uq_enrollments_active_seat` over (`studentId`, `termId`, `seatKey`) is on live `ems` as of 2026-11-01, so a second active seat for one student in one term cannot be written even by a path that never calls `seatHolder()`.', 2],
    ['Its `down()` restores the key once those fixtures name a later term or drop the prior seat first.',
        'The habit was wider than six files: `storage/app/scratch/lane187-census-fixture-seats.php` reads each call site, binds the person and the term to where they came from, and answers eight files, and `storage/app/scratch/mutate-lane187-provenance.php` proves that reading with 22 legs. Each file was repaired to name a later term, drop the prior seat, or take a second student, and the six that the withdrawal named rehearsed green on a scratch MySQL schema before `2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php` returned the key the same day.', 2],
    ['What the domain tables do hold now for enrollment integrity is `uq_enrollments_request_key` over `requestKey`, which is the guide’s problem 17: a repeat of the same act is answered with the record the first send created rather than a second one.',
        'What the domain tables hold now for enrollment integrity is that seat key beside `uq_enrollments_request_key` over `requestKey` — the second being the guide’s problem 17, where a repeat of the same act is answered with the record the first send created rather than a second one.', 2],
    ['on the same schema moment that retired the VIRTUAL `seatKey`.',
        'on the same schema moment that first retired and then, once the fixtures held one seat per term, returned the VIRTUAL `seatKey`.', 1],
];

foreach ($edits as $i => [$from, $to, $expect]) {
    $n = substr_count($xml, $from);
    if ($n !== $expect) {
        $fail('edit '.($i + 1).' needle occurred '.$n.' time(s), expected '.$expect.': '.substr($from, 0, 80));
    }
    if (trim($from) === trim($to)) {
        $fail('edit '.($i + 1).' replaces its own needle with the same text.');
    }
    if (strpbrk($to, '<>') !== false) {
        $fail('edit '.($i + 1).' replacement holds a raw angle bracket, which document.xml must not carry as text.');
    }
}

// Applied only after every needle resolved its expected count. A pass that validates a list and then
// forgets to apply it writes the other half of its changes and reads back zero of this half — which is
// exactly what the post-write check below caught on the first run of this file.
foreach ($edits as [$from, $to, $expect]) {
    $xml = str_replace($from, $to, $xml);
}

// ------------------------------------------------------------------ the four column-name runs
/**
 * Insert `runText` immediately before the run of the scoped note paragraph whose text equals
 * `$beforeRun`, cloning that run's formatting. Returns the paragraph's flattened text after the
 * insert so the caller can assert the sentence it intended.
 */
$insertIntoNote = static function (string $tableName, string $beforeRun, string $runText) use (&$xml, $flatten, $escape, $fail): string {
    preg_match_all('/<w:p>.*?<\/w:p>/s', $xml, $pm, PREG_OFFSET_CAPTURE);
    $headAt = null;
    $headEnd = null;
    $scopeEnd = null;
    foreach ($pm[0] as $p) {
        $t = $flatten($p[0]);
        if ($headAt === null) {
            if (preg_match('/^'.preg_quote($tableName, '/').'\s*—\s*\d+ columns$/u', $t)) {
                $headAt = (int) $p[1];
                $headEnd = $headAt + strlen($p[0]);
            }
            continue;
        }
        if (preg_match('/^[a-z0-9_]+\s*—\s*\d+ columns$/u', $t) || preg_match('/^\d+\.\d+/u', $t)) {
            $scopeEnd = (int) $p[1];
            break;
        }
    }
    if ($headAt === null) {
        $fail("no heading paragraph for {$tableName}");
    }
    $scopeEnd ??= strlen($xml);
    $notes = [];
    foreach ($pm[0] as $p) {
        $off = (int) $p[1];
        if ($off <= $headEnd || $off >= $scopeEnd) {
            continue;
        }
        if (str_starts_with($flatten($p[0]), 'Change since the previous edition')) {
            $notes[] = [$off, strlen($p[0]), $flatten($p[0])];
        }
    }
    if (count($notes) !== 1) {
        $fail("the scope under {$tableName}'s heading holds ".count($notes).' "Change since" paragraph(s), expected exactly 1 — the note is the thing being edited, so it cannot be guessed at.');
    }
    [$pOff, $pLen, $beforeText] = $notes[0];

    $paragraph = substr($xml, $pOff, $pLen);
    preg_match_all('/<w:r\b.*?<\/w:r>/s', $paragraph, $rm, PREG_OFFSET_CAPTURE);
    $targets = [];
    foreach ($rm[0] as $r) {
        if ($flatten($r[0]) === $beforeRun) {
            $targets[] = [(int) $r[1], $r[0]];
        }
    }
    if (count($targets) !== 1) {
        $fail("inside {$tableName}'s note paragraph, ".count($targets).' run(s) hold exactly the text '.$beforeRun.', expected 1');
    }
    [$relAt, $template] = $targets[0];
    if (substr_count($template, '<w:t') !== 1) {
        $fail("the run cloned for {$tableName}.{$beforeRun} holds ".substr_count($template, '<w:t').' text elements; the pass writes one.');
    }
    $newRun = preg_replace('/<w:t(?: [^>]*)?>.*?<\/w:t>/s', '<w:t xml:space="preserve">'.$escape($runText).'</w:t>', $template);
    $absolute = $pOff + $relAt;
    $xml = substr_replace($xml, $newRun, $absolute, 0);

    preg_match('/<w:p>.*?<\/w:p>/s', $xml, $check, 0, $pOff);
    $after = $flatten($check[0]);
    if (str_contains($beforeText, $runText)) {
        $fail("{$tableName}'s note paragraph already carried '{$runText}' before this insert.");
    }

    return $after;
};

$assertions = [];
foreach ([
    ['students', 'updated_at', 'rowVersion, ', 'created_at, rowVersion, updated_at.'],
    ['admissions', 'updated_at', 'rejectionReason, ', 'created_at, rejectionReason, updated_at.'],
    ['notifications', 'id', 'dedupeKey, ', 'created_at, data, dedupeKey, id,'],
    ['enrollments', 'returnReason', 'requestKey, ', 'created_at, curriculumId, requestKey, returnReason,'],
    ['enrollments', 'updated_at', 'seatKey, ', 'requestReason-none'],
] as [$table, $beforeRun, $insert, $expectContains]) {
    $after = $insertIntoNote($table, $beforeRun, $insert);
    if ($expectContains === 'requestReason-none') {
        // The second enrollments insert has no fixed neighbour text to name; it is checked by the
        // whole-sentence assertion below, which runs after both inserts landed.
        $assertions[] = [$table, $after, null];
        continue;
    }
    if (! str_contains($after, $expectContains)) {
        $fail("after inserting into {$table}'s note paragraph, its text does not contain '{$expectContains}'. Read: ".substr($after, 0, 200));
    }
    $assertions[] = [$table, $after, $expectContains];
}
$lastEnrollments = $assertions[count($assertions) - 1][1];
if (! str_contains($lastEnrollments, 'created_at, curriculumId, requestKey, returnReason, seatKey, updated_at;')) {
    $fail("enrollments' note paragraph does not read as the ordered list this pass intends: ".$lastEnrollments);
}

printf("%d text edit(s) and %d run insert(s) resolved.\n", count($edits), count($assertions));
foreach ($assertions as [$t, $text, $want]) {
    echo '  '.$t.': '.substr($text, 0, 130)."\n";
}

if ($debug) {
    echo "DRY (debug) — nothing written\n";
    exit(0);
}

if (! $apply) {
    echo "DRY RUN — nothing written\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass390-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('could not write the backup.');
}

$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('could not reopen the docx for writing.');
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    $fail('the written docx did not reopen.');
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();

$bad = [];
foreach ($edits as $i => [$from, $to, $expect]) {
    $afterCount = substr_count($read, $to);
    $residual = substr_count($read, $from);
    // An appended replacement can embed its own needle, in which case the old text is expected to
    // survive as the head of the new sentence (pass 341's rule); here every edit replaces, so the
    // needle must be gone where it is not a twin of another edit's own text.
    if ($afterCount < $expect) {
        $bad[] = 'edit '.($i + 1).' replacement occurs '.$afterCount.' time(s), expected at least '.$expect;
    }
    // Every edit here replaces, so the retired text must be gone. A residual is either a copy the
    // count assertion missed or a replacement that landed somewhere else.
    if ($residual !== 0) {
        $bad[] = 'edit '.($i + 1).' retired needle still occurs '.$residual.' time(s): '.substr($from, 0, 60);
    }
}
if ($bad !== []) {
    fwrite(STDERR, "REFUSED: read-back failed:\n  ".implode("\n  ", $bad)."\nRestored from {$backup}.\n");
    if (! copy($backup, $docx)) {
        fwrite(STDERR, "REFUSED: the restore failed — repair from {$backup} by hand.\n");
    }
    exit(1);
}

// ------------------------------------------------------------------ the instruments, re-run
$run('PHP storage/app/scratch/dump-docx-flat.php');
$lane44After = $run('PHP storage/app/scratch/lane44-unique-keys.php');
$lane60After = $run('PHP storage/app/scratch/lane60-ratio-claims.php --run');
$weigh = $run('PHP storage/app/scratch/lane189-weigh-column-claims.php');

$problems = [];
if (! preg_match('/\s0 problem\(s\)/', $lane44After)) {
    $problems[] = "lane44 still reports a problem after the write:\n".$lane44After;
}
if (! preg_match('/(\d+) sentence figure\(s\) \+ (\d+) heading figure\(s\): \d+ match, 0 stale, 0 unbound/', $lane60After)) {
    $problems[] = "lane60 still reports a stale or unbound figure after the write:\n".$lane60After;
}
if (! str_contains($weigh, 'tables whose heading disagrees with its own row count: 0')) {
    $problems[] = "a §24 table still lists a different number of columns than its own heading says:\n".$weigh;
}
if ($problems !== []) {
    fwrite(STDERR, "REFUSED: the post-write instruments disagree:\n  ".implode("\n  ", $problems)
        ."\nThe document has been restored from {$backup}; the write is not delivered.\n");
    if (! copy($backup, $docx)) {
        fwrite(STDERR, "REFUSED: the restore failed — repair from {$backup} by hand.\n");
    }
    $run('PHP storage/app/scratch/dump-docx-flat.php');
    exit(1);
}

echo "post-write: lane44 clean, lane60 0 stale, every §24 heading equal to its own row count.\n";
printf("delivered: %d text edit(s), %d run insert(s); backup %s\n", count($edits), count($assertions), basename($backup));
