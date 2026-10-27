<?php

/**
 * §1.1's record row for the G-5 and G-10 batch.
 *
 * The document's own revision table is where a batch is recorded, and every prior lane has written one.
 * The prose deliberately states no line-number ranges — a record row that quotes a citation shape invents a
 * citation, which pass 251 learned when its own new row made two needles ambiguous — and no counts of these
 * tools, because a figure this file states is a figure its instruments have to answer.
 *
 * The row is cloned from the G-4 row's own bytes, so it carries the table's real formatting: every run of
 * the template cell is emptied and the flattened cell is required to equal the text supplied, because a
 * cloned multi-run cell otherwise keeps the template's tail.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass263.php [--apply]
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

if (! $zip->open($docx)) {
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

$values = [
    '2026-10-27',
    'Two prompt gaps built, and the authority document repaired on the way. G-10 (§6.4): the return is now ONE '
        .'act behind `enrollment.return`, handled by App\\Services\\ReturnRecorder and reachable from Admission, '
        .'Evaluation and Accounting, and it writes six facts per act into the new `enrollmentreturns` table — '
        .'destination, reason category, the box it came from and the box it goes to, the note, and the correction '
        .'owed — plus the status it moved from and to. Ruled by the owner the same day: a return voids nothing and '
        .'refunds nothing, the receipts keep counting through `Payments::held()`, and the money is not re-billed. '
        .'Because a browser cannot call PHP, the act a desk may offer is answered by the server: the record page '
        .'receives `returnOptions` from the same policy and recorder the save asks, so a screen no longer paints a '
        .'button the route refuses. G-5 (§7.9): `ems:import:timetable` reads a CSV, resolves block, subject, '
        .'instructor and room by name rather than by id, and asks BOTH questions Blocking asks of a meeting — '
        .'against the database inside the block\'s own term, and against another row of the same file — through one '
        .'new definition, App\\Support\\TimetableConflicts, which `detectConflicts()` now calls instead of holding '
        .'its own copy. It prints a verdict per row with the reason under each refusal and writes nothing unless '
        .'`--commit` is present and the whole sheet resolved; the half-open boundary keeps a class that starts '
        .'exactly when another ends back-to-back rather than colliding, and the padded-clock comparison is pinned '
        .'both ways because a MySQL TIME reads HH:MM:SS while a desk form stores HH:MM. Pinned by '
        .'tests/Feature/Enrollment/EnrollmentReturnTest.php and tests/Feature/Console/TimetableImportTest.php , '
        .'proved by the two mutant harnesses for those gaps. Three findings came out of doing it, and all three are '
        .'about instruments rather than code: pass 254 had swallowed one cell-closing tag while changing one '
        .'character, so the document this file calls the authority did not parse — the repair was a splice derived '
        .'byte by byte from the pre-pass backup, and the write guard that should have caught it now balances cell, '
        .'row and table tags as well as runs; a rehearsal of a NEW write path was run against live `ems` with '
        .'`--commit` in its argv and inserted two schedules and two meetings, which is why a preview must never '
        .'carry the flag that writes, and why the rule now says exercise a new write path only on a scratch schema; '
        .'and three §7.1/§27 sentences were found to describe a build that had already moved — a drop row saying the '
        .'edge had no call site, a return row saying only `paid` may be sent back, and the E-4 paragraph saying a '
        .'returned record cannot get back to paid while citing a method this batch retired. Every one of those landed '
        .'on live lines of the right file, so no bounds sweep, meaning sweep or §-reference checker was dissatisfied; '
        .'they are corrected by reading. The citation cascade from the two moved files was taken by byte window, and '
        .'the schema census, the permission total, §24\'s dictionary entry for `enrollmentreturns` and §24.17\'s '
        .'emptiness set were re-measured against the server rather than restated.',
];

// The template: §1.1's G-4 row, found by its own flattened prefix.
$anchors = [];
$near = [];
$at = 0;

while (($start = strpos($xml, '<w:tr', $at)) !== false) {
    $end = strpos($xml, '</w:tr>', $start);

    if ($end === false) {
        break;
    }

    $end += 7;
    $row = substr($xml, $start, $end - $start);
    $text = $flatten($row);
    $at = $end;

    if (strncmp($text, '2026-10-26', 10) === 0 && str_contains($text, 'Examination results adopted')) {
        $anchors[] = ['start' => $start, 'end' => $end, 'row' => $row, 'text' => $text];
    }

    if (str_contains($text, 'Examination results adopted')) {
        $near[] = substr($text, 0, 80);
    }
}

if (count($anchors) !== 1) {
    fwrite(STDERR, 'REFUSED: the G-4 revision row resolved to '.count($anchors)." row(s); the clone's template is ambiguous.\n");

    foreach ($near as $n => $t) {
        fwrite(STDERR, "  candidate {$n}: \"{$t}\"\n");
    }

    exit(1);
}

$template = $anchors[0];

printf("template §1.1 row: %d bytes, flattened prefix \"%s…\"\n", strlen($template['row']), substr($template['text'], 0, 60));

// Offsets are needed to write cells by position, so this match must carry PREG_OFFSET_CAPTURE: without it
// each `$cell` is a plain string and `$cell[1]` is its second CHARACTER — the same defect pass 260c met,
// and it surfaces here as "the clone holds 0 text runs" because the cell body was a one-character slice.
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template['row'], $cm, PREG_OFFSET_CAPTURE);

if (count($cm[0]) !== count($values)) {
    fwrite(STDERR, 'REFUSED: the template row has '.count($cm[0]).' cells, this row writes '.count($values).".\n");

    exit(1);
}

$clone = $template['row'];
$pairs = [];

foreach ($cm[0] as $j => $cell) {
    $pairs[] = [$j, (int) $cell[1], strlen($cell[0]), $cell[0]];
}

usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

foreach ($pairs as [$j, $at2, $len, $src]) {
    $cell = $src;
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);

    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            fwrite(STDERR, "REFUSED: cell {$j} of the template row holds a second paragraph with text — read it before cloning.\n");

            exit(1);
        }

        $cell = str_replace($extra, '', $cell);
    }

    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($values[$j]).'</w:t></w:r></w:p>', $body);

    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        fwrite(STDERR, "REFUSED: cell {$j} of the clone holds ".substr_count($new, '<w:t xml:space="preserve">')." text runs.\n");

        exit(1);
    }

    $clone = substr_replace($clone, $new, $at2, $len);
}

// Containment is not enough: the flattened clone must EQUAL the text supplied, or the template's tail rode
// along in a later run.
$cloneText = $flatten($clone);

foreach ($values as $j => $want) {
    $cellText = [];
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $cm2);
    $cellText = $flatten($cm2[0][$j]);

    if ($cellText !== $want) {
        fwrite(STDERR, "REFUSED: cloned cell {$j} flattens to a string that is not the text this pass supplied (".strlen($cellText)." vs ".strlen($want)." bytes).\n");

        exit(1);
    }
}

printf("clone  %d bytes, %d cells verified by flattened equality\n", strlen($clone), count($values));

if (substr_count($xml, $template['row']) !== 1) {
    fwrite(STDERR, "REFUSED: the template row is not a unique span of the document.\n");

    exit(1);
}

$updated = substr_replace($xml, $clone, $template['end'], 0);

foreach ([['<w:tc>', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl>', '</w:tbl>']] as [$open, $close]) {
    if (substr_count($updated, $open) !== substr_count($updated, $close)) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open}.\n");

        exit(1);
    }
}

if (substr_count($updated, '<w:tr') !== substr_count($xml, '<w:tr') + 1) {
    fwrite(STDERR, "REFUSED: the document did not gain exactly one row.\n");

    exit(1);
}

if (simplexml_load_string($updated) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%s§1.1 would gain its record row after the G-4 row\n", $apply ? '' : 'DRY RUN — ');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass263-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $updated);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $updated) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 263 applied: §1.1 records the G-5 and G-10 batch and the three instrument findings it produced.\n";
