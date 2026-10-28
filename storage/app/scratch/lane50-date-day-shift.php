<?php

// Lane 50: a calendar day must be painted as the day it is, in any viewer's zone.
//
// Why this exists. A zoneless day column reaches the browser either as "2026-09-15" (uncast) or as
// "2026-09-15T00:00:00.000000Z" (a `date` cast — Laravel's serializeDate). In both spellings
// `new Date(value).toLocaleDateString()` re-projects the day into the *viewer's* zone, so anywhere west of UTC
// the screen shows the day BEFORE the one on the record. Measured on this box (zone Etc/GMT+11) across 11 desk
// screens: all 21 `date` columns shifted — students.birthdate 2004-05-12 painted "5/11/2004" on /students/92.
// A real instant is different: rendering it in the viewer's zone is correct, so this checker must leave those.
//
// Column types come from the migrations and casts from the models, both static, so this runs on CI with no
// database. Anything it cannot bind is UNBOUND and refuses the run. Blind spots are printed, not counted clean.
//
//   php storage/app/scratch/lane50-date-day-shift.php
//   EMS_LANE50_PAGES=Students,Exam php storage/app/scratch/lane50-date-day-shift.php

$root = dirname(__DIR__, 3);
$pagesEnv = getenv('EMS_LANE50_PAGES');
$scope = ($pagesEnv === false || $pagesEnv === '') ? null : array_map('trim', explode(',', $pagesEnv));

// Typed as an instant, used as a day. Re-derive with lane50-probe-midnight-columns.php against a real database.
// payments.paymentDate is `datetime` and every one of its 15 live rows is midnight, because a date-only input
// writes it — so rendering it through the viewer's zone loses a day exactly like a DATE column does.
$dayShapedInstants = ['paymentDate'];

// ---------------------------------------------------------------------------------------------
// 1. Column types from the migrations. Later migrations win, because ->change() re-types a column.
// ---------------------------------------------------------------------------------------------
$columnType = [];
$migrationFiles = glob($root.'/database/migrations/*.php');
sort($migrationFiles);

if (count($migrationFiles) === 0) {
    fwrite(STDERR, "NO CONCLUSION: no migration files under database/migrations\n");
    exit(1);
}

