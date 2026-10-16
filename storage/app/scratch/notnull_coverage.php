<?php

// Read-only audit scratch tool (gitignored path). Reports NOT NULL, no-default
// columns that create/insert literals in app/ never supply.

$repo = dirname(__DIR__, 3);
$db = $repo.'/storage/app/scratch/audit.sqlite';

require $repo.'/vendor/autoload.php';
$app = require $repo.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = new PDO('sqlite:'.$db);

$skip = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions'];
$sql = 'select name from sqlite_master where type="table" and name not like "sqlite_%"';
$tables = array_filter(array_column($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC), 'name'),
    fn ($n) => ! in_array($n, $skip, true));

$required = [];
foreach ($tables as $t) {
    foreach ($pdo->query('PRAGMA table_info("'.str_replace('"', '', $t).'")') as $row) {
        if ((int) $row['notnull'] === 1 && $row['dflt_value'] === null && (int) $row['pk'] === 0) {
            $required[$t][$row['name']] = true;
        }
    }
}

$modelToTable = [];
foreach (glob($repo.'/app/Models/*.php') as $file) {
    $class = 'App\\Models\\'.basename($file, '.php');
    if (class_exists($class)) {
        try {
            $modelToTable[$class] = (new $class)->getTable();
        } catch (Throwable $e) {
            // skip
        }
    }
}

$shortToTable = [];
foreach ($modelToTable as $class => $table) {
    $shortToTable[substr($class, strrpos($class, '\\')+1)] = $table;
}

$methods = ['create', 'firstOrCreate', 'updateOrCreate', 'insert'];
$sites = [];

$files = array_merge(
    glob($repo.'/app/Http/Controllers/*.php'),
    glob($repo.'/app/Http/Controllers/*/*.php'),
    glob($repo.'/app/Http/Requests/*.php'),
    glob($repo.'/app/Http/Requests/*/*.php'),
    glob($repo.'/app/Services/*.php'),
    glob($repo.'/app/Listeners/*.php'),
    glob($repo.'/app/Observers/*.php')
);

foreach ($files as $file) {
    $tokens = token_get_all(file_get_contents($file));
    $n = count($tokens);
    for ($i = 1; $i < $n - 1; $i++) {
        $t = $tokens[$i];
        if (! is_array($t) || $t[0] !== T_DOUBLE_COLON) {
            continue;
        }
        $prev = $tokens[$i - 1];
        $next = $tokens[$i + 1];
        if (! is_array($prev) || $prev[0] !== T_STRING || ! is_array($next) || $next[0] !== T_STRING) {
            continue;
        }
        if (! in_array($next[1], $methods, true) || ! isset($shortToTable[$prev[1]])) {
            continue;
        }
        $j = $i + 2;
        while ($j < $n && (! is_string($tokens[$j]) || $tokens[$j] !== '(')) {
            $j++;
        }
        if ($j >= $n) {
            continue;
        }
        $depth = 0;
        $keys = [];
        for ($k = $j; $k < $n; $k++) {
            $tok = $tokens[$k];
            if (is_string($tok)) {
                if ($tok === '(') {
                    $depth++;
                } elseif ($tok === ')') {
                    if (--$depth === 0) {
                        break;
                    }
                }

                continue;
            }
            if ($tok[0] === T_CONSTANT_ENCAPSED_STRING) {
                $look = $k + 1;
                while ($look < $n && is_array($tokens[$look]) && $tokens[$look][0] === T_WHITESPACE) {
                    $look++;
                }
                if ($look < $n && is_array($tokens[$look]) && $tokens[$look][0] === T_DOUBLE_ARROW) {
                    $keys[trim($tok[1], "'\"")] = true;
                }
            }
        }
        $sites[] = [
            'file' => str_replace($repo.'/', '', $file),
            'line' => $t[2],
            'class' => $prev[1],
            'method' => $next[1],
            'table' => $shortToTable[$prev[1]],
            'keys' => array_keys($keys),
        ];
    }
}

$report = [];
foreach ($sites as $s) {
    if (! isset($required[$s['table']])) {
        continue;
    }
    $missing = array_values(array_diff(array_keys($required[$s['table']]), $s['keys'], ['created_at', 'updated_at']));
    if ($missing !== []) {
        $report[$s['table']][] = $s['file'].':'.$s['line'].' '.$s['class'].'::'.$s['method'].' -> '.implode(', ', $missing);
    }
}

ksort($report);
echo '=== write sites that omit NOT NULL / no-default columns ==='."\n";
foreach ($report as $table => $lines) {
    echo "\n[".$table.']'."\n";
    foreach ($lines as $l) {
        echo '  '.$l."\n";
    }
}
echo "\nTotal tables flagged: ".count($report).' of '.count($required)."\n";
