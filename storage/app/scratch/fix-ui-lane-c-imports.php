<?php
/**
 * Repair the two defects my own Lane C script introduced: a literal `\@` written into five import
 * specifiers (PHP does not interpolate \@ inside the replacement string, so it stayed), and one
 * file where the needed-import guard decided studentTypeTone was already present when it was not.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3).'/resources/js/Pages');

$files = [
    'Admission/Index.jsx', 'Admission/Show.jsx', 'Clearance/Index.jsx',
    'Evaluation/Show.jsx', 'Students/Show.jsx',
];

$bad = "'\\@/Components/ui'";
$good = "'@/Components/ui'";
$fixed = 0;

foreach ($files as $rel) {
    $path = $root.'/'.$rel;
    $src = (string) file_get_contents($path);

    if (! str_contains($src, $bad)) {
        printf("  skip %-22s no escaped specifier\n", $rel);

        continue;
    }

    $n = substr_count($src, $bad);
    $src = str_replace($bad, $good, $src);
    printf("  ok   %-22s %d specifier(s) unescaped\n", $rel, $n);
    $fixed++;

    file_put_contents($path, $src);
}

// Admission/Show calls studentTypeTone but never imported it.
$path = $root.'/Admission/Show.jsx';
$src = (string) file_get_contents($path);

if (str_contains($src, 'studentTypeTone[') && ! preg_match('/\bstudentTypeTone\b\s*(?:,|\})/', explode("\n", $src)[2] ?? '')) {
    $line = explode("\n", $src)[2];
    $newLine = preg_replace('/statusToneFor\b/', 'statusToneFor, studentTypeTone', $line, 1);
    $lines = explode("\n", $src);
    $lines[2] = $newLine;
    file_put_contents($path, implode("\n", $lines));
    printf("  ok   %-22s import += studentTypeTone\n", 'Admission/Show.jsx');
    $fixed++;
}

printf("\n%d file(s) touched\n", $fixed);

$left = 0;
foreach ($files as $rel) {
    $left += substr_count((string) file_get_contents($root.'/'.$rel), $bad);
}
echo $left === 0 ? "post-check: no escaped specifier remains\n" : "POST-CHECK FAILED: {$left} remain\n";
exit($left === 0 ? 0 : 1);
