<?php

// Read-only audit scratch tool (gitignored path). Finds validation rules that
// declare a field `nullable` while the same name is a NOT NULL, no-default
// column somewhere in the schema — the shape that turns "leave this blank"
// into a 500 on a strict MySQL connection.

$repo = dirname(__DIR__, 3);
$db = $repo.'/storage/app/scratch/audit.sqlite';

$pdo = new PDO('sqlite:'.$db);

$skip = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions'];
$sql = 'select name from sqlite_master where type="table" and name not like "sqlite_%"';
$tables = array_filter(array_column($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC), 'name'),
    fn ($n) => ! in_array($n, $skip, true));

$notnull = [];
foreach ($tables as $t) {
    foreach ($pdo->query('PRAGMA table_info("'.str_replace('"', '', $t).'")') as $row) {
        if ((int) $row['notnull'] === 1 && $row['dflt_value'] === null && (int) $row['pk'] === 0) {
            $notnull[$row['name']][$t] = true;
        }
    }
}

$files = array_merge(
    glob($repo.'/app/Http/Controllers/*.php'),
    glob($repo.'/app/Http/Controllers/*/*.php'),
    glob($repo.'/app/Http/Requests/*.php'),
    glob($repo.'/app/Http/Requests/*/*.php')
);

$hits = [];
foreach ($files as $file) {
    foreach (file($file) as $no => $line) {
        if (! preg_match("/'([a-zA-Z_]+)'\s*=>\s*.*(nullable)/", $line, $m)) {
            continue;
        }
        $col = $m[1];
        if (! isset($notnull[$col])) {
            continue;
        }
        // a coerced-to-'' / boolean fallback in the same expression is already safe
        $hits[] = str_replace($repo.'\\', '', $file).':'.$no."  ".$col."  [".implode(',', array_keys($notnull[$col]))."]  ".trim($line);
    }
}

echo implode("\n", $hits), "\n\n";
echo count($hits), " candidate(s)\n";
