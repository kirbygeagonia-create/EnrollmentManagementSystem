<?php
/**
 * Pass 384 — §23.6.1, §24 and §26.4/§26.7 after the active-seat key was withdrawn and the request key arrived.
 *
 * Three things moved together and every figure is taken from the server, not from arithmetic:
 *   1. §23.6.1's ROW for `uq_enrollments_active_seat` becomes the row for `uq_enrollments_request_key`. That table's
 *      lead sentence claims it holds EVERY unique index in the domain tables, so `lane44-unique-keys.php` read this
 *      schema moment as TWO problems at once — `MISS uq_enrollments_request_key` and `PHANTOM uq_enrollments_active_seat`.
 *      Rewriting the four cells in place, rather than deleting the row and cloning a sibling, clears both in one
 *      write and keeps the register at twelve rows, which is what the server holds. The rewrite travels through the
 *      engine's `text` kind because each of those four cells carries EXACTLY ONE paragraph and ONE `<w:t>` run —
 *      measured by `lane185-read-seat-key-row.php` before this technique was chosen. A value split across runs
 *      cannot go through a text needle, and here that is a refusal, not a silent fallback to something else.
 *   2. the count sentence beside it takes the pair lane 44 reads off the server — and it already agrees, because the
 *      seat key went out and the request key came in: twelve in ten either way. That prints as a SKIP naming the
 *      lane 44 line that proved it.
 *   3. §24's `enrollments` per-table heading and its column-aggregate cross-check row are WEIGHED, not written.
 *      `seatKey` was a real column to `information_schema` and pass 382 moved both numbers up for it, so this pass
 *      reads the heading out of the document's own bytes, compares it with the server's column count taken at
 *      stage 0, and asks `lane60-ratio-claims.php` — which owns the aggregate's derivation — whether its own footer
 *      still reads 0 stale and 0 differing headings. Any disagreement is a REFUSAL that names the figure: a skip
 *      written over an instrument's complaint is the fake-clean verdict this repository keeps meeting. Declining the
 *      §24 write is also the run-split rule — a heading needle can straddle two runs and a guess corrupts it.
 *
 * It REFUSES before touching anything while live `ems` still holds the seat index or does not yet hold the request
 * index: writing "the schema defends this with a unique key" about an index that is not on the school's database, or
 * the reverse about one that is, is a document corrected against a schema it does not describe. Run
 * `php artisan migrate` on live `ems` (dump first) before this pass.
 *
 * Two write hazards this file had to get right, both recorded here because they are the reason it is a pass and not
 * a one-liner: `word/document.xml` is an ENTRY inside a zip, so nothing may be written with file_put_contents over
 * the .docx; and the engine's own backup is taken from the file as it finds it, so a pass that mutates before
 * calling docxApply must keep its own copy of the original or its rollback restores a half-edited document. This
 * pass therefore performs no pre-write at all — every change is one docxApply call.
 *
 * Third hazard, and it bit this file first: `<w:t[^>]*>` is NOT the run pattern. It opens on `<w:tc>`, `<w:tcPr>`,
 * `<w:tr>` and `<w:tbl>` too, so a "flattened text" built with it is one run plus a swallow of markup, and a needle
 * count read off that string can agree with nothing the document says. The engine and `dump-docx-flat.php` use
 * `<w:t(?: [^>]*)?>`; this pass uses the same one, and `lane185` was written with the wrong pattern and reported a
 * table cell as 564 bytes of `w:tcPr` before the sweep caught it.
 *
 * Dry run by default; --apply writes.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', $GLOBALS['argv'], true);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$stamp = date('Ymd-His');
$origCopy = $root.'/storage/app/scratch/pass384-original-'.$stamp.'.docx';
$engineBackup = $root.'/storage/app/scratch/pass384-engine-'.$stamp.'.docx';

$failExit = static function (string $message) use ($apply, $origCopy, $src): never {
    fwrite(STDERR, "REFUSED: {$message}\n");
    if ($apply && is_file($origCopy)) {
        copy($origCopy, $src);
        fwrite(STDERR, "  restored the original docx from {$origCopy}\n");
    }
    exit(1);
};

/** Flatten the way the dumper does: a space after every run, entities folded back. */
$flatten = static function (string $xml): string {
    $runs = preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $xml, $tm) ? $tm[1] : [];

    return html_entity_decode(implode('', array_map(static fn (string $t): string => $t.' ', $runs)),
        ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

/** Run a scratch checker and keep ITS own exit code — a pipe's status belongs to the pipe, not the tool. */
$runTool = static function (string $cmd) use ($php): array {
    $out = [];
    $rc = 0;
    exec(escapeshellarg($php).' '.$cmd.' 2>&1', $out, $rc);

    return [(int) $rc, implode("\n", $out)];
};

// ── stage 0: ask the school's database what it actually holds ───────────────────────────────────────────────
$env = [];
foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], " \"'");
    }
}
$schema = $env['DB_DATABASE'] ?? '';
if ($schema !== 'ems') {
    $failExit(".env names `{$schema}`, not live `ems`. This pass writes the sentences that describe the school's own "
        .'schema, so it runs against the database the document is about.');
}
$pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';port='.($env['DB_PORT'] ?? '3306'),
    $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$q = static fn (string $sql) => $pdo->query($sql);

