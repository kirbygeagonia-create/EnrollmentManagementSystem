<?php

/**
 * Lane 70: every citation whose sentence quotes SOURCE CODE in a parenthetical, checked against the file
 * it points at.
 *
 * Why this class exists: `unset($steps[2])` sits in §7.2's row as the thing that removes the Assessment box,
 * with a line range beside it. That statement was true until 29b5532 rewrote the skip as an `array_filter()`,
 * and no instrument could see it — the range lands on live lines (the bounds sweep is green), the file is named
 * (the §-sweep is green), and lane 32 only judges a pair when the prose names a *method*, not when it quotes an
 * expression. A quoted snippet is different from a gloss: "the office test" cannot be weighed by a regex, but
 * `unset($steps[2])` either exists in that file or it does not.
 *
 * Three verdicts, and the two hard ones are what to read:
 *   NOT-IN-FILE   the quoted call does not exist anywhere in the cited file. The prose describes a build that
 *                 is gone, or a call that was never there. This is a finding, not a queue entry.
 *   PARAPHRASE    the parenthetical does not appear verbatim and names no call — e.g. `stepOrder = $order + 1`,
 *                 where the source writes `'stepOrder' => $order + 1`. A reader who greps the quoted text finds
 *                 nothing, so it is reported for re-reading, not refused.
 *   OK            the quoted call exists; when the citation carries a range, whether an occurrence falls inside
 *                 it is printed as IN-RANGE or OUT-OF-RANGE (a hint, never a verdict — a gloss may legitimately
 *                 name a helper defined elsewhere).
 *
 * It exits 0 always, because the precision is not measured yet. Run it as a queue.
 *
 * usage: php storage/app/scratch/lane70-gloss-snippets.php [--dump=<flat dump>]
 */
$root = dirname(__DIR__, 3);
$dumpPath = $root.'/storage/app/scratch/docx-flat.txt';
foreach ($argv ?? [] as $a) {
    if (str_starts_with($a, '--dump=')) {
        $dumpPath = substr($a, 7);
    }
}
if (! is_file($dumpPath)) {
    echo "REFUSED: no dump at {$dumpPath}. Run dump-docx-flat.php first — an unread document is not a clean one.\n";
    exit(1);
}
$dump = (string) file_get_contents($dumpPath);
$lines = explode("\n", $dump);

$filename = '(?:(?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js|json|neon|xml|md))';
$cite = '/(?<name>'.$filename.'):(\d+)(?:-(\d+))?/';

/** Basename -> repository paths, built once. The walk prunes on the way down: descending into node_modules
 *  and then ignoring it costs minutes, and a checker that slow gets skipped. */
$index = [];
$skipDirs = ['node_modules', 'vendor', '.git', 'storage', 'bootstrap', 'public', 'Images'];
$prune = new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    function ($current) use ($root, $skipDirs) {
        if (! $current->isDir()) {
            return true;
        }
        $rel = str_replace('\\', '/', substr($current->getPathname(), strlen($root) + 1));

        return ! in_array($rel, $skipDirs, true) && ! str_starts_with($rel, '.git');
    }
);
foreach (new RecursiveIteratorIterator($prune) as $f) {
    if (! $f->isFile()) {
        continue;
    }
    $p = str_replace('\\', '/', $f->getPathname());
    $rel = ltrim(str_replace('\\', '/', substr($p, strlen($root))), '/');
    if (str_contains($rel, '/visualizations/') || str_ends_with($rel, '.sql')) {
        continue;
    }
    $index[basename($rel)][] = $rel;
    if ($rel !== basename($rel)) {
        $index[$rel][] = $rel;
    }
}

$contents = [];
/** The method whose declaration precedes the given line — the prose may name the owner of a range. */
$enclosingFunction = function (array $fileLines, int $line): ?string {
    $name = null;
    for ($i = $line - 1; $i >= 0; $i--) {
        if (preg_match('/(?:function|const)\s+([A-Za-z_][A-Za-z0-9_]*)/', $fileLines[$i], $m) === 1) {
            $name = $m[1];

            break;
        }
    }

    return $name;
};
$read = function (string $rel) use ($root, &$contents): ?array {
    if (! array_key_exists($rel, $contents)) {
        $file = @file($root.'/'.$rel);
        $contents[$rel] = $file === false ? null : $file;
    }

    return $contents[$rel];
};