foreach ($migrationFiles as $file) {
    $s = file_get_contents($file);

    if (! preg_match_all('/Schema::(create|table)\(\s*[\'"](\w+)[\'"]/i', $s, $blocks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($blocks as $bi => $block) {
        $table = strtolower($block[2][0]);
        $from = $block[0][1];
        // With PREG_SET_ORDER + PREG_OFFSET_CAPTURE the next block's own offset is [0][1], not [1].
        $to = isset($blocks[$bi + 1]) ? $blocks[$bi + 1][0][1] : strlen($s);
        $body = substr($s, $from, $to - $from);

        if (preg_match_all('/\$table->(date|dateTime|datetime|timestamp|timestampTz|datetimeTz|dateTz)\(\s*[\'"](\w+)[\'"]/i', $body, $cols, PREG_SET_ORDER)) {
            foreach ($cols as $col) {
                $kind = strtolower($col[1]);
                $columnType[$table.'.'.$col[2]] = in_array($kind, ['date', 'datetz'], true) ? 'date' : 'instant';
            }
        }
    }
}

if (count($columnType) === 0) {
    fwrite(STDERR, sprintf("NO CONCLUSION: read %d migrations and found no date or datetime column — the parser matched nothing\n", count($migrationFiles)));
    exit(1);
}

// ---------------------------------------------------------------------------------------------
// 2. Casts from the models: `date` means the app calls it a calendar day, `datetime` an instant.
// ---------------------------------------------------------------------------------------------
$modelDay = $modelInstant = [];
$modelFiles = glob($root.'/app/Models/*.php');

foreach ($modelFiles as $file) {
    $s = file_get_contents($file);

    if (! preg_match('/protected function casts\(\): array\s*\{\s*return \[(.*?)\n\s*\];/s', $s, $block)) {
        continue;
    }

    preg_match_all('/([\'"])(\w+)\1\s*=>\s*([\'"])([\w:\-]+)\3/', $block[1], $pairs, PREG_SET_ORDER);

    foreach ($pairs as $pair) {
        $type = strtolower($pair[4]);

        // "datetime" starts with "date", so the instant test has to run first.
        if (str_starts_with($type, 'datetime') || $type === 'timestamp') {
            $modelInstant[$pair[2]] = true;
        } elseif ($type === 'date' || $type === 'immutable_date' || str_starts_with($type, 'date:')) {
            $modelDay[$pair[2]] = true;
        }
    }
}

// Every name the schema or a model knows, and what it is.
$dayNames = $instantNames = $knownNames = [];

foreach ($columnType as $key => $kind) {
    $name = substr($key, strrpos($key, '.') + 1);
    $knownNames[$name] = $kind;
    $kind === 'date' ? $dayNames[$name] = true : $instantNames[$name] = true;
}

foreach ($modelDay as $n => $v) {
    $knownNames[$n] ??= 'date';
    $dayNames[$n] = true;
    unset($instantNames[$n]);
}

foreach ($modelInstant as $n => $v) {
    $knownNames[$n] ??= 'instant';
}

foreach ($dayShapedInstants as $n) {
    $dayNames[$n] = true;
}

// A name is a day only when nothing calls it an instant — except the ones measured above as days in disguise.
$isDayName = function (string $name) use ($dayNames, $instantNames, $dayShapedInstants): bool {
    if (in_array($name, $dayShapedInstants, true)) {
        return true;
    }

    return isset($dayNames[$name]) && ! isset($instantNames[$name]);
};

// ---------------------------------------------------------------------------------------------
// 3. The screens.
// ---------------------------------------------------------------------------------------------
$files = [];

foreach (['Pages', 'Components', 'Layouts'] as $dir) {
    $files = array_merge($files, glob($root.'/resources/js/'.$dir.'/*.jsx') ?? []);
    $files = array_merge($files, glob($root.'/resources/js/'.$dir.'/**/*.jsx') ?? []);
    $files = array_merge($files, glob($root.'/resources/js/'.$dir.'/**/**/*.jsx') ?? []);
}

$files = array_values(array_unique($files));

if ($scope !== null) {
    $files = array_values(array_filter($files, function ($f) use ($scope, $root) {
        $rel = str_replace('\\', '/', substr($f, strlen($root) + 1));

        foreach ($scope as $s) {
            if (str_contains($rel, '/'.$s.'/') || str_contains($rel, '/'.$s.'.jsx')) {
                return true;
            }
        }

        return false;
    }));
}

if (count($files) === 0) {
    fwrite(STDERR, "NO CONCLUSION: EMS_LANE50_PAGES matched no screen\n");
    exit(1);
}

// A zone-reading call is what turns an instant back into a local calendar day. (These fragments get wrapped in
// delimiters below — `preg_match('(a|b)\b', …)` without them is PCRE's "unknown modifier" warning, and a
// warning in a checker's output is the only trace that its parser stopped working.)
$zoneReading = '(toLocaleDateString|toLocaleTimeString|toLocaleString|getFullYear|getMonth|getDate|getHours|getDay|getTime)\b';
$zoneRe = '/'.$zoneReading.'/';

// The shared day formatter this lane introduces: presence of it is the fix.
$dayFormatter = '(formatCalendarDay|calendarYear|dayInputValue)';

// Lane 51's school-zone formatters. A day must not be run through them either: they re-project the value, which
// is exactly what a calendar day cannot survive.
$schoolFormatter = '(formatSchoolDay|formatSchoolMoment)';

$problems = 0;
$blind = [];
$counts = array_fill_keys(['DAY-VIA-ZONE', 'YEAR-VIA-ZONE', 'DAY-BY-UTC-SLICE', 'DAY-FORMATTER', 'INSTANT-LOCAL', 'VIEWER-ZONE', 'SCHOOL-FORMATTER', 'ARITHMETIC', 'NOW', 'HELDER-PARAM', 'UNBOUND', 'UTC-DAY-STAMP', 'UTC-DAY-COMPARE', 'SCHOOL-DAY', 'PHP-UNBOUND'], 0);

// First pass over a file: which local names wrap a Date? `const fmt = (d) => (d ? new Date(d).toLocale… : '—')`
foreach ($files as $file) {
    $rel = str_replace('\\', '/', substr($file, strlen($root) + 1));
    $text = str_replace("\r\n", "\n", file_get_contents($file));
    $lines = explode("\n", $text);

    $zoneHelpers = $dayHelpers = $schoolHelpers = [];

    if (preg_match_all('/(?:const|function)\s+(\w+)\s*(?:=\s*(?:\([^)]*\)|\w+)\s*=>|\([^)]*\)\s*\{)(.{0,300}?)(?:;\n|\n\n|$)/s', $text, $defs, PREG_SET_ORDER)) {
        foreach ($defs as $def) {
            // A page component is not a date helper just because its body formats a date: components are
            // PascalCase and take destructured props, helpers are camelCase and take a value.
            if (preg_match('/^[A-Z]/', $def[1]) || str_contains($def[2], 'return (<')) {
                continue;
            }

            $body = $def[2];

            if (! str_contains($body, 'new Date(') && ! preg_match('/'.$dayFormatter.'\(/', $body) && ! preg_match('/'.$schoolFormatter.'\(/', $body)) {
                continue;
            }

            if (preg_match('/'.$schoolFormatter.'\(/', $body)) {
                $schoolHelpers[$def[1]] = true;
            } elseif (preg_match('/'.$dayFormatter.'\(/', $body)) {
                $dayHelpers[$def[1]] = true;
            } elseif (preg_match($zoneRe, $body)) {
                $zoneHelpers[$def[1]] = true;
            }
        }
    }

    $helperNames = array_keys($zoneHelpers + $dayHelpers + $schoolHelpers);

    // A local handed a column — `const passSlipConfirmedAt = enrollment.clearanceConfirmedAt || null` — is that
    // column wearing another name. Without this the checker calls it UNBOUND, and the same read in two
    // spellings gets two different verdicts.
    $alias = [];

    foreach ($lines as $text) {
        if (! str_contains($text, '=>') && preg_match('/(?:const|let)\s+(\w+)\s*=\s*[\w.$?\[\]\'"]*?\.(\w+)\b/', $text, $a)) {
            $alias[$a[1]] = $a[2];
        }
    }

    foreach ($lines as $i => $line) {
        // Collect every converted value on the line: `new Date(x)` and `fmt(x)` for a known date helper.
        $targets = [];

        if (preg_match_all('/new Date\(\s*([^)]{0,120}?)\s*\)/', $line, $m)) {
            foreach ($m[1] as $arg) {
                $targets[] = ['arg' => $arg, 'zone' => true, 'src' => 'new Date'];
            }
        }

        if ($helperNames && preg_match_all('/\b('.implode('|', array_map('preg_quote', $helperNames)).')\(\s*([^,)]{1,120})/', $line, $m)) {
            foreach ($m[2] as $k => $arg) {
                $targets[] = ['arg' => $arg, 'zone' => isset($zoneHelpers[$m[1][$k]]), 'src' => $m[1][$k]];
            }
        }

        // The fixed shape must be counted too. A line that no longer holds `new Date(` would otherwise vanish
        // from the scan, and a checker whose coverage drops to zero still prints "0 problems".
        if (preg_match_all('/\b(formatCalendarDay|calendarYear|dayInputValue|formatSchoolDay|formatSchoolMoment)\(\s*([^,)]{1,120})/', $line, $m)) {
            foreach ($m[2] as $k => $arg) {
                $targets[] = ['arg' => $arg, 'zone' => false, 'src' => $m[1][$k]];
            }
        }

        if ($targets === []) {
            if (preg_match('/(formatCalendarDay|calendarYear|dayInputValue)\(/', $line) || str_contains($line, 'new Date(')) {
                $blind[] = sprintf('%s:%d  a date call whose argument this file cannot read: %s', $rel, $i + 1, mb_substr(trim($line), 0, 90));
            }

            continue;
        }

        foreach ($targets as $t) {
            $arg = trim($t['arg']);

            // A destructured props object is a component invocation, not a date being converted.
            if (str_starts_with($arg, '{')) {
                continue;
            }

            if ($arg === '') {
                $counts['NOW']++;

                continue;
            }

            // The browser's own clock is not a column, so it can never be bound to one: a print sheet stamps the
            // instant it was made. Rendered through the school formatter that is the school's day, which is the
            // rule — so it counts as NOW and is printed, and is never called UNBOUND.
            if (preg_match('#^(?:new\s+Date\(\)?|Date\.now\(\)?)$#i', $arg)) {
                $counts['NOW']++;
                $blind[] = sprintf('%s:%d  the client clock (%s), rendered by %s — no column to bind it to', $rel, $i + 1, $arg, $t['src']);

                continue;
            }

            $tail = preg_split('/[.?]/', $arg);
            $name = trim(end($tail));

            if (! isset($knownNames[$name]) && isset($alias[$name])) {
                $name = $alias[$name];
            }

            // A bare parameter is a helper's own variable — the call sites are what matter, so record it and move on.
            if (preg_match('/^(d|date|dateStr|value|v|iso|t|s)$/', $name)) {
                $counts['HELDER-PARAM']++;
                $blind[] = sprintf('%s:%d  "%s" is a helper parameter, judged at its call sites', $rel, $i + 1, $arg);

                continue;
            }

            if (! isset($knownNames[$name])) {
                $counts['UNBOUND']++;
                $problems++;
                printf("  UNBOUND      %s:%d  %s — nothing on the schema is named \"%s\"\n", $rel, $i + 1, mb_substr(trim($line), 0, 90), $name);

                continue;
            }

            $day = $isDayName($name);
            $slices = (bool) preg_match('/toISOString\(\)\s*\.?\s*split/', $line);
            $lineZone = (bool) preg_match($zoneRe, $line);
            $throughHelper = $t['src'] !== 'new Date' && isset($zoneHelpers[$t['src']]);

            // Either the line itself reads a zone off the Date, or the value goes through a local helper that
            // does — `fmtDate(p.clearanceStartDate)` moves a day exactly as loudly as the long form.
            $paints = $lineZone || $throughHelper;

            $usesFormatter = (bool) preg_match('/'.$dayFormatter.'\(/', $line)
                || ($t['src'] !== 'new Date' && isset($dayHelpers[$t['src']]));

            $usesSchoolFormatter = in_array($t['src'], ['formatSchoolDay', 'formatSchoolMoment'], true)
                || ($t['src'] !== 'new Date' && isset($schoolHelpers[$t['src']]));

            $differs = (bool) preg_match('/-\s*new Date\(/', $line) || (bool) preg_match('/new Date\([^)]*\)\s*-/', $line);

            // A day handed to the school-zone formatter is still handed to a zone: the value is re-projected,
            // which is the one thing a calendar day cannot survive.
            if ($day && $usesSchoolFormatter) {
                $counts['DAY-VIA-ZONE']++;
                $problems++;
                printf("  %-13s %s:%d  %s is a calendar day given to the school-zone formatter: %s\n", 'DAY-VIA-ZONE', $rel, $i + 1, $name, mb_substr(trim($line), 0, 100));

                continue;
            }

            if (! $day && $usesSchoolFormatter) {
                $counts['SCHOOL-FORMATTER']++;

                continue;
            }

            // A difference of two days shifts both ends the same way, so it stays right in every zone.
            if ($differs && ! $lineZone) {
                $counts['ARITHMETIC']++;

                continue;
            }

            if ($day && $usesFormatter) {
                $counts['DAY-FORMATTER']++;

                continue;
            }

            if ($day && $slices && ! $paints) {
                $counts['DAY-BY-UTC-SLICE']++;
                $blind[] = sprintf('%s:%d  %s reaches an <input type=date> by UTC slice — right only while the wire says UTC midnight', $rel, $i + 1, $name);

                continue;
            }

            // Lane 51: an institutional instant is read at the school, so it goes through the school-zone
            // formatter. Rendering it in the browser's own zone makes the same signature say one thing in Manila
            // and another here — 18:00Z is tomorrow at the school.
            $usesSchoolFormatter = (bool) preg_match('/(formatSchoolDay|formatSchoolMoment)\(/', $line)
                || ($t['src'] !== 'new Date' && isset($schoolHelpers[$t['src']]));

            if (! $day && $paints && ! $usesSchoolFormatter) {
                $counts['VIEWER-ZONE']++;
                $problems++;
                printf("  %-13s %s:%d  %s is an instant rendered in the viewer's zone: %s\n", 'VIEWER-ZONE', $rel, $i + 1, $name, mb_substr(trim($line), 0, 100));

                continue;
            }

            if ($day && $paints) {
                $kind = $name === 'effectiveYear' ? 'YEAR-VIA-ZONE' : 'DAY-VIA-ZONE';
                $counts[$kind]++;
                $problems++;
                printf("  %-13s %s:%d  %s is a calendar day run through the viewer's zone: %s\n", $kind, $rel, $i + 1, $name, mb_substr(trim($line), 0, 100));

                continue;
            }

            $counts['INSTANT-LOCAL']++;
        }
    }
}

// ---------------------------------------------------------------------------------------------
// 4. The server side (lane 85): a civil day taken from the machine's clock.
//
// The screens are not the only surface that answers "what day is it". A DATE column compared to
// `now()->toDateString()`, or stamped with it, is the same eight-hour error one layer down — the
// application's clock is UTC (`config/app.php`, and `+00:00` forced on every session), so between
// 16:00Z and midnight the school is already on the next date. `SchoolTime::today()` is the honest
// answer, and it is what Academicterms::covering() and the dashboard already use.
//
// tools/ is scanned too: PHPStan covers app/ only and pint.json excludes tools, so a day written
// there is invisible to both static gates.
// ---------------------------------------------------------------------------------------------
$machineDay = '/(now\(\)->toDateString\(\)|today\(\)->toDateString\(\)|now\(\)->format\(.[Ym-]*Y-m-d.\)|\bdate\(.[Ym-]*Y-m-d.\))/';
$phpFiles = [];
foreach (['app', 'tools'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $phpFiles[] = $f->getPathname();
        }
    }
}
sort($phpFiles);

if ($phpFiles === []) {
    fwrite(STDERR, "REFUSED: the server-day pass read no PHP file under app/ or tools/ — a clean verdict over nothing.\n");
    exit(1);
}

foreach ($phpFiles as $file) {
    $rel = str_replace('\\', '/', substr($file, strlen($root) + 1));
    if ($scope !== null && ! preg_match('/('.implode('|', array_map('preg_quote', $scope)).')/i', $rel)) {
        continue;
    }

    $fileLines = explode("\n", (string) file_get_contents($file));

    foreach ($fileLines as $i => $line) {
        if (preg_match($machineDay, $line) !== 1) {
            continue;
        }

        // Prose in a comment is not a live expression. The rule this file enforces is written down in
        // the code it audits, so a whole-line comment quoting the retired call must not read as a defect.
        $t = trim($line);
        if (str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '/*')) {
            continue;
        }

        // Already answered from the school's declaration: the machine-day pattern matched prose
        // or an older comment, not a live expression.
        if (str_contains($line, 'SchoolTime::')) {
            $counts['SCHOOL-DAY']++;

            continue;
        }

        // `'formIssuedDate' => now()->toDateString()` — a day written into a day column.
        if (preg_match("/'([a-zA-Z]*[Dd]ate|effectiveYear)'\s*=>/", $line, $m) === 1 && $isDayName($m[1])) {
            $counts['UTC-DAY-STAMP']++;
            $problems++;
            printf("  %-13s %s:%d  %s is a calendar day stamped from the machine's clock: %s\n",
                'UTC-DAY-STAMP', $rel, $i + 1, $m[1], mb_substr(trim($line), 0, 96));

            continue;
        }

        // `where('startDate', '<=', now()->toDateString())` — a day column filtered by the wrong day.
        if (preg_match("/where(?:Raw)?\(\s*'([a-zA-Z]+)'/", $line, $m) === 1 && $isDayName($m[1])) {
            $counts['UTC-DAY-COMPARE']++;
            $problems++;
            printf("  %-13s %s:%d  %s is a DATE column compared to the machine's day: %s\n",
                'UTC-DAY-COMPARE', $rel, $i + 1, $m[1], mb_substr(trim($line), 0, 96));

            continue;
        }

        // A hoisted day: `$today = now()->toDateString();` on one line, `where('startDate', '<=', $today)`
        // below it. The batch that fixed the terms filter moved the call into a variable, so a checker
        // that only reads the expression's own line would call that defect invisible.
        //
        // The bound has to be the call's own parentheses, not a semicolon: a fluent chain carries no
        // semicolons until its last line, so `[^;]*` ran forward and accused the first column named in
        // a later `where(...)` — it matched `semester`, a real column and not a day, and the case read clean.
        if (preg_match('/^\s*\$([a-zA-Z_]\w*)\s*=\s*.*(now\(\)|today\(\))/', $line, $mv) === 1) {
            $after = implode("\n", array_slice($fileLines, $i));
            if (preg_match("/where(?:Raw)?\(\s*'([a-zA-Z]+)'[^()]*\\$".preg_quote($mv[1], '/').'/', $after, $mc) === 1
                && $isDayName($mc[1])) {
                $counts['UTC-DAY-COMPARE']++;
                $problems++;
                printf("  %-13s %s:%d  %s is a DATE column filtered by the machine's day (hoisted into \$%s): %s\n",
                    'UTC-DAY-COMPARE', $rel, $i + 1, $mc[1], $mv[1], mb_substr(trim($line), 0, 96));

                continue;
            }
        }

        $counts['PHP-UNBOUND']++;
        $blind[] = sprintf('%s:%d  a machine-day expression bound to no day column this schema knows — %s',
            $rel, $i + 1, mb_substr(trim($line), 0, 90));
    }
}

