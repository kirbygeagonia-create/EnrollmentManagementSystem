<?php

// Lane 51: did this batch's docx pass move every citation into a file it changed — and only by the amount the
// file actually moved?
//
// Arithmetic on the current document alone cannot answer that. A pointer one row short still lands on a live
// line, so the bounds sweep clears it; and a per-citation test cannot see a crossover either — HEAD cited the OR
// rule at :186 and the amount rule at :187, both moved by one, so ":187" is on the page twice and is right
// twice over. Only the whole set for a file tells the truth, joined against the document as it stood in HEAD
// (whose numbers were correct against HEAD's code) and `git diff -U0` (which says where the lines went).
//
// Stage 1 compares the MULTISET of cited ranges per file, counting values rather than trusting positions, and
// only against rows whose words still match a HEAD row — so a citation inside a row this batch newly wrote is
// reported as newly written instead of being mistaken for a pointer left behind.
//
// Stage 2 pairs each row with its HEAD twin by the words around its numbers and asks how every bare
// continuation moved (`:84-112` names no file, so no file-plus-number checker sees it at all). A bare number
// that stayed put is a question, not a defect: `receivedDate (:83-85; …)` is a Blade line, and the Blade did
// not move.
//
//   git diff -U0 HEAD > storage/app/scratch/lane51-diff.txt
//   git show HEAD:"Documentation/EMS Complete Documentation.docx" > storage/app/scratch/lane51-head/docx-head.docx
//   php storage/app/scratch/lane51-dump-any-flat.php storage/app/scratch/lane51-head/docx-head.docx storage/app/scratch/lane51-docx-flat-head.txt
//   php storage/app/scratch/lane51-reconcile-cites.php
//
// --self-test compares HEAD's document with itself: nothing can have moved, so the tool must complain. A run
// that stays quiet has read nothing.

$selfTest = in_array('--self-test', $argv, true);

// A mutant copy is measured through this flag; a flag that is accepted but ignored makes a planted defect
// look like a clean run, which is the failure mode this lane has been caught by twice.
$dumpOverride = null;

foreach ($argv as $arg) {
    if (str_starts_with((string) $arg, '--dump=')) {
        $dumpOverride = substr((string) $arg, 7);
    }
}

if ($dumpOverride !== null && $selfTest) {
    fwrite(STDERR, "REFUSE: --dump= and --self-test together would compare a mutant with itself\n");

    exit(1);
}
$root = dirname(__DIR__, 3);
if ($dumpOverride !== null && ! is_file($dumpOverride)) {
    fwrite(STDERR, "REFUSE: --dump= points at nothing: $dumpOverride\n");

    exit(1);
}

$newDumpPath = $dumpOverride ?? $root.'/storage/app/scratch/docx-flat.txt';
$oldDumpPath = $root.'/storage/app/scratch/lane51-docx-flat-head.txt';
$diffPath = $root.'/storage/app/scratch/lane51-diff.txt';
$headDir = $root.'/storage/app/scratch/lane50-head';

foreach (['old dump' => $oldDumpPath, 'new dump' => $newDumpPath, 'diff' => $diffPath, 'HEAD copies' => $headDir] as $what => $p) {
    if (! file_exists($p)) {
        fwrite(STDERR, "REFUSE: missing the $what at $p\n");

        exit(1);
    }
}

$linesOf = function (string $p): array {
    return explode(chr(10), str_replace("\r\n", chr(10), (string) file_get_contents($p)));
};

// ---- stage 0: the line map, from the diff's own hunk headers -----------------------

$maps = [];
$file = null;
$hunks = [];