/** Words that mean the parenthetical is prose, not a quoted expression. */
$prose = [' the ', ' and ', ' not ', ' with ', ' only ', ' which ', ' no ', ' is ', ' are ', ' both ',
    ' same ', ' then ', ' that ', ' this ', ' from ', ' before ', ' after ', ' ruled ', ' measured ',
    ' created ', ' called ', ' permission ', ' build ', ' row ', ' rows ', ' code ', ' fixed ', ' scope ',
    ' order ', ' box ', ' boxes ', ' list ', ' test ', ' check ', ' call ', ' impl '];

$hard = [];
$soft = [];
$dropped = [];
$okCount = 0;
$glossCount = 0;

foreach ($lines as $li => $raw) {
    $line = preg_replace('/\s+/', ' ', html_entity_decode(str_replace('&quot;', '"', $raw), ENT_QUOTES));
    // Every parenthetical on the line that looks like quoted source and is not itself a citation.
    // One nesting level is allowed, and the inner pair may be empty: the document writes `( array_filter() )`
    // and `( unset($steps[2]) )`, and an argument-list-free call is exactly the shape a stale pointer hides in.
    if (preg_match_all('/\(((?:[^()]|\([^()]{0,40}\)){3,110})\)/u', $line, $pm) === 0) {
        continue;
    }
    foreach ($pm[1] as $gloss) {
        $g = trim($gloss);
        $low = ' '.mb_strtolower($g).' ';
        $isCodeish = preg_match('/[A-Za-z_][A-Za-z0-9_]*\s*\(|->|::|\$|\=\>/', $g) === 1
            || preg_match('/\w+\s*=\s*\$?\w/', $g) === 1;
        $isCitation = preg_match('/[A-Za-z]\.(?:php|jsx|js|md|json|neon|xml)\b/', $g) === 1
            || preg_match('/:\d/', $g) === 1;
        $hasProseWord = false;
        foreach ($prose as $w) {
            if (str_contains($low, $w)) {
                $hasProseWord = true;
                break;
            }
        }
        if (! $isCodeish || $isCitation || $hasProseWord) {
            continue;
        }
        // Attribute it to the nearest citation on the same line, before or after, within 200 chars. A bare
        // continuation (`:97`, `( stepOrder = $order + 1 )`) borrows the file named most recently before it —
        // the same rule the line sweep uses, and without it a row that cites one file twice looks unbound.
        $cites = [];
        if (preg_match_all('/(?<name>'.$filename.'):(?<from>\d+)(?:-(?<to>\d+))?|(?<lead>[,(]|\bat|\band|\bto|\bis)[\s;]*:(?<bfrom>\d+)(?:-(?<bto>\d+))?(?![\w\-])/u',
            $line, $cm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
            $lastFile = null;
            foreach ($cm as $s) {
                if (($s['name'][0] ?? '') !== '') {
                    $lastFile = $s['name'][0];
                    $cites[] = ['at' => $s[0][1], 'len' => strlen($s[0][0]), 'file' => $lastFile,
                        'from' => (int) $s['from'][0],
                        'to' => ($s['to'][0] ?? '') === '' ? (int) $s['from'][0] : (int) $s['to'][0]];

                    continue;
                }
                if ($lastFile === null) {
                    continue;
                }
                $cites[] = ['at' => $s[0][1], 'len' => strlen($s[0][0]), 'file' => $lastFile,
                    'from' => (int) $s['bfrom'][0],
                    'to' => ($s['bto'][0] ?? '') === '' ? (int) $s['bfrom'][0] : (int) $s['bto'][0]];
            }
        }
        $pos = strpos($line, '('.$gloss);
        $best = null;
        $bestDist = PHP_INT_MAX;
        foreach ($cites as $c) {
            $d = $c['at'] > $pos ? $c['at'] - $pos : $pos - ($c['at'] + $c['len']);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $c;
            }
        }
        if ($best === null || $bestDist > 200) {
            continue;
        }
        $glossCount++;
        // A quoted call, not an English word followed by a bracket: "index on (notifiable_type, …)" is a column
        // list, and `on` is not a function. Two bytes or a stopword is not evidence of a call.
        $stop = ['on', 'in', 'at', 'by', 'of', 'to', 'or', 'and', 'is', 'as', 'it', 'so', 'no', 'not', 'the',
            'for', 'with', 'from', 'that', 'this', 'read', 'index', 'both', 'own', 'set', 'get', 'run', 'use'];
        $cands = preg_match_all('/([A-Za-z_][A-Za-z0-9_]{2,})\s*\(/', $g, $im) > 0
            ? array_values(array_diff($im[1], $stop)) : [];
        // A quoted VARIABLE is a different lookup from a quoted call: `$isFlexibleStudent` is used, not called,
        // so a pattern that demands a bracket after the name finds no occurrence and reports a live line as
        // silent. Each candidate carries the pattern that recognises it in the source.
        $patterns = [];
        foreach ($cands as $c) {
            $patterns[$c] = '/\b'.preg_quote($c, '/').'\s*[(\[{]/';
        }
        if (preg_match_all('/\$([A-Za-z_]\w*)/', $g, $vm) > 0) {
            foreach ($vm[1] as $v) {
                $patterns['$'.$v] = '/\$'.preg_quote($v, '/').'\b/';
            }
        }
        // Two shapes the bracket pattern cannot see, both of which the document really does quote: a class
        // constant or static member WITHOUT a call (`Str::headline of studentType`, `ClinicRecordStatus::Completed`,
        // `ApplicantType::class`), and a property written as an assignment (`appliesTo = firstYear | transferee`).
        // They were silently discarded as "nothing checkable", which is how 5 of 11 rows vanished from a summary
        // that printed a clean zero.
        if (preg_match_all('/([A-Z][A-Za-z0-9_]+)::([A-Za-z][A-Za-z0-9_]*)/', $g, $cmm, PREG_SET_ORDER) >= 1) {
            foreach ($cmm as $x) {
                $patterns[$x[0]] = '/'.preg_quote($x[0], '/').'/';
            }
        }
        if (preg_match_all('/\b([a-z][A-Za-z0-9_]{3,})\s*=/', $g, $am) > 0) {
            foreach ($am[1] as $a) {
                $patterns[$a] = '/\b'.preg_quote($a, '/').'\b/';
            }
        }
        if ($patterns === [] && preg_match('/->|\|\||&&|\=\>/', $g) !== 1) {
            // Counted, never swallowed. The first version of this file printed "examined: 11 / OK: 6" with an
            // empty queue, which reads as a clean document while five rows went nowhere; an instrument whose own
            // numbers do not add up cannot report a zero.
            $dropped[] = 'NO-SYMBOL      '.$best['file'].':'.$best['from'].'-'.$best['to'].'  gloss "('.$g
                .')" — code-shaped prose with no call, variable or operator left to look for';

            continue;
        }
        // A path-shaped citation is resolved directly: vendor/ and any absolute name never enters the index.
        $paths = null;
        if (str_contains($best['file'], '/') && is_file($root.'/'.$best['file'])) {
            $paths = [$best['file']];
        }
        if ($paths === null) {
            $paths = $index[$best['file']] ?? null;
        }
        if ($paths === null) {
            $soft[] = "NO-FILE      {$best['file']}:{$best['from']} — gloss '{$g}' (no such file in the tree)";

            continue;
        }
        $verb = str_replace(' ', '', $g);
        // An ellipsis in the gloss means the sentence is deliberately not quoting verbatim — `config(…
        // schoolName, default)` — so only the named symbol is weighed.
        $abbreviated = str_contains($g, '…') || str_contains($g, '...');
        $foundAny = $abbreviated;
        $inRange = false;
        $lineMate = false;
        $enclosing = null;
        $absent = array_keys($patterns);
        foreach ($paths as $rel) {
            $fileLines = $read($rel);
            if ($fileLines === null) {
                continue;
            }
            $whole = str_replace(["\r", "\n", ' '], '', implode('', $fileLines));
            if (str_contains($whole, $verb)) {
                $foundAny = true;
            }
            // The method that owns the cited lines: naming it is not an out-of-range claim, any more than
            // glossing a range with the method that encloses it is.
            $enclosing = $enclosingFunction($fileLines, $best['from']);
            foreach ($patterns as $name => $pat) {
                $hits = [];
                foreach ($fileLines as $i => $l) {
                    if (preg_match($pat, $l)) {
                        $hits[] = $i + 1;
                    }
                }
                if ($hits === []) {
                    continue;
                }
                $foundAny = true;
                $absent = array_values(array_diff($absent, [$name]));
                foreach ($hits as $h) {
                    if ($h >= $best['from'] && $h <= $best['to']) {
                        $inRange = true;
                    }
                }
                if (strcasecmp($name, (string) $enclosing) === 0
                    || strcasecmp(ltrim($name, '$'), (string) $enclosing) === 0) {
                    $inRange = true;
                }
            }
        }
        // A gloss may name a symbol the *neighbouring* citation owns: "a balance read through Payments::held()
        // ( PaymentPolicy.php:115 )" quotes the policy while the nearest citation is the controller. The symbol
        // is then accounted for on the line, and the row is not a finding.
        if (! $inRange) {
            foreach ($cites as $c) {
                if ($c['file'] === $best['file'] && $c['from'] === $best['from']) {
                    continue;
                }
                foreach (($index[$c['file']] ?? []) as $rel) {
                    $fileLines = $read($rel);
                    if ($fileLines === null) {
                        continue;
                    }
                    foreach ($patterns as $name => $pat) {
                        foreach ($fileLines as $i => $l) {
                            if (preg_match($pat, $l) === 1 && $i + 1 >= $c['from'] && $i + 1 <= $c['to']) {
                                $lineMate = true;
                            }
                        }
                    }
                }
            }
        }
        $label = $best['file'].':'.$best['from'].'-'.$best['to'].'  gloss "('.$g.')"';
        if ($absent !== []) {
            $hard[] = $label.' — no such symbol in the file: '.implode(', ', $absent);
        } elseif ($inRange || $lineMate) {
            $okCount++;
        } elseif (! $foundAny) {
            $soft[] = 'PARAPHRASE   '.$label.' — the quoted text is not verbatim in the file';
        } else {
            $soft[] = 'OUT-OF-RANGE '.$label.' — the symbol exists in the file but neither inside the cited '
                .'lines nor as the method that owns them ('.$enclosing.')';
        }
    }
}

