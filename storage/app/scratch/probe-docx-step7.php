<?php
/**
 * TEMPORARY step-7 probe: which passages of the authority docx the build steps made stale.
 *
 * Read-only. Extracts paragraph text from word/document.xml and reports, for each needle,
 * the paragraphs that still assert the old behaviour.
 */

$docx = $argv[1] ?? 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem/Documentation/EMS Complete Documentation.docx';

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

echo 'document.xml chars: '.strlen($xml)."\n";

// Paragraph text: everything between <w:p ...> and </w:p>, tags stripped.
$paragraphs = [];
if (preg_match_all('/<w:p[ >].*?<\/w:p>/s', $xml, $m)) {
    foreach ($m[0] as $p) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $p, $t);
        $text = trim(html_entity_decode(implode('', $t[1] ?? []), ENT_QUOTES | ENT_XML1));
        if ($text !== '') {
            $paragraphs[] = $text;
        }
    }
}

echo 'paragraphs with text: '.count($paragraphs)."\n\n";

$needles = [
    'dead status' => ['never produced', 'unreachable', 'dead value', 'cannot be reached', 'no code can reach'],
    'term' => ['Summer', 'term 18', 'termId 18', 'special term'],
    'clearance gate' => ['no open period', 'open period', 'vacuously', 'clearance prerequisite'],
    'replacement fee' => ['hard-coded', '₱100', '100 is charged', 'config'],
    'pass slip' => ['clearance-passed', 'pass slip', 'clearance confirmed', 'confirmed by the department'],
    'refund' => ['refund', 'Refunded', 'part payment', 'partial'],
    'scholarship revoke' => ['revoke', 'expire', 'revoked', 'expired'],
    'drop' => ['drop an enrollment', 'dropped', 'Drop'],
    'officehead' => ['OfficeHead', 'office head', 'generalist'],
    'shift' => ['shifting', 'shift request', 'change of program', 'G-7'],
    'print attribution' => ['carrying no key', 'unattributable', 'NULL enrollment', 'one representative enrollment'],
    'returning student' => ['returning student', 'G-1', 'continues at Admission', 'starts at Admission'],
];

foreach ($needles as $group => $terms) {
    echo "===== {$group} =====\n";
    $seen = 0;

    foreach ($paragraphs as $i => $text) {
        foreach ($terms as $term) {
            if (mb_strpos($text, $term) !== false) {
                echo '  [p'.$i.'] '.$term.' :: '.mb_substr(preg_replace('/\s+/', ' ', $text), 0, 240)."\n";
                $seen++;
                break;
            }
        }

        if ($seen >= 8) {
            break;
        }
    }

    if ($seen === 0) {
        echo "  (no paragraph matches)\n";
    }

    echo "\n";
}
