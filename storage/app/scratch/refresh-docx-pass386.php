<?php

/**
 * Pass 386 — the §24/§23 column figures that P05's `students.rowVersion` moved.
 *
 * Discovered by a refusal, not by reading: `refresh-docx-pass384.php` stopped before writing and asked lane 60,
 * which answered that the enrollments aggregate is fine (the withdrawn `seatKey` and the new `requestKey` cancel)
 * but TWO other figures are stale — §23's census sentence (480 columns, the server holds 481) and §24's per-table
 * `students` heading (22 stated, 23 on the server). Both come from P05, and neither was in any plan until the
 * checker said so, which is the whole argument for deriving a document figure from the tool that prints it.
 *
 * Every replacement number is read from lane 60's own stale line on this run, never typed. It re-asks lane 60 after
 * writing and prints whatever remains, so a figure that only moves once its neighbour is fixed (the headings-sum
 * row) is surfaced rather than silently left.
 *
 * Dry run by default; --apply writes.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', $GLOBALS['argv'], true);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$stamp = date('Ymd-His');
$orig = $root.'/storage/app/scratch/pass386-original-'.$stamp.'.docx';
$engineBackup = $root.'/storage/app/scratch/pass386-engine-'.$stamp.'.docx';

chdir($root);

/** Ask lane 60 and take its stale lines apart. */
$ask = function (): array {
    global $php;
    $out = preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec(escapeshellarg($php).' storage/app/scratch/lane60-ratio-claims.php --run 2>&1'));
    $stale = [];
    foreach (preg_split('/\n/', $out) as $line) {
        // The columns are padded, and a per-table row carries TWO tokens before `line` ("per-table students"),
        // while a sentence row carries one ("counts-sentence"). Split loosely, then read the tail.
        if (preg_match('/STALE\s+(.*?)\s+line \d+\s+(.*)$/', $line, $m)) {
            $head = preg_split('/\s+/', trim($m[1]));
            $stale[] = [
                'surface' => $head[0] ?? '',
                'what' => $head[1] ?? '',
                'table' => count($head) > 2 ? $head[2] : '',
                'detail' => trim($m[2]),
            ];
        }
    }

    return [$stale, $out];
};

// Read the document once, up front: the per-table branch needs its real bytes to build a needle, and a heading
// spelled with one space rather than the two the document uses matches nothing.
$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: could not open the docx as an archive.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$joined = html_entity_decode(implode('', array_map(static fn (string $t): string => $t.' ',
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $tm) ? $tm[1] : [])), ENT_QUOTES | ENT_HTML5, 'UTF-8');

[$stale, $outRaw] = $ask();
if ($stale === []) {
    echo "lane60 reports no stale figure — this pass has nothing to do.\n";
    exit(0);
}
echo "lane60 stale lines this run:\n";
foreach ($stale as $s) {
    printf("  [%s] %s — %s\n", $s['surface'], $s['what'], $s['detail']);
}

$edits = [];
foreach ($stale as $s) {
    if ($s['what'] === 'column-aggregate'
        && preg_match('/claimed (\d+), the server answers (\d+)/', $s['detail'], $m)) {
        // §24's cross-check row: the per-table headings must add up to what the server holds. It only becomes stale
        // once a heading is fixed — which is why this pass re-asks lane 60 rather than computing the pair itself.
        $edits[] = [$m[1].' columns documented for', $m[2].' columns documented for',
            '§24 column-aggregate cross-check row'];
        continue;
    }
    if ($s['what'] === 'per-table' && $s['table'] !== ''
        && preg_match('/stated (\d+) columns, the server holds (\d+)/', $s['detail'], $m)) {
        // Take the needle from the bytes the document holds, not from a spelling this file guesses: §24's headings
        // exist in two spacing families (`name  —  N columns` and `name — N columns`), and a single-space needle
        // matched nothing in the other one. The separator is preserved because Word renders what is stored.
        $pat = '/'.preg_quote($s['table'], '/').'\s*—\s*'.$m[1].' columns/u';
        if (! preg_match($pat, $joined, $hit)) {
            fwrite(STDERR, "REFUSED: no heading for `{$s['table']}' matching `{$m[1]} columns` in the document.\n");
            exit(1);
        }
        $from = $hit[0];
        $to = preg_replace('/'.$m[1].' columns$/u', $m[2].' columns', $from);
        $edits[] = [$from, $to, '§24 per-table heading for `'.$s['table'].'` (raw: '.str_replace("\n", ' ', $from).')'];
        continue;
    }
    if ($s['what'] === 'counts-sentence' && preg_match('/columns claimed (\d+), the server answers (\d+)/', $s['detail'], $m)) {
        $from = $m[1].' columns,';
        $to = $m[2].' columns,';
        $edits[] = [$from, $to, '§23 census sentence: the column total'];
        continue;
    }
    printf("\nNOT HANDLED by this pass: [%s] %s — %s\n", $s['surface'], $s['what'], $s['detail']);
}

