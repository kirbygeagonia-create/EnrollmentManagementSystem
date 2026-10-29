<?php

/**
 * Pass 289d: list the Project owner's identifiers the way the table parser reads them.
 *
 * `lane69-owes-table-check.php` takes each identifier from the START of a comma-separated segment. The
 * Registrar row I wrote that day matched ("G-33 (…), G-35 (…), G-37 (…)") while this one did not, because
 * G-41 and G-42 sat behind the word "plus" inside the first segment. So the check was right twice over: the
 * counts agreed while the membership did not, which is exactly the shape a tally can hide.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass289d.php [--apply]
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
$cellsOf = static function (string $row, bool $offsets = false): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, $offsets ? PREG_OFFSET_CAPTURE : 0);

    return $cm[0];
};

$text = 'G-13 (install-time: DEPLOYMENT.md §2 is where the proxy instruction lives, and ems:preflight now '
    .'answers whether the forwarded scheme can be trusted, reading the TRUSTED_PROXIES value it judges), '
    .'G-41 (whether a browser check belongs in the pipeline — §14’s console-error and empty/loading/responsive '
    .'clause has no instrument, so it is unverified rather than passing), G-42 (the remaining half: how long '
    .'a signed-in session lives after a desk deactivates the account). The last two are asked on '
    .'Documentation/School-Decision-Sheet-2026-10-29.md.';

$prefix = 'Project owner 3 G-13';
$hits = [];
$at = 0;
while (($start = strpos($xml, '<w:tr', $at)) !== false) {
    $end = strpos($xml, '</w:tr>', $start);
    if ($end === false) {
        break;
    }
    $end += 7;
    $row = substr($xml, $start, $end - $start);
    if (strncmp(implode(' ', array_map($flatten, $cellsOf($row))), $prefix, strlen($prefix)) === 0) {
        $hits[] = [$start, $end, $row];
    }
    $at = $end;
}
if (count($hits) !== 1) {
    fwrite(STDERR, 'REFUSED: the row ['.$prefix.'] matched '.count($hits)." rows.\n");

    exit(1);
}
[$start, $end, $row] = $hits[0];
$cells = $cellsOf($row, true);
if (count($cells) !== 3) {
    fwrite(STDERR, 'REFUSED: the row holds '.count($cells)." cells.\n");

    exit(1);
}

$src = $cells[2][0];
$cell = $src;
preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
foreach (array_slice($pm[0], 1) as $extra) {
    if ($flatten($extra) !== '') {
        fwrite(STDERR, "REFUSED: the identifiers cell holds a second paragraph with text.\n");

        exit(1);
    }
    $cell = str_replace($extra, '', $cell);
}
preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
$body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
$new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
    .$escape($text).'</w:t></w:r></w:p>', $body);
$rowNew = substr_replace($row, $new, (int) $cells[2][1], strlen($src));

preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rowNew, $check);
$got = $flatten($check[0][2]);
if ($got !== $text) {
    fwrite(STDERR, 'REFUSED READ-BACK: the cell reads ['.mb_substr($got, 0, 90)."].\n");

    exit(1);
}
foreach (['G-13', 'G-41', 'G-42'] as $id) {
    if (preg_match('/(^|, )'.preg_quote($id, '/').'\b/', $got) !== 1) {
        fwrite(STDERR, "REFUSED: [$id] is not at the start of a comma segment, so the parser cannot see it.\n");

        exit(1);
    }
}
$xml = substr_replace($xml, $rowNew, $start, $end - $start);

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

printf("%s: the Project owner row now lists its three ids where the parser looks\n", $apply ? 'APPLY' : 'DRY RUN');
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass289d-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: no backup.\n");

    exit(1);
}
$w = new ZipArchive;
if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen. Restore from {$backup}.\n");

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

echo "\npass 289d applied.\n";
