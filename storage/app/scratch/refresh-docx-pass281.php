<?php

/**
 * Pass 281: the prompt sweep's eleven items go into the §28.3 register.
 *
 * G-33 … G-43 are the items the 2026-10-29 sweep opened (matrix G33 to G43 — the numbering deliberately
 * continues the register, so a matrix id and its register row are the SAME finding, not two systems). Three
 * are closed, one is half closed, and the rest are open with an actor named in the column that counts actors.
 *
 * Two other places state this family and are moved in the same breath, because a stated range that stopped
 * growing is the defect §28.3 G-30 was opened to record:
 *   §28.1's index row  — `G-1 … G-32`
 *   §29.4's family row  — the count cell over the G / D / U / X / N group
 * Both are re-typed only after the document's OWN rows are counted: the pass refuses unless the current
 * figures reconcile with what the register holds today, so 48 is not arithmetic from memory.
 *
 * Rows are cloned from the newest register row (G-32) through pass 251b's helpers: locate the template by its
 * FLATTENED row prefix, write cells by offset keeping each cell's rPr, empty every run of a cloned cell and
 * require it to read back as the text handed to it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass281.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx} (ZipArchive answers an error CODE for a file that is not a zip).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};

/** @return array<int, array{0:int,1:int,2:string}> every <w:tr> whose flattened text STARTS with $prefix */
$rowsStartingWith = static function (string $xml, string $prefix) use ($rowText): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        if (strncmp($rowText($row), $prefix, strlen($prefix)) === 0) {
            $hits[] = [$start, $end, $row];
        }
        $at = $end;
    }

    return $hits;
};

$cellsOf = static function (string $row): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return $cm[0];
};

/** Build one row from a template row, writing $values into its cells by offset. */
$buildRow = static function (string $row, array $values, string $anchor) use ($flatten, $escape, $cellsOf): string {
    $cells = $cellsOf($row);
    if (count($cells) !== count($values)) {
        fwrite(STDERR, "REFUSED: the row cloned for '{$anchor}' has ".count($cells).' cells, this pass writes '
            .count($values).".\n");

        exit(1);
    }
    foreach ($cells as $cell) {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: a template cell holds a SECOND paragraph with text — read it before cloning.\n");

                exit(1);
            }
        }
    }

    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    $pairs = [];
    foreach ($cm[0] as $j => $x) {
        $pairs[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
    }
    usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

    $clone = $row;
    foreach ($pairs as [$j, $at, $len, $src]) {
        $cell = $src;
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        foreach (array_slice($pm[0], 1) as $extra) {
            $cell = str_replace($extra, '', $cell);
        }
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .$escape($values[$j]).'</w:t></w:r></w:p>', $body);
        if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
            fwrite(STDERR, "REFUSED: the replacement for cell {$j} of '{$anchor}' holds "
                .substr_count($new, '<w:t xml:space="preserve">')." text runs.\n");

            exit(1);
        }
        $clone = substr_replace($clone, $new, $at, $len);
    }

    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $check);
    foreach ($check[0] as $j => $cellXml) {
        if ($flatten($cellXml) !== $values[$j]) {
            fwrite(STDERR, "REFUSED: cloned cell {$j} of '{$anchor}' reads ["
                .mb_substr($flatten($cellXml), 0, 90).'] and not what was handed to it.'."\n");

            exit(1);
        }
    }

    return $clone;
};

