<?php
/**
 * Read-only: after pass 186/186b, does any sentence in the document still present a row the owner
 * answered on 2026-10-24 as waiting on the school?
 *
 * lane61 catches a sentence asking what a register has ANSWERED; it cannot catch a row that keeps an
 * answered item's open-phrase without a contradicting verdict cell, so this walks the fresh dump by
 * topic and prints section + row for each hit to be read, not counted.
 */

$dump = (string) file_get_contents('storage/app/scratch/docx-flat.txt');
$lines = explode("\n", $dump);

$topics = [
    'certificate wording' => '/certificate|student type|enrollment type/iu',
    'scholarship term' => '/scholarship determination|fresh scholarship|re-determin/iu',
    'queue worker' => '/queue worker|QUEUE_CONNECTION|Supervisor/iu',
    'exam exemption' => '/waives? the General|entranceExamExempts/iu',
    'retention' => '/retain|retention period|evidence be kept/iu',
    'shifter exam' => '/shifter sit|departmental examination on entry/iu',
    'irregular on shift' => '/always.*Irregular|program change.*Irregular/iu',
];

$openPhrases = [
    'still the school’s', 'still the school’s to state', 'is the open question', 'awaits the school',
    'still owed', 'not yet answered', 'is still the Registrar’s', 'the school must', 'still the office’s',
    'remains the school’s', 'is the school’s to', 'has not stated', 'the school has not',
];

$section = '';
$hits = 0;
foreach ($lines as $l) {
    if (preg_match('/^P \d+: ([0-9]+(?:\.[0-9]+)?)/u', $l, $m)) {
        $section = $m[1];
        continue;
    }
    if (! preg_match('/^(ROW|P) \d+:/u', $l)) {
        continue;
    }
    foreach ($topics as $name => $pat) {
        if (preg_match($pat, $l) !== 1) {
            continue;
        }
        foreach ($openPhrases as $p) {
            if (str_contains($l, $p)) {
                $hits++;
                $id = preg_match('/^(ROW|P) (\d+)/u', $l, $r) ? $r[2] : '?';
                $pos = strpos($l, $p);
                printf("[%s] §%s %s: …%s…\n", $name, $section, $id,
                    trim(preg_replace('/\s+/u', ' ', substr($l, max(0, $pos - 95), 210))));
            }
        }
    }
}

echo "\nhits to read: {$hits}\n";
echo $hits === 0
    ? "No sentence pairs a ruled topic with an open phrase.\n"
    : "Read each above: a hit is a question for reading, not an automatic defect.\n";
