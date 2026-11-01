<?php

/**
 * Pass 385 — the three copies of `1170-1211` that `lane76-shift-cites --skip` left behind.
 *
 * Why they were skipped and why they still move: the tool attributes a bare `:NNNN-NNNN` to the nearest filename on
 * the same dump row, and for one of these that name is `app/Support/EnrollmentReadiness.php` — a file of 203 lines,
 * measured here rather than assumed. A pointer into a 203-line file cannot be `:1170-1211`, so that attribution is
 * the phantom-attribution shape lane 69 already names ("the first run attributed `( :248-267 )` to IDPolicy.php, a
 * 135-line file, and the number was *right*"). The sentence's own words settle it: both bare copies sit inside a
 * clause that names `EvaluationController.php:1146-1168` and `evaluation.clearance.confirm`, i.e. the clearance
 * confirmation act, and the tool had already byte-matched HEAD's 1170-1211 to the working 1194-1235.
 *
 * Refuses if the working window's endpoints are not what the sentences claim, and refuses if the count of needles is
 * not the count this pass read. Dry run by default; --apply writes.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', $GLOBALS['argv'], true);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass385-backup-'.date('Ymd-His').'.docx';
$live = $root.'/app/Http/Controllers/Evaluation/EvaluationController.php';
$head = $root.'/storage/app/scratch/lane181-head-EvaluationController.php';

// ── stage 1: prove the destination window in the working file ─────────────────────────────────────────────
foreach ([$head, $live] as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: missing ".basename($f)."\n");
        exit(1);
    }
}
$headLines = file($head);
$liveLines = file($live);
$want = [1170, 1191, 1211];   // HEAD: the opening of the cited act window, a midpoint, and its end
$got = [1194, 1215, 1235];    // working: the same three lines, +24
$check = static function (array $lines, array $nums, string $label) {
    $out = [];
    foreach ($nums as $n) {
        if (! isset($lines[$n - 1])) {
            fwrite(STDERR, "REFUSED: {$label} has no line {$n}.\n");
            exit(1);
        }
        $out[$n] = trim($lines[$n - 1]);
    }

    return $out;
};
$h = $check($headLines, $want, 'HEAD');
$w = $check($liveLines, $got, 'working');
printf("HEAD 1170: %s\nlive 1194: %s\n", substr($h[1170], 0, 70), substr($w[1194], 0, 70));
printf("HEAD 1211: %s\nlive 1235: %s\n", substr($h[1211], 0, 70), substr($w[1235], 0, 70));
// The endpoints must be the same KIND of line, or the citation moved onto a different construct.
foreach ([[1170, 1194], [1211, 1235]] as [$a, $b]) {
    if ((bool) preg_match('/public function/', $h[$a]) !== (bool) preg_match('/public function/', $w[$b])) {
        fwrite(STDERR, "REFUSED: HEAD {$a} and working {$b} are not the same kind of line; re-read the sentence.\n");
        exit(1);
    }
}

// ── stage 2: how many copies does the document actually hold? ───────────────────────────────────────────
$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: could not open the docx (an error code is truthy).\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$copies = substr_count($xml, '1170-1211');
printf("\n`1170-1211` occurs %d time(s) in document.xml\n", $copies);
if ($copies === 0) {
    echo "Nothing to write — every copy already moved.\n";
    exit(0);
}
// A number the document splits across runs is invisible to a naive count; refuse rather than half-move.
$joined = html_entity_decode(implode('', array_map(static fn (string $t): string => $t,
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $tm) ? $tm[1] : [])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$flatCopies = substr_count($joined, '1170-1211');
if ($flatCopies !== $copies) {
    fwrite(STDERR, "REFUSED: the joined text holds {$flatCopies} copies while the archive holds {$copies} — the token "
        ."is run-split, and a text needle would move only some of them. Take these through the cell kind.\n");
    exit(1);
}

echo "\nplan: rewrite every copy of 1170-1211 to 1194-1235\n";
if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";
    exit(0);
}

$res = docxApply($src, $backup, [['1170-1211', '1194-1235', null, $copies]], [], true);
if ($res === false) {
    fwrite(STDERR, "REFUSED: the engine would not apply this pass.\n");
    exit(1);
}

// ── stage 3: read back ──────────────────────────────────────────────────────────────────────────────────
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: could not re-open the docx.\n");
    exit(1);
}
$body = (string) $zip->getFromName('word/document.xml');
$zip->close();
$left = substr_count($body, '1170-1211');
$moved = substr_count($body, '1194-1235');
printf("\nread-back: `1170-1211` left %d (want 0); `1194-1235` now %d (want >= %d)\n", $left, $moved, $copies);
if ($left !== 0) {
    fwrite(STDERR, "POST-READ FAILED: {$left} copy(ies) survived. Restoring.\n");
    copy($backup, $src);
    exit(1);
}
echo "WROTE and READ BACK clean. Re-dump, then run sweep-docx-citations and the other doc gates.\n";
exit(0);
