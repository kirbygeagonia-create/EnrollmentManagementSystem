<?php

// Lane 51, pass 2 of the cite-shift measurement. The first version accepted the FIRST line offset that made a
// cited range byte-identical, scanning -14..+14 — so a one-line citation landed on whichever `});` happened to
// sit 12 rows earlier and reported a confident, wrong move.
//
// This version matches the whole cited BLOCK and refuses to choose unless exactly one place in the working copy
// holds it. Every citation lands in one of three queues:
//
//   MOVED      one match, a whole block, so the move is proven by content rather than arithmetic
//   AMBIGUOUS  more than one match — the block is short and repeats, so no shift may be written without reading
//   CHANGED    the block is gone — this batch edited the very lines the document quotes, so the prose is re-read
//
//   git diff --name-only > storage/app/scratch/lane51-changed-files.txt
//   php storage/app/scratch/lane51-probe-cite-shift3.php

$root = dirname(__DIR__, 3);
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$listFile = $root.'/storage/app/scratch/lane51-changed-files.txt';
$headDir = $root.'/storage/app/scratch/lane50-head';

if (! is_file($dump) || ! is_file($listFile)) {
    fwrite(STDERR, "REFUSE: missing the flat dump or the changed-file list\n");

    exit(1);
}

// chr(10), never PHP_EOL: on this box that is "\r\n" and the carriage returns are stripped above, so splitting
// on it would hand back one giant "filename".
$files = array_values(array_filter(explode(chr(10), str_replace("\r", '', trim(file_get_contents($listFile))))));
$files = array_values(array_filter($files, fn ($f) => preg_match('/\.(php|jsx)$/', $f) && ! str_starts_with($f, 'storage/app/scratch/')));

if ($files === []) {
    fwrite(STDERR, "NO CONCLUSION: the changed-file list holds nothing the document can cite\n");

    exit(1);
}

$text = str_replace("\r\n", PHP_EOL, file_get_contents($dump));
$lines = explode(chr(10), $text);

$moved = 0;
$ambiguous = 0;
$changed = 0;
$missingHead = 0;

foreach ($files as $rel) {
    $headPath = $headDir.'/'.str_replace('/', '__', $rel);

    if (! is_file($headPath)) {
        printf("  NO HEAD COPY   %s — export it before trusting a verdict about this file\n", $rel);
        $missingHead++;

        continue;
    }

    $head = explode(chr(10), str_replace("\r\n", PHP_EOL, file_get_contents($headPath)));
    $work = explode(chr(10), str_replace("\r\n", PHP_EOL, file_get_contents($root.'/'.$rel)));

    // The bare file name collides across pages (Show.jsx is seven files), so the parent directory is part of
    // the needle.
    $base = basename(dirname($rel)).'/'.basename($rel);
    $needle = '/(?:.*[\/])?'.preg_quote($base, '/').'\s*:\s*(\d+)(?:\s*-\s*(\d+))?/';

    $seen = [];

    foreach ($lines as $li => $row) {
        if (! preg_match_all($needle, $row, $cites, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($cites as $c) {
            $from = (int) $c[1];
            $to = isset($c[2]) && $c[2] !== '' ? (int) $c[2] : $from;
            // Parent directory included: five of the changed files are named Show.jsx, and a key without it
            // would let the first one silence the other four.
            $key = $base.':'.$from.'-'.$to;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $block = [];

            for ($n = $from; $n <= $to; $n++) {
                if (! isset($head[$n - 1])) {
                    $block = null;

                    break;
                }

                $block[] = $head[$n - 1];
            }

            if ($block === null) {
                printf("  PAST END       %s — HEAD has no line %d\n", $key, $to);
                $changed++;

                continue;
            }

            $join = implode(chr(10), $block);
            $hay = implode(chr(10), $work);
            $hits = [];
            $at = 0;

            while (($pos = strpos($hay, $join, $at)) !== false) {
                // A hit only counts when it starts a line, or the match is at the very start of the file.
                if ($pos === 0 || $hay[$pos - 1] === "\n") {
                    $hits[] = 1 + substr_count($hay, "\n", 0, $pos);
                }

                $at = $pos + 1;
            }

            if (count($hits) === 0) {
                printf("  CHANGED        %s  (%s) — this batch edited the quoted lines; re-read the sentence\n", $key, mb_substr(trim($block[0]), 0, 60));
                $changed++;

                continue;
            }

            $shifts = array_values(array_unique(array_map(fn ($h) => $h - $from, $hits)));

            if (count($shifts) > 1) {
                printf("  AMBIGUOUS      %s — the block also lives at %s; no shift may be written without reading\n",
                    $key,
                    implode(', ', array_map(fn ($s) => 'offset '.($s >= 0 ? '+' : '').$s, $shifts)));
                $ambiguous++;

                continue;
            }

            if ($shifts[0] === 0) {
                $moved++;

                continue;
            }

            printf("  MOVED          %s -> %d-%d (%s%d)\n", $key, $from + $shifts[0], $to + $shifts[0], $shifts[0] > 0 ? '+' : '', $shifts[0]);
            $moved++;
        }
    }
}

printf("\n%d moved-or-still-right, %d ambiguous, %d changed, %d file(s) with no HEAD copy.\n", $moved, $ambiguous, $changed, $missingHead);

if ($ambiguous > 0 || $changed > 0 || $missingHead > 0) {
    fwrite(STDERR, "REFUSE: an unproven shift is not a shift, and an edited quotation must be re-read\n");

    exit(1);
}

echo "every citation into a changed file is proven by its own bytes\n";
