<?php

/**
 * Weigh §29.4's count of the school-question register against the table it counts.
 *
 * §29.4's family row states how many questions §28.5 holds, and §28.5 is the document's only call on somebody
 * else's time. A count beside a table is a membership claim (lane 64's family, lane 61's register rows): it does not
 * shrink or grow when somebody inserts or retires a row, and every existing instrument passes it — the line sweep
 * sees live pointers, lane 37 resolves §-references, lane 60 weighs the schema, lane 59 binds figures to the tools
 * that print them, and none of them compares a cell to the rows under its own heading.
 *
 * So this reads both from `document.xml`: §28.5's table rows (minus its header) and §29.4's "Open decisions" second
 * cell, and refuses — rather than printing a clean verdict — when either side cannot be located, when the stated
 * cell holds no number, or when the two disagree.
 *
 * Usage: php storage/app/scratch/lane212-weigh-question-register.php [--docx=<path>]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$known = ['docx'];
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (! preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/', $arg, $m)) {
        fwrite(STDERR, "REFUSED: unrecognised argument {$arg}; this tool reads only --".implode(', --', $known).".\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: --{$m[1]} is not a flag this tool reads (known: --".implode(', --', $known).").\n");
        exit(1);
    }
    if (($m[2] ?? null) === null || $m[2] === '') {
        fwrite(STDERR, "REFUSED: --{$m[1]} needs a value; an empty one would read the real document while looking like a scoped run.\n");
        exit(1);
    }
    $docx = $m[2];
}

if (! is_file($docx)) {
    fwrite(STDERR, "REFUSED: [{$docx}] is not a file.\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the docx did not open as an archive (a plain text file named .docx answers with an error CODE, which is truthy).\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty.\n");
    exit(1);
}

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' | ', array_map($flatten, $cm[0]));
};

// --- the table: §28.5's own heading, then the table that physically follows it.
$headingPos = strpos($xml, 'Consolidated list of decisions to put to the school');
if ($headingPos === false) {
    fwrite(STDERR, "REFUSED: §28.5's heading text was not found, so its table cannot be located without guessing.\n");
    exit(1);
}
$tblStart = strpos($xml, '<w:tbl>', $headingPos);
$tblEnd = $tblStart === false ? false : strpos($xml, '</w:tbl>', $tblStart);
if ($tblStart === false || $tblEnd === false) {
    fwrite(STDERR, "REFUSED: no table follows §28.5's heading.\n");
    exit(1);
}
$table = substr($xml, $tblStart, $tblEnd + 8 - $tblStart);
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $table, $tr);
$physical = count($tr[0]);
if ($physical < 2) {
    fwrite(STDERR, "REFUSED: §28.5's table answered {$physical} row(s) — a register read as empty is not a register read as zero.\n");
    exit(1);
}
$header = $rowText($tr[0][0]);
if (! preg_match('/decision/i', $header)) {
    fwrite(STDERR, "REFUSED: the first row of §28.5's table does not read as its header: [".mb_substr($header, 0, 80)."].\n");
    exit(1);
}
$asked = $physical - 1;

// --- the claim: §29.4's family row for this register, and the cell that counts it.
// "Open decisions" is a phrase the narrative uses as well as the label the table cell holds, so the first hit is not
// evidence — the row is identified by its own shape: a cell whose whole text is the label, followed by a numeric
// cell. Anything else is refused rather than guessed at.
$stated = null;
$at = 0;
$candidates = 0;
while (($at = strpos($xml, 'Open decisions', $at)) !== false) {
    $candidates++;
    $trStart = strrpos($xml, '<w:tr', $at - strlen($xml));
    $trEnd = strpos($xml, '</w:tr>', $at);
    $at += 14;
    // `strrpos($h,$n,$offset)` finds the LAST occurrence AT OR AFTER the offset, not the nearest one before it, so
    // reaching backwards needs the offset expressed against the end of the string. Without the negative form this
    // bounded the row AFTER the label and read no cells at all.
    if ($trStart === false || $trEnd === false || $trStart > $at) {
        continue;
    }
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $trStart, $trEnd + 7 - $trStart), $cells);
    // `preg_match_all` leaves $cells[0] unset rather than [] when nothing matches, so the cell count is read
    // before any cell is — indexing it anyway is how a fatal stands where a refusal belongs.
    $cellList = $cells[0] ?? [];
    if (count($cellList) < 2 || $flatten($cellList[0]) !== 'Open decisions') {
        continue;
    }
    $stated = $flatten($cellList[1]);
    break;
}
if ($stated === null) {
    fwrite(STDERR, "REFUSED: no table row has \"Open decisions\" as its whole first cell ({$candidates} mention(s) of the phrase were read) — the row moved, or the label was reworded.\n");
    exit(1);
}
if (! preg_match('/^\d+$/', $stated)) {
    fwrite(STDERR, "REFUSED: §29.4's count cell reads [$stated], which is not a number — the cell moved, or the row is not the one intended.\n");
    exit(1);
}

printf("§28.5 holds %d question rows (%d physical, one header)\n", $asked, $physical);
printf("§29.4's \"Open decisions\" cell states %d\n", (int) $stated);

if ($asked !== (int) $stated) {
    printf("\nDISAGREE by %d. The register gained or lost a row and its count was not re-taken — re-run the pass that\n", abs($asked - (int) $stated));
    echo "writes the cell, and read the rows before typing the number.\n";
    exit(1);
}

echo "\nAGREES — the count cell equals the rows under its own heading.\n";
exit(0);
