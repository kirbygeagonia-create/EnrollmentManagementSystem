<?php

/**
 * Locates the first table-structure violation libxml trips on in `word/document.xml`.
 *
 * The docx engine's parse guard refused a write on 2026-10-27, and the same refusal reproduces against
 * the UNMODIFIED document with `LIBXML_PARSEHUGE`, through both simplexml and DOM. Word opens the file
 * and `docx-ooxml-check.php` calls it clean, so either libxml is refusing something Word tolerates or a
 * row clone in an earlier pass left a `w:tc` that never closed. The answer decides whether the guard or
 * the document is the defect — and the guard has already been proved able to refuse a truncated file,
 * so its complaint is worth chasing rather than overriding.
 *
 * Reads only. Usage: php storage/app/scratch/lane79-probe-table-depth.php
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive;

if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "REFUSED: the archive would not open\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

echo 'document.xml is '.strlen($xml)." bytes\n";

$token = '/<(\/?)w:(tc|tr|tbl)(?=[ >])/';
$depth = ['tbl' => 0, 'tr' => 0, 'tc' => 0];
$offending = [];
$offset = 0;
$scanned = 0;

while (preg_match($token, $xml, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
    $tag = $m[2][0];
    $closing = $m[1][0] === '/';
    $at = (int) $m[0][1];
    $scanned++;

    if ($closing) {
        $depth[$tag]--;

        if ($depth[$tag] < 0) {
            $offending[] = "a </w:{$tag}> at byte {$at} closes more than are open";

            break;
        }

        // A row that ends while a cell is still open, or a table that ends inside a row, is exactly
        // the cascade libxml reports (tc → tr → tbl → body).
        if ($tag === 'tr' && $depth['tc'] > 0) {
            $offending[] = "a </w:tr> at byte {$at} while {$depth['tc']} w:tc element(s) are still open";

            break;
        }

        if ($tag === 'tbl' && ($depth['tr'] > 0 || $depth['tc'] > 0)) {
            $offending[] = "a </w:tbl> at byte {$at} while tr depth {$depth['tr']} / tc depth {$depth['tc']} are open";

            break;
        }
    } else {
        // Self-closing forms are matched by the same pattern only when they carry a space and end in
        // `/`, so subtract them here rather than leaving a phantom open tag.
        $end = strpos($xml, '>', $at);
        $isSelfClose = $end !== false && ($end === 0 || $xml[$end - 1] === '/');

        if (! $isSelfClose) {
            $depth[$tag]++;
        }
    }

    $offset = $at + strlen($m[0][0]);
}

if (preg_last_error() !== PREG_NO_ERROR) {
    fwrite(STDERR, "REFUSED: the scan stopped because PCRE errored (code ".preg_last_error().'), not because the document answered\n');

    exit(1);
}

echo 'tokens scanned: '.$scanned."\n";
echo 'final depth: tbl='.$depth['tbl'].' tr='.$depth['tr'].' tc='.$depth['tc']."\n";

if ($offending !== []) {
    foreach ($offending as $problem) {
        echo "FOUND  {$problem}\n";
        $byte = (int) preg_replace('/.*byte (\d+).*/', '$1', $problem);
        $from = max(0, $byte - 260);
        echo "        context: ".str_replace(["\n", "\r"], ' ', substr($xml, $from, 320))."\n";
    }

    exit(0);
}

echo "no violation found in the whole stream — libxml's refusal is not a depth problem this scan can see\n";