// Read the document once and assert every needle before writing anything.
$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: could not open the docx as an archive.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$joined = html_entity_decode(implode('', array_map(static fn (string $t): string => $t.' ',
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $tm) ? $tm[1] : [])), ENT_QUOTES | ENT_HTML5, 'UTF-8');

if ($edits === []) {
    echo "\nNo stale figure this pass knows how to write. Nothing to do.\n";
    exit(0);
}
foreach ($edits as [$from, $to, $what]) {
    $got = substr_count($joined, $from);
    if ($got !== 1) {
        fwrite(STDERR, "REFUSED: `$from` ($what) occurs {$got} time(s), expected exactly 1. Nothing written.\n");
        exit(1);
    }
    printf("\nplan  %-52s `%s` -> `%s`\n", $what, $from, $to);
}

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";
    exit(0);
}

if (@copy($src, $orig) !== true) {
    fwrite(STDERR, "REFUSED: no original copy at {$orig}; refusing to mutate without a rollback.\n");
    exit(1);
}

$pairs = [];
foreach ($edits as [$from, $to, $what]) {
    $pairs[] = [$from, $to, null, 1];
}
docxApply($src, $engineBackup, $pairs, [], true);

// ── read back, then re-ask the checker ───────────────────────────────────────────────────────────────────
if ($zip->open($src) !== true) {
    copy($orig, $src);
    fwrite(STDERR, "REFUSED: cannot re-open after the write; restored.\n");
    exit(1);
}
$body = (string) $zip->getFromName('word/document.xml');
$zip->close();
$after = html_entity_decode(implode('', array_map(static fn (string $t): string => $t.' ',
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $body, $am) ? $am[1] : [])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$problem = 0;
foreach ($edits as [$from, $to, $what]) {
    $left = substr_count($after, $from);
    $now = substr_count($after, $to);
    printf("read-back  %-52s old %d (want 0), new %d (want 1)\n", $what, $left, $now);
    if ($left !== 0 || $now !== 1) {
        $problem++;
    }
}
if ($problem > 0) {
    copy($orig, $src);
    fwrite(STDERR, "POST-READ FAILED; restored from {$orig}.\n");
    exit(1);
}

[$stillStale, $second] = $ask();
echo "\n--- lane60 after this pass ---\n";
if ($stillStale === []) {
    echo "no stale figure. ";
} else {
    foreach ($stillStale as $s) {
        printf("  STILL STALE [%s] %s — %s\n", $s['surface'], $s['what'], $s['detail']);
    }
    echo "  (a figure that only moves once its neighbour is fixed is surfaced here, never guessed at —"
        ." run this pass again, which re-reads lane60, or write the next one)\n";
}
echo preg_match('/VERDICT: (clean|refuse)/', $second, $v) ? "lane60 verdict: {$v[1]}\n" : "lane60 printed no verdict.\n";
echo "\nWROTE and READ BACK clean. Re-dump, then run sweep-docx-citations and the doc battery.\n";
exit(str_contains($second, 'VERDICT: clean') ? 0 : 1);
