<?php

/**
 * Does a write of `'someDate' => now()` land in a column the schema calls DATE?
 *
 * Lane 50's PHP half reads the SHAPES `now()->toDateString()` and `whereDate(...)`. A `date`-cast column fed
 * `now()` is invisible to it and answers 0 today, while Eloquent formats a Carbon for a `date` cast in the
 * application's timezone — UTC here — so anything between 16:00Z and midnight files the day before the
 * school's. That is lane 51's rule reached from the write side, and this is the instrument §28.3's G-43 row
 * says does not exist.
 *
 * It binds a write to the migrations, not to a live database, so it runs wherever the suite runs. The binding
 * is by column NAME and is honest about what that means: a name declared `date` somewhere and written from
 * `now()` anywhere is a CANDIDATE, and the row names the tables so a reader confirms the model. A name no
 * migration declares is printed UNBOUND and never counted clean — the same refusal lane 46 and lane 50 learned
 * for their own blind lists.
 *
 * A positive control is built in, because a sweep that cannot say "found it" can only ever say "clean":
 * the five columns measured on live `ems` on 2026-10-29 by lane86-probe-date-columns.php must all come back
 * as candidates, and the run refuses if any of them does not.
 *
 * Usage: php storage/app/scratch/lane87-date-write-columns.php [--ci]
 */
$root = dirname(__DIR__, 3);
$ci = in_array('--ci', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--ci') {
        fwrite(STDERR, "REFUSED: unrecognised argument {$arg}; this tool reads only --ci.\n");

        exit(1);
    }
}

/** @return array<string, list<string>> column name => tables declaring it as DATE */
$readDateColumns = static function (string $dir): array {
    $byCol = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
            continue;
        }
        $src = (string) file_get_contents($f->getPathname());
        // Split on down() so a create migration's rollback drops do not read as columns it adds.
        [$up] = preg_split('/public function down\(\)/', $src);
        if (preg_match('/Schema::(?:create|table)\(\s*[\'"]([a-z0-9_]+)[\'"]/i', $up, $tm) === 1) {
            $table = $tm[1];
            if (preg_match_all('/\$table->date\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $up, $cm) > 0) {
                foreach ($cm[1] as $col) {
                    $byCol[$col][] = $table;
                }
            }
        }
    }

    return $byCol;
};

$columns = $readDateColumns($root.'/database/migrations');
if ($columns === []) {
    fwrite(STDERR, "REFUSED: no migration declared a date column — the reader matched nothing.\n");

    exit(1);
}

$positives = [
    'evaluatedDate', 'submittedDate', 'assessmentDate', 'approvalDate', 'requestDate',
];
$missing = array_values(array_diff($positives, array_keys($columns)));
if ($missing !== []) {
    fwrite(STDERR, 'REFUSED: the positive control is not in the schema reader\'s output: '
        .implode(', ', $missing).'. Either the reader broke or the columns moved — no verdict below can be read.\n');

    exit(1);
}

/** @return list<array{file:string, line:int, col:string}> */
$findWrites = static function (string $dir) use ($root): array {
    $hits = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
            continue;
        }
        $rel = str_replace($root.'/', '', $f->getPathname());
        foreach (explode("\n", (string) file_get_contents($f->getPathname())) as $i => $line) {
            if (preg_match('/[\'"]([A-Za-z0-9_]*Date)[\'"]\s*=>\s*now\(\)/', $line, $m) !== 1) {
                continue;
            }
            if (preg_match('/^\s*(\/\/|\*|#)/', $line) === 1) {
                continue;
            }
            $col = $m[1];
            $hits[] = ['file' => $rel, 'line' => $i + 1, 'col' => $col];
        }
    }

    return $hits;
};

$writes = array_merge($findWrites($root.'/app'), $findWrites($root.'/tools'));
if ($writes === []) {
    fwrite(STDERR, "REFUSED: no `'<col>Date' => now()` write found anywhere in app/ or tools/ — a reader that"
        ." matches nothing reports a clean tree it never saw.\n");

    exit(1);
}

$candidates = [];
$safe = [];
$unbound = [];
$seen = [];
foreach ($writes as $w) {
    $key = $w['file'].':'.$w['line'];
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    if (isset($columns[$w['col']])) {
        $candidates[$w['col']][] = $w + ['tables' => array_unique($columns[$w['col']])];
    } elseif (preg_match('/(Date|At)$/', $w['col']) === 1 && str_ends_with($w['col'], 'At')) {
        $safe[] = $w;
    } else {
        $unbound[] = $w;
    }
}

printf("date columns read from migrations: %d; writes matched: %d\n", count($columns), count($seen));

$found = 0;
foreach ($candidates as $col => $rows) {
    if (! in_array($col, $positives, true)) {
        continue;
    }
    $found++;
    printf("CANDIDATE  %-18s declared date on %-24s written at %s\n", $col, implode('/', $rows[0]['tables']), $rows[0]['file'].':'.$rows[0]['line']);
}
printf("positive control: %d of %d columns measured on live ems come back as candidates\n", $found, count($positives));
if ($found !== count($positives)) {
    fwrite(STDERR, "REFUSED: the positive control expects all five. A checker that lost one cannot clear the rest.\n");

    exit(1);
}

foreach ($candidates as $col => $rows) {
    if (in_array($col, $positives, true)) {
        continue;
    }
    foreach ($rows as $r) {
        printf("CANDIDATE  %-18s declared date on %-24s written at %s:%d\n", $col, implode('/', $r['tables']), $r['file'], $r['line']);
    }
}

foreach ($unbound as $u) {
    printf("UNBOUND    %-18s no migration declares it — %s:%d (printed, not cleared)\n", $u['col'], $u['file'], $u['line']);
}

$total = count($candidates);
printf("\n%s: %d column name(s) written from now() while a migration calls them DATE, %d unbound write(s) printed.\n",
    $total === 0 && count($unbound) === 0 ? 'CLEAN' : 'FINDINGS', $total, count($unbound));

exit($ci && $total > 0 ? 1 : 0);
