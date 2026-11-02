<?php

/**
 * Repair the use-case guide so the figures drawn from it are proper UML.
 *
 * Three defects, each found by a machine reading rather than by eyeballing a render:
 *  (a) `옷` in front of every actor label — a mangled codepoint that prints as garbage in any viewer, 42 sites;
 *  (b) relationship labels written `"<<include>>"` / `"<<extend>>"`. Mermaid's HTML labels swallow the doubled
 *      angle brackets, so the stereotype vanished from the rendered arrow and showed as an empty box. UML spells
 *      them «include» / «extend», which mermaid renders as text;
 *  (c) `UC_Srch360` — "Inspect Unified Student 360° Profile" — is declared in the global boundary diagram and
 *      connected to NOTHING. A use case with no actor is not a use case. Desk 11's own block already answers who
 *      performs it (`A_Staff`, Institutional Staff, `students.view`), so the global diagram gains that actor and
 *      that association from the desk block's own model rather than from a guess made here.
 *
 * Every replacement is asserted against the count the file actually holds, and the pass refuses rather than
 * half-applying. Usage: php storage/app/scratch/lane209-repair-use-case-guide.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if ($a !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$a}; this pass reads only --apply.\n");
        exit(1);
    }
}
$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$file = 'Documentation/Use-Case-Diagram-Guide.md';
$text = (string) file_get_contents($root.'/'.$file);
$before = $text;

// (a) the mojibake glyph, wherever it sits.
$glyph = '옷';
$glyphHits = substr_count($text, $glyph);
if ($glyphHits < 1) {
    $fail('no mojibake glyph found — this pass has already run, or the file is not the one it was written for.');
}
$text = str_replace([$glyph.' ', $glyph], '', $text);

// (b) the stereotype spelling. The extend labels carry a second line inside the same quoted label
// (`"<<extend>>\n[Notice Configured]"`), so the replacement is on the bracket pair itself, not on a whole quoted
// string — a needle that demands the closing quote would silently skip every one of them and report a clean run.
$pairs = [
    ['<<include>>', '«include»'],
    ['<<extend>>', '«extend»'],
];
$counts = [];
foreach ($pairs as [$from, $to]) {
    $hits = substr_count($text, $from);
    if ($hits < 1) {
        $fail("no [{$from}] label remains — the guide's stereotype spelling moved.");
    }
    $counts[$from] = $hits;
    $text = str_replace($from, $to, $text);
}

// (c) the orphan: the global diagram's actor panel and the missing association.
$anchorActor = '        A_AdmStaff["System Administrator"]';
if (substr_count($text, $anchorActor) !== 1) {
    $fail('the global actor panel anchor occurs '.substr_count($text, $anchorActor).' time(s), wanted exactly 1.');
}
if (substr_count($text, 'A_Staff["Institutional Staff (Any Desk)"]') < 1) {
    $fail('desk 11 does not declare the staff actor this repair copies — read the desk block before inventing one.');
}
$text = str_replace(
    $anchorActor,
    $anchorActor."\n        A_Staff[\"Institutional Staff (Any Desk)\"]",
    $text
);

$anchorEdge = '    UC_AppAdm -.->|«extend»"| A_SMS';
$orphan = 'UC_Srch360';
if (substr_count($text, $orphan) !== 1) {
    $fail("the orphan node id occurs ".substr_count($text, $orphan)." time(s); the model changed since this pass was written.");
}
// Attach it where the global diagram's own associations live: append after the last association line in that block.
$lastAssoc = null;
if (preg_match_all('/^    A_[A-Za-z]+ --- UC_[A-Za-z0-9]+$/m', $text, $mm) >= 1) {
    $lastAssoc = end($mm[0]);
}
if ($lastAssoc === null) {
    $fail('no global association line was found to anchor the new one — the block shape moved.');
}
if (substr_count($text, 'A_Staff --- UC_Srch360') !== 0) {
    $fail('the association already exists — this pass has run.');
}
$text = str_replace($lastAssoc, $lastAssoc."\n    A_Staff --- ".$orphan, $text);

if (substr_count($text, 'A_Staff --- UC_Srch360') !== 1) {
    $fail('the new association did not land exactly once.');
}
if (substr_count($text, $glyph) !== 0) {
    $fail('the mojibake glyph survived the replacement.');
}
if (str_contains($text, '"<<')) {
    $fail('a doubled-angle-bracket stereotype label survives; the figure would render an empty box.');
}

printf("read: %d glyph site(s), %d include label(s), %d extend label(s), 1 orphan association added.\n",
    $glyphHits, $counts['<<include>>'], $counts['<<extend>>']);

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}
$backupPath = $root.'/storage/app/scratch/lane209-backup-'.date('Ymd-His').'.md';
if (! copy($root.'/'.$file, $backupPath)) {
    $fail('the backup copy could not be written.');
}
$w = @file_put_contents($root.'/'.$file, $text);
if ($w !== strlen($text)) {
    copy($backupPath, $root.'/'.$file);
    $fail('the write returned '.var_export($w, true).' — restored from '.$backupPath);
}
$again = (string) file_get_contents($root.'/'.$file);
if (substr_count($again, 'A_Staff --- UC_Srch360') !== 1 || str_contains($again, $glyph)) {
    copy($backupPath, $root.'/'.$file);
    $fail('read-back refused — restored from '.$backupPath);
}
printf("written; %d bytes; backup %s\n", $w, basename($backupPath));
