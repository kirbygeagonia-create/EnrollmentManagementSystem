<?php

/**
 * One definition of "which columns does the `students` table actually hold", read from the migrations.
 *
 * Two files answering that question differently is the defect this repository has been caught by in lanes 6, 46, 69,
 * 70 and 90, and the question is load-bearing: a desk eager-load narrowed to a column the table does not have does
 * not warn — it answers 500 at the counter, while every test that reads row *sequences* instead of response status
 * stays green. That is how lane 108's first attempt shipped.
 *
 * Reads `up()`-style blocks only, bound per `Schema::` statement: a migration that touches `students` and also
 * creates another table must not lend that table's columns to this list, or the guard accepts a wrong target.
 * `dropColumn` inside the same block removes the name. A rollback's `Schema::dropIfExists` sits below the block and
 * is never reached, which is why the scan stops at the first `});` after each `Schema::`.
 *
 * @return string[] every column name the students migrations declare, in first-seen order
 */
function students_columns_from_migrations(string $root): array
{
    $cols = [];
    $files = glob($root.'/database/migrations/*.php') ?: [];
    if ($files === []) {
        return [];
    }
    foreach ($files as $file) {
        $src = (string) file_get_contents($file);
        $at = 0;
        while (($start = strpos($src, 'Schema::', $at)) !== false) {
            $close = strpos($src, '});', $start);
            if ($close === false) {
                break;
            }
            $block = substr($src, $start, $close - $start);
            $at = $close + 3;
            if (! preg_match("/^Schema::(?:create|table)\(\s*'students'/", $block)) {
                continue;
            }
            if (preg_match_all('/\$table->dropColumn\(\s*\'([a-zA-Z0-9_]+)\'/', $block, $dm)) {
                foreach ($dm[1] as $c) {
                    unset($cols[$c]);
                }
            }
            if (preg_match_all('/\$table->\w+(?:\([^)]*\))?\(\'([a-zA-Z0-9_]+)\'/', $block, $cm)) {
                foreach ($cm[1] as $c) {
                    $cols[$c] = true;
                }
            }
        }
    }

    return array_keys($cols);
}

/**
 * Refuse loudly unless every name in a column list is a real `students` column.
 *
 * @return string[] the names that are NOT columns of the table (empty means the list is safe to write)
 */
function students_unknown_columns(string $root, string $csv): array
{
    $declared = students_columns_from_migrations($root);
    if ($declared === []) {
        fwrite(STDERR, "REFUSED: no `students` column was read out of the migrations, so the guard would pass on an"
            ." empty set — an accepted-and-inert check, which this repository has refused five times over.\n");

        exit(1);
    }
    $bad = [];
    foreach (array_filter(array_map('trim', explode(',', $csv))) as $c) {
        if (! in_array($c, $declared, true)) {
            $bad[] = $c;
        }
    }

    return $bad;
}
