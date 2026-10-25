<?php

/**
 * The §21.2 inventory's "Seeded?" column: which line of the seeder actually creates each table's rows.
 *
 * Those cells carry bare numbers — `courses | Yes — CRUD | Yes ( :162 )` — and no instrument could judge them,
 * because the owning file is not in the row: the paragraph introducing the table says "Seeded = created by
 * DevReferenceDataSeeder", so the number belongs to the seeder two lines above the header. Confirmed by the
 * rows that are still right: `academicyears … Yes ( :137 , :141 , :148 )` is exactly three
 * `Academicyears::firstOrCreate(` calls. Some neighbours have drifted as the seeder grew.
 *
 * This resolves each table to its model class (app/Models, matched on the singular of the table name and on the
 * `$table` property), finds every create call for it in the seeder, and prints what the cell should cite. It is
 * read-only: the numbers are proposed, never written.
 *
 *   php storage/app/scratch/lane70-seeder-cites.php
 */
$root = dirname(__DIR__, 3);
$text = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
$seederPath = $root.'/database/seeders/DevReferenceDataSeeder.php';
if (! is_file($seederPath)) {
    echo "REFUSED: no seeder at {\$seederPath}\n";
    exit(1);
}
$seeder = file($seederPath);

// Model classes and the table each one claims.
$models = [];
foreach (glob($root.'/app/Models/*.php') as $f) {
    $src = (string) file_get_contents($f);
    $class = basename($f, '.php');
    $table = null;
    if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/u', $src, $m) === 1) {
        $table = $m[1];
    }
    $models[] = ['class' => $class, 'table' => $table ?? strtolower(preg_replace('/(?([a-z])([A-Z]))/', '$1_$2', $class)).'s'];
}

// Where the §21.2 inventory lives: from its header row to the next heading.
$rows = [];
$in = false;
foreach (explode("\n", $text) as $line) {
    if (preg_match('/^ROW \d+: Table \| Managed by a screen\? \| Seeded\? \| Notes/u', $line) === 1) {
        $in = true;

        continue;
    }
    if ($in && preg_match('/^(?:P \d+: 2[2-9]|ROW \d+: Table \|)/u', $line) === 1) {
        break;
    }
    if ($in && preg_match('/^ROW (\d+): ([a-z][a-z_]+) \|/u', $line, $m) === 1) {
        $rows[] = ['dumpLine' => (int) $m[1], 'table' => $m[2], 'row' => $line];
    }
}

$found = 0;
foreach ($rows as $r) {
    $model = null;
    foreach ($models as $cand) {
        if ($cand['table'] === $r['table']) {
            $model = $cand['class'];

            break;
        }
    }
    if ($model === null) {
        continue;
    }
    $hits = [];
    foreach ($seeder as $i => $code) {
        if (preg_match('/'.$model.'::(firstOrCreate|updateOrCreate|create|firstOrNew|insert)\(/u', $code) === 1) {
            $hits[] = $i + 1;
        }
    }
    if ($hits === []) {
        continue;
    }
    preg_match_all('/:\s*(\d+)(?:-(\d+))?|: ?(\d+)(?:-(\d+))?/', $r['row'], $nums);
    $bare = array_values(array_filter(array_merge($nums[1] ?? [], $nums[3] ?? [])));
    $found++;
    printf("%-22s %-26s create calls at :%s\n", $r['table'], $model.'::', implode(', :', array_map('strval', $hits)));
    printf('%-22s %-26s cell quotes    : %s%s', '', '', implode(', :', $bare) ?: '(none)', $bare === [] ? '' : "\n");
    if ($bare !== []) {
        $outside = array_values(array_filter($bare, fn ($b) => ! in_array((int) $b, $hits, true)));
        printf("%-22s => %s\n", '', $outside === []
            ? 'every quoted number is a create call'
            : 'NOT a create line: :'.implode(', :', $outside).' — true lines are :'.implode(', :', array_map('strval', $hits)));
    }
    echo "\n";
}
printf("%d inventory row(s) resolved to a model with create calls in the seeder.\n", $found);
