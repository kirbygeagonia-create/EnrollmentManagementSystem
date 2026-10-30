<?php

/**
 * Docx pass 308 — the seven register rows the coverage census produced (G-44 … G-50).
 *
 * §28.3 stopped at G-43 while the matrix had been scoring G-44 … G-50 for a day, and two pointers in this
 * repository — the mark-read test's own header comment and one AGENTS.md bullet — cited `§28.3 G-47` for a row
 * the authority document did not hold. `lane37b-repo-citation-sweep.php` refused both as NO-ID, which is the
 * finding: a comment that cites a register row is a promise the row is IN that section.
 *
 * The recipe is the one `refresh-docx-pass251b.php` established and AGENTS.md records: the engine refuses any pass
 * that moves a `<w:tr>`, so each row is cloned from the G-43 template on the in-memory bytes, every run of every
 * cell is emptied (a cloned multi-run cell otherwise carries the template's tail into the new row), the values are
 * written into the first run's formatting, and the file is written only when all seven resolved. Insertion is
 * planned in REVERSE because every row lands immediately after the anchor.
 *
 * The two places that COUNT the register — §28.1's `G-1 … G-43` index row and §29.4's `60` cell for the G/D/U/X/N
 * family — are a separate pass, because `docxApply()` re-reads the archive and would discard everything this pass
 * wrote. Pass 308b does them.
 *
 * Every fact below was read off the working tree the same day it was typed: the two listeners' `$afterCommit`,
 * `Students`'s `$hidden`, the conditional `enrollments.clinicrecords` load, both disks' shared
 * `storage_path('app/private')` root, the non-unique `notifications` index, and the absence of any dispatch in the
 * admission-reject and requirement-decide actions. No cell states a case count or an assertion total, because
 * `lane58-suite-claims.php` binds those to the runner's junit and a register row must not carry a figure the runner
 * can stale.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass308.php [--apply]
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
    fwrite(STDERR, "REFUSED: cannot open {$docx} (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// One-shot: the pass declines once its own first row is in the document.
if (str_contains($xml, 'and a hold notify nobody')) {
    fwrite(STDERR, "REFUSED: pass 308's own G-44 row is already in the document.\n");

    exit(1);
}

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

/** @return int[] every <w:tr> whose first cell is EXACTLY $id */
$rowsForId = static function (string $xml, string $id) use ($flatten): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
        if (($flatten($cm[0][0] ?? '') ?? '') === $id) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};

/** Build one row from a template row, writing $values into its cells. */
$buildRow = static function (string $row, array $values, string $anchor) use ($flatten, $escape): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    if (count($cm[0]) !== count($values)) {
        fwrite(STDERR, "REFUSED: the template row '{$anchor}' has ".count($cm[0])
            .' cells, this row writes '.count($values).".\n");

        exit(1);
    }
    foreach ($cm[0] as $cell) {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        if (count($pm[0]) === 1) {
            continue;
        }
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: a cell of the template '{$anchor}' holds a SECOND paragraph with text — read it before cloning.\n");

                exit(1);
            }
        }
    }

    // Cells are replaced BY OFFSET, and the clone is rebuilt from document.xml rather than from any dump:
    // the flat dump joins runs with a space, so a string copied out of it invents spacing the file never had.
    $clone = $row;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm2, PREG_OFFSET_CAPTURE);
    $pairs = [];
    foreach ($cm2[0] as $j => $x) {
        $pairs[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
    }
    usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
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

    if (substr_count($clone, '<w:tc>') !== count($cm[0])) {
        fwrite(STDERR, "REFUSED: the clone of '{$anchor}' lost or gained a cell.\n");

        exit(1);
    }

    // The cell must read back as EXACTLY the value the pass supplied — containment is not enough, and a
    // cloned multi-run cell otherwise carries the template's tail.
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $check);
    foreach ($check[0] as $j => $cell) {
        if ($flatten($cell) !== trim(preg_replace('/\s+/', ' ', $values[$j]))) {
            fwrite(STDERR, "REFUSED: cell {$j} of the new row does not read back as what was supplied.\n");

            exit(1);
        }
    }

    return $clone;
};

