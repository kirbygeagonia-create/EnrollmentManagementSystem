<?php

/**
 * Lane 71: §24's model declarations, checked against the model files.
 *
 * Stage 2 of the mechanism census (`lane71-mechanism-shapes.php`) found 73 VALUE claims, and most of them are one
 * uniform family: §24's per-table entry states
 *
 *     Model `App\Models\X` (app/Models/X.php), $primaryKey = 'someId', $timestamps = true|false.
 *
 * That is exactly the shape a probe can answer without guessing at prose: read the class, take `$primaryKey` and
 * `$timestamps` as the code declares them (Laravel's defaults are `id` and `true` when the property is absent), and
 * compare. It is also falsifiable in both directions — change the document and the tool must complain, fix the
 * document and it must go silent — which is what the corpus-wide `substr_count` verdict could not do: it called
 * `$primaryKey = 'studentId'` consistent because the string appears 926 times somewhere in the repository.
 *
 * Every sentence is classified, none is dropped: OK, WRONG-PK, WRONG-TIMESTAMPS, NO-FILE (the named path is not in
 * the repository), and UNREADABLE (the model parsed but declared neither form, which is a statement the tool cannot
 * check and must say so about). The run refuses when it judged nothing, and refuses when its printed rows plus
 * withheld do not equal the tally.
 *
 * Usage: php storage/app/scratch/lane71-model-decls.php [--dump=path] [--limit=0]  (limit 0 prints every row)
 */
$root = dirname(__DIR__, 3);
$dumpPath = $root.'/storage/app/scratch/docx-flat.txt';
$limit = 0;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with((string) $a, '--dump=')) {
        $dumpPath = substr((string) $a, 7);
    } elseif (str_starts_with((string) $a, '--limit=')) {
        $limit = (int) substr((string) $a, 8);
    } else {
        printf("REFUSED: unknown argument [%s] — an accepted-and-inert flag makes a run look narrower than it is.\n", $a);
        exit(1);
    }
}
if (! is_file($dumpPath)) {
    printf("REFUSED: no dump at %s — run dump-docx-flat.php first.\n", $dumpPath);
    exit(1);
}

$shape = '/Model\s+`?App\\\\Models\\\\([A-Za-z]+)`?\s*\(([^)]*?\.php)\)\s*,\s*\$primaryKey\s*=\s*[\'"`]([A-Za-z]+)[\'"`]\s*,\s*\$timestamps\s*=\s*(true|false)/u';

$lines = explode("\n", (string) file_get_contents($dumpPath));
$counts = ['OK' => 0, 'WRONG-PK' => 0, 'WRONG-TIMESTAMPS' => 0, 'NO-FILE' => 0, 'UNREADABLE' => 0];
$printed = 0;
$withheld = 0;
$claims = 0;
$modelCache = [];
foreach ($lines as $i => $line) {
    $t = trim((string) $line);
    if ($t === '') {
        continue;
    }
    if (preg_match_all($shape, $t, $m, PREG_SET_ORDER) === 0) {
        continue;
    }
    foreach ($m as $c) {
        $claims++;
        [$cls, $path, $pk, $ts] = [$c[1], trim($c[2]), $c[3], $c[4]];
        $file = $root.'/'.ltrim(str_replace('\\', '/', $path), '/');
        if (! is_file($file)) {
            $counts['NO-FILE']++;
            $verdict = 'NO-FILE: '.$path.' is not in the repository';
        } else {
            if (! array_key_exists($file, $modelCache)) {
                $src = (string) file_get_contents($file);
                $codePk = preg_match('/\$primaryKey\s*=\s*[\'"]([A-Za-z]+)[\'"]/', $src, $p) === 1 ? $p[1] : null;
                $codeTs = preg_match('/\$timestamps\s*=\s*(true|false);/', $src, $q) === 1 ? $q[1] : null;
                $modelCache[$file] = [$codePk, $codeTs];
            }
            [$codePk, $codeTs] = $modelCache[$file];
            $codePk ??= 'id';       // Laravel's default when the class does not say otherwise
            $codeTs ??= 'true';
            if ($codePk !== $pk) {
                $counts['WRONG-PK']++;
                $verdict = sprintf('WRONG-PK: %s declares $primaryKey = %s', basename($file), "'".$codePk."'");
            } elseif ($codeTs !== $ts) {
                $counts['WRONG-TIMESTAMPS']++;
                $verdict = sprintf('WRONG-TIMESTAMPS: %s declares $timestamps = %s', basename($file), $codeTs);
            } elseif (preg_match('/\$primaryKey\s*=/', (string) file_get_contents($file)) !== 1
                && preg_match('/class\s+\w+\s+extends\s+\w+/', (string) file_get_contents($file)) !== 1) {
                $counts['UNREADABLE']++;
                $verdict = 'UNREADABLE: the file holds no class to read';
            } else {
                $counts['OK']++;
                $verdict = 'ok';
            }
        }
        if ($limit === 0 || $printed < $limit) {
            $printed++;
            printf("%-16s line %d  %s :: pk=%s ts=%s   %s\n", $verdict === 'ok' ? 'OK' : trim(explode(':', $verdict)[0]),
                $i + 1, $cls, $pk, $ts, $verdict);
        } else {
            $withheld++;
        }
    }
}

$total = array_sum($counts);
printf("\n%d claim(s) read = %d judged (%s). %d printed, %d withheld.\n",
    $claims, $total,
    implode(', ', array_map(fn (string $k): string => $k.' '.$counts[$k], array_keys($counts))),
    $printed, $withheld);
if ($claims !== $total) {
    printf("REFUSED: %d claim(s) read but only %d landed in a bucket — an uncounted path.\n", $claims, $total);
    exit(1);
}
if ($printed + $withheld !== $total) {
    printf("REFUSED: tallies %d rows but printed %d and withheld %d.\n", $total, $printed, $withheld);
    exit(1);
}
if ($total === 0) {
    echo "REFUSED: 0 model declarations judged — the shape is gone or the dump was not read, not a clean §24.\n";
    exit(1);
}
exit($counts['WRONG-PK'] + $counts['WRONG-TIMESTAMPS'] + $counts['NO-FILE'] + $counts['UNREADABLE'] > 0 ? 1 : 0);