$seatLeft = (int) $q('select count(*) from information_schema.STATISTICS where TABLE_SCHEMA = '.$pdo->quote($schema)
    ." and INDEX_NAME = 'uq_enrollments_active_seat'")->fetchColumn();
if ($seatLeft !== 0) {
    $failExit("live `{$schema}` still holds uq_enrollments_active_seat ({$seatLeft} index row(s)). Run the "
        .'withdrawal migration there first, after a dump to C:\\Users\\ADMIN\\ems-backups\\ .');
}

// The row this pass writes must state what the server states, so the key's own shape is read rather than recalled:
// which table, which column, and whether the index really is UNIQUE.
$req = $q('select TABLE_NAME, COLUMN_NAME, NON_UNIQUE, NULLABLE from information_schema.STATISTICS '
    .'where TABLE_SCHEMA = '.$pdo->quote($schema)." and INDEX_NAME = 'uq_enrollments_request_key' order by SEQ_IN_INDEX")
    ->fetchAll(PDO::FETCH_ASSOC);
if ($req === []) {
    $failExit("live `{$schema}` holds no uq_enrollments_request_key, so the row this pass writes would describe a "
        .'schema that does not exist here. Run the batch migrations on live `ems` first (dump beforehand).');
}
if (count($req) !== 1) {
    $failExit('uq_enrollments_request_key covers '.count($req).' column(s) on the server; this pass writes a '
        .'one-column row and refuses to guess at the rest.');
}
if ((int) $req[0]['NON_UNIQUE'] !== 0) {
    $failExit('the server says uq_enrollments_request_key is NOT unique (NON_UNIQUE='.$req[0]['NON_UNIQUE']
        .'), so §23.6.1 must not list it among the unique keys.');
}
if ($req[0]['TABLE_NAME'] !== 'enrollments' || $req[0]['COLUMN_NAME'] !== 'requestKey') {
    $failExit('the server puts this index on `'.$req[0]['TABLE_NAME'].'`.`'.$req[0]['COLUMN_NAME']
        .'`, not on `enrollments`.`requestKey` — the row this pass writes is spelled from the enrollment table.');
}
$enrollCols = (int) $q('select count(*) from information_schema.COLUMNS where TABLE_SCHEMA = '
    .$pdo->quote($schema)." and TABLE_NAME = 'enrollments'")->fetchColumn();
printf("live `%s`: seat index gone; request key is UNIQUE over `enrollments`.`requestKey` (nullable %s); "
    ."`enrollments` holds %d columns\n", $schema, $req[0]['NULLABLE'], $enrollCols);

// ── stage 1: the count pair comes from lane 44, which owns that sentence ────────────────────────────────────
chdir($root);
// lane44 reads the regenerated flat dump, so dump first: a clean boundary read off yesterday's dump is a reading of
// a document that no longer exists.
[$rcDump, $dumpOut] = $runTool('storage/app/scratch/dump-docx-flat.php');
if ($rcDump !== 0) {
    $failExit('dump-docx-flat.php exited '.$rcDump.': '.$dumpOut);
}
[$rc44, $lane44] = $runTool('storage/app/scratch/lane44-unique-keys.php');
if (! preg_match('/the server holds (\d+) keys? across (\d+) tables/', $lane44, $m)) {
    $failExit("lane44 printed no `the server holds N keys across M tables` line (exit {$rc44}). Its tail:\n"
        .substr($lane44, -900));
}
[$serverKeys, $serverTables] = [(int) $m[1], (int) $m[2]];
printf("lane44 reads the server: %d keys across %d tables (exit %d)\n", $serverKeys, $serverTables, $rc44);

