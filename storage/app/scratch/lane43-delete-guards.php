<?php

/**
 * Lane 43 — does every desk delete list the children the schema will refuse?
 *
 * Live `ems` enforces 100 foreign keys across 63 InnoDB tables. Only four cascade (all RBAC pivots, which is
 * how `RbacSeeder`'s prune works); one is RESTRICT; the other 95 either SET NULL (26, mostly the signature
 * columns — deleting an officer must not erase the signature they left) or NO ACTION (69). A NO ACTION child
 * makes `Model::delete()` throw SQLSTATE[23000], and `bootstrap/app.php:140` renders every QueryException on a
 * write into one flash: "That could not be saved — it conflicts with information already on file… Reload the
 * record, repeat the step". Measured, not assumed: with the guard removed, that is what the course screen
 * returned. So the harm is not a stack trace — it is a message that names no record and tells the admin to
 * retry a delete that can never succeed. Naming the child table is what removes it.
 *
 * The guards are hand-written: `ReferenceDataController::blockedDeletionMessage()` is given a literal array of
 * `label => count` per child table. Hand-kept lists drift from the schema — that is the defect class this
 * repository keeps finding — so this script asks the database what it refuses, and the source what it checks.
 *
 * Every delete site in app/Http/Controllers is bound to its parent table by the route-model-bound parameter,
 * and its guarded children are the `DB::table('x')` / `Model::` reads inside the same method body.
 *
 * Exit 0 only when every site is parsed AND every must-guard child is named.
 *
 * Usage: php storage/app/scratch/lane43-delete-guards.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

set_exception_handler(function (\Throwable $e): void {
    fwrite(STDERR, 'FATAL: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n");
    exit(1);
});

$root = dirname(__DIR__, 3);

// ---- 1. what the schema refuses ---------------------------------------------------------

$fkRows = DB::select(
    "SELECT rc.TABLE_NAME AS child, rc.REFERENCED_TABLE_NAME AS parent, rc.DELETE_RULE AS rule
     FROM information_schema.REFERENTIAL_CONSTRAINTS rc
     WHERE rc.CONSTRAINT_SCHEMA = ?",
    [DB::connection()->getDatabaseName()]
);

$mustGuard = [];   // parent => [child, ...] that would refuse a delete
$soft = [];        // parent => [child(rule), ...] that will not refuse
foreach ($fkRows as $r) {
    if (in_array($r->rule, ['NO ACTION', 'RESTRICT'], true)) {
        $mustGuard[$r->parent][$r->child] = true;
    } else {
        $soft[$r->parent][] = $r->child.'('.$r->rule.')';
    }
}

printf("Schema: %d relations refuse a delete (NO ACTION/RESTRICT), %d will not (SET NULL/CASCADE).\n\n",
    count(array_filter($fkRows, fn ($r) => in_array($r->rule, ['NO ACTION', 'RESTRICT'], true))),
    count(array_filter($fkRows, fn ($r) => ! in_array($r->rule, ['NO ACTION', 'RESTRICT'], true)))
);

// ---- 2. what the source checks ----------------------------------------------------------

/** Map an App\Models class to its table without assuming naming conventions. */
$tableOf = function (string $class) use ($root): ?string {
    $short = basename(str_replace('\\', '/', $class));
    $fqcn = 'App\\Models\\'.$short;
    if (! class_exists($fqcn)) {
        return null;
    }

    return (new $fqcn)->getTable();
};

$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Http/Controllers')) as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $files[] = (string) $f;
    }
}
sort($files);

/**
 * relation method name => table, read off the models themselves.
 *
 * The guards are written three ways and a checker that knows only one of them reports the other two as defects:
 * `$req->clearanceapprovals()->exists()`, `$schedule->meetings()->delete()` (cleanup, not counting), and a
 * shared helper (`officeUsage()`, `undeletableReasons()`) whose own body names the tables. All three are a
 * refusal with a reason, which is what the schema check actually asks.
 */
