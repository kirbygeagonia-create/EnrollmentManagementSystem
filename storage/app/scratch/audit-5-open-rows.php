<?php

/**
 * Audit lane 5 — the authority document's own registers: which rows are still open.
 *
 * The docx is the authority (owner ruling 2026-09-29), and it carries the finding registers (§25,
 * §28 and the §23.x tables). A row that still reads "Open" or "Partly closed" after the code moved
 * on is a claim the defense can quote against us, and a row marked closed for the wrong reason is
 * worse. This lists every register row whose status is not terminal, so the remaining work is read
 * off the document rather than remembered.
 *
 * Read-only.
 */

$path = 'Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive;
if (! $z->open($path)) {
    exit("cannot open $path\n");
}
$xml = $z->getFromName('word/document.xml');
$z->close();

$terminal = ['closed', 'answered', 'enforced', 'consistent', 'resolved', 'done', 'not a defect',
    'by design', 'recorded', 'retired', 'replaced', 'verified', 'n/a', 'not applicable',
    'not a problem', 'no action', 'fixed', 'complete', 'accepted', 'correct as written',
    'built', 'ruled', 'not reproducible', 'cannot reproduce', 'informational'];

$openWords = ['open', 'partly', 'partial', 'pending', 'unverified', 'unresolved', 'todo', 'to do', 'remains',
    'still', 'not yet', 'needs', 'deferred', 'blocked', 'human', 'outstanding', 'not closed',
    'not implemented', 'not enforced', 'never'];

$tables = preg_split('/<w:tbl>/', $xml);
$reported = 0;
foreach (array_slice($tables, 1) as $ti => $chunk) {
    $body = substr($chunk, 0, (int) strpos($chunk, '</w:tbl>'));
    if ($body === '' || $body === false) {
        continue;
    }
    $rows = preg_split('/<w:tr[\s>]/', $body);
    $cellsOf = function (string $row): array {
        $out = [];
        foreach (preg_split('/<w:tc>/', $row) as $c) {
            if (preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $c, $m) === false) {
                continue;
            }
            $t = html_entity_decode(preg_replace('/\s+/', ' ', implode(' ', $m[1] ?? [])), ENT_QUOTES | ENT_XML1);
            $out[] = trim($t);
        }

        return $out;
    };

    if (count($rows) < 3) {
        continue;
    }
    // preg_split on '<w:tr' leaves the text BEFORE the first row in $rows[0], so the header is
    // $rows[1]. Reading $rows[0] found no 'Status' column in any table and the whole audit came
    // back "0 tables" — a clean document and a broken detector look identical until you check.
    $header = $cellsOf($rows[1]);
    $dataRows = array_slice($rows, 2);
    $headerJoined = strtolower(implode(' | ', $header));
    if (! str_contains($headerJoined, 'status') && ! str_contains($headerJoined, 'disposition')) {
        continue;
    }
    $statusCol = null;
    foreach ($header as $i => $h) {
        if (preg_match('/status|disposition|outcome/i', $h)) {
            $statusCol = $i;

            break;
        }
    }
    if ($statusCol === null) {
        continue;
    }

    $open = [];
    foreach ($dataRows as $row) {
        $cells = $cellsOf($row);
        if (count($cells) <= $statusCol) {
            continue;
        }
        $status = strtolower($cells[$statusCol]);
        // Classification order matters twice over. 'not closed' contains the terminal word 'closed';
        // 'not implemented' contains the terminal word 'implemented'. And a status the two
        // vocabularies simply do not know is NOT evidence of closure, so the default is to report
        // the row for a human look rather than to drop it.
        $negated = preg_match('/^(not|no|non|un|partial|partly|open|pending|todo|to do)\b/', $status) === 1
            || preg_match('/\b(not|never)\s+\S/', $status) === 1;
        $hitsOpen = false;
        foreach ($openWords as $o) {
            if (str_contains($status, $o)) {
                $hitsOpen = true;

                break;
            }
        }
        $hitsTerminal = false;
        foreach ($terminal as $t) {
            if (str_contains($status, $t)) {
                $hitsTerminal = true;

                break;
            }
        }
        if (! $negated && ! $hitsOpen && $hitsTerminal) {
            continue;
        }
        $looksOpen = $negated || $hitsOpen;
        $open[] = ($looksOpen ? 'OPEN ' : 'ASK  ')
            .implode(' || ', array_map(fn ($c, $i) => $i === $statusCol ? "[$c]" : $c, $cells, array_keys($cells)));
    }

    if ($open === []) {
        continue;
    }
    $reported++;
    echo "===== TABLE ".($ti + 1)." (header: ".implode(' / ', array_slice($header, 0, 6)).") — ".count($open)." non-terminal row(s)\n";
    foreach ($open as $o) {
        echo '  '.substr($o, 0, 460)."\n";
    }
    echo "\n";
}

echo "tables with a status column and at least one non-terminal row: $reported\n";
