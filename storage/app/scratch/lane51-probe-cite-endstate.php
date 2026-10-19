<?php

// Lane 51, the end-state check. Pass 150 already moved the pointers, so measuring HEAD -> work again would
// index HEAD with a working-copy number and report garbage. This asks the question that survives a move: for
// every citation the document makes into a file this batch changed, does the range land, in the WORKING copy,
// on the code the sentence names?
//
// It is a reading queue, not a verdict: sweep-docx-citations.php owns bounds and blank lines, lane32 owns the
// construct names. What this adds is the whole cited block in front of a human, plus the bare `:84-112`
// continuations that every file-plus-name checker is blind to.
//
//   php storage/app/scratch/lane51-probe-cite-endstate.php

$root = dirname(__DIR__, 3);
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$listFile = $root.'/storage/app/scratch/lane51-changed-files.txt';

if (! is_file($dump) || ! is_file($listFile)) {
    fwrite(STDERR, "REFUSE: missing the flat dump or the changed-file list\n");

    exit(1);
}

$files = array_values(array_filter(explode(chr(10), str_replace("\r", '', trim(file_get_contents($listFile))))));
$files = array_values(array_filter($files, fn ($f) => preg_match('/\.(php|jsx)$/', $f) && ! str_starts_with($f, 'storage/app/scratch/')));

if ($files === []) {
    fwrite(STDERR, "NO CONCLUSION: the changed-file list holds nothing the document can cite\n");

    exit(1);
}

$text = str_replace("\r\n", PHP_EOL, file_get_contents($dump));
$rows = explode(chr(10), $text);

$byBase = [];

foreach ($files as $rel) {
    $byBase[basename($rel)][] = $rel;
}

// Walk backwards from a line to the nearest construct opener — enough to tell "inside settleNoBalance()" from
// "inside dailyReport()".
function enclosing(array $lines, int $at): string
{
    for ($i = $at - 1; $i >= 0 && $i >= $at - 400; $i--) {
        $l = $lines[$i] ?? '';

        if (preg_match('#\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(#', $l, $m)) {
            return $m[1].'() @ :'.($i + 1);
        }

        if (preg_match('#^(?:export\s+default\s+function|export\s+default|export\s+const|const)\s+([A-Za-z_][A-Za-z0-9_]*)#', $l, $m)) {
            return $m[1].' @ :'.($i + 1);
        }
    }

    return 'top of file';
}

function read(string $p): array
{
    return explode(chr(10), str_replace("\r\n", PHP_EOL, (string) file_get_contents($p)));
}

$named = 0;
$bare = 0;
$ambig = 0;
$pastEnd = 0;
$blank = 0;

foreach ($byBase as $base => $candidates) {
    $needle = '#(?:[A-Za-z0-9_.\-]*/)?'.preg_quote($base, '#').'\s*:\s*(\d+)(?:\s*-\s*(\d+))?#';

    foreach ($rows as $ri => $row) {
        if (! preg_match_all($needle, $row, $cites, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($cites as $c) {
            $prefix = rtrim(trim(preg_replace('#'.preg_quote($base, '#').'.*$#', '', $c[0])), '/ ');

            $rel = null;
            $contenders = [];

            foreach ($candidates as $cand) {
                // Compare against the candidate's DIRECTORY, not its whole path: a citation says `Accounting`
                // or `ID`, and the path ends with the file name, so testing the path matched nothing at all.
                $dir = str_replace('\\', '/', dirname($cand));

                if ($prefix !== '' && ! ($dir === $prefix || str_ends_with($dir, '/'.$prefix))) {
                    continue;
                }

                if ($prefix === '') {
                    $contenders[] = $cand;
                }

                if ($prefix !== '') {
                    $rel = $cand;

                    break;
                }
            }

            if ($prefix === '' && count($contenders) > 1) {
                printf("\n  AMBIGUOUS BARE NAME  %s  (flat row %d)\n", $c[0], $ri + 1);
                printf("      sentence: %s\n", mb_substr(trim(preg_replace('#\s+#', ' ', $row)), 0, 220));

                foreach ($contenders as $cand) {
                    $wl = read($root.'/'.$cand);
                    $f = (int) $c[1];
                    printf("      %-46s :%-5d %s\n", $cand, $f, isset($wl[$f - 1])
                        ? (trim($wl[$f - 1]) === '' ? '(blank)' : mb_substr(trim($wl[$f - 1]), 0, 74))
                        : 'PAST END of '.count($wl).' lines');
                }

                $ambig++;

                continue;
            }

            if ($prefix === '' && $contenders !== []) {
                $rel = $contenders[0];
            }

            if ($rel === null) {
                printf("\n  NOT A CHANGED FILE  %s — prefix %s matches none of: %s\n", $c[0], $prefix,
                    implode(', ', array_map(fn ($x) => dirname($x), $candidates)));

                continue;
            }

            $work = read($root.'/'.$rel);
            $from = (int) $c[1];
            $to = isset($c[2]) && $c[2] !== '' ? (int) $c[2] : $from;
            $named++;

            if ($to > count($work)) {
                printf("  PAST END        %s :%d-%d — the file has %d line(s)\n", $rel, $from, $to, count($work));
                $pastEnd++;

                continue;
            }

            printf("\n  %s :%d-%d   (flat row %d)\n", $rel, $from, $to, $ri + 1);
            printf("      sentence: %s\n", mb_substr(trim(preg_replace('#\s+#', ' ', $row)), 0, 220));

            for ($n = $from; $n <= min($to, $from + 7); $n++) {
                if (trim($work[$n - 1]) === '') {
                    $blank++;
                    printf("      %-5d (blank)\n", $n);

                    continue;
                }

                printf("      %-5d %s\n", $n, mb_substr(trim($work[$n - 1]), 0, 96));
            }

            printf("      opens in %s, last cited line in %s\n", enclosing($work, $from), enclosing($work, $to));

            // A bare continuation is a second citation the pattern above cannot see: `File.jsx:60-82, :84-112`.
            $tail = substr($row, (int) strpos($row, $c[0]) + strlen($c[0]));

            if (preg_match('#^\s*[,;]?\s*:\s*(\d+)(?:\s*-\s*(\d+))?#', $tail, $cont)) {
                $cf = (int) $cont[1];
                $ct = isset($cont[2]) && $cont[2] !== '' ? (int) $cont[2] : $cf;
                $bare++;
                printf("      ALSO CITED, BARE :%d-%d\n", $cf, $ct);

                for ($n = $cf; $n <= min($ct, $cf + 4); $n++) {
                    if (! isset($work[$n - 1])) {
                        printf("      %-5d PAST END of a %d-line file\n", $n, count($work));

                        break;
                    }

                    printf("      %-5d %s%s\n", $n, mb_substr(trim($work[$n - 1]), 0, 88), trim($work[$n - 1]) === '' ? '   (blank)' : '');
                }

                printf("      that range sits in %s\n", enclosing($work, $cf));
            }
        }
    }
}

printf("\n%d citation(s) attributed and read, %d ambiguous bare name(s), %d bare continuation(s), %d past end, %d blank-line landing(s).\n",
    $named, $ambig, $bare, $pastEnd, $blank);