$rows = [
    [
        'G-44',
        'A clearance decline, an admission rejection and a hold notify nobody: only the two workflow listeners ever write a notice, so a refusal is a fact on the record and silence at the next desk.',
        'Open 2026-10-30, measured on the two acts rather than inferred from the gap list. `AdmissionController::reject()` validates the reason, writes it, stamps the evaluation date and files the audit row, and dispatches no event; the requirement-decide action in `ClearanceController` does the same for a rejected line. The bell that does exist is addressed correctly — `WorkflowInbox::actors()` resolves the office from the destination, and a return reads its own handoff map — so what is missing is a notice for a refusal, not an addressee rule. Whether every decline must ring a bell, and where such a notice then takes the desk, is the question G-35 already carries and the 2026-10-29 sheet asks once; it is not charged a second time here.',
        'Nobody',
        'Prompt §8, §6.5 — swept 2026-10-30',
    ],
    [
        'G-45',
        'Both notification listeners are queued and no dispatch carried an after-commit flag, so a request that rolled back after firing its event could hand the next desk a bell for a transaction that never happened — and a retried job would re-insert the rows the rollback removed, because `notifications` has no unique key.',
        'Half closed 2026-10-29, and by the half that needed no ruling: `public bool $afterCommit = true;` on `SendEnrollmentNotification` and `SendWorkflowNotification`, read off both files today. `ShouldQueue` was deliberately KEPT — dropping it would run the listener inside the transaction, which is the other failure — and `NotificationAfterCommitTest` pins both halves so a case cannot pass by the job never having been queued. Outstanding: a unique key on `notifications` so a re-run cannot insert a second row for one act. That is a migration over a table the framework owns, and it is the same schema question G-36 raises, so it waits there rather than here.',
        'Nobody',
        'Prompt §8 — swept 2026-10-29',
    ],
    [
        'G-46',
        'The dashboard’s queue counts were institution-wide while the pages under them are bounded: no card applied the college boundary, `evaluation` also omitted the returned-to-evaluation state, and the assessment and accounting cards counted enrollments where their desks list fee sheets.',
        'Closed 2026-10-30, and the fix produced its own mirror before it was right. Every card now comes from one class, `App\Support\DeskQueues`, which carries a population only — no eager load, no search, no order — and `ClinicController::index()` and `IDController::index()` build their own lists from that same class, so those two pairs are one call rather than two copies. A first draft also scoped the two assessment badges by college through a new scope method; that was WRONG and came out, because `AssessmentController` and `AccountingController` apply no college filter to their own lists — a bounded badge over an unbounded page is the same contradiction with the sign flipped. The live directory was asked before deciding: the only college-bound accounts are department teachers, who hold the evaluation and admission rights alone, so no bounded account can open any of the unbounded desks today. `DashboardQueueCountMatchesDeskTest` compares each badge with the number the desk prints, in both directions and for a bounded and an unbounded account, and `mutate-g46-dashboard-queues.php` is what showed the second direction was missing. The remaining card is deliberately not unified: its badge counts records waiting at an office while the page lists timetable blocks, and which subject that card should name is on the 2026-10-29 sheet.',
        'Nobody',
        'Prompt §8 — swept 2026-10-30',
    ],
    [
        'G-47',
        'A bell row is a message and a timestamp: no title, no action-required mark, no link to the record the desk must open — and a mark-read asked by an account that is not the addressee answered ok while writing nothing.',
        'Half closed 2026-10-29. The false success is gone: `NotificationController::markRead()` asks whether the row is addressed to this account and refuses with the reason named, and `NotificationMarkReadOwnershipTest` asserts the row’s own read-at column stays null rather than trusting the body. The older bell test had been pinned to the lie — it required the ok from a stranger’s POST — and was re-tensioned the same day the refusal landed, with a comment saying so, because a pin that holds a retired behaviour is the reason nobody notices when it changes. `mutate-g47-mark-read-ownership.php` kills the silent branch, the deleted addressee test and the dropped sentence. Outstanding: the notice still carries no deep link, so a desk reads one sentence and then hunts for the record. Where a notice should take the desk is the question G-35 carries and the 2026-10-29 sheet asks once.',
        'Nobody',
        'Prompt §8 — swept 2026-10-29',
    ],
    [
        'G-48',
        'The `Students` model declared no hidden-attributes list where `Staffusers` hides its credential hash, so every Student 360 payload serialised a student’s bcrypt hash to any account holding the directory right.',
        'Closed 2026-10-29 on the sibling model’s own precedent: `app/Models/Students.php` now hides `passwordHash`, and nothing in `app/` reads the column after the fact — the registration path only writes it. `Student360PrivacyTest` asserts the hash is absent for a directory desk and for the clinic while still asserting the column holds a hash, so the case cannot pass because the fixture had no credential to leak. `mutate-g48-g49-privacy-tests.php` plants the deleted list, a list naming the wrong column, the re-unconditional clinical load, the inverted gate test and the widened holder’s load.',
        'Nobody',
        'Prompt §10 — swept 2026-10-29',
    ],
    [
        'G-49',
        'Student 360 eager-loaded every enrollment’s clinic records and the page printed the findings text, so a desk with no clinical right received the notes in its payload — the prompt’s own example of what must not be shared.',
        'Closed 2026-10-29: `StudentController::show()` loads the clinical relation only when the requester holds the clinic-view right, so the payload has nothing for anyone else to print and the screen no longer decides the act. The case is directional in both directions — a non-holder receives no clinic key at all, the clinic still receives its findings — and the harness leg for it had to be re-cut, because deleting the load outright is what the fix achieves and a leg that removes the gate tests nothing. How far that gate should reach, whether Guidance or Advising reads notes as well as the clinic, is item 3 on the 2026-10-29 sheet, and the privacy-programme question it belongs to is registered as G-38.',
        'Nobody',
        'Prompt §10 — swept 2026-10-29',
    ],
    [
        'G-50',
        'The `documents` disk shares its root with the disk that is served, so a student’s uploaded photo sits behind nothing but the application-key HMAC — and `ems:preflight` refuses a blank key precisely because that is the only gate.',
        'Open 2026-10-30, and measured rather than reasoned: `config/filesystems.php` gives both disks the same private storage root, and Laravel registers the storage routes because the served disk is marked to serve. Either half of the fix is one configuration line — give `documents` its own root outside the served tree, or take the serve flag off the disk that shares it — and `ServedUploadPathTest` plus the preflight rows already pin the gate that would be carrying all of the weight if neither moved. Nothing in `app/` mints the HMAC those routes require, so no desk uses them today; that is why this row is open and not urgent.',
        'Nobody',
        'Prompt §10 — swept 2026-10-30',
    ],
];

