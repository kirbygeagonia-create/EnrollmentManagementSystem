<?php

/**
 * Inspection for the "answered copy" of the school's ADS guide form: where the tick boxes live, how many there
 * are, what each one's surrounding sentence is, and where the answer area sits under each problem.
 *
 * Read-only. It prints counts and offsets so the writer can be built from what the file actually holds rather
 * than from how the printed PDF looks.
 *
 * Usage: php storage/app/scratch/lane201-inspect-guide-form.php [--src=<docx>]
 */
$src = 'C:/Users/ADMIN/Downloads/ADS_Final_Project_Guide_EMS_updated.docx';
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('/^--src=(.*)$/', $a, $m)) {
        $src = $m[1];
    } else {
        fwrite(STDERR, 'REFUSED: unreadable argument '.$a."\n");
        exit(1);
    }
}
if (! is_file($src)) {
    fwrite(STDERR, "REFUSED: the guide is not at {$src}.\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: the guide docx did not open (ZipArchive answered a truthy error code, which is how a missing file looks like a real archive).\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$names = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $names[] = $zip->getNameIndex($i);
}
$zip->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty.\n");
    exit(1);
}

printf("parts: %d — %s\n", count($names), implode(', ', array_slice($names, 0, 12)));
printf("document.xml: %d bytes\n", strlen($xml));
printf("literal U+2610 BALLOT BOX (☐): %d\n", substr_count($xml, '☐'));
printf("literal U+2611 BALLOT BOX WITH X (☑): %d\n", substr_count($xml, '☑'));
printf("MS FormCheckBox / FORMCHECKBOX fields: %d\n", substr_count($xml, 'FORMCHECKBOX'));
printf("w:sdt content controls: %d\n", substr_count($xml, '<w:sdt>'));
printf("checkbox symbol font runs (Wingdings): %d\n", substr_count(strtolower($xml), 'wingdings'));
printf("paragraphs: %d, tables: %d, table rows: %d\n", substr_count($xml, '<w:p>'), substr_count($xml, '<w:tbl>'), substr_count($xml, '<w:tr>'));

// Flatten the way Word shows it: per paragraph, the joined runs.
$paras = [];
if (preg_match_all('/<w:p[ >].*?<\/w:p>/s', $xml, $pm)) {
    foreach ($pm[0] as $p) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $p, $tm);
        $text = trim(html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1));
        if ($text !== '') {
            $paras[] = $text;
        }
    }
}
printf("\nnon-empty paragraphs: %d\n", count($paras));

$boxes = [];
foreach ($paras as $i => $t) {
    if (str_contains($t, '☐') || str_contains($t, '☑')) {
        $boxes[] = [$i, $t];
    }
}
printf("paragraphs holding a tick box: %d\n\n", count($boxes));
foreach ($boxes as [$i, $t]) {
    printf("[%4d] %s\n", $i, mb_substr($t, 0, 120));
}

echo "\n--- the answer-area sentences, and what follows each ---\n";
foreach ($paras as $i => $t) {
    if (preg_match('/Your Analysis|Applicability|If N\/A|N\/A explanation|Be prepared|Student 1|Student 2|Instructor|Date|Signature/', $t)) {
        printf("[%4d] %s\n", $i, mb_substr($t, 0, 130));
    }
}
