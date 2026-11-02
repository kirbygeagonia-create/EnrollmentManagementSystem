<?php

/**
 * Pass 403 — G-37 closed: the audit trail names the act, and every place that told a reader to filter the log by
 * `created`/`updated`/`deleted` for a desk act is re-taken in the same batch.
 *
 * The owner ruled item 4 of the 2026-10-29 sheet on 2026-11-02 — "name the act, one word list" — and the build
 * landed: `App\Support\AuditAct` holds the vocabulary and the route map, `App\Http\Middleware\NameAuditAct` stamps
 * the act for every row one desk request writes, and the observer copies it into `auditlogs.action`. Live `ems`
 * was read before the wording was written: the column held `created` 3,252, `updated` 271 and `deleted` 5 rows and
 * nothing else, while the audit desk's own filter offered twelve words, nine of which no code in the repository
 * has ever written.
 *
 * Three sites in this document tell a reader which word to filter on, so three sites move with the mechanism —
 * §20.2's purpose row, and the two rows of the "what answers which question" table that name `updated` as the way
 * to find a superseded signature or a voided receipt. §13's long narrative paragraph carries the same phrase
 * inside a cell of styled runs; no text needle spans it and the `para` kind would flatten the citations beside it,
 * so this pass PRINTS the run boundaries and leaves the sentence, rather than claiming a coverage it does not have.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass403.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
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
    $fail('the authority docx is not on this path.');
}

// ---------------------------------------------------------------- read the vocabulary from the code, not from memory
$actSource = (string) file_get_contents($root.'/app/Support/AuditAct.php');
preg_match_all("/public const [A-Z_]+ = '([a-z_.]+)';/", $actSource, $am);
$names = array_values(array_unique(array_filter($am[1], static fn (string $w): bool => ! str_contains($w, '/'))));
if (count($names) < 10) {
    $fail('the act vocabulary read back as '.count($names).' names from app/Support/AuditAct.php; refusing to write a count the code does not hold.');
}
$actCount = count($names);

// The words the log actually holds, asked of the server — the sentence below quotes them. Credentials come from
// .env like every other probe here, and `.env` is parsed line by line because `parse_ini_file()` warns on this
// file's own syntax and hands back nothing.
$env = [];
foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^\s*([A-Z_]+)\s*=\s*(.*)$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], " \t\"'");
    }
}
foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $need) {
    if (($env[$need] ?? '') === '') {
        $fail(".env does not answer {$need}, so this pass cannot ask the server what the audit column holds.");
    }
}
$mysqli = null;
try {
    $mysqli = new mysqli($env['DB_HOST'], $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', $env['DB_DATABASE']);
} catch (mysqli_sql_exception $e) {
    $fail($env['DB_DATABASE'].' could not be opened ('.$e->getMessage().'); this pass states row counts and refuses to type them.');
}
$res = $mysqli->query('SELECT action, COUNT(*) n FROM auditlogs GROUP BY action ORDER BY n DESC');
if ($res === false) {
    $fail('the audit log query failed: '.$mysqli->error);
}
$held = [];
foreach ($res as $row) {
    $held[] = $row['action'].' '.$row['n'];
}
$mysqli->close();
if ($held === []) {
    $fail('auditlogs answered no rows at all, which is not a measurement of what the column holds.');
}
printf("vocabulary read from the code: %d named acts\n", $actCount);
printf("auditlogs.action on live ems: %s\n", implode(', ', $held));

$verdictCell = 'Closed 2026-11-02, ruled by the owner as one word list (item 4 of the 2026-10-29 sheet). '
    ."App\\Support\\AuditAct now holds {$actCount} named acts and the route map that says which act each desk route performs, "
    .'and App\Http\Middleware\NameAuditAct names the act for every row that request writes, so the log answers '
    .'"who voided a receipt" without reading eight domain tables; a plain edit keeps the event word, which is honest '
    .'about a write that belongs to no decision. Measured before being written: the column is a varchar(100), so no '
    .'migration was owed — live ems held '.implode(', ', $held).' and nothing else. The audit desk\'s Action filter is '
    .'now built from the same list rather than typed on the screen, which retires nine options that named a word no '
    .'code has ever written. Pinned by tests/Feature/Audit/AuditActNamingTest.php — including a case that drives a real '
    .'request, because every other case would stay green if the middleware were never registered — and proved by '
    .'storage/app/scratch/mutate-g37-audit-act-tests.php.';

$answerCell = 'Answered 2026-11-02 by the owner: name the act, one word list, and the list is held in one class so a '
    .'screen cannot offer an act nothing writes. Built the same day — §28.3 G-37. What remains institutional is narrower '
    .'than the question as asked: prompt §9 lists file-view and access actions among the high-impact events "if required '
    .'by institutional policy", and the school has not said whether a view of a student record must be logged.';

$owesCell = 'G-33 (only its vocabulary: fixed list or free text) and G-35 (where a notice takes the desk) are asked on '
    ."Documentation/School-Decision-Sheet-2026-10-29.md with what each answer costs and how it comes back off; the mechanisms "
    .'behind G-33 and G-34 are built and pinned, and G-37 was answered and built on 2026-11-02.';

$cellEdits = [
    ['The audit trail stores the row change, not the act', 'Open 2026-10-29', $verdictCell],
    ['Should the audit trail name the act, not just the row change?', 'The column stores create', $answerCell],
    // §28.8's Registrar row carries an owed-slot count and the list it counts; both move together, because a tally
    // that stopped growing beside a shrinking list is this document's oldest defect (lane 61, lane 64).
    ['G-33 (only its vocabulary: fixed list or free text)', '3', '2'],
    ['G-33 (only its vocabulary: fixed list or free text)', 'G-33 (only its vocabulary', $owesCell],
    ['Who signed it the first time', 'auditlogs', 'auditlogs rows named step.sign — the earlier signature survives in that row, because a box that is signed again is one act, not two'],
    ['Was a payment voided and re-collected', 'payments', 'payments ( paymentStatus ) + auditlogs rows named payment.void or payment.refund'],
];

$textEdits = [
    ['Yes — on every Eloquent create / update / delete',
        'Yes — on every Eloquent create / update / delete, and under the name of the desk act while one is running'],
];


foreach ($cellEdits as $i => [$anchor, $prefix, $to]) {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail('the docx did not open.');
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    $anchors = substr_count($xml, $anchor);
    if ($anchors !== 1) {
        $fail("cell edit {$i} row anchor occurred {$anchors} time(s), expected exactly 1: ".substr($anchor, 0, 60));
    }
    if (preg_match('/[<>]/', $to) === 1) {
        $fail("cell edit {$i} holds a raw angle bracket.");
    }
    if ($prefix === $to) {
        $fail("cell edit {$i} writes its own anchor — the cell already reads as intended and this row has aged.");
    }
    printf("ok   cell edit %d anchor unique (%d bytes of cell text)\n", $i, strlen($to));
}

foreach ($textEdits as $i => [$from, $to]) {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail('the docx did not open.');
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    $hits = substr_count($xml, $from);
    if ($hits !== 1) {
        $fail("text edit {$i} [{$from}] occurs {$hits} time(s), expected exactly 1.");
    }
    printf("ok   text edit %d needle unique\n", $i);
}

// Where the narrative paragraph carries the retired phrase, print the run boundaries so the next reader can see why
// no needle spans it — reported, not silently skipped.
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open for the residual probe.');
}
$xmlAll = (string) $zip->getFromName('word/document.xml');
$zip->close();
$at = strpos($xmlAll, 'recoverable only from');
if ($at === false) {
    echo "NOTICE  the narrative sentence about recovering a first signature was not found; §13 may have been re-worded.\n";
} else {
    $window = substr($xmlAll, $at, 900);
    preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/', $window, $rm);
    $span = '';
    foreach ($rm[1] as $run) {
        $span .= '['.html_entity_decode($run).']';
        if (str_contains($span, 'workflowsteps')) {
            break;
        }
    }
    echo "RESIDUAL (reported, not judged) — the narrative phrase sits across these runs:\n  ".$span."\n";
}

printf("%s%d cell edit(s) and %d text edit(s) prepared.\n", $apply ? '' : 'DRY RUN — ', count($cellEdits), count($textEdits));

$engineBackup = $root.'/storage/app/scratch/pass403-engine-backup.docx';
docxApply($docx, $engineBackup, $textEdits, $cellEdits, $apply);

if (! $apply) {
    exit(0);
}

// Read back: the closed verdict must be in the file, the retired wording must be gone from the cells this pass owns.
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the written docx did not reopen for the read-back.');
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$checks = [
    'the closed verdict landed' => str_contains($after, 'Closed 2026-11-02, ruled by the owner as one word list'),
    'the old open verdict is gone' => ! str_contains($after, 'Open 2026-10-29. Measured before being written'),
    'the school-question row is answered' => str_contains($after, 'Answered 2026-11-02 by the owner: name the act'),
    'the Registrar tally moved with its list' => substr_count($after, $owesCell) === 1 && ! str_contains($after, 'the audit action word list'),
    'the act word reaches the purpose table' => str_contains($after, 'auditlogs rows named step.sign'),
    'the event word no longer answers the signature question' => ! str_contains($after, 'auditlogs updated rows on workflowsteps'),
];
$failed = array_keys(array_filter($checks, static fn (bool $v): bool => ! $v));
if ($failed !== []) {
    copy($engineBackup, $docx);
    $fail('read-back refused ['
        .implode('; ', $failed)
        .'] — the docx was restored from the engine backup.');
}

echo "written, and every read-back check agreed.\n";