// The pass is written for ONE schema moment, so the instrument's complaints must be EXACTLY this pair — not merely
// contain them. Matching on a single-space string was the first attempt and it refused a true reading, because
// lane44 pads `MISS` to two spaces beside `PHANTOM`'s one: a checker's label is matched by shape, never by the
// spacing a printf happens to write. Some other complaint means some other edit owns the register, and rewriting the
// seat row would then paper over a different defect.
preg_match_all('/^\s*(MISS|PHANTOM)\s+(\S+)/m', $lane44, $cm2, PREG_SET_ORDER);
$complaints = ['MISS' => [], 'PHANTOM' => []];
foreach ($cm2 as $c) {
    $complaints[$c[1]][] = $c[2];
}
printf("lane44's complaints: MISS=[%s] PHANTOM=[%s]\n",
    implode(', ', $complaints['MISS']), implode(', ', $complaints['PHANTOM']));
if ($complaints['MISS'] !== ['uq_enrollments_request_key']) {
    $failExit('lane44 does not report exactly one MISS, named uq_enrollments_request_key, so the row this pass '
        ."writes is not what the instrument says is missing. Its tail:\n".substr($lane44, -700));
}
if ($complaints['PHANTOM'] !== ['uq_enrollments_active_seat']) {
    $failExit('lane44 does not report exactly one PHANTOM, named uq_enrollments_active_seat, so §23.6.1 already '
        ."carries no row for the withdrawn key and there is nothing for this pass to rewrite. Its tail:\n"
        .substr($lane44, -700));
}

$words = [6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve',
    13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen'];
if (! isset($words[$serverKeys], $words[$serverTables])) {
    $failExit("the server pair {$serverKeys}/{$serverTables} is outside this pass's word list.");
}

// ── stage 2: read the document once, locate everything, refuse before any write ─────────────────────────────
$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    $failExit('could not open the docx as an archive (an error code is truthy; `!== true` is the guard).');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $failExit('word/document.xml read back empty.');
}
$joined = $flatten($xml);

$oldCount = null;
if (preg_match('/\b(?:One|Two|Three|Four|Five|Six|Seven|Eight|Nine|Ten|Eleven|Twelve|Thirteen|Fourteen|Fifteen)'
    .' keys in (?:six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen) tables\./i', $joined, $mm)) {
    $oldCount = $mm[0];
}
if ($oldCount === null) {
    $failExit('no `N keys in M tables.` sentence found — the §23.6.1 paragraph is spelled differently than '
        .'recorded. Re-read before writing.');
}
if (substr_count($joined, $oldCount) !== 1) {
    $failExit("the count sentence `{$oldCount}` occurs ".substr_count($joined, $oldCount).' time(s), expected 1.');
}
printf("count sentence found verbatim: `%s`\n", $oldCount);
// The sentence is located case-insensitively (it opens a paragraph, so it reads `Twelve`), which means a naive
// rebuild from the lowercase word map turns a TRUE sentence into a wrong one: `Twelve keys in ten tables.` would
// "change" into `twelve keys in ten tables.` and the engine would apply it, because the two strings differ.
// So the pair is compared without case, and the document's own capitalisation is carried into the replacement.
$sentence = $words[$serverKeys].' keys in '.$words[$serverTables].' tables.';
$newCount = ctype_upper(substr($oldCount, 0, 1)) ? ucfirst($sentence) : $sentence;
$countChanged = $newCount !== $oldCount;
if ($countChanged && strcasecmp($oldCount, $sentence) === 0) {
    $failExit("the only difference between `{$oldCount}` and the server's pair is the first letter's case, which is "
        .'not a correction. The document capitalises this sentence because it opens a paragraph; leave it.');
}
// The pair can already agree, because the seat key went out and the request key came in: twelve in ten either way.
// That is a skip, not a contradiction — the row rewrite is still owed, since §23.6.1 must not list an index the
// schema no longer holds.
$countChanged = $newCount !== $oldCount;
if (! $countChanged) {
    printf("the count sentence already reads the server's pair (%s) — skipped, proved by lane44's line above; the "
        ."row still goes.\n", $newCount);
}

