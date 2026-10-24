<?php

/**
 * Pass 187 — six §28.2 rows still name someone who must decide, for questions the owner answered
 * on 2026-10-24.
 *
 * §28.8's question is "which rows still name an actor", so an answered row that keeps "Registrar" or
 * "Project owner" in that column keeps claiming owed work. lane61-who-owes listed eight such rows; six
 * of them were answered today (C-2, C-4, C-6, C-7, C-8, C-10). C-5 stays — the owner ruled on 2026-10-23
 * that the five board-program flags remain until the departments speak — and G-13 stays as the sheet
 * records it: a fact about an install that does not exist, carried by DEPLOYMENT.md §5.
 *
 * Replaced by row scope, not by string: "Registrar", "Department" and "Project owner" each occur
 * elsewhere in the document, so a needle list would hit the wrong cells — the exact defect the
 * EvaluationController citation cascade was caused by.
 *
 *   php storage/app/scratch/refresh-docx-pass187.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass187.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$rows = [
    'C-2' => 'Registrar with the departments',
    'C-4' => 'Registrar',
    'C-6' => 'Department',
    'C-7' => 'Registrar with Accounting and Scholarship',
    'C-8' => 'Registrar (records-retention policy)',
    'C-10' => 'Project owner',
];
$replaced = 'Answered 2026-10-24 — no decision outstanding';

// Rewritten from the end of the document backwards, so no earlier splice moves a later offset.
$splices = [];
foreach ($rows as $id => $actor) {
    $idCell = '>'.$id.'<';
    $at = strpos($xml, $idCell);
    if ($at === false) {
        $say(false, $id.': its id cell was not found');
        continue;
    }
    $start = strrpos(substr($xml, 0, $at), '<w:tr');
    $end = strpos($xml, '</w:tr>', $at);
    if ($start === false || $end === false) {
        $say(false, $id.': row boundaries not found');
        continue;
    }
    $row = substr($xml, $start, $end - $start);
    $needle = '>'.$actor.'<';
    $n = substr_count($row, $needle);
    $say($n === 1, $id.': the actor cell '.$actor.' appears '.$n.' time(s) inside its own row');
    if ($n !== 1) {
        continue;
    }
    // +1: the needle begins at the closing `>` of the `<w:t>` tag, and only the bytes after it are text.
    $pos = $start + strpos($row, $needle) + 1;
    $say(substr($xml, $pos - 1, 1) === '>' && substr($xml, $pos + strlen($actor), 1) === '<',
        $id.': the span is delimited by > and < , so only text is replaced');
    $splices[] = [$pos, strlen($actor), $replaced, $id];
}

// Apply right-to-left so every offset still refers to the untouched bytes before it.
usort($splices, fn ($a, $b) => $b[0] <=> $a[0]);
$staged = $xml;
foreach ($splices as [$pos, $len, $to, $id]) {
    $staged = substr_replace($staged, $to, $pos, $len);
    printf("  ok   %s spliced at byte %d\n", $id, $pos);
}

$say(count($splices) === 6, 'all six rows spliced (got '.count($splices).')');
$say(substr_count($staged, $replaced) === 6, 'the answered phrasing now appears exactly 6 times');
foreach ($rows as $id => $actor) {
    if ($id === 'C-4' || $id === 'C-6' || $id === 'C-10') {
        continue; // these three strings legitimately live elsewhere in the document
    }
    $say(substr_count($staged, '>'.$actor.'<') === 0, $id.': its actor cell is gone');
}
$say(substr_count($staged, '>Department with Registrar<') === 1, 'C-5 still names the departments, as ruled');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: six actor cells would read ‘Answered 2026-10-24 — no decision outstanding’.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass187.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write — nothing written.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