$relationTable = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Models')) as $mf) {
    if (! $mf->isFile() || $mf->getExtension() !== 'php') {
        continue;
    }
    $msrc = (string) file_get_contents((string) $mf);
    if (! preg_match_all('/public function (\w+)\([^)]*\)\s*:\s*\w*[Mm]any\w*|\bpublic function (\w+)\([^)]*\)\s*:\s*\w*[Oo]ne\w*/', $msrc, $rm)) {
        continue;
    }
    foreach (explode("\n", $msrc) as $i => $line) {
        if (! preg_match('/public function (\w+)\(/', trim($line), $dn)) {
            continue;
        }
        for ($k = $i; $k < min($i + 8, count(explode("\n", $msrc))); $k++) {
            $bodyLine = explode("\n", $msrc)[$k];
            if (preg_match('/\$this->(hasMany|hasOne|belongsTo|morphMany|morphToOne|hasManyThrough)\(\s*(\w+)::class/', $bodyLine, $bt)) {
                $t = $tableOf($bt[2]);
                if ($t !== null) {
                    $relationTable[$dn[1]] = $t;
                }

                break;
            }
            if ($k > $i && str_contains($bodyLine, '}')) {
                break;
            }
        }
    }
}

/** Every table the schema actually has, so a quoted literal can be recognised as a table name. */
$allTables = [];
foreach (DB::select('SELECT TABLE_NAME t FROM information_schema.tables WHERE table_schema = ?',
    [DB::connection()->getDatabaseName()]) as $t) {
    $allTables[$t->t] = true;
}

/** Read a body for table names in any of the four idioms the guards are written in. */
$readTables = function (string $body, ?string $parent) use ($tableOf, $relationTable, $allTables): array {
    $found = [];
    preg_match_all("/DB::table\('([a-z_]+)'\)/", $body, $tt);
    foreach ($tt[1] as $t) {
        $found[$t] = true;
    }
    // `officeUsage()` passes the table to a closure, so the name appears only as a quoted literal
    preg_match_all("/'([a-z_]+)'/", $body, $ql);
    foreach ($ql[1] as $t) {
        if (isset($allTables[$t])) {
            $found[$t] = true;
        }
    }
    preg_match_all('/\b([A-Z][A-Za-z]+)::(?:where|whereIn|whereHas|has|count|query|exists)\b/', $body, $mm);
    foreach (array_unique($mm[1]) as $cls) {
        $t = $tableOf($cls);
        if ($t !== null) {
            $found[$t] = true;
        }
    }
    preg_match_all('/->(\w+)\(\)->(exists|count|delete)\b/', $body, $rr);
    foreach ($rr[1] as $rel) {
        if (isset($relationTable[$rel])) {
            $found[$relationTable[$rel]] = true;
        }
    }
    unset($found[$parent]);

    return array_keys($found);
};

$sites = [];
$unparsed = [];

foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    // split into methods; the body runs to the next 4-space closing brace. `[^{]*\{` because PSR-12 puts the
    // opening brace on its own line after the return type — `[^\n]*\{` matched nothing and read as a clean run.
    if (! preg_match_all('/^    public function (\w+)\(([^)]*)\)[^{]*\{(.*?)^    \}/ms', $src, $methods, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach (array_keys($methods[0]) as $j) {
        // with PREG_OFFSET_CAPTURE each group is its own parallel array: $methods[$i][$j] = [text, offset]
        $whole = $methods[0][$j][0];
        $nameM = $methods[1][$j][0];
        $paramsM = $methods[2][$j][0];
        if (! preg_match('/->(delete|forceDelete)\(\)/', $whole)) {
            continue;
        }
        // which bound model does the delete act on? Walk every parameter and take the first real model —
        // `proposeSubjects(Request $request, Enrollments $enrollment)` binds the second one, and reading only
        // the first turned a child cleanup into an UNBOUND row in a report that should have said nothing.
        $pm = null;
        foreach (explode(',', $paramsM) as $p) {
            if (preg_match('/\b([A-Z][A-Za-z]*)\s+\$(\w+)/', trim($p), $cand) && $tableOf($cand[1]) !== null) {
                $pm = [null, $cand[1], $cand[2]];

                break;
            }
        }
        if ($pm === null) {
            // A form action that only ever cleans up children — `$student->guardians()->delete()` — is not a
            // parent delete and has no FK to refuse it. Only an unbindable *parent* delete is a real gap.
            $onlyChildren = preg_match_all('/->delete\(\)|->forceDelete\(\)/', $whole, $dc)
                === preg_match_all('/->\w+\(\)->(delete|forceDelete)\(\)|DB::table\([a-z_\x27]+\)(->[^;]*)?->delete\(\)/', $whole, $cc);
            if ($onlyChildren) {
                continue;
            }
            $unparsed[] = basename($file)."::{$nameM} (no App\\Models model bound to a deleted row)";

            continue;
        }
        $parent = $tableOf($pm[1]);
        // Is the delete acting on the bound model, or on a child of some other row?
        // `$student->guardians()->delete()` inside a form action is a cleanup, not a parent delete, and
        // calling it a delete site would put a fake verdict in the column of the table this report keeps.
        $deletesBound = str_contains($whole, '$'.$pm[2].'->delete()')
            || str_contains($whole, '$'.$pm[2].'->forceDelete()');
        if (! $deletesBound) {
            continue;
        }

        $guarded = $readTables($whole, $parent);

        // a private helper in the same file may be where the counting actually happens
        preg_match_all('/\$this->(\w+)\(/', $whole, $hc);
        foreach (array_unique($hc[1]) as $helper) {
            if ($helper === $nameM
                || ! preg_match('/function '.$helper.'\([^)]*\)[^{]*\{(.*?)^    \}/ms', $src, $hb)) {
                continue;
            }
            $guarded = array_values(array_unique(array_merge($guarded, $readTables($hb[1], $parent))));
        }

        // catch around the delete: the constraint still fires, but the desk gets a reason, not a 500
        $catches = (bool) preg_match('/catch\s*\(/', $whole);

        $sites[] = [
            'file' => str_replace($root.'/', '', $file),
            'method' => $nameM,
            'parent' => $parent,
            'guarded' => $guarded,
            'guardCall' => str_contains($whole, 'blockedDeletionMessage(')
                || $guarded !== []
                || str_contains($whole, 'exists()')
                || $catches,
            'catches' => $catches,
        ];
    }
}

printf("Delete sites found: %d; unparsable: %d\n\n", count($sites), count($unparsed));

// A checker that finds nothing has not verified anything. This project's own rule: a needle that cannot match
// must never answer zero, because an empty verdict is indistinguishable from a clean one.
if ($sites === []) {
    fwrite(STDERR, "REFUSED: no delete site was parsed out of app/Http/Controllers — the parser is broken, "
        ."not the code.\n");
    exit(1);
}

// ---- 3. compare --------------------------------------------------------------------------

$problems = 0;
$checked = 0;

foreach ($sites as $s) {
    $must = array_keys($mustGuard[$s['parent']] ?? []);
    sort($must);
    $missing = array_values(array_diff($must, $s['guarded']));

    if ($must === []) {
        printf("  ok    %-34s deletes %s — the schema refuses nothing, %d child(ren) detach or null themselves\n",
            $s['method'], $s['parent'], count($soft[$s['parent']] ?? []));
        $checked++;

        continue;
    }

    if (! $s['guardCall']) {
        printf("  MISS  %-34s deletes %s — %d table(s) would refuse and the method never asks: %s\n",
            $s['method'], $s['parent'], count($must), implode(', ', $must));
        $problems++;

        continue;
    }

    if ($missing !== []) {
        printf("  MISS  %-34s deletes %s — guard omits %s\n", $s['method'], $s['parent'], implode(', ', $missing));
        $problems++;

        continue;
    }

    printf("  ok    %-34s deletes %s — all %d must-guard child(ren) named%s\n",
        $s['method'], $s['parent'], count($must),
        ($soft[$s['parent']] ?? []) === [] ? '' : ' (soft: '.implode(', ', $soft[$s['parent']]).')');
    $checked++;
}

foreach ($unparsed as $u) {
    printf("  UNBOUND %s — the checker could not bind this delete to a table\n", $u);
}

printf("\n  %d site(s) judged, %d problem(s), %d unparsable.\n", $checked + $problems, $problems, count($unparsed));
echo "  A NO ACTION child the guard does not name is not a warning: the delete is refused by the schema and the"
    ." desk is told to reload and retry something that cannot ever work.\n";

exit($problems === 0 && $unparsed === [] ? 0 : 1);
