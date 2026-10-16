<?php

// Read-only audit scratch tool (gitignored path): compare every hard-coded `in:`
// validation list in app/ against (a) the MySQL enum values a column of that name
// declares in database/migrations and (b) the backing values of every PHP enum in
// app/Enums. A value the rule offers that neither accepts is one that strict MySQL
// rejects on write and the read model crashes on.

$repo = dirname(__DIR__, 3);

// (a) column => union of enum values declared for that column name anywhere
$colValues = [];
foreach (glob($repo.'/database/migrations/*.php') as $file) {
    preg_match_all("/\\\$table->enum\(\s*'([a-zA-Z_]+)'\s*,\s*(\[[^;]*?\])/s", file_get_contents($file), $m, PREG_SET_ORDER);
    foreach ($m as $hit) {
        $vals = [];
        preg_match_all("/'([^']*)'/", $hit[2], $v);
        foreach ($v[1] as $val) {
            $vals[] = $val;
        }
        $colValues[$hit[1]] = array_unique(array_merge($colValues[$hit[1]] ?? [], $vals));
    }
}

// (b) every PHP enum backing value, as one global allow-list
$enumValues = [];
foreach (glob($repo.'/app/Enums/*.php') as $file) {
    preg_match_all("/case\s+[a-zA-Z]+\s*=\s*'([^']*)'/", file_get_contents($file), $v);
    foreach ($v[1] as $val) {
        $enumValues[$val] = true;
    }
}

$files = array_merge(
    glob($repo.'/app/Http/Controllers/*.php'),
    glob($repo.'/app/Http/Controllers/*/*.php'),
    glob($repo.'/app/Http/Requests/*.php'),
    glob($repo.'/app/Http/Requests/*/*.php'),
    glob($repo.'/app/Services/*.php')
);

$report = [];
foreach ($files as $file) {
    foreach (file($file) as $no => $line) {
        if (! preg_match("/'([a-zA-Z_]+)'\s*=>[^;]*?(?:\||')in:([^']*)'/", $line, $m)) {
            continue;
        }
        [$col, $list] = [$m[1], $m[2]];
        // Only enum-backed columns can reject a value on write; a plain varchar column
        // offers no second authority to compare against.
        if (! isset($colValues[$col])) {
            continue;
        }
        foreach (array_filter(array_map('trim', explode(',', $list))) as $val) {
            $inColumn = in_array($val, $colValues[$col], true);
            $inEnum = isset($enumValues[$val]);
            if (! $inColumn && ! $inEnum) {
                $report[] = str_replace($repo.'\\', '', $file).':'.($no + 1)."  {$col} offers '{$val}'".
                    '  (column accepts: '.implode('|', $colValues[$col]).')';
            }
        }
    }
}

echo implode("\n", array_unique($report))."\n\n".count(array_unique($report))." enum/rule mismatch(es)\n";
