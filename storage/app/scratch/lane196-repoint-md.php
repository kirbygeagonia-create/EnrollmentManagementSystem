<?php

/**
 * Apply a byte-verified list of markdown re-point pairs produced by reading HEAD against the working file.
 *
 * `lane193-repoint-md.php` carries its own frozen needle list from the batch that wrote it, and on this run
 * every one of those needles matched 0 — the pointers it knew had already been re-taken, and the ones this
 * batch aged were not in its list. So the pairs come in as arguments instead, and the same discipline holds:
 * validate every pair before writing anything (a half-applied register is this repository's worst docx
 * defect, and a markdown file edited halfway is the same state), refuse a pair whose replacement is identical
 * to its needle, and refuse a needle that does not match exactly once.
 *
 * Pointers inside a document that carries the `Pointer currency` marker are REFUSED here rather than re-typed:
 * a dated document's digits are that day's record, and re-aging them states lines that were never true on its
 * own day.
 *
 * Usage: php storage/app/scratch/lane196-repoint-md.php \
 *          --pair=FILE|OLD|NEW [...]            (dry run)
 *        … --apply
 */
$root = dirname(__DIR__, 3);
chdir($root);

$pairs = [];
$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if ($a === '--apply') {
        $apply = true;

        continue;
    }
    if (preg_match('/^--pair=(.+)$/', $a, $m)) {
        $bits = explode('|', $m[1]);
        if (count($bits) !== 3) {
            fwrite(STDERR, 'REFUSED: --pair needs FILE|OLD|NEW, got '.escapeshellarg($a).".\n");
            exit(1);
        }
        $pairs[] = $bits;

        continue;
    }
    fwrite(STDERR, 'REFUSED: this tool does not understand '.escapeshellarg($a).".\n");
    exit(1);
}
if ($pairs === []) {
    fwrite(STDERR, "REFUSED: no --pair= given, so this run would judge nothing and print a clean verdict.\n");
    exit(1);
}

$contents = [];
$errors = [];
foreach ($pairs as [$file, $old, $new]) {
    if (! is_file($file)) {
        $errors[] = "missing file: {$file}";

        continue;
    }
    if ($old === $new) {
        $errors[] = "no-op pair refused by name: {$file} | {$old}";

        continue;
    }
    if (! isset($contents[$file])) {
        $contents[$file] = (string) file_get_contents($file);
        // A dated document keeps its digits; the marker is the currency sentence **with the day in parentheses**,
        // not merely the words. `AGENTS.md` explains that marker in prose, and a check on the bare phrase refused
        // to re-point a living file — the exemption matched the document that only mentions it.
        if (preg_match('/Pointer currency \(\d{4}-\d{2}-\d{2}\)/', $contents[$file]) === 1) {
            $errors[] = "{$file} carries the `Pointer currency` marker — its stale pointers are that day's record and are not re-typed";
        }
    }
    $hits = substr_count($contents[$file], $old);
    if ($hits !== 1) {
        $errors[] = "{$file}: needle {$old} occurs {$hits} time(s), expected 1";
    }
}
if ($errors !== []) {
    fwrite(STDERR, "REFUSED: nothing was written.\n");
    foreach ($errors as $e) {
        fwrite(STDERR, '  '.$e."\n");
    }
    exit(1);
}

foreach ($pairs as [$file, $old, $new]) {
    $contents[$file] = str_replace($old, $new, $contents[$file]);
    echo "PLAN      {$file}  {$old}  ->  {$new}\n";
}

if (! $apply) {
    echo count($pairs)." pair(s) validated (not written)\n";
    exit(0);
}

foreach ($contents as $file => $text) {
    if (file_put_contents($file, $text) === false) {
        fwrite(STDERR, "REFUSED: {$file} could not be written after the pairs were applied.\n");
        exit(1);
    }
}

// Read back: every new pointer present once, every retired pointer gone from the files this run touched.
$still = [];
foreach ($pairs as [$file, $old, $new]) {
    $text = (string) file_get_contents($file);
    if (substr_count($text, $new) < 1 || substr_count($text, $old) !== 0) {
        $still[] = "{$file} still reads {$old} / {$new}";
    }
}
if ($still !== []) {
    fwrite(STDERR, "REFUSED AFTER WRITE: the read-back does not match the plan.\n");
    foreach ($still as $s) {
        fwrite(STDERR, '  '.$s."\n");
    }
    exit(1);
}
echo 'written: '.count($pairs).' pointer(s) re-taken across '.count($contents)." file(s).\n";