// The row: the one <w:tr> carrying the withdrawn index name, located by absolute offset into document.xml.
preg_match_all('/<w:tr\b[^>]*>/', $xml, $rm, PREG_OFFSET_CAPTURE);
$targets = [];
foreach ($rm[0] as $hit) {
    $open = $hit[1];
    $body = $open + strlen($hit[0]);
    $e = strpos($xml, '</w:tr>', $body);
    if ($e !== false && str_contains(substr($xml, $open, $e - $open), 'uq_enrollments_active_seat')) {
        $targets[] = [$open, $e + strlen('</w:tr>')];
    }
}
if (count($targets) !== 1) {
    $failExit('the seat-key row was located '.count($targets).' time(s), expected exactly 1. A rewrite that matches '
        .'two rows overwrites a true row.');
}
[$rs, $re] = $targets[0];
$rowXml = substr($xml, $rs, $re - $rs);
$trTotal = substr_count($xml, '</w:tr>');
printf("seat-key row: bytes %d..%d (%d bytes); document holds %d </w:tr>\n", $rs, $re, $re - $rs, $trTotal);

// Four cells, each with exactly one paragraph and one <w:t> run, or the text kind cannot carry it.
preg_match_all('#<w:tc>.*?</w:tc>#s', $rowXml, $cm);
if (count($cm[0]) !== 4) {
    $failExit('the seat-key row holds '.count($cm[0]).' cells; §23.6.1 is a four-column register and this pass writes '
        .'four values, so a row of another shape belongs to a different table.');
}
$cellRaw = [];
foreach ($cm[0] as $j => $cell) {
    if (substr_count($cell, '<w:p>') !== 1) {
        $failExit('cell '.($j + 1).' of the seat-key row holds '.substr_count($cell, '<w:p>')
            .' paragraphs; the text kind addresses a run, and a multi-paragraph cell needs the cell kind.');
    }
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
    if (count($tm[1]) !== 1) {
        $failExit('cell '.($j + 1).' of the seat-key row carries '.count($tm[1])
            .' <w:t> runs. A value split across runs cannot travel through one text needle — take it through the '
            .'cell kind, which rebuilds the whole cell.');
    }
    $cellRaw[] = $tm[1][0];
}
foreach ($cellRaw as $j => $raw) {
    $n = substr_count($xml, $raw);
    if ($n !== 1) {
        $failExit("cell ".($j + 1)."'s run bytes occur {$n} time(s) document-wide; a needle that is not unique would "
            .'be resolved by luck. Take that cell through the cell kind instead.');
    }
}

$cellTo = [
    'uq_enrollments_request_key',
    'requestKey',
    'One enrollment per issuance act, so a retried send answers the record the first send created.',
    '`requestKey` is a nullable varchar(64) written by `EnrollmentIssuer::issue()` from a key the desk mints when '
    .'the act is opened and replaces after a success. The index is the guard and the pre-read is only the fast path, '
    .'because two workers can pass a read together; a key that already names another student or term is refused '
    .'rather than answered with somebody else’s record. Added 2026-11-01 for the Advanced Database Systems guide’s '
    .'problem 17, on the same schema moment that retired the VIRTUAL `seatKey`.',
];
foreach ($cellTo as $j => $to) {
    if ($to === $cellRaw[$j]) {
        $failExit('cell '.($j + 1).' would be rewritten with its own bytes — nothing changes and the pass lies.');
    }
    if (str_contains($to, '<') || str_contains($to, '>') || str_contains($to, '&')) {
        $failExit("cell ".($j + 1)."'s replacement carries a character the engine will not take through a text edit "
            .'(&, < or >). Spell it in words.');
    }
}

// The tail sentence is copied into BOTH registers that ask the question, so both carry the record.
// `which today it does not.` occurs twice in this document, so the tail needle states how many copies it expects.
$tailCopies = 2;
$tailFrom = 'should also reject a row written straight to the table, which today it does not.';
$tailTo = $tailFrom.' The schema was tried once for this and retreated: a VIRTUAL generated `seatKey` with '
    .'`uq_enrollments_active_seat` was added on 2026-11-01 for the Advanced Database Systems guide’s problem 3 '
    .'and withdrawn the same day by `2026_11_01_1600000000_withdraw_the_active_seat_key_until_the_fixtures_are_'
    .'repaired.php`, because six test files each wrote two active seats for one student and one term and CI’s '
    .'MySQL job was the only surface that could see them. Its `down()` restores the key once those fixtures name '
    .'a later term or drop the prior seat first. What the domain tables do hold now for enrollment integrity is '
    .'`uq_enrollments_request_key` over `requestKey`, which is the guide’s problem 17: a repeat of the same act is '
    .'answered with the record the first send created rather than a second one.';

