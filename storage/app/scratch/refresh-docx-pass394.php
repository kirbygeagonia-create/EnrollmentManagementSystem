<?php

/**
 * Docx pass 394 — the two rows that still say the database does not hold the seat rule, and the census figure
 * that still says nine unique keys.
 *
 * Pass 393 removed the two rows §23.6.2 no longer owns. This pass follows the SAME mechanism sentence into the two
 * registers that restate it — lane 71's rule, learned when a corrected notification claim survived in four sections
 * because only one section owned it:
 *   §23.9 "What must never be orphaned, duplicated, or reused" says of the seat rule "No unique key on
 *     (studentId, termId), and no guard in the schema", and its test-pointer cell cites §23.6.2, which no longer
 *     lists the rule;
 *   §28's storyboard reconciliation says "The application enforces it; the database does not" and "no unique key
 *     over (studentId, termId) exists", under a row that still carries the seatHolder() citation
 *     (app/Services/EnrollmentIssuer.php:37-43) — re-read before re-emitting: those lines are the docblock tail, the
 *     signature and the body of seatHolder(), so the pointer is kept verbatim rather than retyped from memory;
 *   the §24 census row states "nine across the domain tables, listed individually in §23.6.1", where lane44 reads
 *     thirteen — a figure in a DIFFERENT section describing the same list, which lane44 cannot see because it only
 *     weighs its own sentence. The replacement number is taken from lane44's server pair at run time.
 *
 * Two cells per row go through the engine's `cell` kind because those cells split every identifier into its own
 * styled run — no sentence-level text needle spans them. That kind rewrites the cell as a single run, so the
 * identifiers lose their monospace; the text, the citation and the claim survive intact, and the read-back proves
 * each cell's exact flattened text.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass394.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$apply = in_array('--apply', $argv, true);
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$lane44 = (string) shell_exec($php.' '.escapeshellarg($root.'/storage/app/scratch/lane44-unique-keys.php').' 2>&1');
if (! preg_match('/the server holds (\d+) keys across (\d+) tables/', $lane44, $m)) {
    fwrite(STDERR, "REFUSED: lane44 printed no server pair, so the census figure cannot be read from an instrument.\n");
    exit(1);
}
$keyCount = (int) $m[1];
if (! preg_match('/\s0 problem\(s\)/', $lane44)) {
    fwrite(STDERR, "REFUSED: lane44 does not currently read the register clean, so this pass would be writing onto a\n"
        ."          disagreement it cannot explain. Its own output ends:\n".substr($lane44, -400)."\n");
    exit(1);
}

$seatCell = 'The server holds the rule now: uq_enrollments_active_seat over ( studentId , termId , seatKey ), where '
    .'seatKey is a VIRTUAL generated column that is NULL on a dropped row and on an unnamed term, so a second active '
    .'seat is refused even by a writer that never asks the application. Every creation point still asks '
    .'EnrollmentIssuer::seatHolder() before it writes, and that is what makes a drop release the seat (G-4, ruling 3). '
    .'Until 2026-11-01 this row read "no unique key", and it was true as written.';

$storyCell = 'The application enforces it and, since 2026-11-01, so does the server: uq_enrollments_active_seat over '
    .'( studentId , termId , seatKey ) refuses a second active row even one written straight to the table. Every '
    .'creation point asks EnrollmentIssuer::seatHolder() ( app/Services/EnrollmentIssuer.php:37-43 ) before it writes, '
    .'so a second active row for one student in one term is refused by name, and dropped releases the seat because the '
    .'VIRTUAL seatKey is NULL exactly when the record is dropped.';

$storyReasonCell = 'Closed in code 2026-10-03 by ruling 3 (G-4): no act a desk can perform produces the second row, '
    .'and the schema has refused one since 2026-11-01. The row stays in this register because the storyboard states a '
    .'database invariant and the document has to say which half the server now holds: the unique key exists, and '
    .'idx_enrollments_term_status beside it is still a plain index.';

$cellEdits = [
    ['Two active rows give the Registrar, Accounting and the ID desk', 'No unique key on', $seatCell],
    ['Two active rows give the Registrar, Accounting and the ID desk', 'G-4 in 28.3', 'G-4 in 28.3; §23.6.1; 28.5'],
    ['The application enforces it; the database does not.', 'The application enforces it', $storyCell],
    ['The application enforces it; the database does not.', 'Closed in code 2026-10-03', $storyReasonCell],
];

// The census cell spells its number as a word, so the figure is re-spelled rather than dropped in as a digit —
// and the pass refuses a count it has no word for instead of silently changing the document's spelling family.
$words = [1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven',
    8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
    14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
    19 => 'nineteen', 20 => 'twenty'];
if (! isset($words[$keyCount])) {
    fwrite(STDERR, "REFUSED: the server holds {$keyCount} keys and this pass has no word for it; the cell is written in\n"
        ."          words, and typing a digit would make the document spell one claim two ways.\n");
    exit(1);
}

$textEdits = [
    ['nine across the domain tables, listed individually in',
        $words[$keyCount].' across the domain tables, listed individually in'],
];

foreach ($cellEdits as $i => [$anchor, $prefix, $to]) {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        fwrite(STDERR, "REFUSED: the docx did not open.\n");
        exit(1);
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    $anchors = substr_count($xml, $anchor);
    if ($anchors !== 1) {
        fwrite(STDERR, "REFUSED: cell edit {$i} row anchor occurred {$anchors} time(s), expected exactly 1: ".substr($anchor, 0, 50)."\n");
        exit(1);
    }
    if (preg_match('/[<>]/', $to) === 1) {
        fwrite(STDERR, "REFUSED: cell edit {$i} holds a raw angle bracket.\n");
        exit(1);
    }
    printf("ok   cell edit %d anchor unique, %d bytes of cell text\n", $i, strlen($to));
}

printf("%s%d cell edit(s) and %d text edit(s) prepared (key count read from lane44: %d).\n",
    $apply ? '' : 'DRY RUN — ', count($cellEdits), count($textEdits), $keyCount);

$engineBackup = $root.'/storage/app/scratch/pass394-engine-backup.docx';
docxApply($docx, $engineBackup, $textEdits, $cellEdits, $apply);

if (! $apply) {
    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the written docx did not reopen.\n");
    exit(1);
}
$read = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$bad = [];
$rowRanges = [];
{
    $at = 0;
    while (($s = strpos($read, '<w:tr', $at)) !== false) {
        $e = strpos($read, '</w:tr>', $s) + 7;
        $rowRanges[] = [$s, $e];
        $at = $e;
    }
}
foreach ($cellEdits as $i => [$anchor, $prefix, $to]) {
    // The anchor is NOT re-counted after the write: for the §28 row the anchor is the very sentence the edit
    // replaces, so it is expected to vanish. What proves the row survived is a phrase this pass never touches.
    $probe = $i >= 2 ? 'A student may hold at most one active enrollment per academic term'
        : 'Two active rows give the Registrar, Accounting and the ID desk';
    $rows = [];
    foreach ($rowRanges as [$s, $e]) {
        if (str_contains($flatten(substr($read, $s, $e - $s)), $probe)) {
            $rows[] = substr($read, $s, $e - $s);
        }
    }
    if (count($rows) !== 1) {
        $bad[] = 'edit '.$i.': the untouched phrase ['.substr($probe, 0, 40).'] locates '.count($rows).' row(s), expected 1';

        continue;
    }
    $flat = $flatten($rows[0]);
    if (! str_contains($flat, $to)) {
        $bad[] = 'edit '.$i.': the rewritten row does not contain the new cell text';
    }
    // The retired text each edit must have removed, stated separately from the search prefix: edit 2's own
    // replacement BEGINS with the same words the prefix used ("The application enforces it…"), so a prefix-based
    // absence check would refuse a correct write — the append-that-embeds-its-needle trap.
    $retired = [0 => 'No unique key on', 1 => null, 2 => '; the database does not.', 3 => 'invariant and the database does not hold it'];
    if ($retired[$i] !== null && str_contains($flat, $retired[$i])) {
        $bad[] = 'edit '.$i.': the row still carries the retired text ['.$retired[$i].']';
    }
}
if (substr_count($read, $textEdits[0][1]) !== 1 || substr_count($read, $textEdits[0][0]) !== 0) {
    $bad[] = 'the census figure did not move to '.$words[$keyCount];
}
if ($bad !== []) {
    fwrite(STDERR, "REFUSED: read-back failed:\n  ".implode("\n  ", $bad)."\nRestore from {$engineBackup}.\n");
    if (! copy($engineBackup, $docx)) {
        fwrite(STDERR, "REFUSED: the restore failed — repair from {$engineBackup} by hand.\n");
    }
    exit(1);
}
echo "read-back: all four cells and the census figure carry the intended text.\n";