// ---------------------------------------------------------------- the eleven items
$sweep = 'Prompt §6.5, §7.2, §8–§14 — swept 2026-10-29';
$rows = [
    ['G-33', 'A rejected admission carried no reason anywhere: the desk posted an empty body, the action validated nothing, and the table held no column that could store one.',
        'Closed 2026-10-29. `admissions` gained `rejectionReason` (varchar 500, nullable — a rejection filed before the column stays valid and simply shows no reason, which is the truth about it); the reject action refuses under ten characters; the confirm dialog takes an opt-in reason box on the shared `CauseEffectModal`, and the rejected panel prints the stored text instead of telling the applicant to ask. Pinned by `AdmissionRejectionReasonTest`, and proved by a harness that killed six defects after two of them first survived. The half that waits is vocabulary, not mechanism: whether the reason is a fixed list or free text.',
        'Registrar (vocabulary only)'],
    ['G-34', 'A clearance requirement could be refused with `remarks` left null, so the student was declined by a record that said nothing.',
        'Closed 2026-10-29. The decide action requires ten characters on a `rejected` status and a reasonless refusal moves neither the line nor the clearance’s overall status; an approval with an empty remark still passes, which is the directional case proving the rule did not break the common act. Pinned by `ClearanceRequirementRejectionTest`. **No mutant harness has been run over these five cases yet** — they are pinned, not proved able to fail, and the register says so.',
        'Nobody'],
    ['G-35', 'An in-app notice carries a body but no title and no link, so a desk reads a sentence and hunts for the record it is about.',
        'Open 2026-10-29 (phase 2). Prompt §8 asks that a notification name the record and take the reader to it; the bell renders the message text only. Destination per notice type is what the school must say, and the sheet prices the answer as one template change.',
        'Registrar'],
    ['G-36', 'Nothing stops a duplicate notice when a queued listener retries.',
        'Open 2026-10-29 (phase 2). No unique key and no idempotency guard sits on the notification write, so a retried job can ring the same desk twice. Buildable without a ruling; the honest shape is a key on the act and the addressee, which is a migration and therefore waits on nothing but the decision to spend one.',
        'Nobody'],
    ['G-37', 'The audit trail stores the row change, not the act: `create`, `update`, `delete` where prompt §9 names seven action families.',
        'Open 2026-10-29. Measured before being written: `auditlogs.action` is a varchar, so naming the act needs NO migration — which is why this is a vocabulary question and not a schema one. The word list is on the 2026-10-29 sheet.',
        'Registrar'],
    ['G-38', 'Who may read a clinical note, as distinct from the clinic’s outcome, is stated nowhere.',
        'Open 2026-10-29. The desk screens restrict clinic detail to the permitted office, but prompt §10’s privacy clause is a disclosure question the repository cannot answer for the school; the Data Protection Officer owns it, and the sheet asks it with the cost of each answer beside it.',
        'Data Protection Officer'],
    ['G-39', 'One print page re-declares a status tone map of its own, and that map has not learned the two return states.',
        'Open 2026-10-29 (phase 5). Measured by reading rather than recalled: only ONE surface re-declares the map, not all of them — the claim this sweep was handed said more, and the files disproved it. The fix is to read the desk’s own tone function rather than keep a second answer to one word.',
        'Nobody'],
    ['G-40', 'No list table sorts, and two carry no pagination at all.',
        'Open 2026-10-29 (phase 5). Prompt §14 asks for density controls — sorting, filtering, search, pagination, row actions. Filtering, search and row actions are built and instrumented (lane 46 owns the filter contract); sorting exists nowhere, which is a table-wide gap rather than a per-screen defect.',
        'Nobody'],
    ['G-41', 'Prompt §14’s clause about console errors and empty, loading and responsive states has no instrument, so it is unverified rather than passing.',
        'Open 2026-10-29. The repository proves screens with Pest, PHPStan, Pint, ESLint and a Vite build, and no browser job runs in CI. Whether one belongs there is an owner question with a runner-time cost, put on the 2026-10-29 sheet rather than answered by a tool.',
        'Owner'],
    ['G-42', 'A deactivated account was refused at login by one line of prose and no test, and an already-open session is never re-checked.',
        'Half closed 2026-10-29. The refusal is pinned by a pair in `AuthenticationTest` that authenticates the SAME account once it is active again, so the case cannot pass by the credentials failing. The other half waits on the school: how long a session lives after a desk deactivates an account is a policy cut-off, and it is item 2 on the 2026-10-29 sheet.',
        'Owner (session cut-off only)'],
    ['G-43', 'A DATE column written from `now()` files the machine’s civil day, because the application clock is UTC and Eloquent formats a `date` cast in it.',
        'Open 2026-10-29, and the family is measured not recalled: `admissions.evaluatedDate`, `studentrequirementsubmissions.submittedDate`, `studentassessments.assessmentDate`, `clearanceapprovals.approvalDate` and `idrequests.requestDate` are all `date` on the server and all fed a Carbon. The two admission stamps moved to `SchoolTime::today()` with this batch and the day case freezes the clock so it cannot pass while both clocks agree. Three writers remain. Lane 50’s PHP half answers 0 over them: it matches `now()->toDateString()` and `whereDate(…)`, shapes a `date`-cast write is not — so the instrument for this class is not built yet.',
        'Nobody'],
];

