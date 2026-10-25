<?php

/**
 * Lane 71, second shape: who a notification is addressed to, read from three sources at once.
 *
 * §28.3 G-31 records the class of sentence no instrument could judge — "a sentence that states how code behaves
 * today carries no status word, no figure, no instrument count and no dead line number". Its first instance was the
 * morph target: four sections narrated the bell addressing `App\Models\Students` long after §28.3 closed N-1 and
 * after both listeners had started writing `Staffusers::class`. §19.3 proved that a second member of the class
 * (§19.3's own tense), and §24's model row is now bound by `lane71-model-decls.php`.
 *
 * This is the third: the notifications morph target has exactly one correct answer, and three independent places
 * that must agree — what the document asserts, what the code passes, and what the rows hold. A disagreement is
 * machine-checkable with no interpretation at all, which is the test this lane applies before calling something an
 * instrument rather than a note.
 *
 * Read-only. It refuses rather than answering: no claim found, no write site found, an unreadable database, or a
 * schema it was not told to read. A busy instrument may not report a negative.
 *
 *   php storage/app/scratch/lane71-morph-targets.php
 *   php storage/app/scratch/lane71-morph-targets.php --dump=<file> --allow-schema=<name>
 *   php storage/app/scratch/lane71-morph-targets.php --ci                # two surfaces judged, the rows named
 *
 * There is no `--selftest`: planting a wrong class is what `mutate-lane71-morph-targets.php` does, in a throwaway
 * copy of the dump, and a flag here that only promised that work was an accepted-and-inert switch. Any flag this
 * tool does not read is refused by name, for the same reason.
 */
$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$opt = static function (string $name) use ($argv): ?string {
    foreach ($argv as $a) {
        if (str_starts_with($a, $name.'=')) {
            return substr($a, strlen($name) + 1);
        }
    }

    return null;
};
$dump = $opt('--dump') ?? $scratch.'/docx-flat.txt';
$allowSchema = $opt('--allow-schema');
// `--ci` judges the two surfaces the runner can reach — what the document asserts and what the code passes — and
// NAMES the third instead of judging it, because how many notification rows exist belongs to whichever database
// stated them (lane 60's rule for row counts) and the SQLite job has no persistent MySQL to ask.
$ci = in_array('--ci', $argv, true);
if ($ci && $allowSchema !== null) {
    printf("REFUSED: --ci reads no database, so --allow-schema=%s would be accepted and inert.\n", $allowSchema);
    exit(1);
}
// A flag nobody reads must not be able to run the tool: `--selftest` sat here for a while promising to plant a wrong
// class in a copy of the dump while doing nothing but printing a note about it, which is how an unmeasured run looks
// like a measured one.
foreach ((array) $argv as $i => $a) {
    $a = (string) $a;
    if ($i === 0 || ! str_starts_with($a, '--')) {
        continue;
    }
    foreach (['--dump', '--allow-schema', '--ci'] as $known) {
        if ($a === $known || str_starts_with($a, $known.'=')) {
            continue 2;
        }
    }
    printf("REFUSED: %s is not a flag this tool reads (known: --dump=, --allow-schema=, --ci).\n", $a);
    exit(1);
}

// --------------------------------------------------------------- 1. what the document asserts
$lines = (array) file($dump);
if (count($lines) < 500) {
    printf("REFUSED: the dump at %s holds %d line(s); re-run dump-docx-flat.php.\n", $dump, count($lines));
    exit(1);
}
// One definition of "which class", used by all three readers: the document writes `Staffusers::class` and
// `App\Models\Staffusers` for the same answer, and the rows hold the second form. Two spellings of one class is
// the defect this lane keeps being bitten by, so it is collapsed here rather than compared as a disagreement.
$fqcn = static function (string $name): string {
    $name = trim($name);
    if (str_ends_with($name, '::class')) {
        $name = substr($name, 0, -7);
    }
    $base = basename(str_replace('\\', '/', $name));

    return 'App\\Models\\'.$base;
};

$claims = [];
foreach ($lines as $i => $s) {
    // The document states the address with a connector — `notifiable_type = Staffusers::class`,
    // `notifiable_type` — `App\Models\Staffusers`. A schema listing separates the columns with commas, and a
    // column-pair gloss with a slash, so neither is read as a claim about who a notification is addressed to.
    $re = '/notifiable_type[`\'\x{2019}]?\s*(?:=|\x{2014}|-|:)\s*[`\'\x{201C}]?(?:App\\\\Models\\\\)?([A-Z]\w+)(?:::class)?/u';
    if (preg_match_all($re, (string) $s, $m, PREG_SET_ORDER) >= 1) {
        foreach ($m as $one) {
            $claims[] = ['line' => $i + 1, 'class' => $fqcn($one[1]), 'spelling' => trim($one[0])];
        }
    }
}
$claims = array_values(array_unique($claims, SORT_REGULAR));
if ($claims === []) {
    echo "REFUSED: no notification-address claim was read from the dump, so this run judged nothing.\n";
    exit(1);
}

