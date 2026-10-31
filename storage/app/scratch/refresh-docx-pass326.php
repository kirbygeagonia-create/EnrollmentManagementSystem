<?php

/**
 * Docx pass 326 — the register row the ENUM audit produced (G-51), and today's revision row.
 *
 * The owner's ruling of 2026-10-31 extended the ENUM headers to three more queues. While auditing the wiring the
 * join itself turned out to widen the row: a builder with no select list emits `select *`, so ordering a fee sheet
 * by Name shipped each student's credential hash, birthdate and contact number inside every row of the queue —
 * fields `Students::$hidden` cannot withhold, because they were not riding on a Students model. That is §28.3's
 * G-51, and this pass writes it beside the §1.1 row that records the batch.
 *
 * Recipe (AGENTS.md, established by pass 251b and re-used by 308): the engine refuses any pass that moves a
 * `<w:tr>`, so each row is cloned from its own template on the in-memory bytes, EVERY run of every cell is emptied
 * so a multi-run cell cannot carry the template's tail, values are written into the first run's formatting, and the
 * file is written only when every row resolved. Order is asserted on read-back because a row's id lives in its own
 * first cell and no bounds check would ever complain about the sequence. A failed post-guard restores this pass's
 * own backup rather than printing advice.
 *
 * The two places that COUNT the register — §28.1's `G-1 … G-50` index row and §29.4's `67` cell for the
 * G/D/U/X/N family — are a separate pass, because `docxApply()` re-reads the archive and would discard everything
 * written here. Pass 326b does them.
 *
 * No cell states a case count or an assertion total: `lane58-suite-claims.php` binds those to the runner's junit,
 * and a register row must not carry a figure the runner can stale.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass326.php [--apply] [--only=both|g51|revision]
 *
 * `--only=` exists because the first real run broke the parse and nothing said which row did it: the pass wrote
 * BOTH rows before the post-write guard, so the error named a column inside pre-existing text. Building one row at
 * a time localised it to the interaction — each row alone parsed, together they did not — which is how the
 * ascending-offset insert was found. Any other flag is refused by name.
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$only = 'both';
foreach (array_slice($argv, 1) as $arg) {
    // The flag NAME is validated, not the variable it happens to leave behind: testing `$only` instead would accept
    // `--bogu=whatever` silently, because `$only` still reads the default — an accepted-and-inert flag is how a
    // mutating run looks clean.
    if ($arg === '--apply') {
        continue;
    }
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, 7);
        if (! in_array($only, ['both', 'g51', 'revision'], true)) {
            fwrite(STDERR, "REFUSED: --only={$only} names no row this pass builds; the choices are both, g51, revision.\n");

            exit(1);
        }
        continue;
    }
    fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads --apply and --only=both|g51|revision.\n");

    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx} (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (str_contains($xml, 'widened the row the desk lists')) {
    fwrite(STDERR, "REFUSED: pass 326's own G-51 row is already in the document.\n");

    exit(1);
}

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

/** @return array<int, array{0:int,1:int}> every <w:tr> whose first cell is EXACTLY $id */
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

/** The single <w:tr> whose cells match every predicate supplied (null = any). */
$rowWhere = static function (string $xml, callable $first, callable $second) use ($flatten): array {
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
        if (count($cm[0]) >= 2 && $first($flatten($cm[0][0])) && $second($flatten($cm[0][1]))) {
            $hits[] = [$start, $end, $flatten($cm[0][1])];
        }
        $at = $end;
    }

    return $hits;
};

$buildRow = static function (string $row, array $values, string $anchor) use ($flatten, $escape): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    if (count($cm[0]) !== count($values)) {
        fwrite(STDERR, 'REFUSED: the template row \''.$anchor.'\' has '.count($cm[0])
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
            fwrite(STDERR, 'REFUSED: the replacement for cell '.$j.' of \''.$anchor.'\' holds '
                .substr_count($new, '<w:t xml:space="preserve">')." text runs.\n");

            exit(1);
        }
        $clone = substr_replace($clone, $new, $at, $len);
    }

    if (substr_count($clone, '<w:tc>') !== count($cm[0])) {
        fwrite(STDERR, "REFUSED: the clone of '{$anchor}' lost or gained a cell.\n");

        exit(1);
    }

    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $check);
    foreach ($check[0] as $j => $cell) {
        if ($flatten($cell) !== trim(preg_replace('/\s+/', ' ', $values[$j]))) {
            fwrite(STDERR, "REFUSED: cell {$j} of the new row does not read back as what was supplied.\n");

            exit(1);
        }
    }

    return $clone;
};

