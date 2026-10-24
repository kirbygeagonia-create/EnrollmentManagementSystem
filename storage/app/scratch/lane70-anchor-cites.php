<?php

/**
 * Lane 70: does the line a bare pointer quotes actually hold the thing the sentence names?
 *
 * `:113-135` says nothing to a bounds checker as long as line 113 is not blank, and 92 of the document's
 * continuations are bare. Printing them as blind spots (lane 69) was honest but left them unmeasured, and the
 * proposer in this same folder showed why that matters: the row that reads "Copied from applicantType ( :328 )"
 * lands on `'uploadedDate' => now()`, and the one that reads `ShiftRequestController::decide(), :200` lands on
 * `decide()` at :228. So this tool takes the identifier the sentence itself names — a `Class::method`, a column,
 * a class word — and asks the cited file where that identifier really lives.
 *
 * Verdicts: OK (the range holds the anchor), MOVED (the anchor is in the file, but outside the quoted range —
 * the true line is printed), NO-ANCHOR (the anchor is nowhere in that file, so the row names the wrong file),
 * UNBOUND (the sentence offers no identifier to search for, which stays a human read and is counted, not cleared).
 *
 * It refuses on MOVED and NO-ANCHOR, because both are false claims about the build. Reading is the human half,
 * but a checker can at least say which sentences are worth reading.
 *
 *   php storage/app/scratch/lane70-anchor-cites.php [--dump=path] [--all] [--limit=N]
 */
$root = dirname(__DIR__, 3);
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$all = false;
$limit = 0;
foreach ($argv as $a) {
    if (str_starts_with($a, '--dump=')) {
        $dump = substr($a, 7);
    }
    if ($a === '--all') {
        $all = true;
    }
    if (str_starts_with($a, '--limit=')) {
        $limit = (int) substr($a, 8);
    }
}
if (! is_file($dump) || filesize($dump) < 1000) {
    echo "REFUSED: no dump at {$dump} — run dump-docx-flat.php first.\n";
    exit(1);
}
$text = (string) file_get_contents($dump);

$filename = '(?:(?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js|json|neon|xml|md))';
$pattern = '/(?<name>'.$filename.'):(?<n>\d+)(?:-(?<to>\d+))?|(?:(?<fileonly>'.$filename.')(?![:\w]))|(?:(?<lead>[,(]|\bat|\band|\bto|\bis)[\s;]*:(?<bare>\d+)(?:-(?<bareTo>\d+))?(?![\w\-]))/u';
preg_match_all($pattern, $text, $m, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL | PREG_OFFSET_CAPTURE);

// One index walk; line starts for the same reason the sweep does it that way.
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
$lineStart = [0];
$pos = 0;
while (($nl = strpos($text, "\n", $pos)) !== false) {
    $lineStart[] = $nl + 1;
    $pos = $nl + 1;
}
$lineOf = function (int $offset) use ($lineStart): int {
    $lo = 0;
    $hi = count($lineStart) - 1;
    while ($lo < $hi) {
        $mid = intdiv($lo + $hi + 1, 2);
        if ($lineStart[$mid] <= $offset) {
            $lo = $mid;
        } else {
            $hi = $mid - 1;
        }
    }

    return $lo;
};

$lines = explode("\n", $text);
// Names mentioned per line, whether or not they carry a number, so a cell that ends with a file name still owns
// the next cell's bare number.
$lineNames = [];
$tokens = [];
foreach ($m as $x) {
    $line = $lineOf($x[0][1]);
    if (($x['name'][0] ?? null) !== null) {
        $lineNames[$line][] = $x['name'][0];
    } elseif (($x['fileonly'][0] ?? null) !== null) {
        $lineNames[$line][] = $x['fileonly'][0];
    } else {
        $tokens[] = [(int) $x['bare'][0], ($x['bareTo'][0] ?? null) === null ? (int) $x['bare'][0] : (int) $x['bareTo'][0], $x[0][1], $line];
    }
}

