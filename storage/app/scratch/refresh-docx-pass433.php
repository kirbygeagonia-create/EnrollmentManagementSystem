<?php

/**
 * Pass 433 — the five pointers `lane76-shift-cites.php` refused to guess, re-taken by reading.
 *
 * The batch that added the two load ceilings grew `EvaluationController.php` by 16 lines and
 * `BlockingController.php` by 32, and the shift tool moved 59 and 36 number tokens. It refused the rest,
 * which is the behaviour this file asks for: a `--skip=` holds a key back when a bare copy of the same
 * digits sits beside a DIFFERENT file's citation, and an AMBIGUOUS HEAD line whose text matches sixteen
 * places is not a window. Every number below was read in both files before it was written.
 *
 * Two of the five are worse than aged — they were wrong by meaning before the batch touched the file,
 * which is why no bounds check ever flagged them:
 *   `the act at :1194-1235` named `confirmClearance()` but opened 22 lines early, inside
 *   `approvedPassSlip()`'s own body, and stopped mid-method (HEAD 1194 is the `$window =
 *   Clearanceperiods::accepting()->first();` read and 1235 a `->first();`); and
 *   `filters its roster by it ( :148 )` named the year-level filter while HEAD 148 is the term filter.
 * The first now carries the method's real window, the second the line its own sentence asks for.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass433.php [--apply]
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
$eval = $root.'/app/Http/Controllers/Evaluation/EvaluationController.php';
$block = $root.'/app/Http/Controllers/Blocking/BlockingController.php';

$readPart = static function (string $docx, string $part) use ($fail): string {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail("could not open {$part} inside the docx.");
    }
    $body = $zip->getFromName($part);
    $zip->close();
    if (! is_string($body) || $body === '') {
        $fail("{$part} read empty.");
    }

    return $body;
};

// The line a new number names must carry the claim, read from the live file rather than assumed from arithmetic.
$lineOf = static function (string $file, int $n) use ($fail): string {
    $lines = explode("\n", (string) file_get_contents($file));
    if (! isset($lines[$n - 1])) {
        $fail(basename($file)." has no line {$n}.");
    }

    return trim($lines[$n - 1]);
};

/**
 * Each edit: [needle, replacement, anchor|null, expect, file, [line => must-contain]].
 *
 * The needle for `:507` is carried by its anchor rather than by a longer literal because the run that holds it
 * contains ONLY `:507`, while the other copy of those digits is a comma-tail inside one long run belonging to
 * `RegistrarController.php` — and a text replacement may not carry markup, so `:507</w:t>` is not available.
 */
$edits = [
    [
        'why' => 'the act at :1194-1235 -> confirmClearance()\'s real window (both copies)',
        'from' => ':1194-1235', 'to' => ':1232-1273', 'anchor' => null, 'expect' => 2,
        'file' => $eval, 'assert' => [1232 => 'confirmClearance', 1273 => '}'],
    ],
    [
        'why' => 'filters its roster by it ( :148 ) -> the yearLevel filter the sentence names',
        'from' => 'by it ( :148', 'to' => 'by it ( :150', 'anchor' => null, 'expect' => 1,
        'file' => $block, 'assert' => [150 => "where('yearLevel', \$block->yearLevel)"],
    ],
    [
        'why' => 'the §20.1 print row: BlockingController.php:624 and :620 -> the download action',
        'from' => ':624 and :620', 'to' => ':624 and :653', 'anchor' => null, 'expect' => 1,
        'file' => $block, 'assert' => [653 => 'downloadBlockSchedule', 624 => 'printBlockSchedule'],
    ],
    [
        'why' => 'the §25.1 print-route row: downloadBlockSchedule() serves the PDF ( :620 )',
        'from' => 'serves the PDF ( :620', 'to' => 'serves the PDF ( :653', 'anchor' => null, 'expect' => 1,
        'file' => $block, 'assert' => [653 => 'downloadBlockSchedule'],
    ],
    [
        'why' => 'the §28 E-11 row: cap at :507 -> the room-capacity refusal',
        'from' => ':507', 'to' => ':539', 'anchor' => 'cap at', 'expect' => 1,
        'file' => $block, 'assert' => [539 => 'capacity', 541 => 'Room capacity exceeded'],
    ],
];

$xml = $readPart($docx, 'word/document.xml');

// Stage 1: every needle counted in the archive, every replacement line read in the live file.
foreach ($edits as $i => $e) {
    $n = substr_count($xml, $e['from']);
    if ($n < $e['expect']) {
        $fail("edit {$i} [{$e['why']}] found its needle {$n} time(s), expected {$e['expect']} — the pointer has already moved, or the spelling is not the archive's.");
    }
    if ($e['anchor'] !== null) {
        $a = substr_count($xml, $e['anchor']);
        if ($a !== 1) {
            $fail("edit {$i} is addressed positionally by [{$e['anchor']}] and that anchor occurs {$a} time(s), not 1.");
        }
    }
    foreach ($e['assert'] as $line => $must) {
        $text = $lineOf($e['file'], (int) $line);
        if (! str_contains($text, $must)) {
            $fail("edit {$i} would name ".basename($e['file']).":{$line} for [{$must}] but that line reads [{$text}].");
        }
    }
    printf("stage 1 ok: edit %d — %s\n", $i, $e['why']);
}

// A number this pass is about to write must not already be in the document under a different claim.
foreach ([':1232-1273' => 0, ':653' => 0] as $probe => $want) {
    $have = substr_count($xml, $probe);
    if ($have !== $want) {
        $fail("the new token {$probe} already occurs {$have} time(s) where this pass expected {$want} — read where it came from before writing it again.");
    }
}

$textEdits = [];
foreach ($edits as $e) {
    $textEdits[] = [$e['from'], $e['to'], $e['anchor'], $e['expect']];
}

printf("stage 2 ready: %d pointer edit(s). %s\n", count($textEdits), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass433-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
docxApply($docx, $backup, $textEdits, [], true);

// ------------------------------------------------- read back
$read = $readPart($docx, 'word/document.xml');
foreach ([
    // The roster pointer moves off :148; two other :148 citations stay, belonging to AdmissionController
    // and DevReferenceDataSeeder, which this batch did not touch.
    ':1194-1235' => 0, ':148' => 2, ':620' => 0, ':1232-1273' => 2,
    'by it ( :150' => 1, ':624 and :653' => 1, 'serves the PDF ( :653' => 1,
    // The RegistrarController comma-tail keeps its 507: this batch moved no line in that file.
    ':507 , :519' => 1, 'cap at' => 1,
] as $needle => $want) {
    $have = substr_count($read, $needle);
    if ($have !== $want) {
        unlink($backup);
        copy($backup, $docx);
        $fail("after the write [{$needle}] occurs {$have} time(s), expected {$want} — the backup was restored.");
    }
}
// The E-11 cap now names the blocking refusal, so exactly one :507 (the Registrar tail) survives.
if (substr_count($read, ':507') !== 1) {
    unlink($backup);
    copy($backup, $docx);
    $fail('the room-capacity pointer did not move while the Registrar tail must stay: :507 count is '
        .substr_count($read, ':507').'.');
}
echo "WROTE five pointer re-takes; backup at ".basename($backup)."\n";
