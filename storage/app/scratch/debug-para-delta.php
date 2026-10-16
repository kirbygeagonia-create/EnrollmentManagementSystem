<?php
// Why does the paragraph count drop? Apply each para rewrite in isolation and measure.

$z = new ZipArchive();
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

$base = substr_count($xml, '<w:p>');
$anchors = [
    'When the course-specific examination auto-approves an application',
    'It also reads true when there is nothing to read',
    'Installments are stored as ordinary',
    'A scholarship cannot be withdrawn after award',
    'the front end never reads it',
    'Eleven values are accepted by their columns',
];

foreach ($anchors as $a) {
    $p = strpos($xml, $a);
    $s = max(strrpos(substr($xml, 0, $p), '<w:p>'), strrpos(substr($xml, 0, $p), '<w:p '));
    $e = strpos($xml, '</w:p>', $p) + 6;
    $body = substr($xml, $s, $e - $s);

    preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $new = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $body);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">TEXT</w:t></w:r></w:p>', $new);

    printf(
        "%-52s isolated_delta=%d bodyOpen=%d newOpen=%d runs=%d\n",
        substr($a, 0, 50),
        substr_count(substr_replace($xml, $new, $s, strlen($body)), '<w:p>') - $base,
        substr_count($body, '<w:p>'),
        substr_count($new, '<w:p>'),
        preg_match_all('/<w:r\b/', $body)
    );

    $xml = substr_replace($xml, $new, $s, strlen($body));
}

echo 'cumulative delta = '.(substr_count($xml, '<w:p>') - $base)."\n";
