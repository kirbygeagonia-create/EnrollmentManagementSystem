<?php
/**
 * Audit every form.reset({...}) site before changing the method name to setData.
 *
 * WHY THIS EXISTS BEFORE THE FIX. reset(object) is a silent no-op — that is the bug. The smallest
 * repair changes only the method name, which preserves every line number, and the authority
 * document cites line ranges INSIDE these components. But setData(OBJECT) replaces the whole data
 * object while reset left it untouched, so a literal that omits any key the useForm() hook declared
 * silently drops that field from the form and turns a controlled input into an uncontrolled one.
 * Every site's literal is therefore compared against its file's useForm keys.
 *
 * A previous version of this script reported every site as clean because it read
 * preg_match_all groups without PREG_SET_ORDER and stored the word "Array" as the key — which is
 * how a check that cannot fail checks nothing. The extracted key lists are printed here so the
 * result can be read, not merely believed.
 *
 * Read-only. Nothing is written.
 */

$root = dirname(__DIR__, 3).'/resources/js';

/** Top-level keys of the object literal whose '{' is at $start; returns [keys, endOffset]. */
$objectKeys = function (string $src, int $start): array {
    $depth = 0;
    $keys = [];
    $len = strlen($src);
    $expectKey = true;
    $i = $start;

    for (; $i < $len; $i++) {
        $ch = $src[$i];

        if ($ch === '"' || $ch === "'" || $ch === '`') {          // skip string literals
            $q = $ch;
            $i++;
            while ($i < $len && $src[$i] !== $q) {
                $i += ($src[$i] === '\\') ? 2 : 1;
            }
            continue;
        }

        if ($ch === '{' || $ch === '[' || $ch === '(') {
            $depth++;
            continue;
        }
        if ($ch === '}' || $ch === ']' || $ch === ')') {
            $depth--;
            if ($depth === 0) {
                return [$keys, $i];
            }
            continue;
        }

        if ($depth === 1 && $expectKey && preg_match('/\G([A-Za-z_][\w]*)\s*:/', $src, $m, 0, $i)) {
            $keys[] = $m[1];
            $i += strlen($m[0]) - 1;
            $expectKey = false;
            continue;
        }
        if ($depth === 1 && ($ch === ',' || $ch === "\n")) {
            $expectKey = true;
        }
    }

    return [$keys, $start];
};

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $files[] = $f->getPathname();
    }
}
sort($files);

$sites = 0;
$clean = 0;
$problem = 0;
$unbound = 0;
$perFile = [];

foreach ($files as $file) {
    $src = file_get_contents($file);

    // Any variable's reset with an object literal: $editForm.reset({...}), form.reset({...}).
    if (! preg_match_all('/([A-Za-z_][\w]*)\.reset\(\s*\{/', $src, $hits, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    // Every useForm in the file, bound to the variable that received it — a page can hold
    // several (Clinic/Show has a form per action), and comparing a site against the first
    // one only would pass a site that drops fields from the second.
    $declared = [];
    if (preg_match_all('/const\s+([A-Za-z_][\w]*)\s*=\s*useForm\(\s*\{/', $src, $um, PREG_OFFSET_CAPTURE)) {
        foreach ($um[0] as $idx => $u) {
            $var = $um[1][$idx][0];
            [$keys] = $objectKeys($src, $u[1] + strlen($u[0]) - 1);
            $declared[$var] = $keys;
        }
    }

    $rel = str_replace(dirname(__DIR__, 3).'\\', '', $file);

    foreach ($hits[0] as $idx => $hit) {
        $sites++;
        $var = $hits[1][$idx][0];
        $brace = $hit[1] + strlen($hit[0]) - 1;
        [$literalKeys] = $objectKeys($src, $brace);
        $line = substr_count(substr($src, 0, $hit[1]), "\n") + 1;
        $useFormKeys = $declared[$var] ?? null;

        $before = substr($src, max(0, $hit[1] - 1200), min(1200, $hit[1]));
        preg_match_all('/const (open\w+|handle\w+|\w*Modal\w*)\s*=/', $before, $hm);
        $handler = $hm[1] ? end($hm[1]) : '(?)';

        if ($useFormKeys === null) {
            $unbound++;
            $flag = 'VAR? ';
            $missing = [];
        } else {
            $missing = array_values(array_diff($useFormKeys, $literalKeys));
            $flag = $missing === [] ? 'ok   ' : 'DROP ';
            $missing === [] ? $clean++ : $problem++;
        }
        $perFile[$rel] = ($perFile[$rel] ?? 0) + 1;

        printf("%s %-56s:%-4d %-16s %-14s useForm(%s)=[%s]\n%50s literal(%d)=[%s]%s\n",
            $flag, $rel, $line, $var.'->', $handler,
            $useFormKeys === null ? '?' : count($useFormKeys),
            $useFormKeys === null ? 'no matching useForm' : implode(',', $useFormKeys),
            '', count($literalKeys), implode(',', $literalKeys),
            $missing === [] ? '' : '   << MISSING FROM LITERAL: '.implode(',', $missing));
    }
}

echo "\n";
foreach ($perFile as $f => $n) {
    printf("  %-60s %d site(s)\n", $f, $n);
}
printf("\nfiles=%d sites=%d clean=%d would-drop-a-field=%d unbound-var=%d\n",
    count($perFile), $sites, $clean, $problem, $unbound);