$got = substr_count($joined, $tailFrom);
if ($got !== $tailCopies) {
    $failExit("`{$tailFrom}` (§26.4 / §26.7 active-seat question row) occurs {$got} time(s), expected {$tailCopies}.");
}
$rawTail = substr_count($xml, $tailFrom);
if ($rawTail !== $tailCopies) {
    $failExit('the tail clause occurs '.$rawTail.' time(s) in raw document.xml, expected '.$tailCopies
        .'; the flattened count and the byte count disagree, which is a run split. Say it here rather than let the '
        .'engine half-apply a pass.');
}

// ── stage 2b: §24's two figures for the same schema moment, WEIGHED rather than assumed skipped ──────────────
if (! preg_match('/\benrollments\s+—\s+(\d+)\s+columns\b/u', $joined, $hm)) {
    $failExit('no `enrollments — N columns` heading read out of the flattened document. §24 spells its headings in '
        .'two spacing families; neither matched, so this pass is looking at a layout it has not been told about.');
}
$headStated = (int) $hm[1];
if ($headStated !== $enrollCols) {
    $failExit("§24's heading reads `enrollments — {$headStated} columns` while live `{$schema}` answers "
        ."`{$enrollCols}`. This pass writes no §24 figure; re-take it with the spacing family the document actually "
        ."uses, proved by lane60's per-table read, before running this one.");
}
printf("§24 heading weighed, not assumed: `enrollments — %d columns` equals the server's %d\n", $headStated, $enrollCols);

[$rc60, $aggOut] = $runTool('storage/app/scratch/lane60-ratio-claims.php --run');
if (! preg_match('/(\d+) match, (\d+) stale, (\d+) unbound, (\d+) history/', $aggOut, $fm)) {
    $failExit('lane60 printed no `N match, M stale …` footer (exit '.$rc60.'), so its silence is not a measurement. '
        ."Its tail:\n".substr($aggOut, -700));
}
if (! preg_match('/§24 per-table headings: (\d+) read, (\d+) agree with the server, (\d+) differ/', $aggOut, $pm)) {
    $failExit('lane60 printed no §24 per-table headings tally. Its tail:'.substr($aggOut, -700));
}
if ((int) $fm[2] !== 0 || (int) $pm[3] !== 0 || $rc60 !== 0) {
    $lines = [];
    foreach (explode("\n", $aggOut) as $l) {
        if (str_starts_with($l, 'STALE')) {
            $lines[] = '  '.$l;
        }
    }
    $failExit('lane60 exits '.$rc60.' and reports '.$fm[2].' stale sentence figure(s) and '.$pm[3]
        ." differing heading(s); a skip written over an instrument's complaint is the fake-clean verdict this "
        ."repository keeps meeting.\n".implode("\n", $lines));
}
printf("lane60 agrees: %s figures match, 0 stale, %d/%d headings equal the server — §24 skipped because it was "
    ."weighed, not because nothing was found.\n", $fm[1], $pm[2], $pm[1]);

echo "\nplan\n";
foreach ($cellRaw as $j => $raw) {
    // $raw is the run's INNER bytes, so there is nothing for $flatten to read — decode it directly, or the plan
    // prints an empty "before" value beside every real one and the reader cannot see what is being replaced.
    printf("  cell %d    : `%s`\n            -> `%s`\n", $j + 1,
        mb_substr(html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 52), mb_substr($cellTo[$j], 0, 52));
}
printf("  count      : %s\n", $countChanged ? "`{$oldCount}` -> `{$newCount}`" : "skipped, already the server's pair ({$newCount})");
printf("  §24 head   : unchanged (%d columns, weighed against the server above)\n", $headStated);
printf("  §26.4 row  : append %d bytes after its own clause, in %d copie(s)\n", strlen($tailTo) - strlen($tailFrom), $tailCopies);
printf("  </w:tr>    : %d before and after (no row is added or deleted)\n", $trTotal);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";
    exit(0);
}

if (@copy($src, $origCopy) !== true) {
    $failExit("could not keep an original copy at {$origCopy}; refusing to mutate without a rollback.");
}

$textEdits = [];
foreach ($cellRaw as $j => $raw) {
    $textEdits[] = [$raw, $cellTo[$j]];
}
$textEdits[] = [$tailFrom, $tailTo, null, $tailCopies];
if ($countChanged) {
    $textEdits[] = [$oldCount, $newCount];
}
printf("text edits this pass will make: %d\n", count($textEdits));

