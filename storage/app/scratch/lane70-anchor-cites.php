<?php

/**
 * Lane 70: does the line a bare pointer quotes actually hold the thing the sentence names?
 *
 * `:113-135` says nothing to a bounds checker as long as line 113 is not blank, and 136 of the document's
 * continuations are bare (the bounds sweep judges 135 and prints 1 as a blind spot). Printing them as blind spots
 * (lane 69) was honest but left them unmeasured, and the proposer in this same folder showed why that matters: the
 * row that reads "Copied from applicantType ( :328 )" lands on `'uploadedDate' => now()`, and the one that reads
 * `ShiftRequestController::decide(), :200` lands on `decide()` at :228. So this tool takes the identifier the
 * sentence itself names — a `Class::method`, a call, a column word — and asks the cited file where it really lives.
 *
 * Verdicts: OK (the range holds the identifier, or sits inside the construct that does), MOVED (the identifier is
 * in the file but outside the quoted range — the true line is printed), NO-ANCHOR (the identifier is nowhere in
 * that file, so the row names the wrong file), UNBOUND (the sentence offers no identifier for this pointer, which
 * stays a human read and is counted, not cleared), BLIND (no file within the attribution window, or none that
 * resolves — also a read).
 *
 * **Attribution is not this tool's to invent.** Which file owns a bare number and which construct owns a
 * citation are decided by `docx-cite-attribution.php`, because the bounds sweep and the meaning sweep already
 * answer both, and a third answer would let two checkers disagree about one sentence. An earlier version of this
 * file did invent one: it took the FIRST file named anywhere on the dump row and the FIRST identifier in the
 * sentence. Measured in full on 2026-10-25, that produced 47 MOVED rows, and reading those 47 *pairings* found no
 * claim the build contradicts — 8 rows named a file other than the citation's own, 6 quoted a clause inside the
 * construct they named, and 33 charged a register row's column label or its first-mentioned name to a range some
 * other construct owns. So a MOVED row is a pairing to read; exit 1 means "these are worth opening", not "the
 * document is wrong". **And "worth opening" is not "nothing is there": re-opening the ten rows that survived the
 * attribution fix found ten further pointers that were live code describing something else (passes 224-226, one of
 * them thirteen pointers inside one policy file). A clean read of a pairing is not a clean read of the file it
 * points at.**
 *
 *   php storage/app/scratch/lane70-anchor-cites.php [--dump=path] [--limit=N] [--show-ok]
 */
$root = dirname(__DIR__, 3);
require_once $root.'/storage/app/scratch/docx-cite-attribution.php';
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$limit = 0;
$showOk = false;
foreach ($argv as $a) {
    if (str_starts_with($a, '--dump=')) {
        $dump = substr($a, 7);
    }
    if (str_starts_with($a, '--limit=')) {
        $limit = (int) substr($a, 8);
    }
    // The mutant harness needs a KNOWN-CORRECT pairing to move, and hardcoding one decays the first time a pass
    // re-points it (lane 32's parenthesis leg died exactly that way). `--show-ok` prints them so the harness can
    // read one out of the current document instead of remembering one.
    if ($a === '--show-ok') {
        $showOk = true;
    }
}
// `--all` was deleted rather than left working: the queue rows it gated are now printed unconditionally, and
// `$limit` used to default to 0, which made `printed++ < limit` false for every caller — so `--all` printed nothing
// and the footer still claimed the queue was in the output. A flag that is accepted and inert is how a checker
// reports a queue it never wrote (lane 44's sleeping mutant).
if (! is_file($dump) || filesize($dump) < 1000) {
    echo "REFUSED: no dump at {$dump} — run dump-docx-flat.php first.\n";
    exit(1);
}
$text = (string) file_get_contents($dump);

$index = [];
foreach (['app', 'resources', 'routes', 'database', 'bootstrap', 'config', 'tools', 'tests'] as $dir) {
    if (! is_dir($root.'/'.$dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $index[$f->getFilename()][] = str_replace('\\', '/', $f->getPathname());
        }
    }
}
$resolve = static function (string $name) use ($index, $root): ?string {
    $key = str_replace('\\', '/', $name);
    $b = basename($key);
    if (isset($index[$b])) {
        return $index[$b][0];
    }
    if (str_contains($key, '/') && is_file($root.'/'.$key)) {
        return $root.'/'.$key;
    }

    return null;
};

