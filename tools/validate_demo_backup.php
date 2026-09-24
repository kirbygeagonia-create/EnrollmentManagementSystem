<?php

/**
 * Throwaway validator for Documentation/ems-demo-backup.sql: checks that every
 * INSERT row matches its CREATE TABLE column count. mysqldump-style file, so
 * INSERTs may or may not carry explicit column lists. Quote-aware: handles
 * backslash escapes and doubled '' quotes inside string literals.
 *
 * Usage: php check-backup.php <path-to-sql>
 */
$file = $argv[1] ?? exit("usage: php check-backup.php <file>\n");
$data = file_get_contents($file);
if ($data === false) {
    fwrite(STDERR, "cannot read $file\n");
    exit(1);
}

// PowerShell 5.1's `>` redirect wrote this dump as UTF-16LE with BOM; decode
// so the ASCII SQL is parseable.
if (substr($data, 0, 2) === "\xFF\xFE") {
    $data = substr($data, 2);
    $data = iconv('UTF-16LE', 'UTF-8', $data);
    if ($data === false) {
        fwrite(STDERR, "cannot decode UTF-16LE\n");
        exit(1);
    }
    printf("Decoded UTF-16LE BOM (%d bytes -> %d chars)\n", strlen(file_get_contents($file)), strlen($data));
}
$sql = $data;
$len = strlen($sql);

/** Split $s at depth-0 commas, honoring string literals and nested parens. */
function splitTop(string $s): array
{
    $out = [];
    $cur = '';
    $depth = 0;
    $inStr = false;
    $n = strlen($s);
    for ($i = 0; $i < $n; $i++) {
        $ch = $s[$i];
        if ($inStr) {
            $cur .= $ch;
            if ($ch === '\\') {
                $cur .= $s[$i + 1] ?? '';
                $i++;

                continue;
            }
            if ($ch === "'") {
                if (($s[$i + 1] ?? '') === "'") {
                    $cur .= "'";
                    $i++;

                    continue;
                }
                $inStr = false;
            }

            continue;
        }
        if ($ch === "'") {
            $inStr = true;
            $cur .= $ch;

            continue;
        }
        if ($ch === '(') {
            $depth++;
        }
        if ($ch === ')') {
            $depth--;
        }
        if ($ch === ',' && $depth === 0) {
            $out[] = $cur;
            $cur = '';

            continue;
        }
        $cur .= $ch;
    }
    if (trim($cur) !== '') {
        $out[] = $cur;
    }

    return $out;
}

/** Return the offset of the closing quote of the string opened at $open. */
function skipString(string $s, int $open): int
{
    $n = strlen($s);
    for ($j = $open + 1; $j < $n; $j++) {
        $c = $s[$j];
        if ($c === '\\') {
            $j++;

            continue;
        }
        if ($c === "'") {
            if (($s[$j + 1] ?? '') === "'") {
                $j++;

                continue;
            }

            return $j;
        }
    }

    return $n;
}

// --- CREATE TABLE column counts ---
$colCounts = [];
if (preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE/s', $sql, $m)) {
    foreach ($m[1] as $i => $name) {
        $cols = [];
        foreach (splitTop($m[2][$i]) as $seg) {
            $seg = trim($seg);
            // Column definitions start with `name` <type>. KEY/PRIMARY KEY/
            // CONSTRAINT segments don't start with a backtick.
            if (preg_match('/^`([^`]+)`\s+[A-Za-z]/', $seg, $c)) {
                $cols[] = $c[1];
            }
        }
        $colCounts[$name] = $cols;
    }
}

// --- scan for INSERT tuples ---
$header = '/INSERT INTO `([^`]+)`\s+(?:\(([^)]*)\)\s+)?VALUES\s*/';
$tuples = [];
$i = 0;
while ($i < $len) {
    $ch = $sql[$i];
    if ($ch === "'") {
        $i = skipString($sql, $i) + 1;

        continue;
    }
    if ($ch === 'I' && preg_match($header, $sql, $m, PREG_OFFSET_CAPTURE, $i)) {
        // The offset parameter only says where the SEARCH begins; the match
        // itself may start later. Land on the true statement position.
        // preg_match yields a FLAT [text, offset] pair under PREG_OFFSET_CAPTURE.
        $matchStart = $m[0][1];
        $matchText = $m[0][0];
        $table = $m[1][0];
        $cols = (isset($m[2][0]) && trim($m[2][0]) !== '')
            ? array_map('trim', explode(',', $m[2][0]))
            : null;
        if ($matchStart > $i) {
            $i = $matchStart;
        }
        $pos = $matchStart + strlen($matchText);
        while ($pos < $len && $sql[$pos] !== '(') {
            if ($sql[$pos] === "'") {
                $pos = skipString($sql, $pos) + 1;

                continue;
            }
            $pos++;
        }
        $depth = 0;
        $start = -1;
        $j = $pos;
        for (; $j < $len; $j++) {
            $c = $sql[$j];
            if ($c === "'") {
                $j = skipString($sql, $j);

                continue;
            }
            if ($c === '(') {
                $depth++;
                if ($depth === 1) {
                    $start = $j + 1;
                }

                continue;
            }
            if ($c === ')') {
                $depth--;
                if ($depth === 0 && $start >= 0) {
                    $tuples[] = [$table, $cols, $start, $j];
                    if (getenv('DEBUG')) {
                        printf(
                            "TUPLE %s at %d :: prev: %s :: first: %s\n",
                            $table,
                            $start,
                            str_replace("\n", ' ', substr($sql, max(0, $i), 60)),
                            str_replace("\n", ' ', substr($sql, $start, 50))
                        );
                    }
                    $start = -1;
                }

                continue;
            }
            if ($c === ';' && $depth === 0) {
                break;
            }
        }
        $i = max($j, $i + 1);

        continue;
    }
    $i++;
}

// --- report ---
$errors = 0;
$byTable = [];
foreach ($tuples as [$table, $cols, $start, $end]) {
    $byTable[$table] = ($byTable[$table] ?? 0) + 1;
    $expected = $cols !== null ? count($cols) : (isset($colCounts[$table]) ? count($colCounts[$table]) : null);
    if ($expected === null) {
        printf("UNKNOWN TABLE `%s`\n", $table);
        $errors++;

        continue;
    }
    $vals = splitTop(substr($sql, $start, $end - $start));
    if (count($vals) !== $expected) {
        $errors++;
        printf(
            "MISMATCH `%s`: expected %d cols, got %d values :: %s\n",
            $table,
            $expected,
            count($vals),
            mb_substr(preg_replace('/\s+/', ' ', substr($sql, $start, $end - $start)), 0, 100)
        );
    }
}
printf("Tuples per table: %s\n", json_encode($byTable));
printf(
    "Checked %d tuples across %d tables (%d with CREATE TABLE). %s\n",
    count($tuples),
    count($byTable),
    count($colCounts),
    $errors ? sprintf('%d ERRORS', $errors) : 'ALL OK'
);
exit($errors ? 1 : 0);
