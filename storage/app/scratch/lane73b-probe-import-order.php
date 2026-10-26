<?php

/**
 * Read-only: every PHP file whose import sits BELOW the first line of code that uses its short name.
 *
 * PHP resolves an unqualified class name at compile time, in statement order, so `use …\Kernel;` written after
 * `$app->make(Kernel::class)` does not apply to that call: the name resolves to global `\Kernel`, the container
 * cannot build it, and the script dies with "Target class [Kernel] does not exist". The file still parses, and
 * `pint --test` calls it clean, because the formatter is indifferent to the order it created — Pint's own
 * `fully_qualified_strict_types` rule shortens the call and files the import below it. That is lane 65's trap, met
 * a third time on 2026-10-26 by `lane73-packet-evidence.php`, a file named in a gate bullet and therefore
 * *formatted* since it broke but never *run*.
 *
 * It is a tokenizer walk, not a line grep, and that is the whole design: the first draft matched on raw lines and
 * answered 16 files, of which 15 were the tool's own reading — a docblock saying `ServeFile::hasValidSignature()`
 * is prose about a name, not a use of it, and this very file's comments quote the names they explain. Only a
 * parser can tell "a name appears" from "a name is resolved".
 *
 *   php storage/app/scratch/lane73b-probe-import-order.php            # every scratch + tools file
 *   php storage/app/scratch/lane73b-probe-import-order.php --ci       # exit 1 when a file is found
 *   php storage/app/scratch/lane73b-probe-import-order.php --file=path/to.php   # one file, for a positive control
 */
$root = dirname(__DIR__, 3);
$ci = in_array('--ci', $argv, true);

// Flags the tool does not read are refused by name. An accepted-and-inert flag is how a mutating run looks clean:
// the caller believes it scoped the walk, and the walk never saw the scope.
$unknown = [];
foreach (array_slice($argv, 1) as $arg) {
    if (is_string($arg) && str_starts_with($arg, '--') && $arg !== '--ci' && ! str_starts_with($arg, '--file=')) {
        $unknown[] = preg_replace('/=.*$/s', '=', $arg);
    }
}
if ($unknown !== []) {
    fwrite(STDERR, sprintf('REFUSED: %s %s not a flag this tool reads (known: --ci, --file=).\n',
        implode(', ', array_unique($unknown)), count($unknown) === 1 ? 'is' : 'are'));
    exit(1);
}

$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $only = substr($arg, strlen('--file='));
    }
}

if ($only === null) {
    $files = [];
    foreach ([$root.'/storage/app/scratch', $root.'/tools'] as $dir) {
        $files = array_merge($files, glob($dir.'/*.php') ?: []);
    }
} else {
    $files = [is_file($only) ? $only : $root.'/'.$only];
    if (! is_file($files[0])) {
        // A refusal must carry a status: `exit("text")` returns 0, so this refusal would have reported success to CI.
        fwrite(STDERR, "REFUSED: --file= names no file I can read: {$files[0]}\n");
        exit(1);
    }
}

if ($files === []) {
    fwrite(STDERR, 'REFUSED: the walk found no PHP file at all, so a clean verdict here would be a measurement of '
        ."nothing.\n");
    exit(1);
}

sort($files);

/**
 * The short names an import block declares, and the line of the first *code* use of each.
 *
 * @return array{imports: array<string, int>, uses: array<string, int>}
 */
