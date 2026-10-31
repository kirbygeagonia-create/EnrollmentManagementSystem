<?php

/**
 * Docx pass 329 — G-52, the over-broad student load the live-payload read exposed.
 *
 * Pass 326 registered G-51, the join that widened the listed row. Re-running that question against the REAL desk
 * payloads on live `ems` (`lane107-probe-live-queue-payload.php`) came back clean at the row level — no credential
 * column on any fee-sheet or enrollment row, and the same column set under every order — but it printed where the
 * remaining student fields do sit: `assessments.enrollment.student.birthdate` and `.contactNumber`, and the
 * Registrar queue's own `enrollments.student.*`. Those arrive because each queue eager-loads the whole student to
 * paint a name, and no page in the three reads either field (grep over the three `Index.jsx` files answers
 * nothing). `passwordHash` is absent from every payload, which is `Students::$hidden` working on the relation — the
 * contrast is the point of the row: the model's own hidden list protects a nested Students model and could not
 * reach G-51, where the value was an attribute of the row being listed.
 *
 * It is registered OPEN rather than fixed in this batch, because the fix is a change to three handlers' eager loads
 * and each line moved ages the citations into those files — the same cascade this batch already paid for once. The
 * cost is stated in the row so the next reader does not price it as needing a ruling: it does not.
 *
 * Recipe per AGENTS.md: clone from the G-51 template on in-memory bytes, empty every run of each cell, write by
 * cell offset, inserts later-first, order and counts asserted on read-back, a failed guard restores the backup.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass329.php [--apply]
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

// The one-shot guard is the precise one: a row whose FIRST cell is exactly `G-52`. An earlier draft also tested a
// marker phrase — which was inert, because that phrase is not in this row's text, and an inert guard reads like a
// working one.
if (count(rowsForId($xml, 'G-52')) !== 0) {
    fwrite(STDERR, "REFUSED: a row whose first cell is 'G-52' is already in the document.\n");

    exit(1);
}

function flattenXml(string $f): string
{
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $f, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
}

/** @return array<int, array{0:int,1:int}> every <w:tr> whose first cell is EXACTLY $id */
function rowsForId(string $xml, string $id, string $unused = ''): array
{
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
        if (flattenXml($cm[0][0] ?? '') === $id) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
}

function buildRow(string $row, array $values, string $anchor): string
{
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    if (count($cm[0]) !== count($values)) {
        fwrite(STDERR, 'REFUSED: the template row \''.$anchor.'\' has '.count($cm[0]).' cells, this row writes '.count($values).".\n");
        exit(1);
    }
    foreach ($cm[0] as $cell) {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        foreach (array_slice($pm[0], 1) as $extra) {
            if (flattenXml($extra) !== '') {
                fwrite(STDERR, "REFUSED: a cell of the template '{$anchor}' holds a SECOND paragraph with text — read it before cloning.\n");
                exit(1);
            }
        }
    }

    $escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
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
            fwrite(STDERR, 'REFUSED: cell '.$j.' of \''.$anchor.'\' would hold '.substr_count($new, '<w:t xml:space="preserve>')." text runs.\n");
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
        if (flattenXml($cell) !== trim(preg_replace('/\s+/', ' ', $values[$j]))) {
            fwrite(STDERR, "REFUSED: cell {$j} of the new row does not read back as what was supplied.\n");
            exit(1);
        }
    }
    if (substr_count(implode('', $values), '|') !== 0) {
        fwrite(STDERR, "REFUSED: a supplied value carries a literal pipe, which the flat dump cannot tell from a cell boundary.\n");
        exit(1);
    }

    return $clone;
}

$g52 = [
    'G-52',
    'The desk queues eager-load the whole student to paint one name, so a cashier’s and an assessment officer’s payload carries that student’s birthdate and contact number — fields no screen of theirs reads.',
    'Open 2026-10-31, found by reading the real payload rather than the code. `lane107-probe-live-queue-payload.php` asks the three queues through the HTTP stack on live `ems` and reports where each sensitive field sits: `passwordHash` is absent from every payload, which is `Students::$hidden` working on the nested model — the contrast with G-51 is the whole lesson, since the same list could not reach a value that was an attribute of the row being listed. `birthdate` and `contactNumber` arrive nested (`assessments.enrollment.student.birthdate`, `enrollments.student.contactNumber`), and a grep over the three queue pages reads neither: the name is all any of them paints. The fix is one `with` clause per handler, narrowed to the identity columns the screen uses — the repository already does exactly this where it lists enrollments for a term, selecting `enrollmentId, studentId, termId, studentType, academicStanding` rather than taking the row whole. It needs no school ruling, because no desk is asking to see these fields and none displays them; it waits only on the citation cascade, since three handlers move and each line is a document pointer. No case count or assertion total is stated here, because such a figure belongs to a run rather than to the code.',
    'Nobody',
    'Prompt §10 — met reading the live desk payload 2026-10-31',
];

$tmpl = rowsForId($xml, 'G-51');
if (count($tmpl) !== 1) {
    fwrite(STDERR, 'REFUSED: the G-51 template matched '.count($tmpl)." rows, expected exactly 1.\n");
    exit(1);
}

$before = count(familyIds($xml));
printf('family rows before: %d (expected 68)%s', $before, PHP_EOL);
if ($before !== 68) {
    fwrite(STDERR, "REFUSED: the register holds {$before} rows, not the 68 this pass was cut against.\n");
    exit(1);
}

$clone = buildRow(substr($xml, $tmpl[0][0], $tmpl[0][1] - $tmpl[0][0]), $g52, 'G-52');
$xml = substr_replace($xml, $clone, $tmpl[0][1], 0);

echo 'ok   G-52 staged after G-51', PHP_EOL;
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass329-backup-'.date('Ymd-His').'.docx';
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
    restore_and_bail($backup, $docx, 'document.xml no longer parses');
}

$ids = familyIds($xml);
$family = count($ids);
printf('family rows now: %d (expected 69); neighbours: %s%s', $family, implode(', ', array_slice($ids, max(0, (int) array_search('G-52', $ids, true) - 2), 4)), PHP_EOL);
$at52 = array_search('G-52', $ids, true);
if ($family !== 69 || $at52 === false || ($ids[$at52 - 1] ?? null) !== 'G-51' || ($ids[$at52 + 1] ?? null) !== 'D-1') {
    restore_and_bail($backup, $docx, "the row did not land between G-51 and D-1, or the count is {$family}");
}

echo 'backup: '.basename($backup)."\nwritten, re-read, parsed, counted, ordered.\n";

/** A guard that only prints leaves the document broken. */
function restore_and_bail(string $backup, string $docx, string $why): void
{
    if (! copy($backup, $docx)) {
        fwrite(STDERR, "REFUSED: {$why} — AND the restore failed. Restore ".basename($backup)." by hand.\n");
        exit(1);
    }
    fwrite(STDERR, "REFUSED: {$why}. Restored ".basename($backup)."; nothing of the bad write is left.\n");
    exit(1);
}

/** @return string[] every G/D/U/X/N row id, in document order */
function familyIds(string $xml): array
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
        $first = flattenXml($cm[0][0] ?? '');
        if (preg_match('/^([GDUXN]-\d+)$/', $first, $m)) {
            $ids[] = $m[1];
        }
    }

    return $ids;
}
