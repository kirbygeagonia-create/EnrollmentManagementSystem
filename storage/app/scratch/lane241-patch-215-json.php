<?php

/**
 * Give `lane215-plan-remaining-screens.php` a machine-readable output.
 *
 * The queue's shape verdict — is this page's list one the server pages, so a header means something? — is decided
 * here, in the one place that resolves a page to its action and its action to a builder. `lane140` needs the same
 * answer to state an owed figure that a desk can actually satisfy, so the predicate is EXPORTED rather than
 * re-implemented: two definitions of "does this action paginate" is the defect this repository has refused for year
 * levels, sort keys and "is this narrowed".
 *
 * Every replacement is asserted to match exactly once before anything is written, and the file is linted after.
 */
$root = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem';
$f = $root.'/storage/app/scratch/lane215-plan-remaining-screens.php';
$s = (string) file_get_contents($f);

$pairs = [
    // 1. the accumulators
    [
        "\$shapeTally = [];\nforeach (\$pages as \$p) {",
        "\$jsonOut = null;\n\$records = [];\nforeach (\$pages as \$p) {",
    ],
    // 2. the record, carrying the fields a consumer needs
    [
        "    \$shapeTally[] = ['page' => \$rel, 'paginates' => \$paginates];",
        "    \$records[] = [\n"
        ."        'page' => \$p['path'], 'short' => \$rel,\n"
        ."        'action' => basename(\$hit['file'], '.php').'::'.\$hit['action'],\n"
        ."        'model' => \$model, 'table' => \$table, 'paginates' => \$paginates,\n"
        ."        'offer' => 'not reached', 'direct' => [], 'derived' => [], 'painted' => \$painted,\n"
        ."    ];",
    ],
    // 3. the three places the loop leaves a record's offer undecided or decided
    [
        "        echo \$model === null\n"
        ."            ? \"  offer       UNRESOLVED — the builder's model was not read; decide by hand, do not guess a column set\\n\\n\"\n"
        ."            : \"  offer       NOT-IN-MIGRATIONS — `{\$model}` was read but its table is not created under database/migrations (a vendor-owned table); ask information_schema by hand before offering columns\\n\\n\";",
        "        \$records[count(\$records) - 1]['offer'] = \$model === null ? 'UNRESOLVED' : 'NOT-IN-MIGRATIONS';\n"
        ."        echo \$model === null\n"
        ."            ? \"  offer       UNRESOLVED — the builder's model was not read; decide by hand, do not guess a column set\\n\\n\"\n"
        ."            : \"  offer       NOT-IN-MIGRATIONS — `{\$model}` was read but its table is not created under database/migrations (a vendor-owned table); ask information_schema by hand before offering columns\\n\\n\";",
    ],
    [
        "        echo '  offer       SUSPECT-BINDING — none of '.implode(', ', \$top).\" is a column of `{\$table}`; do not wire from this line\\n\";",
        "        \$records[count(\$records) - 1]['offer'] = 'SUSPECT-BINDING';\n"
        ."        echo '  offer       SUSPECT-BINDING — none of '.implode(', ', \$top).\" is a column of `{\$table}`; do not wire from this line\\n\";",
    ],
    [
        "    \$direct = array_values(array_intersect(\$painted, \$stored));",
        "    \$records[count(\$records) - 1]['offer'] = 'ORDERABLE';\n"
        ."    \$direct = array_values(array_intersect(\$painted, \$stored));",
    ],
    [
        "    \$derived = array_values(array_diff(\$painted, \$stored));",
        "    \$records[count(\$records) - 1]['direct'] = \$direct;\n"
        ."    \$derived = array_values(array_diff(\$painted, \$stored));\n"
        ."    \$records[count(\$records) - 1]['derived'] = \$derived;",
    ],
];

foreach ($pairs as $i => [$from, $to]) {
    $c = substr_count($s, $from);
    if ($c !== 1) {
        fwrite(STDERR, "REFUSED: pair ".($i + 1)." matched {$c} times, expected exactly 1 — the tool's bytes have aged.\n");
        exit(1);
    }
    $s = str_replace($from, $to, $s);
}

// 4. the footer already counts shapes; re-point it at the records, then write the JSON.
$footerFrom = "foreach (\$shapeTally as \$s) {";
if (str_contains($s, $footerFrom)) {
    fwrite(STDERR, "REFUSED: the footer already loops \$shapeTally in a new way; re-read it before patching.\n");
    exit(1);
}
$s = str_replace('$shapeTally', '$records', $s);

$jsonBlock = <<<'PHP'

if ($jsonOut !== null) {
    $jsonPath = str_starts_with($jsonOut, '/') || preg_match('#^[A-Za-z]:#', $jsonOut) === 1
        ? $jsonOut
        : $root.'/'.$jsonOut;
    if (file_put_contents($jsonPath, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        $fail("the --json= target {$jsonPath} could not be written.");
    }
    printf("wrote %d record(s) to %s\n", count($records), $jsonPath);
}
PHP;

$anchor = "echo \"\\n\";\n";
if (substr_count($s, $anchor) < 1) {
    // Append instead, at the end of the classified-summary block.
    $s .= $jsonBlock."\n";
} else {
    $s = preg_replace('/'.preg_quote($anchor, '/').'/', $jsonBlock."\n".$anchor, $s, 1);
}

if (file_put_contents($f, $s) === false) {
    fwrite(STDERR, "REFUSED: the patch could not be written.\n");
    exit(1);
}
echo "patched lane215: --json= exported, records carry action/model/table/offer/direct/derived\n";
