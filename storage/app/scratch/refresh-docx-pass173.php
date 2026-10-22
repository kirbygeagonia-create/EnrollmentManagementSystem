<?php

/**
 * Pass 173 — the sections that still ASK what the registers ANSWER, and one register row for the class.
 *
 * Measured 2026-10-21 by lane61-open-claims.php (status words) and lane61-who-owes.php (the actor column):
 *  - §28.7 "The order these should be taken in" names nine items; the ids they name read Closed or Answered in
 *    their own rows, eight of the nine entirely. Row 5 is the exception: P-1 closed, but P-2 carries no verdict
 *    word at all and enrollment-form.blade.php still holds no course, term or year-level reference.
 *  - §28.8 counts 46 owed slots over six actors, "counted from the Who must act column of 28.2 and 28.3". That
 *    column now names an actor in 8 of 56 rows and names none in the other 48. No engineering actor is named.
 *  - §30.5 calls C-11 "a decision the school has not made" while C-11's own row reads Ruled 2026-10-02 and built.
 *  - §25.5 said the settings table has no effect on a printed document; SettingsServiceProvider landed 2026-10-01
 *    in 29b5532 and a test pins it. Passes 171 and 171b rewrote that paragraph and P-7's row.
 *
 * Two stages, for the reason pass 169 learned: docxApply() opens the file itself, so a row cloned into a string
 * here never reaches disk unless this file writes it. Stage 1 clones the §1.1 revision row and §28.3's G-29 and
 * moves every place that counts the register; stage 2 goes through the engine so its guards run.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// ---------------------------------------------------------------- guards, all read from the instruments

$dump = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
$rows = file($root.'/storage/app/scratch/docx-flat.txt', FILE_IGNORE_NEW_LINES);

/** The live split, re-derived here rather than copied from a terminal scrollback. */
$ACTORS = '/^(Front-end developer|Developer|Registrar|SysAdmin|Project owner|Department|School|College|Owner)\b/u';
$SETTLED = '/^(Closed|Answered|Ruled|Contained|Done|Corrected|Built|Partly closed|Deferred|None|No decision)/u';
$named = 0;
$settled = 0;
$unreadable = 0;
$total = 0;
$byWho = [];
foreach ($rows as $i => $l) {
    if (! preg_match('/^ROW \d+: ([A-Z]{1,3}-\d{1,2}) \|/', $l, $m)) {
        continue;
    }
    $n = $i + 1;
    // §28.2 runs to its next heading, §28.3 the same; both located by their header rows in the dump.
    if (! (($n >= 2508 && $n <= 2518) || ($n >= 2522 && $n <= 2566))) {
        continue;
    }
    $cells = preg_split('/\s*\|\s*/', $l);
    $who = $cells[4 - 1] ?? '';
    if (count($cells) !== 5) {
        // G-7, G-17 and G-26 carry a pipe inside a code span, so the split gives them more cells than the
        // table has columns and no index is trustworthy. Counted, never judged — same rule lane 49 uses for
        // the guards its text rules cannot reach.
        $unreadable++;
    } elseif (preg_match($ACTORS, $who)) {
        $named++;
        $byWho[] = $m[1];
    } elseif (preg_match($SETTLED, $who)) {
        $settled++;
    } else {
        printf("REFUSED: row %s at line %d names no actor, states no answer and is not a split artifact.\n", $m[1], $n);
        exit(1);
    }
    $total++;
}
if ($total !== 56) {
    printf("REFUSED: %d register rows read, not 56.\n", $total);
    exit(1);
}
if ($named !== 8 || $named + $settled + $unreadable !== 56) {
    printf("REFUSED: the actor column names %d actors, %d settle, %d cannot be read by column — that is %d of 56 rows.\n",
        $named, $settled, $unreadable, $named + $settled + $unreadable);
    exit(1);
}
sort($byWho, SORT_NATURAL);
if ($byWho !== ['C-2', 'C-4', 'C-5', 'C-6', 'C-7', 'C-8', 'C-10', 'G-13']) {
    printf("REFUSED: the eight live rows are %s, not the list this pass names.\n", implode(', ', $byWho));
    exit(1);
}
if (! str_contains($dump, 'Every enrollment this system creates carries 1')) {
    // sanity: the prose this pass no longer has to carry
    echo "note: §30.4's yearLevel row was already restated by pass 172\n";
}
if (! preg_match('/a decision the school has not made \(C-11\)/', $dump)) {
    exit("REFUSED: §30.5's C-11 clause is not where the checker said it was.\n");
}
if (! str_contains($dump, 'each waits on an answer in 28.5 before enforcement can be written')) {
    exit("REFUSED: §28.7 row 8 does not read as measured.\n");
}
foreach (glob($root.'/resources/views/prints/*.blade.php') ?: [] as $f) {
    if (preg_match('/courseName|academicYear|termName/', (string) file_get_contents($f), $x)
        && str_contains($f, 'enrollment-form')) {
        printf("REFUSED: %s now prints %s — P-2 may be closed.\n", basename($f), $x[0]);
        exit(1);
    }
}

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};
$assertPlain = function (string $text, string $what) use (&$fails): void {
    $bad = [];
    foreach (['&', '<', '>'] as $c) {
        if (str_contains($text, $c)) {
            $bad[] = $c;
        }
    }
    if ($bad !== []) {
        printf("  FAIL %s carries %s — it would be escaped twice or corrupt the part\n", $what, implode(' ', $bad));
        $fails++;
    }
};
$setText = function (string $cell, string $text): string {
    if (preg_match('/(<w:t(?:\s[^>]*)?>)(.*?)(<\/w:t>)/s', $cell, $one) !== 1) {
        throw new RuntimeException('the cell carries no text run to replace');
    }

    return preg_replace_callback('/<w:t(?:\s[^>]*)?>.*?<\/w:t>/s',
        fn (array $x): string => $x[0] === $one[0]
            ? str_replace($one[2], htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8'), $x[0])
            : $x[0],
        $cell, 1);
};
$cloneRow = function (string $new, string $anchor, array $row, string $label) use ($guard, $assertPlain, $setText): string {
    $guard(substr_count($new, $anchor) === 1, $label.': the template row is findable by its own anchor');
    $at = strpos($new, $anchor);
    $rowStart = strrpos(substr($new, 0, (int) $at), '<w:tr');
    $rowEnd = (int) strpos($new, '</w:tr>', (int) $at) + 7;
    $template = substr($new, (int) $rowStart, $rowEnd - (int) $rowStart);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cells);
    $guard(count($cells[0]) === count($row), $label.': template row has '.count($cells[0]).' cells, the pass supplies '.count($row));
    if (count($cells[0]) !== count($row)) {
        return $new;
    }
    $clone = $template;
    foreach ($cells[0] as $i => $cell) {
        $assertPlain($row[$i], $label.' cell '.($i + 1));
        $clone = str_replace($cell, $setText($cell, $row[$i]), $clone);
    }
    $guard($clone !== $template, $label.' carries new text');
    foreach (['<w:tr', '</w:tr>', '<w:tc>', '</w:p>', '</w:t>'] as $n) {
        $guard(substr_count($clone, $n) === substr_count($template, $n), $label.' container '.$n.': one row’s worth');
    }
    $guard(substr_count($clone, '>'.htmlspecialchars($row[0], ENT_QUOTES | ENT_XML1, 'UTF-8').'<') === 1
        || substr_count($clone, $row[0]) >= 1, $label.' is addressed by its own first cell');

    return substr_replace($new, $clone, $rowEnd, 0);
};

