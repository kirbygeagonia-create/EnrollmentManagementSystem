<?php

/**
 * Pass 81 resolver — for each line number the docx cites, take the committed line's text
 * and find that same text in the working tree. The answer is the new number; a citation
 * whose text is not unique, or not present, is reported rather than guessed.
 */

$root = dirname(__DIR__, 3);

$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$text = html_entity_decode(preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1);

$targets = [
    'app/Services/PrintService.php' => 'PrintService.php',
    'bootstrap/app.php' => 'bootstrap/app.php',
];

foreach ($targets as $rel => $citedAs) {
    $old = explode("\n", (string) shell_exec(sprintf(
        'git -C %s show HEAD:%s',
        escapeshellarg($root),
        escapeshellarg($rel)
    )));
    $new = explode("\n", (string) file_get_contents($root.'/'.$rel));

    preg_match_all('/'.preg_quote($citedAs, '/').':(\d+)(?:\s*[-–]\s*(\d+))?/', $text, $m, PREG_SET_ORDER);

    $seen = [];
    echo "\n=== {$citedAs}\n";

    foreach ($m as $d) {
        $raw = $d[0];
        $from = (int) $d[1];
        $to = isset($d[2]) && $d[2] !== '' ? (int) $d[2] : $from;
        $key = $raw;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $resolve = function (int $lineNo) use ($old, $new): string {
            $needle = trim($old[$lineNo - 1] ?? '');
            if ($needle === '') {
                return "old:{$lineNo}=<blank>";
            }
            $hits = [];
            foreach ($new as $i => $line) {
                if (trim($line) === $needle) {
                    $hits[] = $i + 1;
                }
            }
            if (count($hits) === 1) {
                return "{$lineNo}→{$hits[0]}";
            }

            return "{$lineNo}→?".count($hits).' hits';
        };

        printf("%-32s from %s   to %s\n", $raw, $resolve($from), $resolve($to));
    }
}
