<?php

/**
 * Lane 70, pass 243 — NOT TO BE APPLIED. Kept as the proof that an assertion-gated pass refuses a wrong premise.
 *
 * I came to this row believing `Registrar/Index.jsx:93` was the 43rd wrong target: the sentence quotes the page
 * badge "Phase 5 · Registrar Central Desk", and a `sed -n '92p;93p'` I read too fast appeared to put the badge on
 * :92 with :93 the office badge. Opening the file with a real line-numbered reader showed the truth: :92 is the
 * `subtitle`, **:93 is the `phaseBadge` the sentence quotes**, and :94 is the office badge. The citation was always
 * right, and `:134-135` for the summary control is right too.
 *
 * The pass therefore stays exactly as written — its stage-1 assertions encode the false hypothesis, so it fails
 * three guards and exits `NOTHING WRITTEN` every time it is run. That is the artifact worth keeping: the rule this
 * lane repeats ("a pass must assert the lines it cites") is what stopped a confident, unique-needle, correctly-shaped
 * edit from corrupting a true citation, and `dump`/bounds/meaning sweeps would have cleared the result.
 *
 * The durable lesson is about the READER, not the document: `sed -n 'Ap;Bp'` prints lines in file order with no
 * numbers attached, so two adjacent props look like an off-by-one when they are not. Use a numbered reader (or
 * `lane70-read-span.php`, which prints the number) whenever the claim is "this pointer is one line off".
 */
$root = dirname(__DIR__, 3);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};
$jsx = $root.'/resources/js/Pages/Registrar/Index.jsx';
$fl = (array) file($jsx);
$at = static fn (int $n): string => rtrim((string) ($fl[$n - 1] ?? ''));

echo "=== stage 1: the lines the two pointers land on\n";
$guard(str_contains($at(134), 'label="Workflow Phase"') && str_contains($at(135), 'value="Phase 5"'),
    ':134-135 (kept) is the summary control the sentence quotes');
$guard(str_contains($at(92), 'phaseBadge="Phase 5') && str_contains($at(92), 'Registrar Central Desk'),
    ':92 IS the page badge whose wording the sentence quotes: '.trim($at(92)));
$guard(str_contains($at(93), 'officeBadge=') && ! str_contains($at(93), 'Phase 5'),
    ':93 is `officeBadge=` and carries no phase at all — the citation is wrong');
$guard(str_contains($at(91), 'subtitle='), ':91 is the subtitle, so the badge pair really is :92-:93');
if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

echo "\n=== stage 2: the needle on the staged bytes\n";
$z = new ZipArchive();
if ($z->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $z->getFromName('word/document.xml');
$parts = $z->numFiles;
$z->close();
$guard(str_contains($xml, '<w:body>'), 'the part read is document.xml ('.strlen($xml).' bytes)');

$from = 'Index.jsx:134-135 , :93 )';
$to = 'Index.jsx:134-135 , :92 )';
$guard(substr_count($xml, $from) === 1, 'the pair-form needle is contiguous and unique (seen '.substr_count($xml, $from).')');
$guard(substr_count($xml, $to) === 0, 'before the edit the corrected pair occurs nowhere');
if ($fails > 0) {
    printf("\n%d guard(s) failed before any write — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: pass 243 would move one §? label pointer, `Index.jsx:134-135 , :93 )` to `… , :92 )`.\n"
        ."Re-run with --apply.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass243.docx');
docxApply($src, $root.'/storage/app/scratch/pass243-backup.docx', [[$from, $to]], [], $apply);

$z = new ZipArchive();
$z->open($src);
$round = (string) $z->getFromName('word/document.xml');
$roundParts = $z->numFiles;
$z->close();
echo "\n=== stage 3: re-read the written bytes\n";
$guard($roundParts === $parts, 'the archive still holds '.$parts.' members');
$guard(substr_count($round, $from) === 0, 'the old pair is gone');
$guard(substr_count($round, $to) === 1, 'the corrected pair is on disk exactly once');
$guard(substr_count($round, ':92 )') === substr_count($xml, ':92 )') + 1, ':92 gains exactly this one citation');
if ($fails > 0) {
    printf("\n%d guard(s) failed after the write — restore the docx from storage/app/scratch/ems-docx-pre-pass243.docx.\n", $fails);
    exit(1);
}
echo "pass 243 applied.\n";