$flush = function () use (&$file, &$hunks, &$maps, $root, $linesOf, $headDir): void {
    if ($file === null || $hunks === []) {
        return;
    }

    // A file the batch CREATED has no HEAD lines to map, and inventing a row for every working line would let
    // a citation be confirmed against nothing. A file that merely gained lines at its top is the other case: its
    // own -0,0 hunk is a real insertion and every citation below it moves.
    if (is_file($root.'/'.$file) && is_file($headDir.'/'.str_replace('/', '__', $file))) {
        usort($hunks, fn ($a, $b) => $a['a'] <=> $b['a']);
        $map = [];
        $head = 1;
        $w = 1;

        foreach ($hunks as $h) {
            // With -U0, `-14,0` means the insertion sits AFTER HEAD line 14, so the first line the hunk
            // consumes is 15; a replacement (`-288`) consumes its own start.
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

        if ($map !== []) {
            $maps[$file] = $map;
        }
    }

    $hunks = [];
};

foreach ($linesOf($diffPath) as $row) {
    if (preg_match('#^\+\+\+ b/(.+)$#', trim($row), $m)) {
        $flush();
        $file = preg_match('/\.(php|jsx|js)$/', $m[1]) && ! str_starts_with($m[1], 'storage/app/scratch/') ? $m[1] : null;

        continue;
    }

    if ($file !== null && preg_match('#^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@#', $row, $m)) {
        $hunks[] = [
            'a' => (int) $m[1],
            'b' => isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 1,
            'c' => (int) $m[3],
            'd' => isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 1,
        ];
    }
}

$flush();

if ($maps === []) {
    fwrite(STDERR, "REFUSE: the diff holds no citable file — nothing was measured\n");

    exit(1);
}

// Resolve the name the document used to the file it means. A citation may carry the whole repo path, one parent
// directory, or nothing — and several changed files are named Show.jsx, so a bare name that fits more than one
// is refused rather than guessed.
$resolve = function (string $token) use ($maps) {
    $hits = array_values(array_filter(array_keys($maps), fn ($c) => $c === $token || str_ends_with($c, '/'.$token)));

    if (count($hits) === 1) {
        return $hits[0];
    }

    if (count($hits) > 1) {
        return false;
    }

    $byName = str_contains($token, '/') ? [] : array_values(array_filter(array_keys($maps), fn ($c) => basename($c) === $token));

    if (count($byName) === 1) {
        return $byName[0];
    }

    return count($byName) > 1 ? false : null;
};

// ---- read a document: citations tagged with the row that carries them --------------

$QUAL = '(?P<name>(?:[A-Za-z0-9_.\-]+[\/])*[A-Za-z0-9_.\-]+\.(?:php|jsx|js))\s*:\s*(?P<from>\d+)(?:\s*-\s*(?P<to>\d+))?';
$BARE = ':(?P<bfrom>\d+)(?:\s*-\s*(?P<bto>\d+))?';

$read = function (string $p) use ($linesOf, $QUAL, $BARE): array {
    $qual = [];
    $bare = [];
    $masks = [];

    foreach ($linesOf($p) as $row) {
        if (! preg_match_all('#'.$QUAL.'|'.$BARE.'#', $row, $ms, PREG_SET_ORDER)) {
            continue;
        }

        $mask = preg_replace('#\d+#', '#', $row);
        $masks[$mask] = true;
        $lastName = null;

        foreach ($ms as $m) {
            if (($m['name'] ?? '') !== '') {
                $lastName = $m['name'];
                $to = isset($m['to']) && $m['to'] !== '' ? (int) $m['to'] : (int) $m['from'];
                $qual[$lastName][] = [(int) $m['from'], $to, $mask];

                continue;
            }

            $bare[$mask][] = [$lastName, (int) $m['bfrom'], isset($m['bto']) && $m['bto'] !== '' ? (int) $m['bto'] : (int) $m['bfrom']];
        }
    }

    return ['qual' => $qual, 'bare' => $bare, 'masks' => array_keys($masks)];
};

$oldDoc = $read($oldDumpPath);
$newDoc = $read($selfTest ? $oldDumpPath : $newDumpPath);

if ($oldDoc['qual'] === []) {
    fwrite(STDERR, "REFUSE: HEAD's document holds no file-plus-line citation at all\n");

    exit(1);
}

$oldMasks = array_flip($oldDoc['masks']);
$freq = fn (array $list): array => array_count_values($list);

$problems = 0;
$queue = 0;
$settled = 0;
$newlyWritten = 0;

// ---- stage 1: per-file multisets ---------------------------------------------------

foreach ($oldDoc['qual'] as $base => $ranges) {
    $rel = $resolve($base);

    if ($rel === null) {
        continue;
    }

    if ($rel === false) {
        $share = count(array_filter(array_keys($maps), fn ($c) => basename($c) === $base));
        printf("  AMBIGUOUS NAME  %-40s %d range(s) — %d changed files share this bare name, so no set is judged\n", $base, count($ranges), $share);
        $queue++;

        continue;
    }

    $map = $maps[$rel];
    $predicted = [];
    $paired = [];
    $fresh = [];
    $unmapped = 0;

    foreach ($ranges as [$s, $e]) {
        if (! isset($map[$s], $map[$e])) {
            $unmapped++;

            continue;
        }

        $predicted[] = $map[$s].'-'.$map[$e];
    }

    foreach ($newDoc['qual'][$base] ?? [] as [$s, $e, $mask]) {
        if (isset($oldMasks[$mask])) {
            $paired[] = $s.'-'.$e;
        } else {
            $fresh[] = $s.'-'.$e;
        }
    }

    $fp = $freq($predicted);
    $fa = $freq($paired);
    $missing = [];
    $extra = [];

    // Values are counted, never matched by position: two ranges can carry the same number, and one citation can
    // land on it while another moves off it.
    foreach ($fp as $v => $n) {
        $d = $n - ($fa[$v] ?? 0);

        if ($d > 0) {
            $missing = array_merge($missing, array_fill(0, $d, $v));
        }
    }

    foreach ($fa as $v => $n) {
        $d = $n - ($fp[$v] ?? 0);

        if ($d > 0) {
            $extra = array_merge($extra, array_fill(0, $d, $v));
        }
    }

    $newlyWritten += count($fresh);

    if ($missing === [] && $extra === [] && $unmapped === 0) {
        $settled++;

        continue;
    }

    $problems++;
    printf("  SET %s  %-44s (%s)\n", $selfTest ? 'NOT RECONCILED' : 'MISMATCH', $base, $rel);

    foreach ($missing as $m) {
        printf("      the move predicts :%s and no matching row holds it\n", $m);
    }

    foreach ($extra as $e) {
        printf("      a HEAD row holds :%s which no HEAD citation predicts — left behind, or rewritten in place\n", $e);
        $queue++;
    }

    if ($unmapped > 0) {
        printf("      %d range(s) fell outside the line map\n", $unmapped);
        $queue++;
    }
}

// ---- stage 2: bare continuations, paired by the words around them ------------------

foreach ($oldDoc['bare'] as $mask => $tokens) {
    if (! isset($oldMasks[$mask])) {
        continue;
    }

    $current = $newDoc['bare'][$mask] ?? null;

    if ($current === null) {
        continue;
    }

    if (count($current) !== count($tokens)) {
        printf("  BARE COUNT      a row whose words are unchanged holds %d bare number(s), was %d — %s\n",
            count($current), count($tokens), mb_substr($mask, 0, 90));
        $problems++;

        continue;
    }

    foreach ($tokens as $i => [$tok, $from, $to]) {
        [$ctok, $cfrom, $cto] = $current[$i];

        if ($tok === null || $ctok !== $tok) {
            continue;
        }

        $rel = $resolve($tok);

        if (! is_string($rel) || ! isset($maps[$rel][$from])) {
            continue;
        }

        $expect = $maps[$rel][$from] - $from;
        $delta = $cfrom - $from;

        if ($delta === $expect && $cto - $cfrom === $to - $from) {
            continue;
        }

        if ($delta === 0) {
            printf("  BARE UNMOVED?   %-26s :%d-%d stayed while %s moved %+d — is this number another file's?\n",
                $tok, $from, $to, basename($rel), $expect);
            $queue++;

            continue;
        }

        printf("  BARE WRONG      %-26s :%d-%d moved %+d while the file moved %+d\n", $tok, $from, $to, $delta, $expect);
        $problems++;
    }
}

printf("\n%d file(s) carry a line map; %d set(s) broken; %d settled; %d citation(s) inside rows this batch newly wrote; %d item(s) in the reading queue.\n",
    count($maps), $problems, $settled, $newlyWritten, $queue);

if ($selfTest) {
    echo $problems + $queue > 0 ? "self-test ok: it reads, and it can complain\n" : "SELF-TEST FAILED: it found nothing\n";

    exit($problems + $queue > 0 ? 0 : 1);
}

exit($problems > 0 ? 1 : ($queue > 0 ? 2 : 0));
