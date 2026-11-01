<?php

/**
 * Lane 140 — how many desk tables actually sort, derived from the tree rather than carried in prose.
 *
 * §28.3 G-40 and the requirement matrix both state a wired count and an owed count for one denominator: the pages
 * that render the shared table. Every batch so far re-typed those two numbers by hand, and the matrix's own Status
 * cell had fallen four tables behind its neighbouring sentence because nothing compares a cell's figure with the
 * sentence beside it (lane 64's membership defect, lane 61's tally rule, reached from the column side).
 *
 * So the numerator is measured twice, from two different surfaces, and the two must agree:
 *   SCREEN — a page under resources/js/Pages that renders <DataTable and whose own module graph reaches
 *            listSort.js (the screen half of the mechanism);
 *   HANDLER — an action in app/ that calls ListSort::apply (the server half).
 * A page can render the table with no order offered and a handler can apply an order no header sends, so the two
 * readings are printed beside each other and any difference is named, not averaged.
 *
 * Usage: php storage/app/scratch/lane140-measure-wired-screens.php [--json=<path>]
 *        php storage/app/scratch/lane140-measure-wired-screens.php --check-matrix
 *        php storage/app/scratch/lane140-measure-wired-screens.php --check-docx
 */
$root = dirname(__DIR__, 3);
chdir($root);

$jsonTo = null;
$dumpOverride = null;
$matrixOverride = null;
foreach ($argv as $i => $a) {
    if ($i === 0) {
        continue;
    }
    if (str_starts_with($a, '--json=')) {
        $jsonTo = substr($a, 7);

        continue;
    }
    // An addressable path to the dump, so a harness can prove the dated-row rule is read rather than accepted:
    // --dump= against a copy whose dated row is re-spelled as living must make the same claim fire STALE.
    if (str_starts_with($a, '--dump=')) {
        $dumpOverride = substr($a, 7);

        continue;
    }
    // The same for the matrix, so a planted stale figure never has to be written into the tracked document.
    if (str_starts_with($a, '--matrix=')) {
        $matrixOverride = substr($a, 9);

        continue;
    }
    if (in_array($a, ['--check-matrix', '--check-docx'], true)) {
        continue;
    }
    fwrite(STDERR, 'REFUSED: this tool takes --json=<path>, --dump=<path>, --matrix=<path>, --check-matrix or '
        ."--check-docx and received '{$a}'.\n");

    exit(1);
}

if ($dumpOverride !== null && ! in_array('--check-docx', $argv, true)) {
    fwrite(STDERR, "REFUSED: --dump= only means something beside --check-docx, which was not given.\n");

    exit(1);
}

if ($matrixOverride !== null && ! in_array('--check-matrix', $argv, true)) {
    fwrite(STDERR, "REFUSED: --matrix= only means something beside --check-matrix, which was not given.\n");

    exit(1);
}

$pagesRoot = $root.'/resources/js/Pages';
if (! is_dir($pagesRoot)) {
    fwrite(STDERR, "REFUSED: {$pagesRoot} is not a directory, so the population cannot be walked.\n");

    exit(1);
}

/** Every file under a directory with the given extension, by absolute path. */
$walk = static function (string $dir, string $ext) use (&$walk): array {
    if (! is_dir($dir)) {
        fwrite(STDERR, "REFUSED: {$dir} is not a directory, so its {$ext} population cannot be walked.\n");

        exit(1);
    }
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.'.$ext)) {
            $out[] = $f->getPathname();
        }
    }
    sort($out);

    return $out;
};

$files = $walk($pagesRoot, 'jsx');
if ($files === []) {
    fwrite(STDERR, "REFUSED: the walk of resources/js/Pages found no .jsx file at all.\n");

    exit(1);
}

