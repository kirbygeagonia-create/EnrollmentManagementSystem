<?php

/**
 * Docx pass 250b — correct the one thing pass 250 wrote wrong, and prove it is the only one.
 *
 * Pass 250 rewrote §24's `admissions — 11 columns` heading by collapsing its three runs into one and keeping
 * the FIRST run's spacing, which produced `admissions  —2 columns`: the dash lost the spaces after it. Measured
 * in the written file, §24 holds two spacing families already — 47 headings flattening to `name  —  N columns`
 * and 9 single-run headings reading `name — N columns` — so the one heading this batch touched is in neither.
 * It goes back to the family its own runs came from. The chapter's two families are not normalised here: that
 * is a question for the owner, not a side effect of this batch.
 *
 * This pass refuses unless it finds exactly one such heading, and re-reads every §24 heading in the written
 * bytes to show the spacing is uniform again.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass250b.php           (dry run)
 *        php storage/app/scratch/refresh-docx-pass250b.php --apply
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

$original = $xml;
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

/**
 * Every §24 per-table heading paragraph: [offset, paragraph xml, flattened text].
 *
 * @return array<int, array{0:int,1:string,2:string}>
 */
$headings = static function (string $body) use ($escape): array {
    $out = [];
    preg_match_all('/<w:p(?: [^>]*)?>.*?<\/w:p>/s', $body, $pm, PREG_OFFSET_CAPTURE);
    foreach ($pm[0] as $p) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $p[0], $tm);
        $held = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
        if (preg_match('/^[a-z]+\s*[—-]\s*\d+ columns$/u', trim($held))) {
            $out[] = [$p[1], $p[0], $held];
        }
    }

    return $out;
};

// Measured, not assumed: §24 holds TWO spacing families already — 47 headings flattened to `name  —  N columns`
// (the dash carries the two spaces its runs were separated by) and 9 single-run headings reading `name — N
// columns`. This pass restores the one heading it touched to the family that heading came from; it does NOT
// re-space the chapter, which is a question for the owner, not a side effect of this batch.
$families = ['  —  ', ' — '];
$right = static function (string $held) use ($families): bool {
    foreach ($families as $sep) {
        if (preg_match('/^[a-z]+'.preg_quote($sep, '/').'\d+ columns$/u', trim($held))) {
            return true;
        }
    }

    return false;
};

$all = $headings($xml);
$offenders = array_values(array_filter($all, static fn ($h): bool => ! $right($h[2])));

printf("measured  %d §24 headings, %d of them spelled with the wrong spacing\n", count($all), count($offenders));
foreach ($offenders as [, , $held]) {
    echo '  offender: ['.trim($held)."]\n";
}

if (count($offenders) !== 1 || trim($offenders[0][2]) !== 'admissions  —2 columns') {
    fwrite(STDERR, "REFUSED: this pass fixes exactly the admissions heading; it found ".count($offenders)." offender(s).\n");
    exit(1);
}

[$at, $para] = $offenders[0];
$newText = 'admissions  —  12 columns';

preg_match('/<w:r\b[^>]*>.*?<\/w:r>/s', $para, $rm, PREG_OFFSET_CAPTURE);
if (! isset($rm[0][1])) {
    fwrite(STDERR, "REFUSED: the heading paragraph holds no run to take its properties from.\n");
    exit(1);
}
preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0][0], $pr);
$rebuilt = substr($para, 0, $rm[0][1]).'<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
    .$escape($newText).'</w:t></w:r></w:p>';

if (preg_match_all('/<w:p(?: [^>]*)?>/', $rebuilt) !== 1 || substr_count($rebuilt, '</w:p>') !== 1
    || substr_count($rebuilt, '<w:pPr>') !== substr_count($rebuilt, '</w:pPr>')) {
    fwrite(STDERR, "REFUSED: the rebuilt heading is not one balanced paragraph.\n");
    exit(1);
}

$newXml = substr_replace($xml, $rebuilt, $at, strlen($para));

$after = $headings($newXml);
$stillWrong = array_values(array_filter($after, static fn ($h): bool => ! $right($h[2])));
printf("re-read   %d headings (want %d), %d with the wrong spacing (want 0)\n", count($after), count($all), count($stillWrong));
if (count($stillWrong) !== 0 || count($after) !== count($all)) {
    fwrite(STDERR, "REFUSED: the heading census did not come back clean and unchanged in size.\n");
    exit(1);
}
if (substr_count($newXml, '<w:p') !== substr_count($xml, '<w:p')) {
    fwrite(STDERR, "REFUSED: paragraph containers moved; this pass rewrites one paragraph in place.\n");
    exit(1);
}
if (simplexml_load_string($newXml) === false) {
    fwrite(STDERR, "REFUSED: document.xml would not parse.\n");
    exit(1);
}

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass250b-backup-'.date('Ymd-His').'.docx';
copy($docx, $backup);
echo 'backup    '.$backup."\n";

$z = new ZipArchive;
$z->open($docx);
$z->addFromString('word/document.xml', $newXml);
$z->close();

$z2 = new ZipArchive;
$z2->open($docx);
$readBack = (string) $z2->getFromName('word/document.xml');
$z2->close();

if ($readBack !== $newXml) {
    fwrite(STDERR, "BAD: the archive does not hold the bytes this pass wrote.\n");
    exit(1);
}
if ($readBack === $original) {
    fwrite(STDERR, "BAD: the written part is byte-identical to the one read in.\n");
    exit(1);
}

$final = $headings($readBack);
printf(
    "wrote     the part reads back byte-identical; %d §24 headings, %d off-pattern\n",
    count($final),
    count(array_filter($final, static fn ($h): bool => ! $right($h[2])))
);
echo "pass 250b applied and verified.\n";
exit(0);
