<?php

/**
 * Deletes PrintCertificate.jsx's local enrollment-tone map (gap G-39) by byte offset.
 *
 * The multi-line Edit anchor matched nothing because the function is the LAST block in the file and my anchor
 * carried a trailing newline the file does not have — a spliced anchor has to be re-read, and the newline at
 * its end is part of it. (The file turned out to be LF, so this was not a line-ending problem; the measured
 * bytes are what the splice relied on.) Splicing by offset is newline-agnostic either way: find the
 * function's opener, find the closing brace of its body, and remove that span plus the blank line that
 * separated it.
 *
 * Asserts before and after: exactly one `getStatusTone` occurrence (the definition — the call site was
 * already re-pointed at the shared map), and zero afterwards.
 */
$path = dirname(__DIR__, 3).'/resources/js/Pages/Registrar/PrintCertificate.jsx';
$src = (string) file_get_contents($path);
$apply = in_array('--apply', $argv, true);

$opener = 'function getStatusTone(status) {';
if (substr_count($src, $opener) !== 1) {
    fwrite(STDERR, 'REFUSED: ['.trim($opener).'] occurs '.substr_count($src, $opener)." time(s).\n");

    exit(1);
}
if (substr_count($src, 'getStatusTone') !== 1) {
    fwrite(STDERR, 'REFUSED: getStatusTone occurs '.substr_count($src, 'getStatusTone')
        ." time(s); the call site should already be re-pointed, so only the definition may remain.\n");

    exit(1);
}

$at = strpos($src, $opener);
$bodyEnd = strpos($src, "\n}", $at);
if ($bodyEnd === false) {
    fwrite(STDERR, "REFUSED: the function's closing brace was not found.\n");

    exit(1);
}
$stop = $bodyEnd + 2; // through the closing brace and its newline

// Also remove the blank line that separated the two helpers, whichever newline style the file uses.
$lead = substr($src, max(0, $at - 2), 2);
$cutFrom = $at;
foreach (["\r\n\r\n", "\n\n"] as $blank) {
    if (substr($src, $at - strlen($blank), strlen($blank)) === $blank) {
        $cutFrom = $at - strlen($blank) + 1; // keep one newline: the previous function still needs its line break

        break;
    }
}

$out = substr($src, 0, $cutFrom).substr($src, $stop);

printf(
    "%s: removing %d bytes of re-declared tone map (%s)\n",
    $apply ? 'APPLY' : 'DRY RUN',
    strlen($src) - strlen($out),
    str_contains($src, "\r\n") ? 'CRLF' : 'LF'
);
echo 'lead before the opener: ['.str_replace(["\r", "\n"], ['CR', 'LF'], $lead)."]\n";

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

file_put_contents($path, $out);

$again = (string) file_get_contents($path);
if (str_contains($again, 'getStatusTone')) {
    fwrite(STDERR, "REFUSED READ-BACK: getStatusTone still appears in the file.\n");

    exit(1);
}
if (! str_contains($again, 'enrollmentStatusTone[')) {
    fwrite(STDERR, "REFUSED READ-BACK: the shared map is not read by the page any more.\n");

    exit(1);
}

echo "written: the certificate now reads the one tone map the desks read.\n";
