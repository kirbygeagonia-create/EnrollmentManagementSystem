<?php
/**
 * useForm API-misuse audit across every React page.
 *
 * WHY. The catalog bug was a call that is syntactically legal and semantically empty: reset()
 * treats each argument as a FIELD NAME, so reset({...}) matched nothing and silently did
 * nothing — invisible to ESLint, PHPStan, Pint and Vite, visible only to someone who clicked
 * Edit. The same shape has three more hiding places, all checkable against the hook's own
 * declared keys:
 *
 *   NO-OP-KEY   reset('someKey') naming a key the useForm() never declared — nothing is reset.
 *   DROP-KEY    setData({...}) whose object omits a declared key — setData REPLACES the data,
 *               so the omitted field disappears from the object and its controlled input
 *               becomes uncontrolled (the value the user typed is gone on the next render).
 *   DEAD-SUBMIT a <button type="submit" form="some-id"> with no element carrying that id in the
 *               file — clicking it submits nothing, the same silent no-op the catalog had.
 *
 * Every site prints its extracted key lists, because a checker that only prints a verdict can
 * be wrong in silence: an earlier version of this very script reported 33/33 clean while
 * storing the word "Array" as every key.
 *
 * Read-only. Nothing is written.
 */

$root = $argv[1] ?? dirname(__DIR__, 3).'/resources/js';
if (! is_dir($root)) {
    fwrite(STDERR, "not a directory: {$root}\n");
    exit(1);
}
echo "scanning {$root}\n";