// The barrel is how a page reaches the helper, so `@/Components/ui` counts as reaching listSort.js.
$rendersTable = [];
$usesSortHelper = [];
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    $rel = str_replace('\\', '/', substr($f, strlen($root) + 1));
    $usesDataTable = (bool) preg_match('/\bDataTable\b/', $src);
    $importsSort = str_contains($src, 'listSort')
        || (bool) preg_match('/nextSortDirection|sortParams/', $src);
    if ($usesDataTable) {
        $rendersTable[] = $rel;
    }
    if ($usesDataTable && $importsSort) {
        $usesSortHelper[] = $rel;
    }
}

// The server half: every action that calls the helper.
$handlerSites = [];
$appFiles = $walk($root.'/app', 'php');
if ($appFiles === []) {
    fwrite(STDERR, 'REFUSED: the walk of app/ found no .php file, so the handler reading would answer 0 about a '
        ."population it never opened.\n");

    exit(1);
}
foreach ($appFiles as $f) {
    $src = (string) file_get_contents($f);
    if (! str_contains($src, 'ListSort::apply')) {
        continue;
    }
    $lines = explode("\n", $src);
    $action = '(unknown)';
    foreach ($lines as $n => $line) {
        if (! str_contains($line, 'ListSort::apply')) {
            continue;
        }
        for ($b = $n; $b >= 0; $b--) {
            if (preg_match('/^\s*(?:public|protected|private)\s+function\s+(\w+)/', $lines[$b], $m)) {
                $action = $m[1];

                break;
            }
        }
        $rel = str_replace('\\', '/', substr($f, strlen($root) + 1));
        $handlerSites[] = basename($rel).'::'.$action;
    }
}

$screenCount = count($usesSortHelper);
$rawSiteCount = count($handlerSites);
$handlerSites = array_values(array_unique($handlerSites));
sort($handlerSites);
$handlerCount = count($handlerSites);
$tableCount = count($rendersTable);

if ($handlerCount === 0) {
    fwrite(STDERR, "REFUSED: no action in app/ calls ListSort::apply, which cannot be true while {$screenCount} "
        ."page(s) reach the screen half. The reader is blind, not the tree empty.\n");

    exit(1);
}

printf("pages that render the shared table: %d\n", $tableCount);
printf("SCREEN reading — of those, the page reaches the sort helper: %d\n", $screenCount);
printf("HANDLER reading — distinct actions in app/ that call ListSort::apply: %d (%d call site(s))\n",
    $handlerCount, $rawSiteCount);

printf("\nwired pages (%d):\n", $screenCount);
foreach ($usesSortHelper as $p) {
    echo '  '.$p."\n";
}
printf("\nhandlers (%d):\n", $handlerCount);
foreach ($handlerSites as $h) {
    echo '  '.$h."\n";
}

// The two readings count different units and are printed apart rather than reconciled into one number: an action
// can serve two lists, and a page can reach the helper for a table its action does not order.
printf("\nSCREEN %d vs HANDLER %d — %s\n", $screenCount, $handlerCount,
    $screenCount === $handlerCount
        ? 'one wired page per wired action on this reading'
        : 'different units; the register\'s claim is about PAGES, so the SCREEN figure is the one it carries');

// A page that imports the helper but renders no shared table is a different thing from a wired desk table, and the
// count the register carries is about desk tables only.
$helperWithoutTable = array_values(array_filter(
    $files,
    static function (string $f): bool {
        $src = (string) file_get_contents($f);

        return (str_contains($src, 'listSort') || preg_match('/nextSortDirection|sortParams/', $src) === 1)
            && ! preg_match('/\bDataTable\b/', $src);
    }
));
if ($helperWithoutTable !== []) {
    echo "\nIMPORTS THE HELPER BUT RENDERS NO SHARED TABLE (not counted):\n";
    foreach ($helperWithoutTable as $f) {
        echo '  '.str_replace('\\', '/', substr($f, strlen($root) + 1))."\n";
    }
}