printf("Files read: %d.  Column types from %d migrations, casts from %d models, %d date-shaped names known.\n",
    count($files), count($migrationFiles), count($modelFiles), count($knownNames));
printf("DAY-VIA-ZONE: %d   YEAR-VIA-ZONE: %d   DAY-FORMATTER: %d   DAY-BY-UTC-SLICE: %d   SCHOOL-FORMATTER: %d   VIEWER-ZONE: %d   INSTANT-LOCAL: %d   ARITHMETIC: %d   NOW: %d   helper-parameter: %d   UNBOUND: %d\n",
    $counts['DAY-VIA-ZONE'], $counts['YEAR-VIA-ZONE'], $counts['DAY-FORMATTER'], $counts['DAY-BY-UTC-SLICE'],
    $counts['SCHOOL-FORMATTER'], $counts['VIEWER-ZONE'],
    $counts['INSTANT-LOCAL'], $counts['ARITHMETIC'], $counts['NOW'], $counts['HELDER-PARAM'], $counts['UNBOUND']);
printf("Server side: UTC-DAY-STAMP: %d   UTC-DAY-COMPARE: %d   SCHOOL-DAY: %d   PHP-UNBOUND: %d\n",
    $counts['UTC-DAY-STAMP'], $counts['UTC-DAY-COMPARE'], $counts['SCHOOL-DAY'], $counts['PHP-UNBOUND']);

if ($blind !== []) {
    echo "\nBlind spots — reported, not counted as clean:\n";
    foreach (array_slice($blind, 0, 40) as $b) {
        echo '  '.$b."\n";
    }

    printf("  (%d total)\n", count($blind));
}

if ($counts['UNBOUND'] > 0) {
    fwrite(STDERR, sprintf("\nREFUSE: %d date cell(s) bound to nothing — an unread claim must not look like a checked one.\n", $counts['UNBOUND']));
    exit(1);
}

printf("\n%d problem(s).\n", $problems);
exit($problems > 0 ? 1 : 0);
