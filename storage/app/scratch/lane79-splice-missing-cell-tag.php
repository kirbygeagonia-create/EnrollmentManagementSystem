<?php

/**
 * Finds, and on --apply repairs, the one table-cell closing tag that docx pass 254 lost.
 *
 * Measured 2026-10-27: `word/document.xml` is 6,067,387 bytes and `pass254-backup-20261027-012614.docx`
 * carries 6,067,394 — seven bytes, exactly one `</w:tc>`. The backup parses with libxml; the working file
 * does not, and `docx-edit-engine.php` therefore refuses every write. Word opens the file because it
 * repairs an unclosed cell silently, and `docx-ooxml-check.php`, `verify-docx.php` and the citation
 * sweeps all passed the document after 254 wrote it, so nothing downstream saw the damage.
 *
 * The repair point is derived, never guessed: the two texts are walked in lockstep and every maximal
 * mismatch island is printed. Pass 254 also made legitimate edits, so several islands are expected — the
 * script acts only on an island where the backup holds `</w:tc>` and the working file holds nothing at
 * all, and refuses if that island is absent, ambiguous, or of any other shape.
 *
 * Ruled by the owner 2026-10-27: splice the missing tag back rather than roll the document back to the
 * pre-254 backup, because rolling back would also undo what 254 wrote correctly.
 *
 * Usage: php storage/app/scratch/lane79-splice-missing-cell-tag.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$reference = $root.'/storage/app/scratch/pass254-backup-20261027-012614.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

foreach ([['working docx', $docx], ['reference backup', $reference]] as [$what, $path]) {
    if (! is_readable($path)) {
        fwrite(STDERR, "REFUSED: the {$what} is not readable at {$path}\n");

        exit(1);
    }
}

$read = function (string $path): string {
    $zip = new ZipArchive;

    if ($zip->open($path) !== true) {
        fwrite(STDERR, "REFUSED: {$path} would not open as an archive\n");

        exit(1);
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if (! is_string($xml) || $xml === '') {
        fwrite(STDERR, "REFUSED: {$path} answered no word/document.xml\n");

        exit(1);
    }

    return $xml;
};

$now = $read($docx);
$was = $read($reference);

printf("working document.xml: %d bytes\nreference  document.xml: %d bytes\ndelta: %d bytes\n\n",
    strlen($now), strlen($was), strlen($was) - strlen($now));

if (strlen($was) <= strlen($now)) {
    fwrite(STDERR, "REFUSED: the working file is not smaller than the reference — the premise of this pass is gone\n");

    exit(1);
}

// Walk both texts in lockstep and collect maximal mismatch islands: (offsetInWorking, workingRun, referenceRun).
$islands = [];
$i = 0;
$j = 0;
$ln = strlen($now);
$lw = strlen($was);

while ($i < $ln && $j < $lw) {
    if ($now[$i] === $was[$j]) {
        $i++;
        $j++;

        continue;
    }

    // Divergence starts. Grow each run until the remaining tails resynchronise: the first position
    // where a window of 64 bytes matches again ends the island.
    $startNow = $i;
    $startWas = $j;
    $probe = 40;
    $found = false;

    for ($advance = 0; $advance < 4000 && ! $found; $advance++) {
        // Try every split: working consumed k bytes, reference consumed advance-k.
        for ($k = 0; $k <= $advance; $k++) {
            $p = $startNow + $k;
            $q = $startWas + ($advance - $k);

            if ($p > $ln || $q > $lw) {
                continue;
            }

            $tailNow = substr($now, $p, $probe);

            if ($tailNow !== '' && strlen($tailNow) === $probe && substr($was, $q, $probe) === $tailNow) {
                $islands[] = ['now' => substr($now, $startNow, $k), 'was' => substr($was, $startWas, $advance - $k), 'at' => $startNow];
                $i = $p;
                $j = $q;
                $found = true;

                break;
            }
        }
    }

    if (! $found) {
        fwrite(STDERR, "REFUSED: the two texts never resynchronise after byte {$startNow} — this diff cannot be walked safely\n");

        exit(1);
    }
}

if ($i < $ln || $j < $lw) {
    $islands[] = ['now' => substr($now, $i), 'was' => substr($was, $j), 'at' => $i];
}

printf("mismatch islands: %d\n\n", count($islands));

foreach ($islands as $n => $isl) {
    $showNow = $isl['now'] === '' ? '(nothing)' : (strlen($isl['now']) > 120 ? substr($isl['now'], 0, 120).'…' : $isl['now']);
    $showWas = $isl['was'] === '' ? '(nothing)' : (strlen($isl['was']) > 120 ? substr($isl['was'], 0, 120).'…' : $isl['was']);
    printf("  #%d at %d\n      working: %s\n      reference: %s\n", $n + 1, $isl['at'], $showNow, $showWas);
}

echo "\n";

if (count($islands) !== 1) {
    fwrite(STDERR, 'REFUSED: '.count($islands)." islands — this pass acts only when the working file and the reference differ in exactly one place\n");

    exit(1);
}

$isl = $islands[0];
$at = $isl['at'];

// The measured shape: pass 254 changed one character and, in the same rewrite, swallowed a cell's closing
// tag — the reference island is the working island plus `</w:tc>` folded into it. So the repaired stream
// is the REFERENCE with that one changed character taken back from the working file. Two checks make that
// a derivation rather than a guess, and neither of them depends on where the tag sits: removing exactly
// one `</w:tc>` from the candidate must reproduce the working file byte for byte, at exactly one position;
// and the candidate must differ from the reference at exactly the one byte pass 254 meant to change.
if (($now[$at] ?? '') === ($was[$at] ?? '')) {
    fwrite(STDERR, "REFUSED: the working and reference characters at byte {$at} are the same, so this is not a one-character edit\n");

    exit(1);
}

printf("the character pass 254 meant to change: reference '%s' → working '%s' at byte %d\n", $was[$at], $now[$at], $at);

$candidate = substr($was, 0, $at).$now[$at].substr($was, $at + 1);

$positions = [];
$probe = 0;

while (($probe = strpos($candidate, '</w:tc>', $probe)) !== false) {
    if (substr($candidate, 0, $probe).substr($candidate, $probe + 7) === $now) {
        $positions[] = $probe;
    }

    $probe += 7;
}

printf("positions where removing one </w:tc> reproduces the working file exactly: %d\n", count($positions));

if (count($positions) !== 1) {
    fwrite(STDERR, "REFUSED: the restored tag is not uniquely placed — a splice with more than one candidate position is a guess\n");

    exit(1);
}

$differing = 0;
$firstDiff = -1;
$len = strlen($candidate);

for ($k = 0; $k < $len; $k++) {
    if ($candidate[$k] !== $was[$k]) {
        $differing++;
        $firstDiff = $k;
    }
}

if ($differing !== 1 || $firstDiff !== $at) {
    fwrite(STDERR, "REFUSED: the candidate differs from the reference in {$differing} byte(s), not the one pass 254 changed\n");

    exit(1);
}

$repaired = $candidate;

if (strlen($repaired) !== strlen($was)) {
    fwrite(STDERR, sprintf("REFUSED: the repaired stream is %d bytes and the reference is %d — equal length is the point of a one-tag splice\n",
        strlen($repaired), strlen($was)));

    exit(1);
}

$counts = function (string $x): string {
    $parts = [];

    foreach (['tc', 'tr', 'tbl'] as $t) {
        $parts[] = $t.'('.substr_count($x, '<w:'.$t).'/'.substr_count($x, '</w:'.$t).')';
    }

    return implode(' ', $parts);
};

libxml_use_internal_errors(true);
$parse = function (string $x): string {
    libxml_clear_errors();

    return (new DOMDocument)->loadXML($x) ? 'PARSES' : 'FAILS';
};

printf("libxml: working=%s  repaired=%s  reference=%s\n", $parse($now), $parse($repaired), $parse($was));

if ($parse($repaired) !== 'PARSES') {
    fwrite(STDERR, "REFUSED: the repaired stream still does not parse, so the lost tag is not the only damage — stop and look again\n");

    exit(1);
}

printf("cell/row/table open|close: working=%s  repaired=%s  reference=%s\n", $counts($now), $counts($repaired), $counts($was));

if ($counts($repaired) !== $counts($was)) {
    fwrite(STDERR, "REFUSED: the repaired tag census does not equal the reference's\n");

    exit(1);
}

echo "\nall guards passed: the splice restores the one tag, parses, and matches the reference census\n";

if (! $apply) {
    echo "DRY RUN — pass --apply to write it into the docx.\n";

    exit(0);
}

$stamp = date('Ymd-His');
$safe = $root."/storage/app/scratch/pre-splice-{$stamp}.docx";
copy($docx, $safe);

if (! is_readable($safe)) {
    fwrite(STDERR, "REFUSED: the safety copy of the docx did not land at {$safe}\n");

    exit(1);
}

echo "safety copy: {$safe}\n";

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the working docx would not open for writing\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $repaired);
$zip->close();

$after = $read($docx);

if ($after !== $repaired) {
    copy($safe, $docx);
    fwrite(STDERR, "REFUSED: the docx did not come back byte-identical to what this pass meant to write — restored the safety copy\n");

    exit(1);
}

printf("written: word/document.xml is now %d bytes and libxml parses it (%s)\n", strlen($after), $parse($after));
