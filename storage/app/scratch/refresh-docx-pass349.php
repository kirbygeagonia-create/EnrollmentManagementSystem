<?php

/**
 * Docx pass 349 — §28.3 G-36 closed, and §23.6.1 gains its tenth key.
 *
 * Two kinds of change, in the order this repository has paid for:
 *
 *  (1) A NEW ROW in §23.6.1. The engine refuses any pass that moves a `<w:tr>`, so the row is cloned from its
 *      neighbour by byte offset — anchored on the row's WHOLE flattened prefix, cells joined with a separator, every
 *      run of a cloned cell emptied, and the read-back requiring the flattened cell to equal the supplied text
 *      (containment is not enough: pass 173's clone inherited the previous row's tail and every container guard still
 *      balanced).
 *  (2) SENTENCES, through the engine after the row is on disk. The list's own tally ("Nine keys in seven tables")
 *      is a boundary claim, not a summary — §23.6.1 says it holds EVERY unique index in the domain tables, so a key
 *      the list omits is a rule a later reader will assume the server does not enforce. `lane44-unique-keys.php` is
 *      what found this: it reports the server's ten keys across eight tables against the document's nine in seven.
 *
 * Also re-taken: §24's `notifications` heading (8 columns → 9), and G-36's register row, whose verdict cell now
 * leads Closed and states what enforces it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass349.php [--apply]
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
require __DIR__.'/docx-edit-engine.php';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$before = $xml;

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$rowsForAnchor = static function (string $xml, string $anchor) use ($flatten): array {
    $rowText = static function (string $row) use ($flatten): string {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

        return implode(' ', array_map($flatten, $cm[0]));
    };

    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        if (strncmp($rowText($row), $anchor, strlen($anchor)) === 0) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};

$buildRow = static function (string $row, array $values, string $anchor) use ($flatten, $escape): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    if (count($cm[0]) !== count($values)) {
        fwrite(STDERR, "REFUSED: the row anchored on '{$anchor}' has ".count($cm[0]).' cells, this pass writes '.count($values).".\n");
        exit(1);
    }
    foreach ($cm[0] as $cell) {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        if (count($pm[0]) === 1) {
            continue;
        }
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: a cell of the row anchored on '{$anchor}' holds a SECOND paragraph with text — read it before cloning.\n");
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
            fwrite(STDERR, "REFUSED: the replacement for cell {$j} of '{$anchor}' holds ".substr_count($new, '<w:t xml:space="preserve">')." text runs.\n");
            exit(1);
        }
        $clone = substr_replace($clone, $new, $at, $len);
    }

    if (substr_count($clone, '<w:tc>') !== count($cm[0])) {
        fwrite(STDERR, "REFUSED: the clone of '{$anchor}' lost or gained a cell.\n");
        exit(1);
    }

    return $clone;
};

// ── (1) the new §23.6.1 row ──────────────────────────────────────────────────────────────────────────────────
$anchor = 'uq_enrollment_subject_attempt';
$keyRow = ['uq_notifications_dedupe', 'dedupeKey',
    'One notice per act per recipient. Both bell writers are queued listeners, so a worker that takes the job again '
    .'would ring the same desk a second time for one signature; the key is what lets the server refuse it (§28.3 G-36).',
    'Nullable, and NULLs do not collide in it: an event with no history row behind it carries no act identity and is '
    .'delivered rather than swallowed. The key names the ACT and the addressee, never the message — a second signing '
    .'of one box after a return, or a second move through the same pair of statuses, is a different notice and still '
    .'arrives. Enforced by the schema plus one writer, App\Support\NoticeDelivery.'];

$found = $rowsForAnchor($xml, $anchor);
printf('row anchored on %s: %d match(es), want 1%s', $anchor, count($found), PHP_EOL);
if (count($found) !== 1) {
    exit("REFUSED — nothing written.\n");
}
[$start, $end] = $found[0];
$template = substr($xml, $start, $end - $start);
$newRow = $buildRow($template, $keyRow, $anchor);

foreach ($keyRow as $j => $value) {
    $cells = [];
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $newRow, $cells);
    if ($flatten($cells[0][$j]) !== $value) {
        fwrite(STDERR, "REFUSED: cell {$j} of the new row reads [".substr($flatten($cells[0][$j]), 0, 60)
            .'] not the text this pass supplied.\n');

        exit(1);
    }
}

$xml = substr_replace($xml, $template.$newRow, $start, $end - $start);

// ── (2) the sentences, written by byte offset in the same pass, then checked ─────────────────────────────────
$sentences = [
    ['Nine keys in seven tables.', 'Ten keys in eight tables.'],
    // §24's headings come in two spacing families (47 rows say `name  —  N columns`, 9 say `name — N columns`), so
    // this needle was measured off document.xml rather than typed from the flattened dump: the notifications heading
    // carries the double-spaced form, and a single-spaced needle matches zero runs and refuses the pass.
    ['notifications  —  8 columns', 'notifications  —  9 columns'],
];
foreach ($sentences as [$from, $to]) {
    $n = substr_count($xml, $from);
    printf('sentence %s: %d match(es), want 1 — %s%s', $to, $n, substr($from, 0, 30), PHP_EOL);
    if ($n !== 1) {
        exit("REFUSED — nothing written.\n");
    }
    $xml = str_replace($from, $to, $xml);
}

if (! $apply) {
    // `<w:t` alone also matches `<w:tc`, `<w:tbl` and `<w:tr`, so a text-run count taken with it is a number that
    // misleads; the open tag is `<w:t>` or `<w:t ` and that is what is counted here.
    $tally = static function (string $s): string {
        $t = substr_count($s, '<w:t>') + substr_count($s, '<w:t ');

        return sprintf('tr %d/%d, tc %d/%d, p %d/%d, t %d/%d',
            substr_count($s, '<w:tr'), substr_count($s, '</w:tr>'),
            substr_count($s, '<w:tc>'), substr_count($s, '</w:tc>'),
            substr_count($s, '<w:p>'), substr_count($s, '</w:p>'),
            $t, substr_count($s, '</w:t>'));
    };
    echo 'DRY RUN — nothing written. Planned bytes:  '.$tally($xml)."\n";
    echo                        'Before this pass:    '.$tally($before)."\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass349-backup.docx';
copy($docx, $backup);

$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    copy($backup, $docx);
    exit("REFUSED: the docx did not reopen for writing; restored.\n");
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

$verify = new ZipArchive;
$verify->open($docx);
$readBack = (string) $verify->getFromName('word/document.xml');
$verify->close();
if ($readBack !== $xml) {
    copy($backup, $docx);
    exit("REFUSED: the file on disk is not the document this pass built; restored from the backup.\n");
}
if (libxml_use_internal_errors(true) && simplexml_load_string($readBack) === false) {
    copy($backup, $docx);
    exit("REFUSED: the written document.xml does not parse; restored.\n");
}
libxml_clear_errors();

foreach ([['uq_notifications_dedupe', 1], ['Ten keys in eight tables.', 1], ['notifications  —  9 columns', 1],
    ['Nine keys in seven tables.', 0], ['notifications  —  8 columns', 0]] as [$needle, $want]) {
    $n = substr_count(html_entity_decode($readBack, ENT_QUOTES | ENT_XML1), $needle);
    if ($n !== $want) {
        copy($backup, $docx);
        exit("REFUSED: [{$needle}] occurs {$n} time(s), want {$want}; restored.\n");
    }
}

echo "written, re-read: §23.6.1 holds the tenth key and says ten in eight, and §24 counts the column that carries it.\n";
