<?php
/**
 * Lane 69 mutant harness: proves tests/Feature/YearLevelVocabularyTest.php and the two print assertions
 * it moved can actually go red, one planted defect at a time, against the real suite.
 *
 * Every case plants a defect the ruling makes illegal — the ordinal re-spelled, the two definitions
 * pulled apart, the absence invented, a pick-list hand-written again, a print view bypassing the helper —
 * and requires the NAMED case to fail on it. A case whose needle matches nothing reports BAD rather than
 * passing, because a mutation that changes no byte measures a clean tree (lane 44's sleeping mutant).
 * The whole run refuses when the control is not green. Files are restored by hash in a finally.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);

$vocab = 'tests/Feature/YearLevelVocabularyTest.php';
$targets = [
    $vocab,
    'tests/Feature/Print/ClearanceSlipCopyDesignationTest.php',
    'tests/Feature/Print/PrintEnrollmentFormPayloadTest.php',
];

function readAll(array $paths): array
{
    global $root;
    $bytes = [];
    foreach ($paths as $p) {
        $bytes[$p] = (string) file_get_contents($root.'/'.$p);
    }

    return $bytes;
}

function restore(array $original): void
{
    global $root;
    foreach ($original as $p => $bytes) {
        $now = (string) file_get_contents($root.'/'.$p);
        if ($now !== $bytes) {
            file_put_contents($root.'/'.$p, $bytes);
            echo "    (restored {$p} by hash)\n";
        }
    }
}

/** Runs one file and returns [passedCount, the named test's verdict word, full output]. */
function runFile(string $file): array
{
    global $php;
    $out = shell_exec('"'.$php.'" artisan test '.escapeshellarg($file).' 2>&1');
    // Pest writes "Tests:  1 failed, 4 passed", so the passed figure is anywhere on the verdict line.
    if (! is_string($out) || ! preg_match('/Tests:[^\n]*?([0-9]+) passed/u', $out, $m)) {
        // A run that printed no verdict line is a broken instrument, not a result.
        return [-1, 'NO-VERDICT', (string) $out];
    }

    return [(int) $m[1], 'RAN', $out];
}

/** Did the named Pest case fail? Colourised output, so strip ANSI first. */
function caseFailed(string $output, string $caseTitle): bool
{
    $plain = preg_replace('/\e\[[0-9;]*m/', '', $output);
    $at = preg_match('/(✓|⨯|FAIL|✏)[^\n]*'.preg_quote($caseTitle, '/').'/u', $plain, $m);

    return $at === 1 && $m[1] !== '✓';
}

function mutate(string $file, string $from, string $to): bool
{
    global $root;
    $path = $root.'/'.$file;
    $bytes = (string) file_get_contents($path);
    if (substr_count($bytes, $from) !== 1) {
        echo '    BAD: needle matched '.substr_count($bytes, $from).' times in '.$file."\n";

        return false;
    }
    file_put_contents($path, str_replace($from, $to, $bytes));

    return true;
}