// ---------------------------------------------------------------- stage 1: two cloned rows and the register's size

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$parts = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

$revisionRow = [
    '2026-10-21 (this issue)',
    'Three claim families were bound to the instruments that answer them, and one family of prose turned out to be '
        .'status words rather than figures. Lane 59 compares every count the documents state with what the tool named '
        .'in the same sentence prints today. Lane 60 re-measured the schema and the ratios over its rows — 63 tables, '
        .'55 of them domain, 452 columns, 100 foreign keys, 129 unique-to-nothing indexes — and found the census’s '
        .'“175 indexes” had never named what it counted: 175 is the constraint tally, and §28.3 G-28 records the '
        .'dictionary row that named columns the server does not hold. Lane 61 asked the question none of the citation '
        .'sweeps can ask: does a sentence still present as undecided an item its own register row has answered. It '
        .'found twelve over eleven ids, in §28.7, §28.8, §30.5 and §30.6, and a §25.5 that described a settings-to-print '
        .'bridge twenty days after the bridge was built. Gates on this box: 780 tests, 5,220 assertions; CI answers '
        .'771 passed with 5,013 assertions on its SQLite job and 5,015 on its MySQL one, which is why an assertion '
        .'total now cites a run rather than the code.',
];
$new = $cloneRow($new, '2026-10-16–20 (this issue)', $revisionRow, '§1.1 2026-10-21 row');

