<?php

/**
 * Pass 439 — the population moved, so the living rows move with it; the dated rows do not.
 *
 * The timetable import screen is the 37th page that renders the shared table, and it is an OWED page: it shows a
 * preview the action hands over whole, so no server order exists for a header to arrange. That moves the
 * denominator every living register sentence states (36 -> 37) and the owed figure with it (6 -> 7). It is not
 * wiring a screen, so the wired figure stays at thirty and no screen's own case is added here.
 *
 * The same run re-took two instrument figures the batch moved without anyone asking: the pagination census now
 * answers 42 judged rows over 37 pages with 2 rows the reader cannot bind (Accounting's daily report, whose two
 * lists the transaction-reads batch gathered into one array), and the console/table-surface censuses walk 62 pages
 * and 42 table sites. Both sentences name the tool they came from, so lane 59 can weigh them again.
 *
 * The §1.1 rows that carry `34 of the 36 pages`, `eight of the thirty-six tables` and `the plan for the six screens`
 * are dated records of the days they were read and are left exactly as written (lane 60's and lane 72's rule). No
 * post-check in this pass asks the retired digits to be gone DOCUMENT-WIDE — only that each replaced sentence lost
 * its own needle, because those dated rows still hold the same words truthfully.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass439.php [--apply]
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

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
if (! is_file($docx)) {
    $fail('the authority docx is not on disk.');
}

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$readPart = static function (string $path, string $part) use ($fail): string {
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        $fail("could not open {$path} to read {$part}.");
    }
    $xml = $zip->getFromName($part);
    $zip->close();
    if (! is_string($xml) || $xml === '') {
        $fail("{$part} came back empty from {$path}.");
    }

    return $xml;
};

$edits = [
    [
        'from' => 'thirty of the thirty-six tables that render the shared component',
        'to' => 'thirty of the thirty-seven tables that render the shared component',
    ],
    [
        'from' => '6 of the 36 pages that use the shared table',
        'to' => '7 of the 37 pages that use the shared table',
    ],
    [
        'from' => 'the thirty-six tables using the shared component resolve into twenty-nine that receive a paginator'
            .' from their own index action, three that receive a plain collection (Accounting’s daily report, one'
            .' curriculum’s subject list, and the settings table) and four whose rows the browser maps over the prop'
            .' it was given; none of the three is a work queue, since a day’s receipts, the subjects of one'
            .' curriculum and the school’s own settings rows are bounded sets by design.',
        'to' => 'the thirty-seven pages that use the shared component resolve, page by page, into twenty-nine that'
            .' receive a paginator from their own index action, two that receive a plain collection (one'
            .' curriculum’s subject list and the school’s own settings table) and five whose rows the browser maps'
            .' over the prop it was given — the timetable import screen joins that bucket, its rows being the'
            .' preview its own action handed back — beside one page the reader cannot bind at all: the daily'
            .' report’s two lists, gathered into a single array by the batch that made the report read inside one'
            .' transaction, which the tool prints as UNBOUND rather than counting clean. Its row-level census is a'
            .' different unit and answers 42 judged rows over those 37 pages (PAGINATED 29, CLIENT-DERIVED 9,'
            .' COLLECTION 2, UNBOUND 2). None of the bounded sets is a work queue, and the shape reader says so per'
            .' page: of the seven pages that still offer no order, `lane215-plan-remaining-screens.php` answers that'
            .' not one of them pages its rows on the server, so no server order is owed on any of them.',
    ],
    [
        'from' => 'refuses a census when an unread third of them cannot be resolved',
        'to' => 'names a row it cannot bind instead of counting it clean',
    ],
    [
        'from' => '36 pages take the control, 4 do not',
        'to' => '37 pages take the control, 4 do not',
    ],
    [
        'from' => 'over 41 table sites in 61 pages',
        'to' => 'over 42 table sites in 62 pages',
    ],
];

// The four figures this pass writes come from the tools that print them, read in this session rather than typed.
$run = static function (string $tool) use ($fail): string {
    $out = (string) shell_exec('"'.PHP_BINARY.'" storage/app/scratch/'.$tool.' 2>&1');
    if (trim($out) === '') {
        $fail("{$tool} printed nothing, so its figures cannot be weighed.");
    }

    return $out;
};
$ask = [
    'lane140-measure-wired-screens.php' => [
        'pages that render the shared table: 37',
        'SCREEN reading — of those, the page reaches the sort helper: 30',
    ],
    'lane90-probe-pagination.php' => [
        'census by verdict: CLIENT-DERIVED 9; COLLECTION 2; PAGINATED 29; UNBOUND 2',
        'pages rendering the shared table: 37',
    ],
    'lane145-measure-table-surfaces.php' => ['shared control only : 37'],
    'lane142-measure-console-states.php' => ['table sites rendered: 42', 'pages walked: 62'],
];
foreach ($ask as $tool => $needles) {
    $out = $run($tool);
    foreach ($needles as $needle) {
        if (! str_contains($out, $needle)) {
            $fail("{$tool} did not print [{$needle}] — the figure would be typed from memory, not read.");
        }
    }
    echo 'measured  '.count($needles)." figure(s) from {$tool}.\n";
}

// ------------------------------------------------- stage 1: read, do not write
$xml = $readPart($docx, 'word/document.xml');
$before = [];
foreach ($edits as $i => $e) {
    $n = substr_count($xml, $escape($e['from']));
    if ($n !== 1) {
        $fail('edit '.$i." matches {$n} time(s), expected exactly 1: [".substr($e['from'], 0, 70).'…]');
    }
    if (preg_match('/[<>]/', $e['to']) === 1) {
        $fail('edit '.$i.' would write a `<` or `>` through a text edit (the engine refuses it, and a code arrow'
            .' belongs in prose here rather than in a run): '.substr($e['to'], 0, 60).'…');
    }
    if (str_contains($e['to'], $e['from'])) {
        $fail('edit '.$i.' is a no-op or an append whose retired needle survives inside the replacement: '
            .substr($e['to'], 0, 70).'…');
    }
    $before[$i] = substr_count($xml, $escape($e['to']));
}

echo 'stage 1: '.count($edits)." sentence(s) located, each exactly once.\n";

if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass439-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}

docxApply($docx, $backup, array_map(
    static fn (array $e): array => [$e['from'], $e['to'], null, 1],
    $edits
), [], true);

// ------------------------------------------------- read back from the archive
$read = $readPart($docx, 'word/document.xml');
foreach ($edits as $i => $e) {
    $gone = substr_count($read, $escape($e['from']));
    if ($gone !== 0) {
        unlink($backup);
        copy($backup, $docx);
        $fail('the retired sentence is still in the document ('.$gone.' copies): ['
            .substr($e['from'], 0, 60).'…] — the backup was restored.');
    }
    $now = substr_count($read, $escape($e['to']));
    if ($now !== $before[$i] + 1) {
        unlink($backup);
        copy($backup, $docx);
        $fail('edit '.$i.' came back '.$now.' time(s), expected '.($before[$i] + 1)
            .' (it occurred '.$before[$i].' time(s) before) — the backup was restored.');
    }
}

echo 'WROTE '.count($edits).' register sentences. backup: '.basename($backup)."\n";