echo "glossed citation(s) examined: {$glossCount}\n";
echo "OK: {$okCount}\n\n";
echo 'NOT-IN-FILE — the prose quotes code that does not exist ('.count($hard)."):\n";
foreach (array_unique($hard) as $h) {
    echo "  $h\n";
}
echo "\nqueue — worth re-reading, not refusals (".count(array_unique($soft))."):\n";
foreach (array_unique($soft) as $s) {
    echo "  $s\n";
}
echo "\ndropped — the gloss had nothing this tool can look up (".count(array_unique($dropped))."):\n";
foreach (array_unique($dropped) as $d) {
    echo "  $d\n";
}

// Every examined row must land in exactly one bucket. This is an internal invariant, not a claim about the
// document, and it exists because the first published summary of this tool added up to nothing: a reader who sees
// "0 flagged" needs to know whether 11 rows were judged or 6.
$bucketed = $okCount + count($hard) + count($soft) + count($dropped);
if ($bucketed !== $glossCount) {
    printf("\nREFUSED: %d gloss(es) examined but %d accounted for (%d OK, %d not-in-file, %d queue, %d dropped) —"
        ."\n       a row that goes nowhere is a lost pointer, so this run does not get to report a clean count.\n",
        $glossCount, $bucketed, $okCount, count($hard), count($soft), count($dropped));
    exit(1);
}
printf("\nthe tally adds up: %d examined = %d OK + %d not-in-file + %d queue + %d dropped.\n",
    $glossCount, $okCount, count($hard), count($soft), count($dropped));
echo "\nThis is a queue. It exits 0 by design: only NOT-IN-FILE is a statement the repository can falsify.\n";
