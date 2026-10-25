<?php

/**
 * Lane 70, pass 236: §13's Department Evaluation step row cites a comment line for `evaluatedBy`.
 *
 * The cell reads "evaluatedBy written by profile capture ( :273 )". EvaluationController.php:273 is a *comment* inside
 * `returningStudents()` (259-292) — the returning-student picker — while `evaluatedBy` is written at :496, inside
 * `captureProfile()` (416-522), which is exactly the "profile capture" the sentence names. (The other write, :539, is
 * in `decideStanding()`; the row does not claim it.) **31st wrong target.**
 *
 * Pass 235 failed on this with "no needle owned by the anchor", and the reason is the lesson: the anchor phrase
 * `evaluatedBy written by profile capture` spans three `<w:t>` runs (`evaluatedBy` is styled), so it is not a
 * substring of document.xml at all — while `:273` sits alone in its own run. The anchor is therefore a phrase that
 * DOES live in one run: ` written by profile capture (`. `:273` occurs three times in the document (§1.1's own quoted
 * example among them), so the copy is chosen by the anchor, not by a count.
 *
 * After the write the run re-flattens the owning cell and requires it to carry `(:496)` and no longer `(:273)`, and
 * requires the document-wide count of `:273` to have fallen by exactly one — the other two copies belong to prose
 * that is not this row's.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass236-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$ev = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Evaluation/EvaluationController.php'));

$fails = 0;
foreach ([[259, 'private function returningStudents'], [416, 'public function captureProfile'],
    [496, "'evaluatedBy' => Auth::user()->userId"], [539, "'evaluatedBy' =>"]] as [$no, $needle]) {
    $actual = trim((string) ($ev[$no - 1] ?? ''));
    if (! str_contains($actual, $needle)) {
        echo 'ASSERT FAILED EvaluationController.php:'.$no." must carry [$needle] — reads [$actual]\n";
        $fails++;
    }
}
if (str_contains(trim((string) ($ev[272] ?? '')), 'evaluatedBy')) {
    echo "ASSERT FAILED  :273 does carry evaluatedBy — the old pointer was right\n";
    $fails++;
}

// Count in document.xml, never in the .zip bytes: the package is compressed, so a plaintext needle finds nothing
// in the file itself and the guard would refuse a correct edit.
$readXml = static function (string $path): string {
    $z = new ZipArchive();
    if ($z->open($path) !== true) {
        echo "REFUSED: cannot open {$path}\n";
        exit(1);
    }
    $x = (string) $z->getFromName('word/document.xml');
    $z->close();

    return $x;
};

$before = $readXml($src);
$beforeCount = substr_count($before, ':273');
if ($beforeCount < 1 || substr_count($before, ' written by profile capture (') !== 1) {
    echo 'REFUSED: the anchor is not unique (anchor copies '.substr_count($before, ' written by profile capture (')
        .', `:273` copies '.$beforeCount.")\n";
    exit(1);
}
if ($fails > 0) {
    echo "REFUSED: {$fails} assertion(s) failed — no number written\n";
    exit(1);
}

if (! copy($src, $tmp)) {
    echo "REFUSED: no backup at {$tmp}\n";
    exit(1);
}

$apply = in_array('--apply', $argv, true);
docxApply($src, $tmp, [
    [':273', ':496', ' written by profile capture ('],
], [], $apply);

$afterXml = $readXml($apply ? $src : $tmp);
$afterCount = substr_count($afterXml, ':273');
$xml = $afterXml;
preg_match_all('/<w:tr[ >].*?(?=<w:tr[ >]|<\/w:tbl>)/s', $xml, $rm);
$cell = '';
foreach ($rm[0] as $row) {
    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $row, $m);
    $text = html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1);
    if (str_contains($text, 'written by profile capture')) {
        $cell = $text;
    }
}
printf("the row now reads: [%s]\n", mb_substr($cell, 0, 220));
if (! str_contains($cell, '(:496)') || str_contains($cell, '(:273)')) {
    echo "REFUSED: the rewritten cell does not carry (:496)\n";
    exit(1);
}
if ($afterCount !== $beforeCount - 1) {
    echo "REFUSED: `:273` went $beforeCount -> $afterCount, expected exactly one fewer\n";
    exit(1);
}

echo $apply ? "APPLIED\n" : "DRY RUN — nothing written\n";
