<?php

/**
 * Pass 275: §22 gains the workflow monitor's right, and §1.1 records the screen.
 *
 * The catalogue in §22.2 to §22.5 is generated from `RbacSeeder`, so a permission added to the
 * seeder has to reach these tables in the same batch — the document's own sentence promises it
 * ("If the seeder changes, rebuild this document rather than editing the tables").
 *
 * Two surfaces, deliberately told apart:
 *   - the SEEDER declares ninety-six rights across fifteen modules now — that is a property of
 *     the code and is re-typed here from `probe-seeder-rbac-counts.php` output, not recalled;
 *   - the LIVE database still holds ninety-five, because nothing in this batch reseeded it. Every
 *     live-count sentence is left exactly as written (lane 60's and 72's rule), and the divergence
 *     is recorded in the §1.1 row rather than papered over by re-typing a live figure.
 *
 * Cells are written by offset (the pass-251 rule): the Admin row's list and count cells are not
 * unique needles — `refdata.view, students.view, user.view` occurs three times in document.xml —
 * so `str_replace()` would write every copy. A row's own flattened prefix locates it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass275.php [--apply]
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
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

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

/** @return array<int, array{0:int,1:int,2:string}> every <w:tr> whose flattened text starts with $prefix */
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

/** Write one cell of a row as a single run, keeping that cell's own run properties. */
$writeCell = static function (string $row, int $index, string $text) use ($flatten, $escape, $rowText): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    if (! isset($cm[0][$index])) {
        fwrite(STDERR, "REFUSED: the row has no cell {$index} (it has ".count($cm[0]).").\n");

        exit(1);
    }

    $out = $row;
    // Highest offset first, so replacing one cell cannot move another cell's offset.
    $targets = [];
    foreach ($cm[0] as $j => $x) {
        $targets[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
    }
    usort($targets, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

    foreach ($targets as [$j, $at, $len, $src]) {
        if ($j !== $index) {
            continue;
        }
        $cell = $src;
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: cell {$index} holds a second paragraph with text — read it before rewriting.\n");

                exit(1);
            }
            $cell = str_replace($extra, '', $cell);
        }
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .$escape($text).'</w:t></w:r></w:p>', $body);
        if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
            fwrite(STDERR, "REFUSED: the replacement for cell {$index} holds more than one text run.\n");

            exit(1);
        }
        $out = substr_replace($out, $new, $at, $len);
    }

    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $out, $check);
    if ($flatten($check[0][$index]) !== $text) {
        fwrite(STDERR, "REFUSED: cell {$index} did not come back as the text written; it reads ["
            .$flatten($check[0][$index])."].\n");

        exit(1);
    }

    return $out;
};

$assertOnce = static function (string $xml, string $needle, string $what) {
    $n = substr_count($xml, $needle);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSED: {$what} occurs {$n} time(s), expected exactly 1: [{$needle}]\n");

        exit(1);
    }
};

$changes = 0;

// ── 1. §22.2 gains its `workflow` module row, cloned from the one-row `students` row ─────────
$studentsTemplates = $rowsStartingWith($xml, 'students 1 students.view');
if (count($studentsTemplates) !== 1) {
    fwrite(STDERR, 'REFUSED: the students catalogue row matched '.count($studentsTemplates)." row(s), expected 1.\n");

    exit(1);
}
[$sStart, $sEnd, $studentsRow] = $studentsTemplates[0];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $studentsRow, $sc);
if (count($sc[0]) !== 3) {
    fwrite(STDERR, 'REFUSED: the template row holds '.count($sc[0])." cells; this pass writes a three-cell catalogue row.\n");

    exit(1);
}
if ($rowsStartingWith($xml, 'workflow 1 workflow.view') !== []) {
    fwrite(STDERR, "REFUSED: this pass's own catalogue row is already in the document.\n");

    exit(1);
}

$clone = $studentsRow;
foreach ([['workflow', 0], ['1', 1], ['workflow.view', 2]] as [$value, $index]) {
    $clone = $writeCell($clone, $index, $value);
}
if ($rowText($clone) !== 'workflow 1 workflow.view') {
    fwrite(STDERR, 'REFUSED: the cloned catalogue row flattens to ['.$rowText($clone)."].\n");

    exit(1);
}
$xml = substr_replace($xml, $clone, $sEnd, 0);
$changes++;

// ── 2. §22.2's Total row: ninety-five → ninety-six ──────────────────────────────────────────
$hits = $rowsStartingWith($xml, 'Total 95');
if (count($hits) !== 1) {
    fwrite(STDERR, 'REFUSED: the §22.2 Total row prefix matched '.count($hits)." row(s), expected 1.\n");

    exit(1);
}
[$tStart, $tEnd, $totalRow] = $hits[0];
$newTotal = $writeCell($totalRow, 1, '96');
$xml = substr_replace($xml, $newTotal, $tStart, $tEnd - $tStart);
$changes++;

// ── 3. SysAdmin's own sentence ──────────────────────────────────────────────────────────────
$assertOnce($xml, 'Every one of the 95 permissions', 'the SysAdmin sentence');
$xml = str_replace('Every one of the 95 permissions', 'Every one of the 96 permissions', $xml);
$changes++;

// ── 4. the generated-from-code promise, which is what the seeder now does ───────────────────
$assertOnce($xml, '95 permissions across 14 modules, and 17 Spatie roles',
    'the §22.2 build-time sentence');
$xml = str_replace('95 permissions across 14 modules, and 17 Spatie roles',
    '96 permissions across 15 modules, and 17 Spatie roles', $xml);
$changes++;

