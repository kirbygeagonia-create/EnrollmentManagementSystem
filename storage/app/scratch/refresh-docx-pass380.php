<?php

/**
 * Pass 380 — `ems:audit:record-states` gained a twentieth rule, and the authority document states that count in
 * the present tense in one sentence (§18's command chapter, P 765).
 *
 * Both halves travel together: the count, and the list the count belongs to. Leaving the number true while the
 * enumeration stops at the money pair is lane 64's membership defect — a list that stopped growing reads as
 * complete, and the sentence beside it names a rule the paragraph never mentions.
 *
 * Dry run by default. --apply writes, after a timestamped backup.
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass380-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $GLOBALS['argv'], true);

$edits = [
    [
        'nineteen rules',
        'twenty rules',
        null,
        1,
    ],
    [
        'while it still owes',
        'while it still owes, and a block holding more students than its own maxStudents declares (the read side '
        ."of the invariant the assign guard now weighs under a row lock, added 2026-11-01 for the Advanced Database "
        ."Systems guide's capacity problem)",
        null,
        1,
    ],
];

foreach ($edits as $i => $e) {
    [$from, $to, $anchor, $expect] = $e;
    if ($anchor !== null && ! is_string($anchor)) {
        // null is the legal "no landmark" value; only an int or another type in slot 3 is the defect pass 247 met.
        fwrite(STDERR, "REFUSED text ".($i + 1).": slot 3 is the anchor and must be a string or null.\n");
        exit(1);
    }
    if ($from === $to) {
        fwrite(STDERR, "REFUSED text ".($i + 1).": the replacement is identical to the needle.\n");
        exit(1);
    }
    if ($expect !== 1) {
        fwrite(STDERR, "REFUSED text ".($i + 1).": this pass expects exactly one copy of each sentence.\n");
        exit(1);
    }
}

$res = docxApply($src, $backup, $edits, [], $apply);
if ($res === false) {
    fwrite(STDERR, "REFUSED: the engine would not apply this pass.\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    return;
}

// Read back over the archive, not the zip bytes: a guard that counts the compressed file "finds" 0 of a plaintext
// needle and refuses a correct edit, which is what pass 238 met.
$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: could not re-open the docx to read it back.\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();
if (! is_string($xml)) {
    fwrite(STDERR, "REFUSED: word/document.xml did not read back.\n");
    exit(1);
}
$text = preg_replace('/<[^>]+>/', '', $xml) ?? '';
$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

foreach (['nineteen rules' => 0, 'twenty rules' => 1,
    'a block holding more students than its own maxStudents declares' => 1] as $needle => $want) {
    $got = substr_count($text, $needle);
    if ($got !== $want) {
        fwrite(STDERR, "POST-READ FAILED: `{$needle}` occurs {$got} time(s), expected {$want}.\n");
        exit(1);
    }
    echo "read back: `{$needle}` => {$got}\n";
}
echo "backup: {$backup}\n";