$template = $rowsForId($xml, 'G-43');
if (count($template) !== 1) {
    fwrite(STDERR, 'REFUSED: the G-43 template row matched '.count($template).' rows, expected exactly 1.'
        ." — the anchor is a row whose FIRST cell is exactly `G-43`.\n");

    exit(1);
}
[$tStart, $tEnd] = $template[0];
$templateRow = substr($xml, $tStart, $tEnd - $tStart);
$cellCount = preg_match_all('/<w:tc>.*?<\/w:tc>/s', $templateRow, $tmp);
printf("template: the G-43 row at byte %d..%d, %d cell(s)\n", $tStart, $tEnd, $cellCount);

$before = countFamilyRows($xml, $flatten);
printf("family rows before this pass: %d (expected 60)\n", $before);
if ($before !== 60) {
    fwrite(STDERR, "REFUSED: the register holds {$before} G/D/U/X/N rows, not the 60 this pass was cut against."
        ." — re-count before cloning anything.\n");

    exit(1);
}

// Each row lands immediately after the anchor and the anchor's END then advances past it, so the plan runs in
// ASCENDING order. The first cut of this pass reversed the plan for the other recipe — the one where the anchor is
// re-located per row — and wrote G-50 first: the ids came out `G-43, G-50, G-49 … G-44, D-1`, which every bounds
// check called fine because a row's id lives in its own first cell. Order is read back and asserted below.
$plan = $rows;
$report = [];
$failures = [];
foreach ($plan as $r) {
    $id = $r[0];
    if (count($rowsForId($xml, $id)) !== 0) {
        $failures[] = "a row whose first cell is '{$id}' is already in the document";
    }
}
if ($failures !== []) {
    fwrite(STDERR, "\n".count($failures)." row(s) already present; nothing written:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, '  '.$f."\n");
    }

    exit(1);
}