$g51 = [
    'G-51',
    'A sort that joins a relation to order one column widens the row the desk lists: ordering a fee sheet by Name shipped every student’s credential hash, birthdate and contact number inside the row, and no hidden-attribute list could withhold them.',
    'Closed 2026-10-31, and found by auditing the ENUM ruling rather than by any gate — the suite was green over the leak in both directions. `ListSort` joins the relation a column sits on so the ORDER can be taken from it, and a builder with no select list emits `select *`, which then answers the hop’s columns too; on the two money queues the surname order joins `enrollments` and `students`, so the student’s own fields arrived on the fee-sheet row. `Students` hides `passwordHash` on its own model, and that is why G-48 read as closed while this stayed invisible: the value was not riding on a Students model, it was an attribute of the row being listed. The helper now pins the select to the base model’s own columns whenever it adds a hop, so a desk row is one shape whatever header was clicked and a page reads a relation from its eager load, which is where it already read it from. A case on each of the three queues whose order can join compares the column set a row carries sorted against the set it carries unsorted and names the three fields beside the equality, and `mutate-g40-enum-offer-and-select-pin.php` plants the retired helper at each. Two of this batch’s own sequence cases had been GREEN because they read the leaked column: they went red the moment the pin landed and were re-cut to read the relation, which is the record that the leak, not the order, was what made them pass.',
    'Nobody',
    'Prompt §14 and §10 — met while applying the owner’s ruling of 2026-10-31',
];

$revision = [
    // Measured shape (`lane107-probe-revision-row-shape.php`): a §1.1 row is TWO cells and the first
    // carries the date and the title in ONE paragraph, so a value supplying only a date would write
    // a revision column that names no change.
    '2026-10-31 The ENUM ruling reached the three queues that had withheld their columns for their type, and the audit of the join found a leak underneath it',
    'The ENUM ruling reached the three queues that had withheld their columns for their type, and the audit of that wiring found a leak underneath it | Student Type and Final Standing are now headers on the Registrar queue, and the same two columns are ordered on the Assessment and Accounting queues through the enrollment hop a surname sort already builds; the columns stay spelled bare there, because a hop is joined under a private alias and an already-qualified name is obeyed as written, so the qualified spelling would have asked each query for a table it never joined. Both screens still withhold the columns they PAINT rather than store — Payment Status is read off a balance, Covered By sums two columns, Course names a program neither desk works by. Auditing the join turned up G-51: `select *` over a query that joined a relation answered that relation’s columns on the listed row, so a Name order shipped the student’s credential hash to the desk; the helper now pins the select to the base model’s own columns, and the sequence cases that had been passing on the leaked column were re-cut to read the eager load. §28.3 gained G-51 and the two places that count the register moved with it — §28.1’s index row and §29.4’s family cell; the shared helper’s own note stopped naming how many screens ask it, because that number ages with the next queue. The dated row above this one is left as it was written: it records the ruling’s first three screens, not today’s.',
];

$template = $rowsForId($xml, 'G-50');
if (count($template) !== 1) {
    fwrite(STDERR, 'REFUSED: the G-50 template row matched '.count($template).' rows, expected exactly 1.'
        ." — the anchor is a row whose FIRST cell is exactly `G-50`.\n");

    exit(1);
}
if (count($rowsForId($xml, 'G-51')) !== 0) {
    fwrite(STDERR, "REFUSED: a row whose first cell is 'G-51' is already in the document.\n");

    exit(1);
}

// The §1.1 anchor is the row the previous pass wrote: its first cell is today's neighbour's date and its second
// cell carries that row's own title. Locating it by BOTH cells is what keeps a bare date from matching a row in
// some other table.
$revHits = $rowWhere(
    $xml,
    static fn (string $c): bool => str_starts_with($c, '2026-10-30 ') && str_contains($c, 'A ruled reversal'),
    static fn (string $c): bool => strlen($c) > 120  // the body cell, so a two-word row elsewhere cannot be the anchor
);
if (count($revHits) !== 1) {
    fwrite(STDERR, 'REFUSED: the §1.1 anchor matched '.count($revHits).' rows, expected exactly 1.'
        ." — the last revision row is the one whose title begins \"A ruled reversal\".\n");

    exit(1);
}

