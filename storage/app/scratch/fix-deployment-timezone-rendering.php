<?php
/**
 * Repair the two rendering defects the previous script wrote into DEPLOYMENT.md.
 *
 * A fragment built in single quotes carried a literal "\n" instead of a line break, and a sentence lost the
 * opening backtick of one code span. Each fix asserts exactly one match before writing, and the report after
 * is the proof: no literal backslash-n, the backtick balanced, no line past the section's width.
 */
$path = 'DEPLOYMENT.md';
$crlf = "\r\n";
$bytes = file_get_contents($path);

$fails = 0;
$fixes = [
    'the literal \n that was meant to be a line break' => [
        'The second half is not'.chr(92).'na decision:',
        'The second half is not'.$crlf.'a decision:',
    ],
    'the code span that lost its opening backtick' => [
        'and'.$crlf.'enrollmentstatushistory.changedAt`',
        'and'.$crlf.'`enrollmentstatushistory.changedAt`',
    ],
];

foreach ($fixes as $label => [$from, $to]) {
    $hits = substr_count($bytes, $from);
    if ($hits !== 1) {
        fwrite(STDERR, "REFUSE: {$label} — matched {$hits}, expected 1\n");
        $fails++;

        continue;
    }
    $bytes = str_replace($from, $to, $bytes);
    echo "fixed    {$label}\n";
}

$check = str_replace($crlf, "\n", $bytes);
foreach ([
    'no literal backslash-n left in that paragraph' => ! str_contains($check, chr(92).'na decision'),
    'the backticks in the paragraph pair up' => (function () use ($check): bool {
        $at = strpos($check, '**Time zone: two processes');
        $para = substr($check, $at, strpos($check, "\n\n", $at) - $at);

        return substr_count($para, '`') % 2 === 0;
    })(),
    'no doubled CR' => substr_count($bytes, "\r\r\n") === 0,
] as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

echo $fails === 0 ? "ok\n" : "{$fails} problem(s)\n";
if ($fails > 0) {
    exit(1);
}

file_put_contents($path, $bytes);

$lines = explode($crlf, file_get_contents($path));
foreach ($lines as $n => $line) {
    if (strlen($line) > 122) {
        echo '  long line '.($n + 1).' ('.strlen($line)." chars)\n";
    }
}
echo 'rewrote '.count($lines)." CRLF lines\n";