if ($jsonTo !== null) {
    $written = @file_put_contents($jsonTo, json_encode([
        'tablePages' => $tableCount,
        'wiredScreens' => $screenCount,
        'wiredScreenPaths' => $usesSortHelper,
        'wiredHandlers' => $handlerCount,
        'handlers' => $handlerSites,
    ], JSON_PRETTY_PRINT));
    if ($written === false) {
        fwrite(STDERR, "REFUSED: could not write the JSON to '{$jsonTo}'.\n");

        exit(1);
    }
    printf("\nwrote %s\n", $jsonTo);
}

/**
 * Every WIRED / OWED claim a block of prose makes about this one count, extracted with its expected value.
 *
 * Extraction, not a remembered phrase list: a checker handed a frozen sentence stops judging the moment the prose
 * is re-worded, and prints its cleanest verdict exactly then. Each pattern names the shape it reads, and a run that
 * extracts nothing refuses, because "0 stale" over 0 claims is the fake census this repository has met five times.
 *
 * @return array<int, array{0: string, 1: int, 2: string}> [matched text, asserted number, which count it claims]
 */
function wired_owed_claims(string $t): array
{
    $word = 'eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|\d{2}';
    $out = [];
    $forms = [
        ['the ('.$word.') wired pages call', 'wired'],
        ['the ('.$word.') wired (?:screens|tables)', 'wired'],
        ['('.$word.') of the 36 (?:pages|screens)[^.|]{0,70}?use it', 'wired'],
        ['('.$word.') of thirty-six tables use it', 'wired'],
        ['('.$word.') screens use it', 'wired'],
        ['('.$word.') of the 36 pages[^.|]{0,60}still offer no order', 'owed'],
        ['the other ('.$word.') (?:pages|still show)', 'owed'],
        ['That (\d{2}) counts', 'owed'],
        ['still owed: (\d{2}) of the 36', 'owed'],
    ];
    foreach ($forms as [$body, $kind]) {
        $pattern = '/'.str_replace('(', '(', $body).'/iu';
        if (@preg_match_all($pattern, $t, $m) > 0) {
            foreach ($m[0] as $i => $whole) {
                $out[] = [$whole, (string) $m[1][$i], $kind];
            }
        }
    }

    return $out;
}

/** A spelled number is read as a number; anything else is compared as the word it is. */
function claim_value(string $token, array $map): ?int
{
    $lower = strtolower($token);
    if (is_numeric($lower)) {
        return (int) $lower;
    }

    return $map[$lower] ?? null;
}

$WORDNUM = [
    'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15,
    'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19, 'twenty' => 20,
    'thirty' => 30,
];

function judge_claims(string $label, string $text, int $wired, int $owed, array $map): void
{
    $claims = wired_owed_claims($text);
    if ($claims === []) {
        fwrite(STDERR, "REFUSED: {$label} yielded no wired/owed claim at all — the prose shape moved and this tool "
            ."is now judging nothing.\n");

        exit(1);
    }
    $bad = [];
    foreach ($claims as [$whole, $raw, $kind]) {
        $value = claim_value((string) $raw, $map);
        if ($value === null) {
            $bad[] = "unread number [{$whole}]";

            continue;
        }
        $want = $kind === 'wired' ? $wired : $owed;
        if ($value !== $want) {
            $bad[] = sprintf('%s claims %d, the tree answers %d [%s]', strtoupper($kind), $value, $want, substr($whole, 0, 58));
        }
    }
    printf("\n%s: %d wired/owed claim(s) extracted — wired %d, owed %d, denominator %d.\n",
        $label, count($claims), $wired, $owed, $wired + $owed);
    if ($wired + $owed !== 36 && $wired + $owed !== 0) {
        fwrite(STDERR, "REFUSED: wired {$wired} + owed {$owed} = ".($wired + $owed).', not the 36 the sentence names. '
            ."The denominator itself moved and the registers still add to a retired total.\n");

        exit(1);
    }
    if ($bad !== []) {
        foreach ($bad as $b) {
            fwrite(STDERR, "  STALE  {$b}\n");
        }
        fwrite(STDERR, 'REFUSED: '.count($bad)." claim(s) in {$label} disagree with the tree.\n");

        exit(1);
    }
    echo "  AGREES — every claim equals the number the tree answers.\n";
}

