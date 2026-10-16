<?php
/**
 * Audit lane 6 — deletes that will answer with a raw database error.
 *
 * Deleting a row that another table still points at is refused by the database when the key is
 * RESTRICT, and Laravel turns that into a QueryException: a 500 page, not a message. The blocking
 * desk had exactly this and was fixed by naming the children first (BlockingController::destroy).
 * This asks the same question of every parent table the server actually enforces.
 */
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// 1. every table that is a parent under a RESTRICT key, with the children that pin it
$rows = DB::select("
    select k.TABLE_NAME as child, k.COLUMN_NAME as childCol,
           k.REFERENCED_TABLE_NAME as parent, k.CONSTRAINT_NAME as cname
    from information_schema.KEY_COLUMN_USAGE k
    join information_schema.REFERENTIAL_CONSTRAINTS r
      on r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA and r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
    where k.TABLE_SCHEMA = database()
      and k.REFERENCED_TABLE_NAME is not null
      and r.DELETE_RULE = 'RESTRICT'
    order by k.REFERENCED_TABLE_NAME
");

$parents = [];
foreach ($rows as $r) {
    $parents[$r->parent][] = $r->child.'.'.$r->childCol;
}

echo "== tables a RESTRICT key pins (a delete here can 500):\n";
foreach ($parents as $t => $children) {
    printf("   %-26s %2d child key(s): %s\n", $t, count($children), implode(', ', array_slice($children, 0, 4)));
}

// 2. which controllers delete from them, and whether a guard runs first
$controllers = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app/Http/Controllers', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $controllers[str_replace('\\', '/', $f->getPathname())] = (string) file_get_contents($f->getPathname());
    }
}

$modelFor = [];
foreach ($parents as $table => $children) {
    $files = glob(dirname(__DIR__, 3).'/app/Models/*.php');
    foreach ($files as $file) {
        $src = (string) file_get_contents($file);
        if (preg_match("/protected \\\$table = '".$table."';/", $src)) {
            $modelFor[$table] = basename($file, '.php');
        }
    }
}

echo "\n== delete sites and whether anything guards them\n";
foreach ($controllers as $path => $src) {
    $short = basename($path);
    foreach (preg_split('/\R/', $src) as $i => $line) {
        if (! preg_match('/->delete\(\)/', $line)) {
            continue;
        }

        // Look back 30 lines in the same method for a refusal of some kind.
        $before = implode("\n", array_slice(preg_split('/\R/', $src), max(0, $i - 30), min(30, $i)));
        $guarded = preg_match('/(usages|cannot|Cannot|can\'t|refus|blockers|count\(\)\s*>|restrictedBy)/i', $before);

        printf("   %-34s :%-5d %s\n", $short, $i + 1, $guarded ? 'guard within 30 lines' : 'NO GUARD FOUND');
    }
}

echo "\n== models whose table is RESTRICT-pinned but which no controller deletes through a model\n";
foreach ($modelFor as $table => $model) {
    $used = false;
    foreach ($controllers as $src) {
        if (str_contains($src, $model.'::') || str_contains($src, '$'.$lc = lcfirst($model).'->delete()') || str_contains($src, $model.'->delete()')) {
            $used = true;
        }
    }
    if (! $used) {
        printf("   %-22s (%-24s) not deleted from any controller\n", $model, $table);
    }
}