// ── 5. the Admin role row: fourteen read rights, the new one named ──────────────────────────
$adminHits = $rowsStartingWith($xml, 'Admin Institutional overseer');
if (count($adminHits) !== 1) {
    fwrite(STDERR, 'REFUSED: the Admin role row matched '.count($adminHits)." row(s), expected 1.\n");

    exit(1);
}
[$aStart, $aEnd, $adminRow] = $adminHits[0];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $adminRow, $ac);
$adminCount = $flatten($ac[0][2]);
$adminList = $flatten($ac[0][3]);
if ($adminCount !== '13') {
    fwrite(STDERR, "REFUSED: the Admin row's count cell reads [{$adminCount}], not 13 — re-read the row before re-typing it.\n");

    exit(1);
}
if (! str_ends_with($adminList, 'user.view') || str_contains($adminList, 'workflow.view')) {
    fwrite(STDERR, "REFUSED: the Admin row's list cell does not end where this pass expects: [{$adminList}].\n");

    exit(1);
}
$newAdmin = $writeCell($adminRow, 3, $adminList.', workflow.view');
$newAdmin = $writeCell($newAdmin, 2, '14');
$xml = substr_replace($xml, $newAdmin, $aStart, $aEnd - $aStart);
$changes++;

// ── 6. §1.1 records the screen, and says which surface each figure belongs to ───────────────
$date = '2026-10-27';
$record = 'Prompt §6.1 and §6.3: the workflow monitor becomes the fourth place progress is visible. '
    .'Three of §6.1’s four places were already served — the stepper sits on seven desk detail pages — but nothing '
    .'answered the institution-wide question, so §6.3’s admin view is now a screen: one row per record still in the '
    .'pipeline, its boxes signed against its boxes total, which desk is next and what that desk must do, when a desk '
    .'last acted, and for a returned record the category, the sending desk and the destination read from the same '
    .'return row the receiving desk reads. The next-desk answer is the one the notification path already gives, so '
    .'the bell and the list cannot disagree; that helper now reads an eager-loaded box list rather than re-querying, '
    .'which is what keeps a page of records from issuing a query per row. Five tiles, all taken from a clone made '
    .'before the page split: records in view, awaiting a desk, signed through, returned for correction, and records '
    .'with no workflow form at all — the last being §4’s missing-tracker line counted instead of assumed. A desk '
    .'filter keeps only the records whose NEXT open box belongs to that desk, so an office is never offered a record '
    .'another office is ahead of. Read-only by ruling of the build: §6.3 also asks for a configurable version, and '
    .'which phases are mandatory is the school’s to say (matrix G-6, still open on the decision sheet), so the page '
    .'names the definition the records are walking rather than pretending to edit one. It is gated by a new right, '
    .'workflow.view, which the seeder now declares in a fifteenth module and the read-everywhere Admin role takes by '
    .'its own rule; the gate ability is named workflow.monitor instead, because Spatie’s Gate::before would let a '
    .'permission grant an ability that shares its name. The catalogue in §22 is re-typed from the seeder, which is '
    .'what that section promises: ninety-six rights across fifteen modules. The live database still holds ninety-five '
    .'and its sentences are left as written, because no reseed ran in this batch — re-measure them after one does. '
    .'Sixteen cases pin the screen, including the page-versus-tile boundary, the later-box refusal, the newest of '
    .'two returns, a dropped record kept out of the count, and an enum value the column cannot hold refused with a '
    .'message rather than answered with an empty list.';

$anchor = '2026-10-27 §25.1';
$templates = $rowsStartingWith($xml, $anchor);
if (count($templates) !== 1) {
    fwrite(STDERR, 'REFUSED: the §1.1 anchor row ['.$anchor.'] matched '.count($templates)." row(s), expected exactly 1.\n");

    exit(1);
}
[$rStart, $rEnd, $template] = $templates[0];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $rc);
if (count($rc[0]) !== 2) {
    fwrite(STDERR, 'REFUSED: the §1.1 template row holds '.count($rc[0])." cells; this pass writes a two-cell row.\n");

    exit(1);
}
if ($rowsStartingWith($xml, $date.' Prompt §6.1 and §6.3') !== []) {
    fwrite(STDERR, "REFUSED: this pass's own §1.1 row is already in the document.\n");

    exit(1);
}

$rowClone = $template;
foreach ([[$date, 0], [$record, 1]] as [$value, $index]) {
    $rowClone = $writeCell($rowClone, $index, $value);
}
if ($rowClone === $template) {
    fwrite(STDERR, "REFUSED: the cloned §1.1 row is byte-identical to its template.\n");

    exit(1);
}
$xml = substr_replace($xml, $rowClone, $rEnd, 0);
$changes++;

// ── guards on the whole edited document ─────────────────────────────────────────────────────
foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>'], ['<w:r', '</w:r>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

foreach (['Every one of the 96 permissions', '96 permissions across 15 modules', 'workflow.view'] as $want) {
    if (substr_count($xml, $want) < 1) {
        fwrite(STDERR, "REFUSED: the edited document does not contain [{$want}].\n");

        exit(1);
    }
}

if (substr_count($xml, 'Every one of the 95 permissions') !== 0) {
    fwrite(STDERR, "REFUSED: a ninety-five SysAdmin sentence survived the edit.\n");

    exit(1);
}

printf("%s§22 and §1.1 take the workflow monitor (%d changes, %d bytes of §1.1 record)\n",
    $apply ? '' : 'DRY RUN — ', $changes, strlen($record));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass275-backup-'.date('Ymd-His').'.docx';

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

echo "\npass 275 applied: §22 carries workflow.view, and §1.1 records the monitor.\n";
