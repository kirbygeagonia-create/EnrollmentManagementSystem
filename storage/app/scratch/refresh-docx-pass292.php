<?php

/**
 * Pass 292: §1.1 records G-43's closure and the three instrument findings it produced.
 *
 * Cloned from the 2026-10-29 tone-map row, so the new row inherits that table's cell structure. Every cell goes
 * through the same read-back guards pass 286 used: one run per cell, the flattened cell must equal the text
 * handed to it, container counts must balance, document.xml must still parse, and the archive must read back
 * byte-for-byte.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass292.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unknown argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$cellsOf = static function (string $row): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return $cm[0];
};
$rowsStartingWith = static function (string $xml, string $prefix) use ($flatten, $cellsOf): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        $text = implode(' ', array_map($flatten, $cellsOf($row)));
        if (strncmp($text, $prefix, strlen($prefix)) === 0) {
            $hits[] = [$start, $end, $row];
        }
        $at = $end;
    }

    return $hits;
};
$writeCell = static function (string $row, int $index, string $text) use ($flatten, $escape, $cellsOf): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    if (! isset($cm[0][$index])) {
        fwrite(STDERR, "REFUSED: the row has no cell {$index}.\n");

        exit(1);
    }
    $at = (int) $cm[0][$index][1];
    $target = strlen($cm[0][$index][0]);
    $cell = $cm[0][$index][0];
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            fwrite(STDERR, "REFUSED: cell {$index} holds a second paragraph with text.\n");

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
        fwrite(STDERR, "REFUSED: cell {$index} would hold more than one text run.\n");

        exit(1);
    }
    $out = substr_replace($row, $new, $at, $target);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $out, $check);
    if ($flatten($check[0][$index]) !== $text) {
        fwrite(STDERR, "REFUSED: cell {$index} reads [".mb_substr($flatten($check[0][$index]), 0, 80)."].\n");

        exit(1);
    }

    return $out;
};

$anchor = '2026-10-29 One tone map for one status';
$tpl = $rowsStartingWith($xml, $anchor);
if (count($tpl) !== 1) {
    fwrite(STDERR, 'REFUSED: the tone-map row matched '.count($tpl)." rows, expected 1.\n");

    exit(1);
}
[$rStart, $rEnd, $template] = $tpl[0];
if (count($cellsOf($template)) !== 2) {
    fwrite(STDERR, 'REFUSED: the template row has '.count($cellsOf($template))
        ." cells, and this pass writes two.\n");

    exit(1);
}

$mine = '2026-10-29 The school’s day on the write side';
if ($rowsStartingWith($xml, $mine) !== []) {
    fwrite(STDERR, "REFUSED: this pass's own row is already in the document.\n");

    exit(1);
}

$record = 'G-43 closed: §28.3’s write-side date gap. Lane 51 forbids comparing the machine’s day against a DATE '
    .'column and lane 85 policed the read, but nothing policed the write, because a value handed to a `date` cast '
    .'as `now()` matches neither `now()->toDateString()` nor `whereDate(…)` — Eloquent formats a Carbon in the '
    .'application’s timezone, which is UTC, so every one of those rows carried the daemon’s civil day and, between '
    .'16:00Z and midnight, the day before the school’s. `lane87-date-write-columns.php` binds each write to the '
    .'migrations instead of to a live database, so it runs wherever the suite runs. Measured before the repairs it '
    .'enumerated eight column names over forty-eight write sites, of which thirty-eight feed a column the schema '
    .'declares DATE — eight on desk paths and thirty inside the demo generator — and the remaining ten are instants '
    .'which §1.2 says keep the viewer’s zone; they are printed as UNBOUND, not cleared. All thirty-eight moved: '
    .'thirty-three to `SchoolTime::today()`, five to `SchoolTime::civilDateOf(…)` because the generator ages them '
    .'deliberately and an age must stay an age. Four desk sites carry a behaviour case that freezes the clock at '
    .'18:00Z and asserts first that the two clocks disagree, so a case cannot pass by their agreeing — '
    .'`AssessmentController::compute`, `IDController::create`, `ClearanceController::approveRequirement` and '
    .'`EvaluationController::sign`; `mutate-lane87-school-day-writes.php` then plants the retired write at each of '
    .'them and requires BOTH its case to report one failure and the instrument to exit 1 naming that column, and '
    .'asks the same question of `RegistrarController::approve`’s `enrolledDate`, which no test pins — that site is '
    .'covered only because an instrument reads the shape. Three findings are instrument findings and belong on the '
    .'record. First, the instrument’s own print path hid twenty-two of the twenty-seven generator writes it was '
    .'asked about: it printed one row per column name for the columns it treated as its control, so a sweep whose '
    .'whole job is counting sites reported the class at three. The figure a tool prints is a property of how it '
    .'prints, and a count read off a summary line is not a census. Second, that control was keyed to the five '
    .'columns the tree carried on the day it was written, so the first repair turned a green sweep into REFUSED — a '
    .'positive control that requires the defect to survive is a control that fights the fix, and it now plants its '
    .'own throwaway file instead; the harness proves the swap by blinding the pattern so the plant cannot be '
    .'recovered, and by keying recovery on the row rather than the column name, each of which must refuse rather '
    .'than print CLEAN. Third, rehearsing the repaired generator produced eight errors that were the harness’s '
    .'doing, not the code’s: the E2E legs take the schema from `E2E_DB_DATABASE` but the connection from '
    .'`DB_CONNECTION`, and phpunit’s own defaults pin sqlite, so naming a MySQL schema without saying so makes '
    .'Laravel open a SQLite file called `ems_l87_a`. Run in CI’s order with the connection pinned, the same schema '
    .'answers 0 on all seven `Ledger —` lines and OK on 8 tests over 207 assertions.';

$row = $writeCell($template, 0, $mine);
$row = $writeCell($row, 1, $record);
if ($row === $template) {
    fwrite(STDERR, "REFUSED: the cloned row is byte-identical to its template.\n");

    exit(1);
}
$xml = substr_replace($xml, $row, $rEnd, 0);

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>'], ['<w:r', '</w:r>']] as [$o, $c]) {
    $a = preg_match_all('/'.preg_quote($o, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $c);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: unbalanced {$o} ({$a} against {$b}).\n");

        exit(1);
    }
}
if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}
foreach ([$mine, 'a positive control that requires the defect to survive', 'OK on 8 tests over 207 assertions'] as $want) {
    if (substr_count($xml, $want) < 1) {
        fwrite(STDERR, "REFUSED: the edited document does not contain [{$want}].\n");

        exit(1);
    }
}

printf("%s: §1.1 records G-43 (%d bytes of record)\n", $apply ? 'APPLY' : 'DRY RUN', strlen($record));
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass292-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: no backup at {$backup}.\n");

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
    fwrite(STDERR, "REFUSED: the archive did not read back its bytes. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 292 applied: §1.1 carries G-43 and the three instrument findings.\n";
