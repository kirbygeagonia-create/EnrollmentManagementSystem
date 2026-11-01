<?php

/**
 * One-off prose fixer: this repository reserves `§NN` for the authority docx's own sections, and the prompt's
 * numbered parts are named "prompt part NN". `lane37b-repo-citation-sweep.php` charges a bare `§40` to the docx
 * numbering and answers UNRESOLVED, which is the citation being wrong rather than the checker.
 *
 * It prints every line it changes and refuses when a file it was told to read does not exist, because a fixer that
 * silently skipped a file would report a clean sweep over prose it never opened.
 */

$root = dirname(__DIR__, 3);
$files = [
    $root.'/Documentation/DATABASE-DESIGN-ADS.md',
    $root.'/Documentation/EVIDENCE-PACK-ADS.md',
    $root.'/Documentation/IMPLEMENTATION-CHANGELOG-ADS.md',
    $root.'/Documentation/TESTING-AND-VERIFICATION-ADS.md',
    $root.'/Documentation/REQUIREMENTS-TRACEABILITY-ADS.md',
    $root.'/Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md',
    $root.'/Documentation/SECURITY-AND-RECOVERY-ADS.md',
    $root.'/Documentation/FINAL-REPORT-ADS.md',
    $root.'/Documentation/DEFENCE-EVIDENCE-ADS.md',
];
$changed = 0;
foreach ($files as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, 'REFUSED: '.basename($f)." is not on disk; this fixer would report clean over prose it never read.\n");

        exit(1);
    }
    $text = (string) file_get_contents($f);
    $out = preg_replace_callback(
        '/\b(prompt|Prompt)(\s+)§(\d{1,2})\b/u',
        static fn (array $m) => $m[1].$m[2].'part '.$m[3],
        $text,
        -1,
        $count
    );
    if ($count > 0) {
        if (file_put_contents($f, $out) === false) {
            fwrite(STDERR, 'REFUSED: could not write '.basename($f).".\n");

            exit(1);
        }
        echo str_pad(basename($f), 42).' '.$count." reference(s) re-spelled\n";
        $changed += $count;
    } else {
        echo str_pad(basename($f), 42)." no `prompt §NN` spelling\n";
    }
}
echo 'total '.$changed."\n";
