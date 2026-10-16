<?php

/**
 * Every citation in the authority docx that points into a file this batch edited, checked against
 * what that line says NOW.
 *
 * AGENTS.md rule: a cite is re-measured by meaning, not by arithmetic — the number has to land on the
 * method the sentence describes. This prints the cited line(s) and the enclosing function so a human
 * (or the next pass) can see whether the claim survived the edit.
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$edited = [
    'RbacSeeder.php', 'DevReferenceDataSeeder.php', 'Auditlogs.php', 'Enrollmentstatushistory.php',
    'UserManagementController.php', 'MegaAppLauncher.jsx', 'seed_full_demo_pipeline.php',
    'ReferenceDataController.php', 'Preflight.php',
];

$found = 0;
foreach ($edited as $file) {
    preg_match_all('/'.preg_quote($file, '/').':(\d+)(?:-(\d+))?/', $xml, $m, PREG_OFFSET_CAPTURE);
    if ($m[0] === []) {
        printf("%-32s no citations in the docx\n", $file);
        continue;
    }

    $path = null;
    foreach (['app', 'database', 'resources/js', 'tools', 'routes'] as $dir) {
        $cand = $root.'/'.$dir;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cand, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getFilename() === $file) {
                $path = $f->getPathname();
                break 2;
            }
        }
    }

    if ($path === null) {
        printf("%-32s cited but the file was not found — investigate\n", $file);
        continue;
    }

    $lines = file($path);
    printf("\n%s  (%s lines)\n", $file, count($lines));
    foreach ($m[0] as $i => $hit) {
        [$text] = $hit;
        $from = (int) $m[1][$i][0];
        $to = ($m[2][$i][0] ?? '') === '' || $m[2][$i][0] === null ? $from : (int) $m[2][$i][0];
        $found++;
        $past = $to > count($lines);
        printf("  cite %-28s %s\n", $text, $past ? '*** PAST END OF FILE ('.count($lines).' lines) ***' : '');
        foreach (range($from, min($to, count($lines))) as $ln) {
            printf("      %4d  %s\n", $ln, rtrim($lines[$ln - 1]));
        }
        // The enclosing function, searched backwards, so "which method is this line inside" is shown.
        $enc = '?';
        for ($k = min($to, count($lines)); $k > 0; $k--) {
            if (preg_match('/(function|case)\s+(\w+)/', $lines[$k - 1], $mm) === 1) {
                $enc = $mm[2].' (line '.$k.')';
                break;
            }
        }
        printf("      enclosing: %s\n", $enc);
    }
}

printf("\ntotal citations checked: %d\n", $found);
printf("re-run this after any further edit to these files; a cite that moved is a false claim about code.\n");
exit(0);