$cases = [
    [
        'name' => 'ordinal-rejected',
        'why' => 'the helper answers "Year 2" again, which is the vocabulary the owner ruled out',
        'file' => 'app/Support/YearLevel.php',
        'from' => "return \$level === null || \$level < 1 ? null : self::ordinal(\$level).' Year';",
        'to' => "return \$level === null || \$level < 1 ? null : 'Year '.\$level;",
        'test' => $vocab,
        'case' => 'the helper answers the words the school writes',
    ],
    [
        'name' => 'js-vocabulary-drifted',
        'why' => 'the desk screen builds "Year N" while the paper builds "Nth Year"',
        'file' => 'resources/js/Components/ui/statusLabel.js',
        'from' => 'return `${ordinalSuffix(level)} Year`;',
        'to' => 'return `Year ${level}`;',
        'test' => $vocab,
        'case' => 'the javascript formatter and the php helper agree level by level',
    ],
    [
        'name' => 'js-suffix-map-drifted',
        'why' => 'the first level is spelled "nd", so the two definitions disagree on 1',
        'file' => 'resources/js/Components/ui/statusLabel.js',
        'from' => "({ 1: 'st', 2: 'nd', 3: 'rd' })[level % 10]",
        'to' => "({ 1: 'nd', 2: 'nd', 3: 'rd' })[level % 10]",
        'test' => $vocab,
        'case' => 'the javascript formatter and the php helper agree level by level',
    ],
    [
        'name' => 'exception-bounds-drifted',
        'why' => 'the 11th-13th window narrowed to 11, so 12 and 13 stop being exceptions on one side only',
        'file' => 'resources/js/Components/ui/statusLabel.js',
        'from' => 'if (level % 100 >= 11 && level % 100 <= 13) return `${level}th`;',
        'to' => 'if (level % 100 >= 11 && level % 100 <= 11) return `${level}th`;',
        'test' => $vocab,
        'case' => 'the javascript formatter and the php helper agree level by level',
    ],
    [
        'name' => 'absence-invented-as-a-level',
        'why' => 'the helper spells year 0, so a print view can no longer dash an unrecorded level',
        'file' => 'app/Support/YearLevel.php',
        'from' => 'return $level === null || $level < 1 ? null : self::ordinal($level).\' Year\';',
        'to' => 'return $level === null ? null : self::ordinal($level).\' Year\';',
        'test' => $vocab,
        'case' => 'a level that was never recorded answers nothing',
    ],
    [
        'name' => 'picker-hand-spelled-again',
        'why' => 'a desk pick-list re-writes its own "Year 2" label while the tables read the formatter',
        'file' => 'resources/js/Pages/Blocking/Index.jsx',
        'from' => '...yearLevelChoices().map((choice) => ({ value: String(choice.value), label: choice.label })),',
        'to' => "{ value: '1', label: 'Year 1' },\n    { value: '2', label: 'Year 2' },\n    { value: '3', label: 'Year 3' },",
        'test' => $vocab,
        'case' => 'no surface spells a year level by hand',
    ],
    [
        'name' => 'print-view-bypasses-helper',
        'why' => 'the block schedule prints the bare integer, which neither the line sweep nor eslint can see',
        'file' => 'resources/views/prints/block-schedule.blade.php',
        // \$ throughout: a double-quoted needle would interpolate the blade variable into nothing and
        // match no byte, which is the lane 44 mutant that sleeps.
        'from' => "{{ \\App\\Support\\YearLevel::format(\$block->yearLevel) ?? 'N/A' }}",
        'to' => '{{ $block->yearLevel }}',
        'test' => $vocab,
        'case' => 'every print view that names a level reads the one helper',
    ],
    [
        'name' => 'print-view-spells-level-by-hand',
        'why' => 'the certificate builds "Year " . the integer inside the template',
        'file' => 'resources/views/prints/enrollment-certificate.blade.php',
        'from' => "{{ \\App\\Support\\YearLevel::format(\$enrollment->yearLevel) ?? 'Year N/A' }}",
        'to' => "{{ 'Year '.\$enrollment->yearLevel }}",
        'test' => $vocab,
        'case' => 'no surface spells a year level by hand',
    ],
    [
        'name' => 'pick-list-label-drifted',
        'why' => 'yearLevelChoices builds its own wording instead of calling the formatter — a drift the hand-spelling scan cannot see, because no literal "Year N" is involved',
        'file' => 'resources/js/Components/ui/statusLabel.js',
        'from' => 'return levels.map((level) => ({ value: level, label: formatYearLevel(level) }));',
        'to' => 'return levels.map((level) => ({ value: level, label: `Level ${level}` }));',
        'test' => $vocab,
        'case' => 'the pick list builds its labels by calling the one formatter',
    ],
    [
        'name' => 'slip-paper-reverts-to-year-2',
        'why' => 'the paper the student is handed stops matching the school\'s own slip',
        'file' => 'app/Support/YearLevel.php',
        'from' => "return \$level === null || \$level < 1 ? null : self::ordinal(\$level).' Year';",
        'to' => "return \$level === null || \$level < 1 ? null : 'Year '.\$level;",
        'test' => 'tests/Feature/Print/ClearanceSlipCopyDesignationTest.php',
        'case' => 'the program is led by its course code the way the school writes it',
    ],
    [
        'name' => 'form-payload-stops-reading-the-helper',
        'why' => 'the identification block prints the old spelling again',
        'file' => 'resources/views/prints/enrollment-form.blade.php',
        'from' => "{{ \\App\\Support\\YearLevel::format(\$enrollment->yearLevel) ?? 'Year N/A' }}",
        'to' => "{{ 'Year '.\$enrollment->yearLevel }}",
        'test' => 'tests/Feature/Print/PrintEnrollmentFormPayloadTest.php',
        'case' => 'the form prints the identification block on its face',
    ],
];

$original = readAll(array_unique(array_merge(
    $targets,
    array_column($cases, 'file'),
    ['app/Support/YearLevel.php', 'resources/js/Components/ui/statusLabel.js']
)));

try {
    echo "CONTROL — the three files as they stand must be green:\n";
    $controlGreen = true;
    foreach ($targets as $f) {
        [$passed, $verdict, $out] = runFile($f);
        if ($verdict === 'NO-VERDICT' || $passed < 1) {
            echo "  ! {$f} printed no green verdict\n";
            echo preg_replace('/\e\[[0-9;]*m/', '', substr($out, 0, 1500))."\n";
            $controlGreen = false;
        } else {
            echo "  ok {$f}: {$passed} passed\n";
        }
    }
    if (! $controlGreen) {
        echo "\nREFUSED — the suite is not green before any defect was planted, so no red below would prove anything.\n";
        exit(1);
    }

    echo "\nPLANTING ONE DEFECT PER CASE:\n";
    $asleep = [];
    foreach ($cases as $c) {
        echo "  case {$c['name']} — {$c['why']}\n";
        if (! mutate($c['file'], $c['from'], $c['to'])) {
            $asleep[] = $c['name'].' (needle did not apply)';

            continue;
        }
        [, , $out] = runFile($c['test']);
        $red = caseFailed($out, $c['case']);
        restore([$c['file'] => $original[$c['file']]]);

        if ($red) {
            echo "    ok killed by: {$c['case']}\n";
        } else {
            echo "    BAD: {$c['case']} stayed green with the defect planted\n";
            $asleep[] = $c['name'];
        }
    }

    restore($original);
    echo "\nPOST-RESTORE CONTROL (the tree must read exactly as it did before):\n";
    foreach ($targets as $f) {
        [$passed, $verdict] = runFile($f);
        echo $verdict !== 'NO-VERDICT' && $passed >= 1
            ? "  ok {$f}: {$passed} passed\n"
            : "  ! {$f} is NOT green after the restore\n";
    }

    if ($asleep !== []) {
        echo "\nRESULT: ".count($asleep).' case(s) failed to prove the guard: '.implode(', ', $asleep)."\n";
        exit(1);
    }
    echo "\nRESULT: all ".count($cases)." planted defects were killed by the case that names them.\n";
    exit(0);
} finally {
    restore($original);
}