$sweep = static function (string $src): array {
    $imports = [];
    $uses = [];
    $depth = 0;
    $parens = 0;
    $state = 'code';
    $pendingName = '';
    $pendingLine = 0;

    foreach (token_get_all($src) as $tok) {
        if (is_array($tok)) {
            [$id, $text, $line] = $tok;

            if ($id === T_USE && $depth === 0 && $state === 'code') {
                $state = 'after-use';
                $pendingName = '';
                $pendingLine = $line;

                continue;
            }

            if ($state !== 'code') {
                if ($id === T_STRING || $id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED || $id === T_NAME_RELATIVE) {
                    $parts = explode('\\', $text);
                    $pendingName = trim((string) end($parts));
                    $state = 'in-import';
                }

                continue;
            }

            if ($id === T_STRING && $depth === 0) {
                $uses[$text] ??= $line;
            }

            continue;
        }

        if ($state === 'after-use' && $tok === '(') {
            // `function () use ($a, $b)` — a closure binding, not an import. Skip to its matching paren.
            $state = 'closure';
            $parens = 1;

            continue;
        }

        if ($state === 'closure') {
            if ($tok === '(') {
                $parens++;
            } elseif ($tok === ')' && --$parens === 0) {
                $state = 'code';
            }

            continue;
        }

        if ($tok === ';' && $state !== 'code') {
            if ($pendingName !== '' && ! isset($imports[$pendingName])) {
                $imports[$pendingName] = $pendingLine;
            }
            $pendingName = '';
            $state = 'code';

            continue;
        }

        if ($tok === '{') {
            $depth++;
            // A `{` while parsing an import is a grouped import (`use A\{B, C};`); take each name it holds.
            if ($state === 'in-import' && $pendingName !== '') {
                if (! isset($imports[$pendingName])) {
                    $imports[$pendingName] = $pendingLine;
                }
                $pendingName = '';
            }
        } elseif ($tok === '}') {
            $depth--;
        }
    }

    return ['imports' => $imports, 'uses' => $uses];
};

$hits = [];
$byDesign = [];
$scanned = 0;

// One file in this repository places an alias after its use ON PURPOSE: storage/app/scratch/probe-use-ordering.php
// is the experiment that established the rule this tool enforces. Naming it as a defect every run would teach the
// reader to skim the list, so it is printed in its own bucket — stated, not skipped.
$exceptions = ['probe-use-ordering.php'];

foreach ($files as $file) {
    $read = file_get_contents($file);
    if ($read === false) {
        printf("REFUSED: %s cannot be read.\n", $file);
        exit(1);
    }

    // Read by key, not by list destructuring: the sweep returns an associative array, and `[$a, $b] = …` over it
    // answers null for both. Two drafts, two defects of the same family — the first matched raw lines and reported
    // 16 files, fifteen of them prose in a docblock; the second destructured an associative array, warned on every
    // file it read, and still printed a clean verdict over 867. The positive control is what caught the second.
    $found = $sweep($read);

    foreach ($found['imports'] as $short => $declaredAt) {
        $usedAt = $found['uses'][$short] ?? null;
        if ($usedAt !== null && $usedAt < $declaredAt) {
            if (in_array(basename($file), $exceptions, true)) {
                $byDesign[] = [basename($file), $short, $usedAt, $declaredAt];
            } else {
                $hits[] = [basename($file), $short, $usedAt, $declaredAt];
            }
        }
    }

    $scanned++;
}

$byDesign ??= [];
printf("walked %d file(s); %d late import(s) named, %d of them in a file that places one there on purpose.\n",
    $scanned, count($hits) + count($byDesign), count($byDesign));

foreach ($byDesign as [$name, $short, $used, $declared]) {
    printf("  BY-DESIGN    %-42s  %s resolved at :%d but imported at :%d — this file is the experiment\n", $name, $short, $used, $declared);
}

if ($hits === []) {
    echo "VERDICT: no other import sits below the first line of CODE that uses its short name.\n";
    exit(0);
}

foreach ($hits as [$name, $short, $used, $declared]) {
    printf("  LATE-IMPORT  %-42s  %s resolved at :%d but imported at :%d — the call reads global \\%s and dies\n", $name, $short, $used, $declared, $short);
}

printf("\nVERDICT: %d file(s) where the import is below its own use. Those files parse and pass `pint --test` and\n"
    ."    fail at runtime. Move the import above the first line that reads it.\n", count($hits));
exit($ci ? 1 : 0);