$g29 = [
    'G-29',
    'Four sections still asked questions the registers had answered, and §25.5 described a letterhead bridge that had been built twenty days earlier',
    'The citation sweeps all pass: every pointer lands on a live line, every §-reference resolves, and the figures beside '
        .'these sentences were the ones lane 60 had just corrected. What disagreed was the status word. §28.7 put nine '
        .'items in “the order these should be taken in”, and the ids they name — E-1, E-4, G-1, G-3, G-6, G-7, G-8, X-1, '
        .'D-1 … D-4, U-1 … U-4, N-1 … N-4 — each read Closed or Answered in their own rows, eight of the nine rows '
        .'entirely. §28.8 counted 46 owed slots over six actors from a “Who must act” column that now names an actor in '
        .'8 of the 56 register rows and names none in the other 48. §30.5 called C-11 a decision the school had not made while '
        .'C-11 reads Ruled 2026-10-02 and built, and called the settings table empty while it holds 3 rows. §25.5 said '
        .'the settings table has no effect on any printed document, twenty days after 29b5532 registered a provider that '
        .'hydrates config(‘settings.*’) from it at boot and a test that pins exactly that. One row was found by reading '
        .'rather than by the checker: P-2 states no verdict word at all, and the enrollment form still carries no course, '
        .'term or year level, so it is the register’s only live engineering item that the open-claims sweep cannot see.',
    'Corrected 2026-10-21 by passes 171, 171b, 172 and 173: §25.5 states the provider, the six templates and the two '
        .'blank rows; §30’s census, unique-key and open-item figures follow §23.6.1, §24.1 and the registers; §28.7 is '
        .'written as the order the build took; §28.8 names the eight rows that still owe an answer. Read by '
        .'storage/app/scratch/lane61-open-claims.php and storage/app/scratch/lane61-who-owes.php.',
    '§25.5, P-7, P-2, §28.7, §28.8, 30.1, 30.2, 30.4, 30.5, 30.6; found 2026-10-21 by '
        .'storage/app/scratch/lane61-open-claims.php, proved by storage/app/scratch/mutate-lane61-open-claims.php',
];
$new = $cloneRow($new, '>G-28<', $g29, '§28.3 G-29');

$sizeNeedle = 'lane 60 added G-28 from a dictionary row that named columns the schema '
    .'never had, so §28.3 holds 45 data rows';
$sizeNew = 'lane 60 added G-28 from a dictionary row that named columns the schema never had, and lane 61 added G-29 '
    .'from four sections that still asked what the registers answer, so §28.3 holds 46 data rows';
$guard(substr_count($new, $sizeNeedle) === 1, 'the size sentence is present once');
$new = str_replace($sizeNeedle, $sizeNew, $new, $cSize);
$guard($cSize === 1, 'the size sentence was rewritten ('.$cSize.')');

$labelAt = strpos($new, '>G / D / U / X / N<');
$guard($labelAt !== false, 'the family row is findable by its own label cell');
$famStart = $labelAt === false ? 0 : (int) strrpos(substr($new, 0, (int) $labelAt), '<w:tr');
$famEnd = (int) strpos($new, '</w:tr>', (int) $labelAt) + 7;
$famText = substr($new, $famStart, $famEnd - $famStart);
$guard(substr_count($famText, '>45<') === 1, 'exactly one 45 sits inside that family row');
$new = substr_replace($new, str_replace('>45<', '>46<', $famText), $famStart, $famEnd - $famStart);
$guard(substr_count($new, '>45<') === 0, 'no count cell still says 45');

$lastRow = strpos($new, '>G-29<');
$tblStart = $lastRow === false ? 0 : strrpos(substr($new, 0, (int) $lastRow), '<w:tbl>');
$tblEnd = (int) strpos($new, '</w:tbl>', (int) $lastRow) + 7;
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', substr($new, (int) $tblStart, $tblEnd - (int) $tblStart), $all);
$dataRows = count($all[0]) - 1;
$guard($dataRows === 46, '§28.3 recounted from the edited bytes holds 46 data rows (read '.$dataRows.')');
$guard(str_contains($new, $sizeNew), 'the size sentence carries the new count');
$revTbl = new DOMDocument();
$guard(@$revTbl->loadXML($new) !== false, 'the staged document.xml parses');

if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

$rewrite = function (string $path, string $body, int $partsExpected, string $backupTo) use ($root, &$fails): void {
    $out = dirname($path).'/.ems-docx-stage.tmp';
    $w = new ZipArchive();
    if ($w->open($out, ZipArchive::CREATE) !== true) {
        exit("REFUSED: cannot build the replacement archive.\n");
    }
    $r = new ZipArchive();
    $r->open($path);
    $n = $r->numFiles;
    for ($i = 0; $i < $n; $i++) {
        $name = $r->getNameIndex($i);
        $w->addFromString($name, $name === 'word/document.xml' ? $body : $r->getFromIndex($i));
    }
    $w->close();
    $r->close();
    if ($n !== $partsExpected) {
        printf("  FAIL the archive now holds %d parts, expected %d\n", $n, $partsExpected);
        $fails++;

        return;
    }
    if (! rename($out, $path)) {
        exit("REFUSED: could not move the new archive into place; backup at {$backupTo}\n");
    }
    $check = new ZipArchive();
    $check->open($path);
    $round = (string) $check->getFromName('word/document.xml');
    $parts = $check->numFiles;
    $check->close();
    if (hash('sha256', $round) !== hash('sha256', $body)) {
        exit("REFUSED: what is on disk is not the bytes this pass built.\n");
    }
    printf("  ok   wrote %d parts, %d bytes of document.xml\n", $parts, strlen($body));
};

