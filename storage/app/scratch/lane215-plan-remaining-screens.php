<?php

/**
 * Which columns may each remaining desk table actually order?
 *
 * The sort family (§28.3 G-40) has one rule a screen must satisfy twice: a header may only be painted for a key the
 * handler obeys, and the handler can only obey a column the query can reach — its own table, or a relation joined
 * under ListSort's private alias. Every wiring batch so far has re-derived that answer by reading a controller and a
 * `.jsx` by hand. This probe reads the same two surfaces for the whole owed queue at once, so the next batches are
 * a list of decisions rather than a list of discoveries, and so an offer that would be a lie is caught before it is
 * written.
 *
 * Read-only: it never edits a file. Usage:
 *   php storage/app/scratch/lane215-plan-remaining-screens.php [--pages=a,b,c]
 *
 * What it is NOT: a verdict. Three limits are printed here rather than discovered later. (a) It collects the column
 * keys of the whole FILE, so a page that renders two tables reports one merged list — Accounting's daily review is
 * exactly that shape. (b) A dotted key (`enrollment.student.lastName`) is a relation read, not a column of the listed
 * table, so it appears under "needs a hop" and only a hop CAN order it; whether the hop is honest is a second
 * question — a painted value that concatenates two columns (a term label built from year and semester) has no single
 * column to order, and this reader cannot tell the two apart. (c) The table is guessed from the first `Model::query`
 * in the action, so an action that renders from several builders answers for the first only; when nothing resolves it
 * prints UNRESOLVED rather than inventing a set. Read a line from this probe as a lead to open the file, the way the
 * pointer queues are read.
 */
$root = dirname(__DIR__, 3);
chdir($root);

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$overrides = null;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (str_starts_with($arg, '--pages=')) {
        $overrides = array_values(array_filter(array_map('trim', explode(',', substr($arg, 8)))));
    } else {
        $fail("this tool reads --pages= only; it was given [{$arg}].");
    }
}

// The queue as the wired-count instrument leaves it: pages under resources/js/Pages that render the shared table and
// do not yet import the sort helpers. Derived, never remembered — a hand-kept list is the blind spot that prints a
// clean verdict.
$pages = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/js/Pages', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.jsx')) {
        continue;
    }
    $path = str_replace('\\', '/', $f->getPathname());
    $src = (string) file_get_contents($path);
    if (! str_contains($src, '<DataTable')) {
        continue;
    }
    if (str_contains($src, 'sortParams') || str_contains($src, 'nextSortDirection')) {
        continue; // already wired to the mechanism
    }
    $pages[] = ['path' => $path, 'src' => $src];
}
if ($overrides !== null) {
    $pages = array_values(array_filter($pages, static fn (array $p): bool => in_array($p['path'], $overrides, true)
        || in_array(basename($p['path']), $overrides, true)));
    if ($pages === []) {
        $fail('the --pages= filter selected nothing — the name is wrong, not the queue empty.');
    }
}
if ($pages === []) {
    $fail('no unwired DataTable page was found; either the queue is done or the reader is blind. Check by wiring count.');
}

// ------------------------------------------------------------ controllers, by render target
$controllers = [];
$cit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/Http/Controllers', FilesystemIterator::SKIP_DOTS));
foreach ($cit as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $controllers[str_replace('\\', '/', $f->getPathname())] = (string) file_get_contents($f->getPathname());
    }
}
if ($controllers === []) {
    $fail('app/Http/Controllers read empty.');
}

// ------------------------------------------------------------ table columns, from the migrations
$columns = [];
foreach (glob('database/migrations/*create_*_table.php') ?: [] as $mf) {
    $src = (string) file_get_contents($mf);
    if (preg_match("/Schema::create\('([^']+)'/", $src, $t) !== 1) {
        continue;
    }
    $table = $t[1];
    $up = preg_split('/public function down\(\)/', $src)[0] ?? $src; // a rollback's drop is not a column
    if (preg_match_all('/\$table->(\w+)\(\s*[\'"]?([A-Za-z]\w*)?[\'"]?/', $up, $cm) >= 1) {
        foreach ($cm[0] as $i => $_) {
            $type = $cm[1][$i];
            $name = $cm[2][$i];
            if ($name === '' || in_array($type, ['foreign', 'index', 'unique', 'primary', 'constraint'], true)) {
                continue;
            }
            $columns[$table][$name] = $type;
        }
    }
    // Columns added later are real columns too.
    foreach (glob('database/migrations/*add*to*'.$table.'*.php') ?: [] as $af) {
        $asrc = (string) file_get_contents($af);
        $aup = preg_split('/public function down\(\)/', $asrc)[0] ?? $asrc;
        if (preg_match_all('/\$table->(\w+)\(\s*[\'"]?([A-Za-z]\w*)?[\'"]?/', $aup, $am) >= 1) {
            foreach ($am[0] as $i => $_) {
                if ($am[2][$i] !== '' && ! in_array($am[1][$i], ['foreign', 'index', 'unique'], true)) {
                    $columns[$table][$am[2][$i]] = $am[1][$i];
                }
            }
        }
    }
}
if ($columns === []) {
    $fail('no table was read out of the migrations — the census would be a clean verdict over nothing.');
}