printf("template: the G-50 row, %d cell(s)\n", preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $template[0][0], $template[0][1] - $template[0][0]), $tmp));
printf("§1.1 anchor: %d bytes at %d..%d, title reads: %s…\n", $revHits[0][1] - $revHits[0][0], $revHits[0][0], $revHits[0][1], substr($revHits[0][2], 0, 60));

$before = countFamilyRows($xml, $flatten);
printf("G/D/U/X/N rows before this pass: %d (expected 67, measured 2026-10-31)\n", $before);
if ($before !== 67) {
    fwrite(STDERR, "REFUSED: the register holds {$before} rows, not the 67 this pass was cut against."
        ." — re-count before cloning anything.\n");

    exit(1);
}

$gClone = $buildRow(substr($xml, $template[0][0], $template[0][1] - $template[0][0]), $g51, 'G-51');
$revClone = $buildRow(substr($xml, $revHits[0][0], $revHits[0][1] - $revHits[0][0]), $revision, '2026-10-31');
if ($only === 'g51') { $revClone = ''; }
if ($only === 'revision') { $gClone = ''; }
printf("built: g51=%d bytes, revision=%d bytes (only=%s)
", strlen($gClone), strlen($revClone), $only);

// An insert at an earlier byte shifts EVERY later offset, so the plan must run LATER-FIRST. The first cut of this
// pass did the opposite: the revision row sits ~2.1 MB above the register, so inserting it first moved the register
// anchor by the revision row's own length and G-51 landed inside an element — which the parser caught, but only
// after the write. The order is now an asserted precondition, not a comment.
$inserts = [[$template[0][1], $gClone, 'G-51'], [$revHits[0][1], $revClone, 'the §1.1 row']];
$inserts = array_values(array_filter($inserts, static fn (array $i): bool => $i[1] !== ''));
for ($i = 1; $i < count($inserts); $i++) {
    if ($inserts[$i - 1][0] <= $inserts[$i][0]) {
        fwrite(STDERR, "REFUSED: the insert plan is not later-first — {$inserts[$i][2]} sits at byte {$inserts[$i][0]}, which is not below {$inserts[$i - 1][2]} at {$inserts[$i - 1][0]}.\n");

        exit(1);
    }
}
foreach ($inserts as [$at, $row]) {
    $xml = substr_replace($xml, $row, $at, 0);
}

echo 'ok   G-51 staged after G-50', PHP_EOL;
echo 'ok   2026-10-31 revision row staged after the ruling row', PHP_EOL;
printf("%s2 row(s) staged\n", $apply ? '' : 'DRY RUN — ');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass326-backup-'.date('Ymd-His').'.docx';
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
    $e = $errs[0];
    // A refusal that names no position sends the reader back through the whole 5.7 MB. The line the parser gives
    // is a line of $xml as PHP holds it, so print the bytes around it too.
    $lines = explode("\n", $xml);
    $ctx = $lines[max(0, $e->line - 1)] ?? '';
    fwrite(STDERR, sprintf(
        "parse error at line %d column %d: %s\nCONTEXT: %s\n",
        $e->line,
        $e->column,
        trim($e->message),
        substr($ctx, max(0, $e->column - 900), 1500)."
>>> AT COLUMN: ".substr($ctx, $e->column-1, 60)
    ));
    restore_and_bail($backup, $docx, 'document.xml no longer parses');
}

$family = countFamilyRows($xml, $flatten);
$order = familyIds($xml, $flatten);
printf("G/D/U/X/N rows now: %d (expected 68)\n", $family);
printf("ids around the new row: %s\n", implode(', ', array_slice($order, max(0, (int) array_search('G-50', $order, true) - 1), 4)));

if ($family !== 68) {
    restore_and_bail($backup, $docx, "the family count is {$family}, not 68");
}

// Order is a claim. A row's id lives in its own first cell, so nothing else would complain if the plan wrote
// G-51 before G-50.
$at50 = array_search('G-50', $order, true);
if ($at50 === false || ($order[$at50 + 1] ?? null) !== 'G-51' || ($order[$at50 + 2] ?? null) !== 'D-1') {
    restore_and_bail($backup, $docx, 'the new row did not land between G-50 and D-1: '.json_encode(array_slice($order, (int) $at50 - 1, 4)));
}

echo 'backup: '.basename($backup)."\nwritten, re-read, parsed, counted, ordered.\n";

/**
 * A post-write guard that only prints leaves the document broken; restore this pass's own backup and refuse.
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