// --------------------------------------------------------------- 2. what the code passes
$sites = [];
foreach (['app/Listeners', 'database/seeders', 'app/Support', 'app/Services'] as $dir) {
    if (! is_dir($root.'/'.$dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
            continue;
        }
        foreach ((array) file($f->getPathname()) as $n => $text) {
            if (preg_match('/[\'"]notifiable_type[\'"]\s*(?:=>|,)\s*([\w\\\\]+(?:::class)?)/u', $text, $m) === 1
                || preg_match('/where\([\'"]notifiable_type[\'"]\s*,\s*([\w\\\\]+(?:::class)?)/u', $text, $m) === 1) {
                $rel = str_replace($root.'/', '', $f->getPathname());
                $val = $m[1];
                $sites[] = ['file' => $rel, 'line' => $n + 1, 'raw' => $val, 'class' => $fqcn($val)];
            }
        }
    }
}
if ($sites === []) {
    echo "REFUSED: no code site writes or filters notifiable_type — a reader that finds nothing cannot answer.\n";
    exit(1);
}

// --------------------------------------------------------------- 3. what the rows hold
// `parse_ini_file()` is not how this repository reads `.env`: an unquoted value on line 3 makes it throw a syntax
// error and hand back nothing, which a probe must never read as "the environment says no database".
$readRows = static function () use ($root, $allowSchema): array {
    $env = [];
    foreach ((array) @file($root.'/.env') as $l) {
        if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/u', trim((string) $l), $m) === 1) {
            $env[$m[1]] = trim(trim($m[2]), "\"'");
        }
    }
    $schema = $env['DB_DATABASE'] ?? '';
    if ($allowSchema !== null && $allowSchema !== $schema) {
        printf("REFUSED: asked to judge schema %s but the environment answers %s.\n", $allowSchema, $schema ?: '(nothing)');
        exit(1);
    }
    if ($allowSchema === null && $schema !== 'ems') {
        printf("REFUSED: this probe reads live rows and the environment points at %s; say --allow-schema=%s to judge it.\n",
            $schema ?: '(nothing)', $schema);
        exit(1);
    }
    try {
        $pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';dbname='.$schema,
            $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '');
    } catch (Throwable $e) {
        printf("REFUSED: cannot read %s (%s) — an unread database is not an agreement.\n", $schema, $e->getMessage());
        exit(1);
    }
    $groups = $pdo->query('select notifiable_type, count(*) c from notifications group by notifiable_type')->fetchAll(PDO::FETCH_ASSOC);
    if ($groups === []) {
        printf("REFUSED: %s.notifications holds no rows, so it cannot confirm or refute anything.\n", $schema);
        exit(1);
    }

    return [$groups, $schema];
};
if ($ci) {
    $groups = [];
    $schema = 'not read';
    // Printed rather than omitted: a reader who sees no rows line cannot tell an unread surface from an agreeing one.
    echo "\nrows surface: NOT JUDGED — --ci reads no database, and a row count belongs to whichever schema stated it.\n";
} else {
    [$groups, $schema] = $readRows();
}

// --------------------------------------------------------------- verdict
$surfaces = $ci ? ['document', 'code'] : ['document', 'code', 'rows'];
$docClasses = array_count_values(array_column($claims, 'class'));
$codeClasses = array_count_values(array_map(static fn (array $s): string => $s['class'], $sites));
$dbClasses = [];
foreach ($groups as $g) {
    $dbClasses[$fqcn((string) $g['notifiable_type'])] = (int) $g['c'];
}
$all = array_unique(array_merge(array_keys($docClasses), array_keys($codeClasses), array_keys($dbClasses)));

$ok = 0;
$bad = 0;
foreach ($all as $class) {
    $read = ['document' => isset($docClasses[$class]), 'code' => isset($codeClasses[$class]), 'rows' => isset($dbClasses[$class])];
    $missing = array_values(array_filter($surfaces, static fn (string $s): bool => ! $read[$s]));
    if ($missing === []) {
        $ok++;
        printf("AGREE      %-28s document %d sentence(s), code %d site(s)%s\n",
            $class, $docClasses[$class], $codeClasses[$class],
            $ci ? ', rows not judged' : sprintf(', %s holds %d row(s)', $schema, $dbClasses[$class]));

        continue;
    }
    $bad++;
    printf("DISAGREE   %-28s named by no %s\n", $class, implode(', no ', $missing));
}
printf("\n%d document claim(s) over %d distinct class(es), %d code site(s), %s\n",
    count($claims), count($docClasses), count($sites),
    $ci ? 'rows not read (--ci)' : sprintf('%d morph group(s) in %s', count($groups), $schema));
printf("%d class(es) agree on the %d surface(s) this run judged, %d do not.\n", $ok, count($surfaces), $bad);
if ($ok + $bad !== count($all)) {
    echo "REFUSED: the buckets do not add up to the classes read.\n";
    exit(1);
}
if ($bad > 0) {
    echo "VERDICT: a surface contradicts the others — read the row above naming the missing one.\n";
    exit(1);
}
echo $ci
    ? "VERDICT: the document and the code answer the same question with the same class (the rows were not asked).\n"
    : "VERDICT: the document, the code and the rows answer the same question with the same class.\n";
exit(0);
