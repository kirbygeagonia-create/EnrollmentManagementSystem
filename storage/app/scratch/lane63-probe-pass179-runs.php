<?php

/**
 * Pass 179, probe 2 — print the RUNS of the three §24 paragraphs the pass must rewrite, and the
 * cell layout of every row whose anchor occurs twice. A text edit resolves a needle only inside
 * one <w:t>, so this decides which target goes through the engine as a paragraph rewrite and
 * which can be a plain in-run splice.
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive;
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$runsOf = function (string $xml, int $at, string $label): void {
    $start = max((int) strrpos(substr($xml, 0, $at), '<w:p>'), (int) strrpos(substr($xml, 0, $at), '<w:p '));
    $end = (int) strpos($xml, '</w:p>', $at) + 6;
    $body = substr($xml, $start, $end - $start);
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $body, $t);
    printf("\n%s  para @%d..%d  %d run(s):\n", $label, $start, $end, count($t[1]));
    foreach ($t[1] as $i => $run) {
        printf("   run %d: [%s]\n", $i + 1, html_entity_decode($run, ENT_QUOTES | ENT_XML1));
    }
};

// The dictionary heading for studentclearances, and the two prose lines under it.
$head = strpos($xml, '>studentclearances — 9 columns<');
if ($head === false) {
    // Split across runs: find the paragraph that carries the model line, then walk back two paragraphs.
    $model = strpos($xml, 'Model App\Models\Studentclearances');
    $guard = $model === false ? 'NOT FOUND the model line' : 'model line found';
    printf("%s\n", $guard);
    if ($model !== false) {
        $runsOf($xml, $model, 'PARAGRAPH 1811 (model line)');
        // the two paragraphs before it
        $prevEnd = (int) strrpos(substr($xml, 0, $model), '</w:p>');
        $prev = (int) strrpos(substr($xml, 0, $prevEnd), '<w:p');
        $runsOf($xml, $prev, 'PARAGRAPH 1810 (role line)');
        $prevEnd2 = (int) strrpos(substr($xml, 0, $prev), '</w:p>');
        $prev2 = (int) strrpos(substr($xml, 0, $prevEnd2), '<w:p');
        $runsOf($xml, $prev2, 'PARAGRAPH 1809 (heading line)');
    }
} else {
    $runsOf($xml, $head, 'PARAGRAPH 1809 (heading, whole-run)');
}

$printf = printf("\n=== needle counts ===\n");
foreach ([
    'studentclearances — 9 columns',
    '>studentclearances<',
    ' — 9 columns',
    '9 columns',
    'Created by 2026_08_02_1000000021_create_studentclearances_table.php.',
    '2026_08_02_1000000021_create_studentclearances_table.php',
    'Rows in the demo database: 4.',
    'The column makes the schema look as if it supports deadlines',
    'Status moves to returnedToEvaluation',
    'The Evaluation queue shows the record again',
    'RegistrarController.php:288-310',
    'the state machine allows paid',
    'The two disagree on BR5',
    'returnToEvaluation()',
] as $n) {
    printf("  %d\t[%s]\n", substr_count($xml, $n), $n);
}

// Every row carrying a twice-seen anchor, so an expect=2 cell edit is proved against both copies.
foreach (['storyboard list or the code comments?', 'Is a returned enrollment’s payment kept'] as $anchor) {
    $scan = 0;
    $k = 0;
    while (($at = strpos($xml, $anchor, $scan)) !== false) {
        $k++;
        $start = (int) strrpos(substr($xml, 0, $at), '<w:tr');
        $end = (int) strpos($xml, '</w:tr>', $at) + 7;
        $row = substr($xml, $start, $end - $start);
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
        printf("\noccurrence %d of [%s]  row @%d..%d  %d cells\n", $k, mb_substr($anchor, 0, 34), $start, $end, count($cm[0]));
        foreach ($cm[0] as $j => $cell) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
            $flat = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
            printf("   cell %d paras=%d runs=%d head48=[%s]\n", $j + 1, substr_count($cell, '<w:p>'),
                count($tm[1]), mb_substr(preg_replace('/\s+/', ' ', $flat), 0, 48));
        }
        $scan = $at + 1;
    }
}

// The §24 dictionary row to delete: print the row's own cells so the deletion is addressed by content.
$at = strpos($xml, '>extendedDeadline<');
$start = (int) strrpos(substr($xml, 0, $at), '<w:tr');
$end = (int) strpos($xml, '</w:tr>', $at) + 7;
printf("\n§24 extendedDeadline row @%d..%d (%d bytes), flat: [%s]\n", $start, $end, $end - $start,
    preg_replace('/\s+/', ' ', html_entity_decode(
        implode('', (function (string $row): array {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $row, $t);

            return $t[1];
        })(substr($xml, $start, $end - $start))),
        ENT_QUOTES | ENT_XML1)));