printf("queue read: %d unwired DataTable page(s); %d tables read from the migrations; %d controller files.\n",
    count($pages), count($columns), count($controllers));

// The queue's size is a claim two instruments make. `lane140-measure-wired-screens.php` answers owed from the pages
// that reach listSort.js; this reader answers unwired from the import itself. Two answers to "how many are left" is
// the oldest defect in this file, so the two SETS are compared and the run refuses when they part. A narrowed scope
// is not a disagreement, so the comparison is skipped out loud rather than silently satisfied.
if ($overrides !== null) {
    printf("scope narrowed to %d page(s) by --pages=: the lane140 set comparison is NOT JUDGED for this run.\n\n", count($pages));
} else {
    $l140 = [];
    exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/lane140-measure-wired-screens.php').' 2>&1', $l140, $l140Code);
    $l140Text = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $l140));
    if (preg_match('/owed pages \((\d+)\):/', $l140Text, $ow) !== 1) {
        $fail('lane140 printed no owed-pages figure, so the queue could not be cross-checked (exit '.$l140Code.').');
    }
    $theirs = (int) $ow[1];
    // The owed list sits between its own heading and the handlers heading. Slicing from the first 'handlers' instead
    // takes the list of ACTIONS that comes AFTER it, and a regex run over that names no page — which is how a set
    // comparison silently degrades into "I could not read anything".
    $owedFrom = strpos($l140Text, 'owed pages (');
    $owedTo = strpos($l140Text, 'handlers (', $owedFrom === false ? 0 : $owedFrom);
    if ($owedFrom === false || $owedTo === false || $owedTo <= $owedFrom) {
        $fail('the owed-pages window could not be located in lane140 output — refuse rather than compare a partial list.');
    }
    $owedBlock = substr($l140Text, $owedFrom, $owedTo - $owedFrom);
    $theirsSet = [];
    if (preg_match_all('#(resources/js/Pages/\S+\.jsx)#', $owedBlock, $om) >= 1) {
        $theirsSet = $om[1];
    }
    if ($theirsSet === []) {
        $fail('lane140 named no owed page inside its own owed-pages block, so the sets cannot be compared.');
    }
    if (count($theirsSet) !== $theirs) {
        $fail('lane140 heading says '.$theirs.' owed pages while its block names '.count($theirsSet).' — its own print does not add up.');
    }
    $mineSet = array_map(static fn (array $p): string => $p['path'], $pages);
    sort($theirsSet);
    sort($mineSet);
    if ($theirsSet !== $mineSet) {
        $fail('the two readers name different queues — this one '.count($mineSet).' pages, lane140 '.count($theirsSet)
            .'; only-in-mine ['.implode(', ', array_diff($mineSet, $theirsSet)).']'
            .' only-in-lane140 ['.implode(', ', array_diff($theirsSet, $mineSet)).']');
    }
    printf("cross-check: lane140 names the same %d owed pages, page for page — the two readers agree by set, not by luck.\n\n", $theirs);
}