$scan = docxScan($text);
$lineStart = docxLineStarts($text);
// RULE 1, shared with the bounds sweep: the nearest name BEFORE the token on the same dump line, within 160 bytes.
$owners = docxBareOwners($scan['bare'], $scan['anchors'], $lineStart);

$lines = explode("\n", $text);
$cellLabelRe = '/(?:^|[\s|;(])((?:[A-Za-z][A-Za-z0-9_]*[A-Z][A-Za-z0-9_]*|[a-z]+(?:[A-Z][a-z0-9]+)+))(?=\s*[|;)])/';

/** The constructs a sentence could be naming, with their positions, so the shared rule can pair them. */
$sentenceSymbols = static function (string $sentence): array {
    $out = [];
    preg_match_all('/([A-Z][A-Za-z0-9_]+)::([a-z][A-Za-z0-9_]+)/u', $sentence, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($mm as $x) {
        $out[] = ['at' => $x[0][1], 'end' => $x[0][1] + strlen($x[0][0]),
            'name' => $x[2][0], 'hint' => $x[1][0].'.php'];
    }
    preg_match_all('/\b([a-z][A-Za-z0-9_]*)\(\)/u', $sentence, $mp, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($mp as $x) {
        $out[] = ['at' => $x[0][1], 'end' => $x[0][1] + strlen($x[0][0]), 'name' => $x[1][0], 'hint' => null];
    }
    preg_match_all('/\b([a-z]+[A-Z][A-Za-z0-9]{3,})\b/u', $sentence, $mc, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($mc as $x) {
        $out[] = ['at' => $x[0][1], 'end' => $x[0][1] + strlen($x[0][0]), 'name' => $x[1][0], 'hint' => null];
    }
    // `WorkflowService::signStep()` is the same construct twice — once qualified, once bare — and the bare copy
    // sits nearer to a citation in the next clause. Keep one entry per name, and keep the one that carries its
    // class, because the qualifier is what tells the checker which file the name belongs to.
    $byName = [];
    foreach ($out as $i => $s) {
        if (! isset($byName[$s['name']]) || ($out[$byName[$s['name']]]['hint'] === null && $s['hint'] !== null)) {
            $byName[$s['name']] = $i;
        }
    }

    return array_values(array_intersect_key($out, array_flip(array_values($byName))));
};

$counts = ['OK' => 0, 'MOVED' => 0, 'NO-ANCHOR' => 0, 'UNBOUND' => 0, 'BLIND' => 0];
$printed = 0;
$withheld = 0;
// Every row this tool puts in a queue bucket MUST reach the output, or the run must say how many it held back —
// `printed + withheld == UNBOUND + BLIND` is refused below if it is not true. A tally of blind spots with no
// evidence under it is the defect this repository already removed from lane 61's blind list and from the gloss
// checker's summary; the footer used to promise these rows were printed while `$limit = 0` made every print
// condition false.
$mayPrint = function () use (&$printed, &$withheld, $limit): bool {
    if ($limit > 0 && $printed >= $limit) {
        $withheld++;

        return false;
    }
    $printed++;

    return true;
};
foreach ($scan['bare'] as $k => [$from, $to, $offset]) {
    $to = $to ?? $from;
    if ($from < 1) {
        continue;
    }
    $own = $owners[$k];
    if ($own === null) {
        $counts['BLIND']++;
        if ($mayPrint()) {
            printf("BLIND      :%d-%d  no file named within the attribution window on dump line %d\n",
                $from, $to, docxLineOf($offset, $lineStart) + 1);
        }

        continue;
    }

    // The unit this tool asks about is the sentence holding the pointer, cut out of the dump line.
    $lineNo = docxLineOf($offset, $lineStart);
    $col = $offset - $lineStart[$lineNo];
    $sentence = '';
    foreach (preg_split('/(?<=[.!?]) +/u', $lines[$lineNo]) ?: [] as $s) {
        $p = strpos($lines[$lineNo], $s);
        if ($p !== false && $p <= $col && $col <= $p + strlen($s) + 2) {
            $sentence = $s;

            break;
        }
    }
    if ($sentence === '') {
        $sentence = mb_substr($lines[$lineNo], max(0, $col - 120), 240);
    }
    $tokAt = strpos($sentence, ':'.$from);
    if ($tokAt === false) {
        // The sentence split this tool chose does not actually contain the token. Pairing is done in
        // sentence-relative offsets, so falling back to an offset measured from the START OF THE LINE would place
        // the pointer somewhere the pairing never saw it — and the nearest symbol then "owns" a citation that is
        // not in front of it. Say UNBOUND instead of inventing a position: an honest queue row beats a confident
        // wrong pairing, which is the whole lesson of the 47 complaints this pass replaced.
        $counts['UNBOUND']++;

        continue;
    }

    // RULE 3: a register row's column label is a cell, not a claim about a line of code.
    $labels = [];
    if (preg_match_all($cellLabelRe, $lines[$lineNo], $lm) >= 1) {
        $labels = array_values(array_unique($lm[1]));
    }

    // Cites inside the sentence: the named ones, plus THIS pointer carrying the file rule 1 settled. Pairing
    // needs the token to be a candidate owner like any other citation.
    $cites = [];
    $citeRe = '/'.docxFilenamePattern().':(\d+)(?:-(\d+))?/u';
    if (preg_match_all($citeRe, $sentence, $cm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) >= 1) {
        foreach ($cm as $c) {
            $cites[] = ['file' => rtrim($c[0][0], '0123456789-:'), 'from' => (int) $c[1][0],
                'to' => (($c[2][1] ?? -1) === -1) ? (int) $c[1][0] : (int) $c[2][0],
                'start' => $c[0][1], 'end' => $c[0][1] + strlen($c[0][0])];
        }
    }
    $cites[] = ['file' => $own['file'], 'from' => $from, 'to' => $to, 'mine' => true,
        'start' => $tokAt, 'end' => $tokAt + strlen((string) $from)];
    usort($cites, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
    // Sorting moved the token, so find it again by its flag rather than by position: an earlier draft took
    // array_key_last() and paired whatever citation happened to sort last.
    $mineKey = array_search(true, array_column($cites, 'mine'), true);
    if ($mineKey === false) {
        $counts['BLIND']++;
        if ($mayPrint()) {
            printf("BLIND      %-28s :%d-%d  the token could not be found in its own sentence after pairing — read dump line %d\n",
                basename((string) $own['file']), $from, $to, docxLineOf($offset, $lineStart) + 1);
        }

        continue;
    }

    $symbols = array_values(array_filter(
        $sentenceSymbols($sentence),
        static fn (array $s): bool => ! in_array($s['name'], $labels, true)
    ));
    if ($symbols === []) {
        $counts['UNBOUND']++;
        if ($mayPrint()) {
            printf("UNBOUND    %-28s :%d-%d  the sentence names no construct at all — %s\n",
                basename($own['file']), $from, $to, trim(mb_substr((string) $sentence, 0, 96)));
        }

        continue;
    }

    // RULE 2, shared with the meaning sweep: which construct reaches THIS pointer?
    $pair = docxPairByUnit($sentence, $cites, $symbols);
    if (! array_key_exists($mineKey, $pair['byCite'])) {
        $counts['UNBOUND']++;
        if ($mayPrint()) {
            printf("UNBOUND    %-28s :%d-%d  no construct in the sentence reaches it — %s\n",
                basename($own['file']), $from, $to, trim(mb_substr($sentence, 0, 90)));
        }

        continue;
    }
    $sym = $symbols[$pair['byCite'][$mineKey]];
    // A `Class::method` name carries its own file. When that file is not the one the citation belongs to, the
    // pairing has crossed a clause boundary inside a register row — the row names one class's method and cites
    // another class's line — and this tool has no business calling that a false claim about the file. It is the
    // same "the sentence's first identifier is not the pointer's subject" defect that made the 47, seen from the
    // other side: the name is real, the pairing is not.
    if ($sym['hint'] !== null && strcasecmp(basename($own['file'], '.php').'.php', $sym['hint']) !== 0
        && basename($own['file']) !== $sym['hint']) {
        $counts['UNBOUND']++;
        if ($mayPrint()) {
            printf("UNBOUND    %-28s :%d-%d  paired with %s, whose own qualifier is %s — a clause boundary, read it\n",
                basename($own['file']), $from, $to, $sym['name'], $sym['hint']);
        }

        continue;
    }
    // The citation's OWN file is authoritative (rule 1). A `Class::method` name elsewhere in the sentence may
    // belong to another file entirely — 8 of last pass's complaints were exactly that, a `PrintService::recordIssue()`
    // mention weighed against a `RegistrarController.php` pointer — so the hint is only a fallback when the owner
    // does not resolve at all.
    $path = $resolve($own['file']) ?? ($sym['hint'] === null ? null : $resolve($sym['hint']));
    if ($path === null) {
        $counts['BLIND']++;
        if ($mayPrint()) {
            printf("BLIND      %-28s :%d-%d  neither the citation's file nor the name's qualifier resolves in this repository\n",
                basename((string) $own['file']), $from, $to);
        }

        continue;
    }
    $fl = file($path);
    if ($fl === false || $to > count($fl)) {
        $counts['BLIND']++;
        if ($mayPrint()) {
            printf("BLIND      %-28s :%d-%d  the file could not be read, or %d is past its end (%d lines) — the bounds sweep should have refused this\n",
                basename((string) $own['file']), $from, $to, $to, $fl === false ? 0 : count($fl));
        }

        continue;
    }

    $needle = $sym['name'];
    $hits = [];
    foreach ($fl as $i => $row) {
        if (str_contains($row, $needle)) {
            $hits[] = $i + 1;
        }
    }
    if ($hits === []) {
        $counts['NO-ANCHOR']++;
        printf("NO-ANCHOR  %-28s :%d-%d  names \"%s\", which %s never contains\n",
            basename($path), $from, $to, $needle, basename($own['file']));

        continue;
    }
    $inside = false;
    foreach ($hits as $h) {
        if ($h >= $from && $h <= $to) {
            $inside = true;

            break;
        }
    }
    if ($inside) {
        $counts['OK']++;
        if ($showOk) {
            printf("OK         %-28s :%d-%d  holds \"%s\"\n", basename($path), $from, $to, $needle);
        }

        continue;
    }
    // RULE 2b, the encloses case: a pointer may name a clause inside the construct the prose names. Reading the
    // cited span's enclosing construct is the only way to tell that from drift — 6 of the 47 complaints last pass
    // were exactly this, including "the verdict's last clause" and "the signature at".
    $enc = docxEnclosingConstruct($fl, $from);
    $encName = strtok($enc, '(');
    if ($encName !== false && $encName !== '' && str_replace('()', '', $encName) !== ''
        && (mb_stripos($enc, $needle) !== false || mb_stripos($needle, rtrim($encName, '()')) !== false)) {
        $counts['OK']++;

        continue;
    }
    $counts['MOVED']++;
    printf("MOVED      %-28s :%d-%d  but \"%s\" is at %s\n",
        basename($path), $from, $to, $needle, implode('/', array_slice($hits, 0, 3)));
    printf("           in %s — sentence: %s\n", basename($own['file']), trim(mb_substr($sentence, 0, 150)));
}

$judged = $counts['OK'] + $counts['MOVED'] + $counts['NO-ANCHOR'];
$queue = $counts['UNBOUND'] + $counts['BLIND'];
if ($printed + $withheld !== $queue) {
    printf("REFUSED: the run tallies %d queue row(s) (UNBOUND %d, BLIND %d) but wrote %d and withheld %d.\n"
        ."       A queue that is counted and not printed is the defect this tool exists to avoid — some branch\n"
        ."       increments a bucket without going through the printer, so a reader would be sent after rows that\n"
        ."       were never delivered.\n",
        $queue, $counts['UNBOUND'], $counts['BLIND'], $printed, $withheld);
    exit(1);
}

if ($judged === 0) {
    // Lane 43's lesson: a checker that matched nothing looks exactly like a checker that found nothing wrong.
    printf("REFUSED: %d bare pointer(s) read and 0 judged — the dump is empty of attributable pointers, which is\n"
        ."       a broken read, not a clean one (UNBOUND %d, BLIND %d).\n",
        count($scan['bare']), $counts['UNBOUND'], $counts['BLIND']);
    exit(1);
}

printf("\n%d bare pointer(s) judged: %d OK, %d MOVED, %d NO-ANCHOR, %d UNBOUND (no construct reaches it), %d BLIND (no owner within the window)\n",
    count($scan['bare']), $counts['OK'], $counts['MOVED'], $counts['NO-ANCHOR'], $counts['UNBOUND'], $counts['BLIND']);
echo "UNBOUND and BLIND are written above as a queue, not cleared: a sentence that names no construct can only be\n"
    ."read. Run with --limit=N to skim, and the run refuses unless printed + withheld equals the queue tally.\n"
    ."MOVED says which pairing to open, not that the pointer is false — attribution here is shared with the\n"
    ."bounds sweep and the meaning sweep (docx-cite-attribution.php), so a MOVED row is a claim this tool can\n"
    ."make only where those two already agree.\n"
    .($withheld > 0 ? '  NOTE: '.$withheld.' queue row(s) withheld by --limit='.$limit." — the queue above is INCOMPLETE.\n" : '');

exit($counts['MOVED'] > 0 || $counts['NO-ANCHOR'] > 0 ? 1 : 0);
