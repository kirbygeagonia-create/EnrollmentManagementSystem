<?php

/**
 * Pass 183b — the four sentences pass 183 wrote that did not survive being measured.
 *
 * Two of them are figure defects of the class this repository keeps paying for: §1.1 attributed the sheet's
 * item count to §29.4, which counts the register (20, not 16); and P-19 says "seven identity rows" from the
 * rendered inspection PDF while the same row claims the system prints a document number the inspection PDF
 * does not carry. The cause is measurable and belongs in the row: ems:print-fidelity hands the view only a
 * clearance (PrintFidelitySamples.php:115), while PrintService::printClearanceSlip passes the number it
 * minted at :85 — so the desk paper has eight rows and the fidelity paper seven.
 *
 * The third repairs a count stated as a list (item 16 now exists, so "items 1, 2, 4 and 5" is one short),
 * and the fourth gives §25.11's question the same distinction rather than a number that holds only of the
 * inspection file.
 *
 *   php storage/app/scratch/refresh-docx-pass183b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass183b.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

$needles = [
    // §1.1's living row credited the sheet's item count to §29.4, which counts the register.
    ['§25.11 and §28.5 each gained '
        .'that question, which is why §29.4 now counts sixteen open decisions rather than fifteen, and the sheet '
        .'carries the reading of the paper against items 1, 2, 4 and 5 and §2’s record of the board programs '
        .'against items 6 and 9: BSCrim and BSSW are named there, while the database carries the departmental '
        .'flag on five.',
        '§25.11 and §28.5 each gained '
            .'that question, which is why §29.4 counts twenty open decisions where it had counted nineteen and '
            .'Documentation/School-Decision-Sheet-2026-10-23.md now carries sixteen items where it carried fifteen; '
            .'the two figures are different counts of different things, and the sentence that put the sheet’s number '
            .'on the register’s cell is this pass’s own correction. The sheet states the reading of the paper against '
            .'items 1, 2, 4 and 5 and §2’s record of the board programs against items 6 and 9 — BSCrim and BSSW are '
            .'named there, while the database carries the departmental flag on five — and item 16 asks which of the '
            .'two papers the system is to produce.',
        1, '§1.1 attributes the right count to the right register'],
    // P-19's evidence cell: the row count is a property of which render was read.
    ['A grep for a copy designation across every print view and desk page finds none. Live `ems` carries nine '
        .'clearancerequirements rows, one per office, which is what the table prints.',
        'A grep for a copy designation across every print view and desk page finds none. Live `ems` carries nine '
            .'clearancerequirements rows, one per office, which is what the table prints. The number of identity rows '
            .'differs between the two papers this row compares, and the difference is the render, not the paper: the '
            .'seven labels above are what the file read here prints, while the desk slip carries an eighth, Document '
            .'No., because ems:print-fidelity hands the view only a clearance (PrintFidelitySamples.php:115) and the '
            .'desk download also passes the number it has just minted (PrintService.php:85). A claim read off the '
            .'inspection file is therefore never a claim about what a window receives — the same distinction lane 62 '
            .'was built for, and the reason this row counts the office lines from the code and the identity rows from '
            .'the paper.',
        1, 'P-19’s evidence cell separates the desk paper from the inspection PDF'],
    // P-19's consequence cell: item 16 now exists.
    ['and items 1, 2, 4 and 5 of Documentation/School-Decision-Sheet-2026-10-23.md carry the reading of '
        .'the paper against it.',
        'and items 1, 2, 4, 5 and 16 of Documentation/School-Decision-Sheet-2026-10-23.md carry the '
            .'reading of the paper against it — item 16 asks this question itself, and was added after the sheet was '
            .'issued.',
        1, 'P-19’s consequence cell names item 16'],
    // §25.11's question row: "seven identity rows" holds only of the inspection render.
    ['The system prints seven identity rows and a nine-line office table (§25.6) and no print view marks a copy '
        .'at all.',
        'The system prints a block of identity rows over a nine-line office table (§25.6) — eight rows on the desk '
            .'paper, seven on the file ems:print-fidelity renders, because that command passes no document number — '
            .'and no print view marks a copy at all.',
        1, '§25.11’s row states the count as the render that shows it'],
];

foreach ($needles as [$from, $to, $want, $label]) {
    foreach (['<', '>'] as $c) {
        $say(! str_contains($to, $c), $label.': replacement carries no '.$c);
    }
    $say(! str_contains($to, '&amp;'), $label.': replacement carries no entities');
    $c = substr_count($new, $from);
    $say($c === $want, $label.': needle occurs '.$c.' time(s), expected '.$want);
}

// Every row this pass touches must still be the row pass 183 wrote — a needle that drifts onto a neighbour
// would rewrite the wrong cell and every container guard upstream would still balance.
$say(substr_count($new, '>P-19<') === 1, 'P-19 is still addressed once as a bare text node');
preg_match_all('/>P-(\d+)</', substr($new, (int) strpos($new, '>P-1<'), (int) strpos($new, '>P-19<') - (int) strpos($new, '>P-1<') + 7), $pm);
$say($pm[1] === array_map('strval', range(1, 19)), 'the print register still runs P-1 … P-19 in order, once each');
if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    // The write-time assertions are run against a staged copy here as well, so a guard that cannot be satisfied is
    // reported by the dry run rather than discovered by a --apply that refuses and leaves the file alone.
    $staged = $new;
    foreach ($needles as [$from, $to, $want, $label]) {
        $staged = str_replace($from, $to, $staged);
        $say(substr_count($staged, $to) === $want, '[dry] '.$label.': the replacement would land once');
    }
    $say(strlen($staged) > strlen($xml), '[dry] the document would grow');
    $say(@(new DOMDocument)->loadXML($staged) !== false, '[dry] the staged document.xml parses');
    if ($fails > 0) {
        printf("\n%d guard(s) failed in the dry run — the same run would refuse with --apply.\n", $fails);
        exit(1);
    }
    echo "\nDRY RUN: four sentences would be rewritten (the §1.1 count attribution, P-19's evidence and consequence"
        ." cells, and §25.11's question row). Re-run with --apply.\n";
    exit(0);
}

foreach ($needles as [$from, $to, $want, $label]) {
    $new = str_replace($from, $to, $new);
    // The pre-write guard proved the needle occurred exactly $want time, so every needle left in the result sits
    // inside a replacement that chose to keep its own opening clause. The claim that matters is that the new text
    // landed once — asking for the needle to vanish would refuse a correct write, which is what this pass's first
    // run did to itself.
    $say(substr_count($new, $to) === $want, $label.': the replacement is in the document '.$want.' time(s)');
}
$say(strlen($new) > strlen($xml), 'the document grew, as four replacements that add text must make it');
$say(@(new DOMDocument)->loadXML($new) !== false, 'the staged document.xml parses');
if ($fails > 0) {
    printf("\n%d guard(s) failed after the write — the file on disk is untouched (backup not overwritten).\n", $fails);
    exit(1);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass183b.docx');
$z = new ZipArchive();
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $new);
$z->close();
echo "WROTE {$src}\n";