foreach ($pages as $p) {
    // The page's own component name, the way Inertia spells it.
    $rel = preg_replace('#^resources/js/Pages/#', '', $p['path']);
    $component = preg_replace('/\.jsx$/', '', $rel);

    // Painted columns: every `key:` in the columns array, with its label.
    preg_match_all("/\{\s*key:\s*'([^']+)',\s*label:\s*'([^']*)'/", $p['src'], $pm);
    $painted = $pm[1] ?? [];
    if ($painted === []) {
        printf("%-52s NO-COLUMNS-READ (it renders the shared table but its column list was not parsed — read it by hand)\n\n", $rel);

        continue;
    }

    // The action that renders this page, and the model its builder starts from.
    $hit = null;
    foreach ($controllers as $file => $src) {
        if (! str_contains($src, "Inertia::render('{$component}'")) {
            continue;
        }
        $at = strpos($src, "Inertia::render('{$component}'");
        $before = substr($src, 0, $at);
        $fnAt = strrpos($before, 'public function ');
        if ($fnAt === false) {
            continue;
        }
        $name = substr($before, $fnAt + 16);
        $name = (string) preg_replace('/\(.*/s', '', $name);
        $hit = ['file' => $file, 'action' => $name, 'body' => substr($before, $fnAt).(substr($src, $at, 4000))];
        break;
    }
    if ($hit === null) {
        printf("%-52s NO-ACTION (no controller renders this component — the page is unreachable or the name differs)\n\n", $rel);

        continue;
    }

    $model = null;
    // Bind to the builder that actually pages the list, not to the first `Model::` in the action: this action's
    // `Auditlogs::…` audit write is a live line of the right file that belongs to something else, which is the
    // wrong-target shape this repository documents for pointers, met in a plan reader on its first run.
    $anchor = strrpos($hit['body'], '->paginate(');
    if ($anchor === false) {
        $anchor = strrpos($hit['body'], '->simplePaginate(');
    }
    $haystack = $anchor === false ? $hit['body'] : substr($hit['body'], 0, $anchor);
    if (preg_match_all('/(\w+)::(query|with|where|whereIn)\(/', $haystack, $mm) >= 1) {
        $model = $anchor === false ? ($mm[1][0] ?? null) : end($mm[1]);
    }
    $table = null;
    if ($model !== null) {
        // App\Models\Xy -> snake plural of the class name without a trailing plural already present.
        $short = (string) preg_replace('/.*\\\\/', '', $model);
        foreach ([$short, strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short) ?? ''), strtolower($short).'s', strtolower($short).'es'] as $guess) {
            if (isset($columns[$guess])) {
                $table = $guess;
                break;
            }
        }
    }

    printf("%s\n  action      %s::%s()\n  model       %s   table %s\n  painted     %s\n",
        $rel, basename($hit['file'], '.php'), $hit['action'], $model ?? 'UNKNOWN', $table ?? 'NOT RESOLVED',
        implode(', ', $painted));
    // Is it a queue at all? A list the action hands over whole needs no server order — that is lane 90's
    // classification, and a plan that ignores it will spend a batch wiring a header onto a six-row table.
    $paginates = str_contains($hit['body'], '->paginate(') || str_contains($hit['body'], '->simplePaginate(');
    echo '  shape       '.($paginates ? 'SERVER-PAGINATED queue — a server order is what prompt §14 asks for'
        : 'BOUNDED SET (the action answers all() or a plain collection) — no server order is owed here; say so in the register rather than wiring it')."\n";
    if ($table === null) {
        // Two different failures, and one message would name the wrong cause: the model may be unread, or read but
        // owned by a vendor migration this census does not walk (Spatie's `roles` and `permissions` are that shape).
        echo $model === null
            ? "  offer       UNRESOLVED — the builder's model was not read; decide by hand, do not guess a column set\n\n"
            : "  offer       NOT-IN-MIGRATIONS — `{$model}` was read but its table is not created under database/migrations (a vendor-owned table); ask information_schema by hand before offering columns\n\n";

        continue;
    }
    $stored = array_keys($columns[$table]);
    // The binding is then weighed against the page: a table that holds none of the painted columns is a wrong
    // target, not a plan. Say so, and offer the table that does hold them if one exists, rather than printing an
    // "orderable: (none)" line that a reader would trust as a finding about the screen.
    $top = array_values(array_unique(array_map(static fn (string $k): string => explode('.', $k)[0], $painted)));
    if (array_intersect($top, $stored) === []) {
        $candidates = [];
        foreach ($columns as $t => $cols) {
            if (count(array_intersect($top, array_keys($cols))) === count($top)) {
                $candidates[] = $t;
            }
        }
        echo '  offer       SUSPECT-BINDING — none of '.implode(', ', $top)." is a column of `{$table}`; do not wire from this line\n";
        echo '              tables holding every painted key: '.($candidates === [] ? '(none in the migrations)' : implode(', ', $candidates))."\n\n";

        continue;
    }
    $direct = array_values(array_intersect($painted, $stored));
    $derived = array_values(array_diff($painted, $stored));
    echo '  orderable   '.($direct === [] ? '(none of the painted columns is stored)' : implode(', ', $direct))."\n";
    if ($derived !== []) {
        // A painted column that is not a column of this table is either a relation (a hop can order it) or derived
        // per row (no header may be offered). The probe names them; the reader decides which, from the page's render.
        echo '  needs a hop or is derived: '.implode(', ', $derived)."\n";
        foreach ($derived as $d) {
            if (preg_match("/key: '".$d."',.*render: \(row\) => row\.".$d.'\?\./s', $hit['body']) === 1
                || str_contains($p['src'], 'row.'.$d.'?.')) {
                echo "    - {$d} reads a relation on the row -> a ListSort hop can order it if the key is NOT NULL\n";
            }
        }
    }
    echo '  tie-break   '.($stored[0] ?? '?')." (first column of the table; confirm it is the primary key)\n\n";
}