// ---------------------------------------------------------------- reconcile first, then move
$templateHits = $rowsStartingWith($xml, 'G-32 ');
if (count($templateHits) !== 1) {
    fwrite(STDERR, 'REFUSED: the §28.3 template row [G-32 |] matched '.count($templateHits)." rows, expected 1.\n");

    exit(1);
}
[$tStart, $tEnd, $template] = $templateHits[0];
$tCells = array_map($flatten, $cellsOf($template));
echo 'template cells = '.count($tCells)."\n";
foreach ($tCells as $i => $c) {
    echo '  cell '.$i.': '.mb_substr($c, 0, 70)."\n";
}
if (count($tCells) !== 5) {
    fwrite(STDERR, "REFUSED: the register row holds ".count($tCells)." cells; these rows are written for five.\n");

    exit(1);
}

// The register's own rows, counted out of the document rather than recalled.
preg_match_all('/\bG-(\d+)\b/', $rowText($template), $self);
$gRows = 0;
foreach ($rowsStartingWith($xml, 'G-') as $hit) { $row = $hit[2];
    if (preg_match('/^G-\d+$/', $flatten($cellsOf($row)[0] ?? ''))) {
        $gRows++;
    }
}
echo "register rows beginning G-N today: {$gRows}\n";
if ($gRows !== 32) {
    fwrite(STDERR, "REFUSED: expected 32 G rows before the insert and counted {$gRows}; read the register.\n");

    exit(1);
}

// §29.4's family count: only moved if the document's rows reconcile with the figure it states.
$familyHits = $rowsStartingWith($xml, 'G / D / U / X / N ');
if (count($familyHits) !== 1) {
    fwrite(STDERR, 'REFUSED: §29.4\'s G/D/U/X/N family row matched '.count($familyHits)." rows.\n");

    exit(1);
}
$familyRow = $familyHits[0][2];
$familyCells = array_map($flatten, $cellsOf($familyRow));
$statedCount = (int) trim($familyCells[1]);
$idCount = 0;
foreach (['G', 'D', 'U', 'X', 'N'] as $letter) {
    foreach ($rowsStartingWith($xml, $letter.'-') as $hit) { $row = $hit[2];
        if (preg_match('/^'.$letter.'-\d+$/', $flatten($cellsOf($row)[0] ?? ''))) {
            $idCount++;
        }
    }
}
echo "§29.4 states {$statedCount} for G/D/U/X/N; the rows themselves number {$idCount}\n";
if ($idCount !== $statedCount) {
    // Not a refusal: the cell is ALREADY one short of the rows it claims to count, which is the same defect
    // family §28.3 G-30 was opened to record (a count that stopped growing when a row was added). The
    // honest post-write figure is the measured row count plus these rows, and the discrepancy is printed
    // so the next reader knows the cell was wrong before this batch, not because of it.
    echo 'NOTE  §29.4\'s family cell was already stale by '.($idCount - $statedCount)
        ." row(s) before this insert; it moves to the measured total, not to the stated figure plus eleven.\n";
}
$familyTarget = $idCount + count($rows);