$anchor = $rowsForId($xml, 'G-43')[0];
foreach ($plan as $row) {
    [$id] = $row;
    $clone = $buildRow($templateRow, $row, $id);
    $xml = substr_replace($xml, $clone, $anchor[1], 0);
    // The anchor's end moves with every insert above it.
    $anchor = [$anchor[0], $anchor[1] + strlen($clone)];
    $report[] = sprintf('ok   %s -> %d cell(s), %d bytes', $id, count($row), strlen($clone));
}

foreach ($report as $line) {
    echo $line."\n";
}

printf("%s7 row(s) staged after G-43\n", $apply ? '' : 'DRY RUN — ');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass308-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if ($read !== $xml) {
    restore_and_bail($backup, $docx, 'the archive did not read back the bytes it was handed');
}

$dom = new DOMDocument;
libxml_use_internal_errors(true);
if (! $dom->loadXML($xml)) {
    $errs = libxml_get_errors();
    restore_and_bail($backup, $docx, 'document.xml no longer parses: '.trim($errs[0]->message));
}

$family = countFamilyRows($xml, $flatten);
$order = familyIds($xml, $flatten);

printf("family rows now in §28.2 and §28.3: %d (expected 67)\n", $family);
$expectOrder = ['G-43', 'G-44', 'G-45', 'G-46', 'G-47', 'G-48', 'G-49', 'G-50', 'D-1'];
$seenOrder = array_values(array_filter($order, static fn (string $i): bool => in_array($i, $expectOrder, true)));
printf("the new ids in document order: %s\n", implode(', ', $seenOrder));

if ($family !== 67) {
    restore_and_bail($backup, $docx, "the family count is {$family}, not 67");
}

// Order is a claim, so it is asserted rather than printed: the register reads as a sequence to the office that
// uses it, and a row's id sitting in its own first cell is exactly why no bounds check would ever complain.
if ($seenOrder !== $expectOrder) {
    restore_and_bail($backup, $docx, 'the ids came out in the wrong order — the plan needs re-cutting, not a hand edit');
}

echo 'backup: '.basename($backup)."\nwritten, re-read, parsed, counted, ordered.\n";

/**
 * A post-write guard that only prints is a guard that leaves the document broken: the operator reads one line
 * and the file stays wrong. Restore the pass's own backup, say it happened, and refuse.
 */
function restore_and_bail(string $backup, string $docx, string $why): void
{
    if (! copy($backup, $docx)) {
        fwrite(STDERR, "REFUSED: {$why} — AND the restore failed. Restore ".basename($backup)." by hand.\n");

        exit(1);
    }
    fwrite(STDERR, "REFUSED: {$why}. Restored ".basename($backup)."; nothing of the bad write is left.\n");

    exit(1);
}

/** @return string[] every row id of the G/D/U/X/N families, in document order */
function familyIds(string $xml, callable $flatten): array
{
    $ids = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $at = $end;
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $start, $end - $start), $cm);
        $first = $flatten($cm[0][0] ?? '');
        if (preg_match('/^([GDUXN]-\d+)$/', $first, $m)) {
            $ids[] = $m[1];
        }
    }

    return $ids;
}

function countFamilyRows(string $xml, callable $flatten): int
{
    return count(familyIds($xml, $flatten));
}