// ---------------------------------------------------------------- stage 2: the four sections, through the engine

$textEdits = [
    [
        'The rows that wait on an answer rather than on code are G-4, G-6, D-1, D-6, P-4, P-9 and P-12, plus every C-n in 28.2. Working on them first would fix a guess into the schema.',
        'The rows that waited on an answer rather than on code were G-4, G-6, D-1, D-6, P-4, P-9 and P-12, plus every '
            . 'C-n in 28.2, and the order held: each of those ids carries its answer in its own row now — G-4, G-6, D-1 '
            . 'and D-6 closed in code, P-4, P-9 and P-12 closed with the rulings of 2026-10-02 to 2026-10-04. Working on '
            . 'them first would have fixed a guess into the schema, which is what the tabling was for.',
    ],
    [
        'These are the rules §12 and §13 state as process and the build enforces only partially, and each waits on an answer in 28.5 before enforcement can be written.',
        'These were the rules §12 and §13 stated as process and the build enforced only partially, and all four '
            . 'closed: G-3 on 2026-10-03 under rulings 4 and 5, G-6 on 2026-10-01 with the two examination flags, G-7 on '
            . '2026-10-03 under ruling 11, and G-8 on 2026-10-03 when recording a result stopped meaning approval. Each '
            . 'row in §28.3 carries its own evidence.',
    ],
    [
        'Removing them from the schema is a decision the school has not made (C-11), and while the column accepts a value, an agent reading only the schema will keep writing specifications that use it.',
        'Removing them from the schema is a decision the school HAS made — C-11 was ruled on 2026-10-02 and built, and '
            . 'it took two directions: two values left with the action no rule could reach, and others were given '
            . 'behaviour. What this row still says truly is the hazard beside the ones kept: while a column accepts a '
            . 'value, an agent reading only the schema will keep writing specifications that use it.',
    ],
];

$cellEdits = [
    [
        '2026-10-16–20 (this issue)',
        '2026-10-16–20 (this issue)',
        '2026-10-16–20',
        1,
    ],
];

$paraEdits = [
    [
        'so the split between institutional answers and engineering work is visible at a glance',
        'Counted from the “Who must act” column of 28.2 and 28.3, so the split between institutional answers and '
            . 'engineering work is visible at a glance. An item appears under two actors where the fix needs both. The '
            . 'six rows below are that split as the items were opened; 2026-10-21 re-read the column they count, and of '
            . 'the 56 register rows 8 still name an actor while the other 48 name none — 45 of those state the answer in that cell, '
            . 'Closed in code, Answered, Contained, or the ruling that closed them. Three rows — G-7, G-17 and G-26 — cannot be read by column at all, because their own text carries a pipe inside a code span, and the script prints them as a blind spot instead of guessing at them. Measured by '
            . 'storage/app/scratch/lane61-who-owes.php, which prints the column verbatim rather than recounting a '
            . 'remembered split. So today the Registrar is named in five rows (C-2, C-4, C-5, C-7 and C-8), the '
            . 'Department in two (C-5 and C-6), the Project owner in one (C-10) and whoever provisions the server in one '
            . '(G-13). No engineering actor is named at all: every item §28.7 put under Developer or Front-end developer '
            . 'reads Closed in code in §28.3. Two print findings sit outside this table because it counts only 28.2 and '
            . '28.3: P-7, whose letterhead still prints no address and no phone, and P-2, the one print row that states '
            . 'no verdict word at all while the enrollment form it describes still cannot print a program or a term.',
    ],
];

// Stage 2's targets are resolved against the CURRENT bytes before stage 1 writes: a needle that had drifted
// would otherwise be discovered after the register row was already on disk, which is a document carrying half
// a pass. docxApply() refuses to write on any unresolved target, so this call is free.
docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass173-never.docx', $textEdits, $cellEdits, false, $paraEdits);

if (! $apply) {
    echo "\nStage 1 would add the §1.1 row for 2026-10-21 and §28.3 G-29, restate the size sentence and move the "
        ."family cell 45 → 46.\nStage 2 would rewrite §28.7 rows 2 and 8, §28.8's intro, §30.5's C-11 clause and "
        ."row 22's date cell.\n\n".'DRY RUN. Re-run with --apply.'."\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass173.docx');
$rewrite($src, $new, $parts, $root.'/storage/app/scratch/ems-docx-pre-pass173.docx');
echo "     stage 1 written\n";

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass173b.docx', $textEdits, $cellEdits, true, $paraEdits);