foreach ($rows as [$id]) {
    if ($rowsStartingWith($xml, $id.' ') !== []) {
        fwrite(STDERR, "REFUSED: a register row {$id} is already in the document.\n");

        exit(1);
    }
}

// Build the eleven rows and insert them after the template, in document order.
$built = [];
foreach ($rows as [$id, $gap, $today, $who]) {
    $built[] = $buildRow($template, [$id, $gap, $today, $who, $sweep], $id);
}
$block = implode('', $built);
$xml = substr_replace($xml, $block, $tEnd, 0);

// The two places that state the family.
$index = $rowsStartingWith($xml, 'G-1 … G-32 ');
if (count($index) !== 1) {
    fwrite(STDERR, 'REFUSED: §28.1\'s index row [G-1 … G-32 |] matched '.count($index)." rows.\n");

    exit(1);
}
[$iStart, $iEnd, $iRow] = $index[0];
// Cells are re-scanned WITH offsets: `$cellsOf()` returns the cell TEXT positions in the row, and using
// element [1] of that array as a byte offset wrote the new cell over the second character of the row.
// The parse guard below is what caught it; the document on disk was never opened for writing.
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $iRow, $iCells, PREG_OFFSET_CAPTURE);
$iCell0 = $iCells[0][0][0];
$iAt = (int) $iCells[0][0][1];
$newIndexCell = preg_replace('/G-1 … G-32/', 'G-1 … G-43', $iCell0, 1, $cnt);
if ($cnt !== 1) {
    fwrite(STDERR, "REFUSED: the index cell did not carry the range exactly once.\n");

    exit(1);
}
$xml = substr_replace($xml, $newIndexCell, $iStart + $iAt, strlen($iCell0));

// Recompute offsets after that replacement for the §29.4 count cell.
$familyHits2 = $rowsStartingWith($xml, 'G / D / U / X / N ');
$fCells = [];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $familyHits2[0][2], $fcm, PREG_OFFSET_CAPTURE);
$fRowStart = $familyHits2[0][0];
[$fAt, $fLen] = [(int) $fcm[0][1][1], strlen($fcm[0][1][0])];
$fCell = $fcm[0][1][0];
$want = (string) $familyTarget;
$newCell = preg_replace('/<w:t(?: [^>]*)?>'.$statedCount.'<\/w:t>/', '<w:t xml:space="preserve">'.$want.'</w:t>', $fCell, 1, $cnt2);
if ($cnt2 !== 1) {
    fwrite(STDERR, "REFUSED: the §29.4 count cell does not hold {$statedCount} in one text run; read it.\n");

    exit(1);
}
$xml = substr_replace($xml, $newCell, $fRowStart + $fAt, $fLen);

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>'], ['<w:r', '</w:r>']] as [$openT, $closeT]) {
    $a = preg_match_all('/'.preg_quote($openT, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $closeT);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$openT} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

$after = 0;
foreach ($rowsStartingWith($xml, 'G-') as $hit) { $row = $hit[2];
    if (preg_match('/^G-\d+$/', $flatten($cellsOf($row)[0] ?? ''))) {
        $after++;
    }
}
if ($after !== 43) {
    fwrite(STDERR, "REFUSED: the register should number 43 rows after the insert; it numbers {$after}.\n");

    exit(1);
}

printf(
    "%s: §28.3 takes 11 rows (G-33 … G-43); §28.1's range and §29.4's count move with them (%d bytes of prose)\n",
    $apply ? 'APPLY' : 'DRY RUN',
    strlen($block)
);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass281-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

$w = new ZipArchive;
if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing. Restore from {$backup}.\n");

    exit(1);
}
$w->addFromString('word/document.xml', $xml);
$w->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $xml) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 281 applied: the sweep is in the register, and the two places that count the family moved with it.\n";
