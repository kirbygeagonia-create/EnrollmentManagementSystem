<?php

/**
 * Lane 48 — strip the numeric default out of every tile value.
 *
 * `value={stats.total ?? 0}` renders a zero for a number the handler did not send, and once the shared tile has
 * an absence state the default is what stands between the desk and the truth. This edits only inside a
 * `<StatCard …>` opening tag's `value={…}` expression — never the arithmetic a page does for its own totals —
 * and prints what it changed per file, because a bulk edit that silently skips a screen leaves six tiles where
 * the reader cannot tell which one still lies.
 */

$root = dirname(__DIR__, 3);

/** The `{…}` group starting at `$from`, quotes honoured. */
$braceGroup = function (string $s, int $from): ?string {
    $len = strlen($s);
    $depth = 0;
    $quote = null;
    for ($i = $from; $i < $len; $i++) {
        $c = $s[$i];
        if ($quote !== null) {
            if ($c === $quote && $s[$i - 1] !== '\\') {
                $quote = null;
            }

            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            $quote = $c;

            continue;
        }
        if ($c === '{') {
            $depth++;

            continue;
        }
        if ($c === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($s, $from, $i - $from + 1);
            }
        }
    }

    return null;
};

/** Replace `?? 0` / `|| 0` inside one value expression, leaving every other operator alone. */
$strip = function (string $expr): ?string {
    $out = preg_replace('/\s*(?:\?\?|\|\|)\s*0(?![\w.])/', '', $expr);
    $out = preg_replace('/\s+/', ' ', $out);

    return $out === $expr ? null : trim($out);
};

$total = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js/Pages'));
foreach ($it as $f) {
    if ($f->isDir() || ! str_ends_with($f->getFilename(), '.jsx')) {
        continue;
    }
    $src = (string) file_get_contents($f->getPathname());
    if (! str_contains($src, '<StatCard')) {
        continue;
    }
    $changed = [];
    $out = $src;
    $at = 0;
    while (($at = strpos($out, '<StatCard', $at)) !== false) {
        $valueAt = strpos($out, 'value=', $at);
        if ($valueAt === false || strpos($out, '>', $valueAt) === false) {
            break;
        }
        // a `value=` belonging to a different tag inside this one (a modal's input) must not be touched: stop at
        // the `>` that closes this tag, brace-depth aware
        $depth = 0;
        $tagEnd = null;
        for ($i = $at; $i < strlen($out); $i++) {
            $c = $out[$i];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
            } elseif ($c === '>' && $depth === 0) {
                $tagEnd = $i;

                break;
            }
        }
        if ($tagEnd === null || $valueAt > $tagEnd) {
            $at += 9;

            continue;
        }
        if ($out[$valueAt + 6] !== '{') {
            $at = $tagEnd + 1;

            continue;
        }
        $group = $braceGroup($out, $valueAt + 6);
        if ($group === null) {
            break;
        }
        $inner = mb_substr($group, 1, -1);
        $new = $strip($inner);
        if ($new !== null) {
            $out = substr_replace($out, '{'.$new.'}', $valueAt + 6, strlen($group));
            $changed[] = $oneLine = trim(preg_replace('/\s+/', ' ', $inner)).'  ->  '.$new;
            $total++;
        }
        $at = $valueAt + 6 + strlen($group);
    }
    if ($changed !== []) {
        file_put_contents($f->getPathname(), $out);
        printf("%s\n", str_replace($root.'/resources/js/', '', $f->getPathname()));
        foreach ($changed as $c) {
            echo '    '.$c."\n";
        }
    }
}

printf("\n%d tile value expression(s) stripped of a numeric default.\n", $total);
if ($total === 0) {
    fwrite(STDERR, "REFUSED: nothing was changed — either the defaults are already gone or this script reads the wrong construct.\n");
    exit(1);
}
