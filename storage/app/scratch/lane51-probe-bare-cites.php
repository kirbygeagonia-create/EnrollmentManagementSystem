<?php

// A `File.php:NN` checker cannot see `:273` written on its own, and the document uses that spelling constantly:
// "( AccountingController.php:244-281 , the signature at :273 )". When a batch moves lines in a cited file, the
// qualified number moves and the bare one does not — so the sentence ends up naming two places in the same
// method, one of them a row too early.
//
// This lists every bare `:NN` / `:NN-MM` token together with the qualified citation BEFORE it in the same row,
// and shows what the number meant in HEAD against what it means in the working copy. It attributes nothing:
// a bare number can belong to a DIFFERENT file than its neighbour (the slip paragraph points at the Blade
// template and then at PrintSlip.jsx), so each line is for reading, and only a needle that includes the words
// around the number is safe to edit.
//
//   php storage/app/scratch/lane51-probe-bare-cites.php

$root = dirname(__DIR__, 3);
$headDump = $root.'/storage/app/scratch/lane51-docx-flat-head.txt';
$newDump = $root.'/storage/app/scratch/docx-flat.txt';
$diffPath = $root.'/storage/app/scratch/lane51-diff.txt';

foreach ([$headDump, $newDump, $diffPath] as $p) {
    if (! is_file($p)) {
        fwrite(STDERR, "REFUSE: missing $p\n");

        exit(1);
    }
}

$linesOf = function (string $p): array {
    return explode(chr(10), str_replace("\r\n", chr(10), (string) file_get_contents($p)));
};

// ---- line map per changed file, from the diff's own hunk headers -------------------

$maps = [];
$file = null;
$hunks = [];

$flush = function () use (&$file, &$hunks, &$maps, $root, $linesOf): void {
    if ($file === null || $hunks === []) {
        return;
    }

    if (is_file($root.'/'.$file)) {
        usort($hunks, fn ($a, $b) => $a['a'] <=> $b['a']);
        $map = [];
        $head = 1;
        $w = 1;

        foreach ($hunks as $h) {
            $start = $h['b'] === 0 ? $h['a'] + 1 : $h['a'];

            while ($head < $start) {
                $map[$head++] = $w++;
            }

            for ($i = 0; $i < $h['b']; $i++) {
                $map[$head++] = $w;
            }

            $w += $h['d'];
        }

        $limit = count($linesOf($root.'/'.$file)) + 60;

        while ($head <= $limit) {
            $map[$head++] = $w++;
        }

        $maps[$file] = $map;
    }

    $hunks = [];
};

foreach ($linesOf($diffPath) as $row) {
    if (preg_match('#^\+\+\+ b/(.+)$#', trim($row), $m)) {
        $flush();
        $file = preg_match('/\.(php|jsx)$/', $m[1]) && ! str_starts_with($m[1], 'storage/app/scratch/') ? $m[1] : null;

        continue;
    }

    if ($file !== null && preg_match('#^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@#', $row, $m)) {
        $hunks[] = ['a' => (int) $m[1], 'b' => isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 1, 'c' => (int) $m[3], 'd' => isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 1];
    }
}

$flush();

$byBase = [];

foreach ($maps as $rel => $map) {
    $byBase[basename($rel)][] = $rel;
}

// ---- walk the current document, remembering the last qualified citation per row -----

$total = 0;
$stale = 0;

foreach ($linesOf($newDump) as $ri => $row) {
    if (! preg_match_all('#([A-Za-z0-9_.\-]+\.(?:php|jsx|js))\s*:\s*(\d+)(?:-(\d+))?|:(\d+)(?:-(\d+))?#', $row, $ms, PREG_SET_ORDER)) {
        continue;
    }

    $lastFile = null;

    foreach ($ms as $m) {
        if (($m[1] ?? '') !== '') {
            $lastFile = $m[1];

            continue;
        }

        $bare = (int) $m[4];
        $bareEnd = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : $bare;

        if ($lastFile === null) {
            printf("row %-5d :%-6d no qualified citation before it in this row — %s\n", $ri + 1, $bare,
                mb_substr(trim($row), 0, 110));
            $total++;

            continue;
        }

        // Five changed files are named Show.jsx, so a bare number beside a bare `Show.jsx:NN` cannot be
        // attributed to one of them by name alone; it is reported, not guessed.
        $cands = $byBase[$lastFile] ?? [];
        $rel = count($cands) === 1 ? $cands[0] : null;

        if (count($cands) > 1) {
            printf("row %-5d :%-6d beside an ambiguous name %s (%d changed files) — not attributed\n", $ri + 1, $bare, $lastFile, count($cands));
            $total++;

            continue;
        }

        if ($rel === null) {
            continue;
        }

        $map = $maps[$rel];
        $shift = ($map[$bare] ?? $bare) - $bare;
        $total++;

        if ($shift === 0) {
            continue;
        }

        $work = $linesOf($root.'/'.$rel);
        $head = $linesOf($root.'/storage/app/scratch/lane50-head/'.str_replace('/', '__', $rel));

        printf("row %-5d %-26s :%-6d MOVES to :%-6d   (last qualified: %s)\n", $ri + 1, $lastFile, $bare, $bare + $shift, $lastFile);
        printf("      head :%-4d %s\n", $bare, mb_substr(trim($head[$bare - 1] ?? 'PAST END'), 0, 88));
        printf("      work :%-4d %s\n", $bare + $shift, mb_substr(trim($work[$bare + $shift - 1] ?? 'PAST END'), 0, 88));
        printf("      now  :%-4d %s\n", $bare, mb_substr(trim($work[$bare - 1] ?? 'PAST END'), 0, 88));
        $stale++;
    }
}

printf("\n%d bare token(s) examined beside a citation into a changed file; %d sit where the file has moved.\n", $total, $stale);