if (in_array('--check-matrix', $argv, true)) {
    $matrixPath = $matrixOverride ?? $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
    if (! file_exists($matrixPath)) {
        fwrite(STDERR, "REFUSED: no matrix at '{$matrixPath}'.\n");

        exit(1);
    }
    $text = (string) file_get_contents($matrixPath);
    $row = '';
    foreach (explode("\n", $text) as $line) {
        if (str_starts_with($line, '| G40 |')) {
            $row = $line;

            break;
        }
    }
    if ($row === '') {
        fwrite(STDERR, "REFUSED: no | G40 | row in the matrix — the register lost a row, or the tool's key aged.\n");

        exit(1);
    }
    judge_claims('the matrix G40 row', $row, $screenCount, $tableCount - $screenCount, $WORDNUM);
}

if (in_array('--check-docx', $argv, true)) {
    // Read the flat dump, not document.xml: the document's own rows are the unit, and a §1.1 revision row whose
    // first cell is a bare date is that day's RECORD — lane 72's rule, which this checker had to learn the hard
    // way. Its first run called row 37's "34 of the 36 pages" STALE; that figure was true on 2026-10-29, and
    // re-typing it would make the row state a number that was not true on its own day.
    $dumpPath = $dumpOverride ?? $root.'/storage/app/scratch/docx-flat.txt';
    if (! file_exists($dumpPath)) {
        fwrite(STDERR, "REFUSED: no flat dump at '{$dumpPath}'. Re-dump with dump-docx-flat.php before believing "
            ."a verdict about the document.\n");

        exit(1);
    }
    $docxPath = $root.'/Documentation/EMS Complete Documentation.docx';
    if ($dumpOverride === null && filemtime($dumpPath) < filemtime($docxPath)) {
        fwrite(STDERR, "REFUSED: the dump is older than the docx, so it describes a previous build.\n");

        exit(1);
    }
    $rows = 0;
    $judged = 0;
    $dated = 0;
    $bad = [];
    foreach (file($dumpPath, FILE_IGNORE_NEW_LINES) as $i => $line) {
        $claims = wired_owed_claims($line);
        if ($claims === []) {
            continue;
        }
        $rows++;
        // A dated record row: the leading cell begins with a bare ISO date.
        $isDated = preg_match('/^ROW \d+: 20\d\d-\d\d-\d\d\p{Z}/u', $line) === 1;
        foreach ($claims as [$whole, $raw, $kind]) {
            if ($isDated) {
                $dated++;
                printf("  DATED  dump line %d: [%s] — that day's record, not judged\n", $i + 1, substr($whole, 0, 54));

                continue;
            }
            $judged++;
            $value = claim_value($raw, $WORDNUM);
            $want = $kind === 'wired' ? $screenCount : $tableCount - $screenCount;
            if ($value === null) {
                $bad[] = "unread number [{$whole}]";
            } elseif ($value !== $want) {
                $bad[] = sprintf('%s claims %d, the tree answers %d [%s]', strtoupper($kind), $value, $want, substr($whole, 0, 58));
            }
        }
    }
    if ($judged === 0) {
        fwrite(STDERR, 'REFUSED: the dump yielded no JUDGED wired/owed claim — the living sentences moved out of '
            ."reach and this run would otherwise certify a document it never read.\n");

        exit(1);
    }
    printf("\ndocx: %d claim(s) over %d row(s) — %d judged, %d carried by a dated §1.1 row and named, not re-typed.\n",
        $judged + $dated, $rows, $judged, $dated);
    if ($bad !== []) {
        foreach ($bad as $b) {
            fwrite(STDERR, "  STALE  {$b}\n");
        }
        fwrite(STDERR, 'REFUSED: '.count($bad)." claim(s) in the living rows disagree with the tree.\n");

        exit(1);
    }
    echo "  AGREES — every living claim equals the number the tree answers.\n";
}

exit(0);
