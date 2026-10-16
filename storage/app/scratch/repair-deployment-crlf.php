<?php
/**
 * Repair the doubled carriage returns a previous DEPLOYMENT.md write produced.
 *
 * The add-paragraph script joined its lines with PHP_EOL — which on Windows is already "\r\n" — and then ran
 * `str_replace("\n", "\r\n", …)` over the result, turning every one of its own line breaks into "\r\r\n".
 * Its guards counted CRLF pairs and bare LFs, neither of which moves when a third CR appears, so the file
 * passed them. This normalises and then proves no doubled CR survives anywhere in the file.
 */
$path = 'DEPLOYMENT.md';
$bytes = file_get_contents($path);

$before = substr_count($bytes, "\r\r\n");
echo "doubled CR line breaks found: {$before}\n";

if ($before === 0) {
    echo "nothing to repair\n";
    exit(0);
}

$fixed = str_replace("\r\r\n", "\r\n", $bytes);
file_put_contents($path, $fixed);

$after = file_get_contents($path);
printf(
    "repaired: %d → %d doubled, CRLF=%d, bare LF=%d, bytes %+d\n",
    $before,
    substr_count($after, "\r\r\n"),
    substr_count($after, "\r\n"),
    substr_count($after, "\n") - substr_count($after, "\r\n"),
    strlen($after) - strlen($bytes)
);

if (substr_count($after, "\r\r\n") !== 0) {
    fwrite(STDERR, "still doubled — the replacement did not do what this script claims\n");
    exit(1);
}
