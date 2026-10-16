<?php
/**
 * Audit lane 7 — unique indexes the application can violate and then 500 on.
 *
 * A duplicate key is a normal thing for a desk to do: retype a student number, add a room code
 * that already exists. If no validation rule catches it first, MySQL throws, Laravel renders a
 * 500, and the person is told the system broke when in fact they were told nothing useful.
 * This lists every UNIQUE index the server enforces and whether any validation rule names it.
 */
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$uq = DB::select("
    select TABLE_NAME t, INDEX_NAME i, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
    from information_schema.STATISTICS
    where TABLE_SCHEMA = database() and NON_UNIQUE = 0 and INDEX_NAME <> 'PRIMARY'
    group by TABLE_NAME, INDEX_NAME
    order by TABLE_NAME
");

// Every validation rule in app/ that names a unique constraint.
$rules = '';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $rules .= (string) file_get_contents($f->getPathname());
    }
}
$js = '';
$it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/resources/js', FilesystemIterator::SKIP_DOTS));
foreach ($it2 as $f) {
    if ($f->isFile() && preg_match('/\.(jsx|js)$/', $f->getFilename())) {
        $js .= (string) file_get_contents($f->getPathname());
    }
}

printf("%d unique indexes. Rows with no 'unique' rule naming their column are candidates for a 500.\n\n", count($uq));

$candidates = [];
foreach ($uq as $row) {
    $cols = explode(',', $row->cols);

    foreach ($cols as $col) {
        $named = preg_match('/unique:'.preg_quote($row->t, '/').'\s*,\s*'.preg_quote($col, '/').'/', $rules)
            || preg_match("/'".preg_quote($col, '/')."'[^;\n]{0,40}unique/", $rules)
            || str_contains($rules, 'unique('.$col);

        if (! $named) {
            $candidates[$row->t.'.'.$col][] = $row->i;
        }
    }
}

if ($candidates === []) {
    echo "every unique column is named by a validation rule\n";
}

foreach ($candidates as $what => $indexes) {
    printf("   %-42s via %s\n", $what, implode(', ', array_unique($indexes)));
}
printf("\n%d of %d unique-index columns have no matching 'unique' rule in app/\n", count($candidates), count($uq));
