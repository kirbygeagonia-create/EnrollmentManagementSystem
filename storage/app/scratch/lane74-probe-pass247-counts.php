<?php

/**
 * Read-only: did docx pass 247 reach BOTH copies of each doubled sentence?
 *
 * Run because pass 247 passed `[from, to, 2]` — an integer in the engine's ANCHOR slot, not the count slot — and the
 * engine resolves that shape to a single target (see lane74-probe-engine-slot3.php). Each new phrase must read 2 and
 * each old needle 0, which is what the document says today.
 */
$root = dirname(__DIR__, 3);
$z = new ZipArchive;
if ($z->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "REFUSED: the document cannot be opened from $root.\n");
    exit(1);
}
$xml = (string) $z->getFromName('word/document.xml');
$z->close();

$pairs = [
    'Ruled 2026-10-26: all nine lines are the school' => 'Which offices SEAIT requires to sign, and what each one clears, is still the school',
    'Ruled 2026-10-26: as printed' => 'So the answer SEAIT gives is the wording of the lines',
    'Ruled 2026-10-26: the desk print is the paper' => 'Which of the two SEAIT accepts at a window',
    'Ruled 2026-10-26: as built' => "and the \xc\xa1?100.00 in the seeders is the developer",
];
foreach ($pairs as $new => $old) {
    printf("new %-3d  old %-3d  | %s\n", substr_count($xml, $new), substr_count($xml, $old), substr($new, 0, 46));
}

printf("\nand four remained: %d   and four remain:: %d\n", substr_count($xml, 'and four remained'), substr_count($xml, 'and four remain:'));
printf('were ruled on 2026-10-26: %d   stay open on their own questions: %d', substr_count($xml, 'were ruled on 2026-10-26'), substr_count($xml, 'stay open on their own questions'));
echo "\n";