// docxApply is declared `: void` and exits the process when a target does not resolve, so there is no false to test
// for here. The original copy above is the rollback, and a refusal leaves the file untouched because the engine
// writes only after every target resolves — and because it exits, no finally of mine would run.
docxApply($src, $engineBackup, $textEdits, [], true);

// ── stage 3: read the result back from the archive ──────────────────────────────────────────────────────────
if ($zip->open($src) !== true) {
    copy($origCopy, $src);
    $failExit('could not re-open the docx after the write.');
}
$body = (string) $zip->getFromName('word/document.xml');
$zip->close();
$after = $flatten($body);

$problem = 0;
$seatMentions = substr_count($body, 'uq_enrollments_active_seat');
$keyMentions = substr_count($body, 'uq_enrollments_request_key');
printf("\nread-back: </w:tr> %d -> %d; seat name %d time(s) (want %d, the withdrawal records only); request name "
    ."%d time(s) (want %d, the row plus both records)\n",
    $trTotal, substr_count($body, '</w:tr>'), $seatMentions, $tailCopies, $keyMentions, $tailCopies + 1);
if (substr_count($body, '</w:tr>') !== $trTotal) {
    echo "  POST-FAIL: the row count moved; this pass rewrites cells and adds no row.\n";
    $problem++;
}
if ($seatMentions !== $tailCopies) {
    echo '  POST-FAIL: expected the withdrawn name only in the '.$tailCopies.' records, found '.$seatMentions.".\n";
    $problem++;
}
if ($keyMentions !== $tailCopies + 1) {
    echo '  POST-FAIL: expected the new name once in the register plus '.$tailCopies.' records, found '
        .$keyMentions.".\n";
    $problem++;
}
$checks = [[$cellTo[2], 1], [$cellTo[3], 1], ['The schema was tried once for this and retreated', $tailCopies]];
if ($countChanged) {
    $checks[] = [$oldCount, 0];
    $checks[] = [$newCount, 1];
} else {
    $checks[] = [$newCount, 1];
}
foreach ($checks as [$n, $want]) {
    $got = substr_count($after, $n);
    if ($got !== $want) {
        printf("  POST-FAIL: `%s` occurs %d, want %d.\n", mb_substr($n, 0, 46), $got, $want);
        $problem++;
    }
}
if ($problem > 0) {
    copy($origCopy, $src);
    $failExit("read-back found {$problem} problem(s); the original is restored from {$origCopy}.");
}

// ── stage 4: the instrument that owns this boundary must now read it clean ──────────────────────────────────
[$rcDump2, $dumpOut2] = $runTool('storage/app/scratch/dump-docx-flat.php');
if ($rcDump2 !== 0) {
    copy($origCopy, $src);
    $failExit('dump-docx-flat.php exited '.$rcDump2.' after the write: '.$dumpOut2);
}
[$rc44After, $lane44After] = $runTool('storage/app/scratch/lane44-unique-keys.php');
$problems = preg_match('/(\d+) problem\(s\)/', $lane44After, $pp) ? (int) $pp[1] : -1;
preg_match_all('/^\s*(MISS|PHANTOM)\s+(\S+)/m', $lane44After, $cm3, PREG_SET_ORDER);
$left = array_map(static fn (array $c): string => $c[1].' '.$c[2], $cm3);
printf("\nlane44 after the write: %d problem(s), exit %d, complaints [%s]\n",
    $problems, $rc44After, implode(', ', $left));
if ($problems !== 0 || $rc44After !== 0 || $left !== []) {
    copy($origCopy, $src);
    $failExit('lane44 still reads '.$problems.' problem(s) against the boundary list (exit '.$rc44After
        .', complaints ['.implode(', ', $left).']); the original is restored from '.$origCopy
        .'. Its tail:'.substr($lane44After, -900));
}
echo 'lane44 reads §23.6.1 as a closed boundary: the register and the server agree on '.$serverKeys.' keys in '
    .$serverTables." tables, and the row this pass rewrote is the reason.\n";

echo "\nWROTE and READ BACK clean. Now: lane60 --run, sweep-docx-citations, lane37, lane37b, "
    ."repo-file-line-citations, docx-ooxml-check, verify-docx and the Word open test.\n";
exit(0);
