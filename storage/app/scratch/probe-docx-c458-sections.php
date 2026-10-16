<?php
/**
 * The four sections the C-4 / C-5 / C-8 register rows cite, read as they stand now.
 *
 * The register is the last thing a reader meets; the sections are what the build is
 * described in. A ruling answered only in the register leaves the two disagreeing, and
 * that is the defect class this whole reconciliation exists to close. Also looks for the
 * book's revision record under every name it might use.
 *
 * Nothing is written.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
$z->open($docx);
$xml = $z->getFromName('word/document.xml');
$z->close();

$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));

foreach (['9.4', '10.6', '11.4', '20.9'] as $id) {
    echo str_repeat('=', 100)."\nSECTION {$id}\n".str_repeat('=', 100)."\n";
    // Headings are "<id><space><Title Case words>"; take the heading then the text to the
    // next heading of the same shape.
    $from = 0;
    $shown = 0;
    while (preg_match('/'.preg_quote($id, '/').'\s+[A-Z§][^.\n]{4,90}?\s+(?=\d+\.\d+\s+[A-Z]|$)/', substr($plain, $from), $m, PREG_OFFSET_CAPTURE)) {
        $at = $from + $m[0][1];
        echo '@'.$at.'  '.mb_substr($m[0][0], 0, 90)."\n";
        echo '  body: '.mb_substr($plain, $at + strlen($m[0][0]), 700)."\n\n";
        $from = $at + max(1, strlen($m[0][0]));
        if (++$shown === 2) {
            break;
        }
    }
    if ($shown === 0) {
        echo "  no heading matched; raw occurrences of '{$id}': ".substr_count($plain, $id)."\n";
        $scan = 0;
        for ($k = 0; $k < 3 && ($p = strpos($plain, $id, $scan)) !== false; $k++) {
            echo '    @'.$p.'  '.mb_substr($plain, $p, 180)."\n";
            $scan = $p + 1;
        }
    }
    echo "\n";
}

echo str_repeat('=', 100)."\nREVISION RECORD — candidate names\n".str_repeat('=', 100)."\n";
foreach (['Version', 'version', 'Revision', 'revision', 'Amendment', 'Changelog', 'Change Log',
    'Change log', 'History of', 'document history', 'Updated'] as $needle) {
    $n = substr_count($plain, $needle);
    if ($n === 0) {
        continue;
    }
    $p = strpos($plain, $needle);
    echo str_pad($needle, 16)." x{$n}  first: ".mb_substr($plain, max(0, $p - 60), 190)."\n";
}