/** Top-level keys of the object literal whose '{' is at $start. Returns [keys, endOffset]. */
$objectKeys = function (string $src, int $start): array {
    $depth = 0;
    $keys = [];
    $len = strlen($src);
    $expectKey = true;
    $i = $start;

    for (; $i < $len; $i++) {
        $ch = $src[$i];

        if ($ch === '"' || $ch === "'" || $ch === '`') {
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

$tally = ['files' => 0, 'hooks' => 0, 'reset' => 0, 'setData' => 0, 'setDataStr' => 0,
    'submitBtn' => 0, 'noop' => 0, 'drop' => 0, 'dead' => 0, 'phantom' => 0, 'outside' => 0];
$rows = [];

foreach ($files as $file) {
    $src = file_get_contents($file);

    // The submit-button checks must run in files that hold no useForm hook at all — a presentational
    // component like FilterBar.jsx renders its own submit button, and an earlier version of this
    // script skipped every such file, which is how it reported 14 of 15.
    if (! str_contains($src, 'useForm(') && ! str_contains($src, 'type="submit"')) {
        continue;
    }
    $declared = [];
    if (str_contains($src, 'useForm(')) {
        $tally['files']++;
    }
    $rel = str_replace(dirname(__DIR__, 3).'\\', '', $file);

    // Bind every variable to the keys its own useForm() declares (a page may hold several).
    if (preg_match_all('/const\s+([A-Za-z_][\w]*)\s*=\s*useForm\(\s*\{/', $src, $um, PREG_OFFSET_CAPTURE)) {
        foreach ($um[0] as $idx => $u) {
            $var = $um[1][$idx][0];
            [$keys] = $objectKeys($src, $u[1] + strlen($u[0]) - 1);
            $declared[$var] = $keys;
            $tally['hooks']++;
        }
    }

    $line = fn (int $off) => substr_count(substr($src, 0, $off), "\n") + 1;

    // ---- reset(...) with arguments
    if (preg_match_all('/([A-Za-z_][\w]*)\.reset\(([^)"]*)\)/', $src, $rm, PREG_OFFSET_CAPTURE)) {
        foreach ($rm[0] as $idx => $whole) {
            $args = trim($rm[2][$idx][0]);
            if ($args === '') {
                continue;                      // reset() with no args is the correct form
            }
            $var = $rm[1][$idx][0];
            $off = $rm[0][$idx][1];
            $tally['reset']++;

            if ($args[0] === '{') {
                $rows[] = "NO-OP-OBJ $rel:".$line($off)."  $var.reset({...}) — an object matches no field name\n";
                $tally['noop']++;
                continue;
            }

            preg_match_all("/'([^']*)'|\"([^\"]*)\"/", $args, $am);
            $named = array_values(array_filter(array_map(fn ($s) => trim($s), $am[1])));
            $unknown = array_diff($named, $declared[$var] ?? []);
            if ($unknown !== []) {
                $rows[] = sprintf("NO-OP-KEY %s:%d  %s.reset(%s)\n           declares=[%s]\n           asks for=[%s]  << resets nothing: %s\n",
                    $rel, $line($off), $var, implode(', ', $named),
                    implode(',', $declared[$var] ?? []), implode(',', $named), implode(',', $unknown));
                $tally['noop']++;
            }
        }
    }

    // ---- setData({...}) omitting a declared key
    if (preg_match_all('/([A-Za-z_][\w]*)\.setData\(\s*\{/', $src, $dm, PREG_OFFSET_CAPTURE)) {
        foreach ($dm[0] as $idx => $whole) {
            $var = $dm[1][$idx][0];
            $brace = $whole[1] + strlen($whole[0]) - 1;
            [$literal] = $objectKeys($src, $brace);
            $off = $whole[1];
            $tally['setData']++;

            $missing = array_values(array_diff($declared[$var] ?? [], $literal));
            if ($missing !== []) {
                $rows[] = sprintf("DROP-KEY  %s:%d  %s.setData({...}) replaces the data and omits:\n           declares(%d)=[%s]\n           literal(%d)=[%s]\n           << DROPS: %s\n",
                    $rel, $line($off), $var,
                    count($declared[$var] ?? []), implode(',', $declared[$var] ?? []),
                    count($literal), implode(',', $literal), implode(',', $missing));
                $tally['drop']++;
            }
        }
    }

    // ---- setData('key', value) naming a key the hook never declared (a typo creates a
    //      phantom field that no validation rule and no submit payload will ever carry).
    if (preg_match_all('/([A-Za-z_][\w]*)\.setData\(\s*\'([^\']+)\'/', $src, $sm, PREG_OFFSET_CAPTURE)) {
        foreach ($sm[0] as $idx => $whole) {
            $var = $sm[1][$idx][0];
            $key = $sm[2][$idx][0];
            $tally['setDataStr']++;
            if (! isset($declared[$var]) || $declared[$var] === []) {
                continue;                       // a hook we cannot resolve is reported nowhere
            }
            $root = preg_split('/[.\[]/', $key)[0];
            if (! in_array($root, $declared[$var], true)) {
                $rows[] = sprintf("PHANTOM-KEY %s:%d  %s.setData('%s', …) — declares [%s]\n",
                    $rel, $line($whole[1]), $var, $key, implode(',', $declared[$var]));
                $tally['phantom']++;
            }
        }
    }

    // ---- <button type="submit"> with no form="id": it must be nested inside the <form> it
    //      submits. A footer rendered outside its form submits nothing at all — the catalog bug.
    if (preg_match_all('/<button\b[^>]*?type="submit"[^>]*?>/s', $src, $bm, PREG_OFFSET_CAPTURE)) {
        foreach ($bm[0] as $idx => $whole) {
            if (str_contains($whole[0], 'form="')) {
                continue;                        // the association is explicit, checked above
            }
            $off = $whole[1];
            $tally['submitBtn']++;
            if (getenv('AUDIT_DEBUG')) {
                echo "DEBUG no-form button in ".basename($file)." line ".$line($off)."\n";
            }
            $before = substr($src, 0, $off);
            $open = substr_count($before, '<form');
            $close = substr_count($before, '</form>');
            if ($open === $close) {
                $rows[] = sprintf("OUTSIDE-FORM %s:%d  %s — no form=\"id\" and no open <form> encloses it (%d open, %d closed before it)\n",
                    $rel, $line($off), trim(preg_replace('/\s+/', ' ', substr($whole[0], 0, 90))), $open, $close);
                $tally['outside']++;
            }
        }
    }

    // ---- <button type="submit" form="id"> whose id is not defined in the same file
    if (preg_match_all('/<button\b[^>]*form="([^"]+)"[^>]*>/s', $src, $fm, PREG_OFFSET_CAPTURE)) {
        foreach ($fm[1] as $idx => $idm) {
            $off = $fm[0][$idx][1];
            if (! preg_match('/<form\b[^>]*id="'.preg_quote($idm[0], '/').'"/s', $src)
                && ! preg_match('/id="'.preg_quote($idm[0], '/').'"/', $src)) {
                $rows[] = sprintf("DEAD-SUBMIT %s:%d  button form=\"%s\" — no element in this file carries that id\n",
                    $rel, $line($off), $idm[0]);
                $tally['dead']++;
            }
        }
    }
}

echo implode('', $rows === [] ? ['(no findings)'] : $rows)."\n";
printf("scanned: files=%d useForm-hooks=%d reset-with-args=%d setData-object=%d setData-string=%d submit-buttons=%d\n",
    $tally['files'], $tally['hooks'], $tally['reset'], $tally['setData'], $tally['setDataStr'], $tally['submitBtn']);
printf("findings: NO-OP=%d  DROP-KEY=%d  PHANTOM-KEY=%d  DEAD-SUBMIT=%d  OUTSIDE-FORM=%d\n",
    $tally['noop'], $tally['drop'], $tally['phantom'], $tally['dead'], $tally['outside']);
