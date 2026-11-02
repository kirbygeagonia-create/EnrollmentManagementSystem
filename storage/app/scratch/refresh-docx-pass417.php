<?php

/**
 * Pass 417 — three figures the delivered run moved, none of them typed from memory.
 *
 * The whole-suite run that carries the twenty-first table answers `DashboardQueueCountMatchesDeskTest` with a case
 * and ten assertions more than the documents state (the ninth dashboard card's blocking case grew the class), and
 * `lane49-zero-shown-as-absence.php` now prints a different blind-spot count because the new page adds lines of the
 * shape it counts. Both were named by the gates rather than noticed here: `lane58-suite-claims.php` refused on the
 * class claim and `lane59-instrument-counts.php` refused on the instrument figure in two surfaces.
 *
 * Every value is READ first — from the delivered junit for the class, from the tool's own footer for its blind spot —
 * and each markdown needle is asserted to occur exactly once per file before it is replaced. The docx half probes
 * `word/document.xml` for contiguity first, because the flat dump joins `<w:t>` runs with a space and a phrase that
 * reads continuously on screen can be five runs in the file.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass417.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}
$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

// ---------------------------------------------------------------- values read, not recalled
$junit = $root.'/storage/app/scratch/lane200-suite-junit.xml';
if (! is_file($junit)) {
    $fail('no delivered junit to read the class figures from — run the suite first.');
}
preg_match_all('/name="[^"]*DashboardQueueCountMatchesDeskTest"[^>]*tests="(\d+)"[^>]*assertions="(\d+)"/',
    (string) file_get_contents($junit), $cm, PREG_SET_ORDER);
if (count($cm) !== 1 || ! isset($cm[0][1], $cm[0][2])) {
    $fail('the delivered junit answers '.count($cm).' entry/entries and the pair was read as '
        .json_encode($cm[0] ?? null).'; a class total must come from one element with both attributes.');
}
[$cases, $assertions] = [(int) $cm[0][1], (int) $cm[0][2]];

$lane49Out = (string) shell_exec('"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" '
    .escapeshellarg($root.'/storage/app/scratch/lane49-zero-shown-as-absence.php').' 2>&1');
preg_match('/Not a money\/percent figure the guard itself prints:\s*(\d+)/', $lane49Out, $lm);
if ($lm === []) {
    $fail('lane49 printed no "Not a money/percent figure" tally, so its blind-spot count cannot be read.');
}
$linesNow = (int) $lm[1];
printf("read: dashboard class %d cases / %d assertions; lane49 blind-spot lines %d\n", $cases, $assertions, $linesNow);

// ---------------------------------------------------------------- stage 1: the markdown surfaces
$mdEdits = [
    'AGENTS.md' => [
        ['`tests/Feature/Dashboard/DashboardQueueCountMatchesDeskTest.php` (7 cases, 47 assertions)',
         sprintf('`tests/Feature/Dashboard/DashboardQueueCountMatchesDeskTest.php` (%d cases, %d assertions)', $cases, $assertions)],
        ['363 lines where', $linesNow.' lines where'],
    ],
    'Documentation/Prompt-Requirement-Matrix-2026-10-26.md' => [
        ['`tests/Feature/Dashboard/DashboardQueueCountMatchesDeskTest.php` (7 cases, 47 assertions)',
         sprintf('`tests/Feature/Dashboard/DashboardQueueCountMatchesDeskTest.php` (%d cases, %d assertions)', $cases, $assertions)],
    ],
];
$planned = [];
foreach ($mdEdits as $file => $pairs) {
    $body = (string) file_get_contents($root.'/'.$file);
    foreach ($pairs as [$from, $to]) {
        $hits = substr_count($body, $from);
        if ($hits !== 1) {
            $fail("markdown pre-flight: [{$from}] occurs {$hits} time(s) in {$file}, wanted exactly 1.");
        }
        if ($from === $to) {
            $fail("markdown pre-flight: the pair for {$file} is a no-op — the document already carries the reading.");
        }
        // A markdown table row is ONE line: assert the replacement cannot introduce a newline.
        if (str_contains($to, "\n")) {
            $fail("markdown pre-flight: the replacement for {$file} carries a newline, which would split a row.");
        }
        $body = str_replace($from, $to, $body);
        $planned[$file] = $body;
    }
}
foreach ($planned as $file => $body) {
    if (! $apply) {
        continue;
    }
    $w = @file_put_contents($root.'/'.$file, $body);
    if ($w !== strlen($body)) {
        $fail("the write of {$file} returned ".var_export($w, true).'.');
    }
}
printf("stage 1: %d markdown edit(s) across %d file(s) — %s\n",
    array_sum(array_map('count', $mdEdits)), count($mdEdits), $apply ? 'written' : 'DRY RUN');

// ---------------------------------------------------------------- stage 2: the docx surface
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open (ZipArchive answers a truthy error code, so this is the real guard).');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}
$from = '363 lines where the tested operand';
$to = $linesNow.' lines where the tested operand';
$hits = substr_count($xml, $from);
if ($hits !== 1) {
    $fail("docx pre-flight: the phrase [{$from}] occurs {$hits} time(s) in word/document.xml — if it is split "
        ."across runs this pass must go through the cell kind instead of a text needle.");
}
if (substr_count($xml, $to) !== 0) {
    $fail('docx pre-flight: the replacement is already in the document — this pass has run.');
}

docxApply($docx, $root.'/storage/app/scratch/pass417-engine-backup.docx', [[$from, $to]], [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass417-backup-'.date('Ymd-His').'.docx';
if (! copy($root.'/storage/app/scratch/pass417-engine-backup.docx', $backup)) {
    $fail('the second backup copy could not be written.');
}

// Read back both halves before believing anything.
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the written docx did not reopen.');
}
$read = (string) $zip->getFromName('word/document.xml');
$zip->close();
if (substr_count($read, $from) !== 0 || substr_count($read, $to) !== 1) {
    copy($backup, $docx);
    $fail('docx read-back refused — restored from the engine backup.');
}
foreach ($mdEdits as $file => $pairs) {
    $body = (string) file_get_contents($root.'/'.$file);
    foreach ($pairs as [$f, $t]) {
        if (substr_count($body, $f) !== 0 || substr_count($body, $t) !== 1) {
            $fail("markdown read-back refused for {$file} — [{$t}] is not the only form standing.");
        }
    }
}
$prev = libxml_use_internal_errors(true);
$doc = new DOMDocument;
$parsed = $doc->loadXML($read);
libxml_use_internal_errors($prev);
if (! $parsed) {
    copy($backup, $docx);
    $fail('document.xml no longer parses — restored.');
}

// ---------------------------------------------------------------- stage 3: the gates that raised this
exec('"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" '.escapeshellarg($root.'/storage/app/scratch/dump-docx-flat.php').' 2>&1');
foreach ([
    'lane58-suite-claims.php' => ' --junit=storage/app/scratch/lane200-suite-junit.xml --local-suite=storage/app/scratch/lane200-suite.log',
    'lane59-instrument-counts.php' => ' --readings=storage/app/scratch/lane59-readings.json',
] as $tool => $args) {
    $out = [];
    $code = 0;
    exec('"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" '.escapeshellarg($root.'/storage/app/scratch/'.$tool)
        .$args.' 2>&1', $out, $code);
    $body = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out));
    printf("%s exit=%d :: %s\n", $tool, $code, str_replace("\n", ' / ',
        implode("\n", array_slice(explode("\n", $body), -2))));
    if ($code !== 0) {
        foreach (explode("\n", $body) as $line) {
            if (str_contains($line, 'STALE') || str_contains($line, 'UNBOUND')) {
                echo '   > '.mb_substr(trim($line), 0, 200)."\n";
            }
        }
        $fail("{$tool} still refuses after the write — the reading above belongs to the gate, not to this pass.");
    }
}

printf("written; class %d/%d and instrument %d re-taken in three surfaces; backup %s\n",
    $cases, $assertions, $linesNow, basename($backup));
