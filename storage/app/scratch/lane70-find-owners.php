<?php

/**
 * Lane 70: find who owns each still-unattributed pointer.
 *
 * The sweep prints a bare `:NN` as a blind spot when no file is named for it within 160 characters. The honest
 * remedy is the one AGENTS.md states: name the file in the sentence — which requires knowing the real owner, so
 * this searches the tree for the phrase the document is describing and prints the file:line it lives at. The
 * reading half stays here: a candidate is only a candidate until the line is compared with the sentence.
 *
 *   php storage/app/scratch/lane70-find-owners.php
 */
$root = dirname(__DIR__, 3);
$search = [
    'no open clearance period as satisfied' => '/treat.*no open clearance period|as satisfied/i',
    'APPROVED unless approved or waived' => '/APPROVED|approved or waived/',
    'class card badge renders a third' => '/badge/',
    'eligibility filter and per-row guard' => '/eligib/i',
    'grant reduces the bill (coverageType)' => "/coverageType/",
    'key-less legacy rows reserve' => '/MAX\(|mt_rand|reserve/i',
    'lock on the maximum rather than a row count' => '/forUpdate|lockForUpdate/',
    'stores the capture and sets cardPhotoPath' => '/cardPhotoPath/',
    "enrollment's series for everything else" => '/sequence|nextIssueNumber|series/i',
    'only gate keyed on type' => '/studentType/',
    'stepLabel derived in the payload' => '/stepLabel/',
    'studentType copied from applicantType' => '/applicantType/',
    'skipped for a continuing student' => '/continuing|studentType/',
    'completed record corrected and reopened' => '/reopen/',
    'issue + signature dates on the form' => '/formIssuedDate/',
    'the class card is the exception' => '/class-card|classCard/',
];

$targets = [];
foreach (['app', 'resources/js', 'database/seeders', 'routes'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\.(php|jsx|js)$/', $f->getFilename())) {
            $targets[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        }
    }
}
echo count($targets)." source file(s) indexed\n";

foreach ($search as $label => $pattern) {
    echo "\n### ".$label."\n";
    $hits = 0;
    foreach ($targets as $rel) {
        $lines = (array) file($root.'/'.$rel);
        foreach ($lines as $i => $l) {
            if (preg_match($pattern, $l) && preg_match('/'.$label.'/i', $l) === 0) {
                // only phrase matches, not generic identifier matches — print at most 3 per label
                if (++$hits <= 3) {
                    printf("  %-62s :%-5d %s\n", $rel, $i + 1, mb_substr(trim($l), 0, 72));
                }
            }
        }
    }
    if ($hits === 0) {
        echo "  (no phrase hit — the pattern needs a literal from the document's own sentence)\n";
    }
}