/** The identifiers a sentence offers as its own subject. */
$anchorsOf = function (string $sentence): array {
    $out = [];
    if (preg_match_all('/([A-Z][A-Za-z0-9_]+)::([a-z][A-Za-z0-9_]+)/u', $sentence, $mm, PREG_SET_ORDER) >= 1) {
        foreach ($mm as $x) {
            $out[] = [$x[2].'(', $x[1].'.php'];
        }
    }
    if (preg_match_all('/\b([a-z]+[A-Z][A-Za-z0-9]{3,})\b/u', $sentence, $mc) >= 1) {
        foreach ($mc[1] as $w) {
            $out[] = [$w, null];
        }
    }
    if (preg_match_all('/\b([A-Z][a-z]+(?:Controller|Policy|Service|Seeder|Test|Manager))\b/u', $sentence, $mk) >= 1) {
        foreach ($mk[1] as $w) {
            $out[] = ['function', $w.'.php'];
        }
    }

    return $out;
};

$counts = ['OK' => 0, 'MOVED' => 0, 'NO-ANCHOR' => 0, 'UNBOUND' => 0, 'UNRESOLVED' => 0];
$printed = 0;
foreach ($tokens as $t) {
    [$from, $to, $offset, $line] = $t;
    $names = $lineNames[$line] ?? [];
    if ($names === []) {
        for ($d = -2; $d <= 2 && $names === []; $d++) {
            if ($d !== 0) {
                $names = $lineNames[$line + $d] ?? [];
            }
        }
    }
    if ($names === []) {
        $counts['UNRESOLVED']++;
        if ($all && $printed++ < $limit) {
            printf("UNRESOLVED :%s at line %d — no file on the row or within two lines\n", $from, $line + 1);
        }

        continue;
    }
    // Sentence boundaries: a dump row can hold several sentences; use the one containing the token.
    $col = $offset - $lineStart[$line];
    $sent = '';
    foreach (preg_split('/(?<=[.!?]) +/u', $lines[$line]) as $s) {
        $p = strpos($lines[$line], $s);
        if ($p !== false && $p <= $col && $col <= $p + strlen($s) + 2) {
            $sent = $s;

            break;
        }
    }
    $sent = $sent === '' ? mb_substr($lines[$line], max(0, $col - 120), 240) : $sent;
    $anchors = $anchorsOf($sent);

    // Prefer a file the sentence itself names, then the row's own file names.
    $files = [];
    foreach ($anchors as [$needle, $hint]) {
        if ($hint !== null && isset($index[$hint])) {
            $files = [$hint];

            break;
        }
    }
    if ($files === []) {
        $files = array_values(array_unique($names));
    }
    $path = null;
    foreach ($files as $f) {
        foreach ($index[basename($f)] ?? [] as $p) {
            if (preg_match('/\.(php|jsx|js)$/u', $p)) {
                $path = $p;

                break 2;
            }
        }
    }
    if ($path === null) {
        $counts['UNRESOLVED']++;

        continue;
    }
    $contents = (string) file_get_contents($path);
    $fl = explode("\n", $contents);
    $anchorFound = false;
    foreach ($anchors as [$needle, $hint]) {
        if ($needle === 'function') {
            continue;
        }
        $anchorFound = true;
        // Where does the anchor live, and is any of those places inside the quoted range?
        $hits = [];
        foreach ($fl as $i => $row) {
            if (str_contains($row, $needle)) {
                $hits[] = $i + 1;
            }
        }
        if ($hits === []) {
            $counts['NO-ANCHOR']++;
            printf("NO-ANCHOR  %-30s :%d-%d  names \"%s\" which that file never contains\n",
                basename($path), $from, $to, $needle);

            continue 2;
        }
        $inRange = array_filter($hits, fn ($h) => $h >= $from && $h <= $to);
        if ($inRange !== []) {
            $counts['OK']++;

            continue 2;
        }
        $counts['MOVED']++;
        printf("MOVED      %-30s :%d-%d  but \"%s\" is at :%s\n",
            basename($path), $from, $to, $needle, implode('/', array_slice($hits, 0, 3)));

        continue 2;
    }
    if (! $anchorFound) {
        $counts['UNBOUND']++;
    }
}

printf("\n%d bare pointer(s) judged: %d OK, %d MOVED, %d NO-ANCHOR, %d UNBOUND (no identifier to ask about), %d UNRESOLVED (no file anywhere near)\n",
    count($tokens), $counts['OK'], $counts['MOVED'], $counts['NO-ANCHOR'], $counts['UNBOUND'], $counts['UNRESOLVED']);
echo "UNBOUND and UNRESOLVED are printed as queues, not cleared: a sentence that names no identifier can only be read.\n";
exit($counts['MOVED'] > 0 || $counts['NO-ANCHOR'] > 0 ? 1 : 0);
