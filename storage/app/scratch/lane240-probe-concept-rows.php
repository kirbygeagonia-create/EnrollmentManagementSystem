<?php

/**
 * Where does the answered copy put a Part IV concept row, its three option boxes, and the tick? This prints the
 * copy's own paragraphs inside the `IV. Required Database Concepts` window, numbered, so the label-level pairing
 * added to `lane207-verify-guide-copy.php` is written against the artefact's real shape rather than against the
 * writer's memory of it. Read-only: it opens the docx and prints, and writes nothing.
 */
$z = new ZipArchive;
if ($z->open('C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem/Documentation/ADS_Final_Project_Guide_EMS_ANSWERED.docx') !== true) {
    fwrite(STDERR, "REFUSED: the copy did not open as an archive\n");
    exit(1);
}
$xml = (string) $z->getFromName('word/document.xml');
$z->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty\n");
    exit(1);
}

// `<w:p>` is NOT the paragraph pattern: the stored file carries attributes (`<w:p w14:paraId="…">`), so a bare-tag
// pattern reads almost every paragraph as absent — `<w:t[^>]*>` matching `<w:tc>`/`<w:tr>` is the same trap pointed
// the other way. Require a space or an immediate `>` after the element name.
preg_match_all('#<w:p(?:\s[^>]*)?>(.*?)</w:p>#s', $xml, $pm);
$flat = [];
foreach ($pm[1] as $body) {
    preg_match_all('#<w:t(?: [^>]*)?>(.*?)</w:t>#s', $body, $tm);
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
    if ($t !== '') {
        $flat[] = $t;
    }
}

$from = null;
$to = null;
foreach ($flat as $i => $t) {
    if ($from === null && str_contains($t, 'IV. Required Database Concepts')) {
        $from = $i;
    } elseif ($from !== null && $to === null && str_contains($t, 'V. Final Presentation')) {
        $to = $i;
        break;
    }
}
if ($from === null || $to === null) {
    fwrite(STDERR, 'REFUSED: the §IV window could not be bounded (from '.var_export($from, true).', to '.var_export($to, true).")\n");
    exit(1);
}

printf("%d paragraphs in the copy; §IV spans %d..%d (%d shown)\n\n", count($flat), $from, $to, $to - $from);
$boxes = 0;
for ($i = $from; $i < $to; $i++) {
    $t = $flat[$i];
    $b = substr_count($t, '☐') + substr_count($t, '☑');
    $boxes += $b;
    $mark = str_repeat('-', 3).($b > 0 ? '['.$b.' boxes, '.substr_count($t, '☑').' ticked]' : ' plain');
    echo str_pad((string) $i, 4).' '.$mark.'  '.mb_substr($t, 0, 190)."\n";
}
echo "\n§IV box characters in the window: {$boxes}\n";
