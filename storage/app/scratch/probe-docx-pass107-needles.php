<?php

/**
 * Before pass 107 writes anything: does each target string exist as one contiguous byte slice in
 * word/document.xml, or is it split across runs?
 *
 * A citation whose digits sit in their own run cannot be replaced by a whole-string swap — the pass has
 * to edit the smaller slice instead. This census is what decides which form each edit takes, so it prints
 * the run boundaries around every hit rather than only a yes/no.
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'DeskSubNav.jsx:71',
    'MegaAppLauncher.jsx:133',
    'AuthenticatedLayout.jsx:19',
    'Auditlogs.php:16-25',
    'UserManagementController.php:380-397',
    'DevReferenceDataSeeder.php:711-722',
    'RbacSeeder.php:226',
    'to :477',
    ':477',
    'ninety-four permissions',
    'no role figure at all',
    '15 of 15 staff accounts',
    'seventeen rows sit in the live roles table',
];

foreach ($needles as $n) {
    $count = substr_count($xml, $n);
    printf("%-40s %d", $n, $count);
    if ($count === 0) {
        echo "   *** NOT CONTIGUOUS — split across runs, needs a different slice ***\n";

        continue;
    }

    $at = strpos($xml, $n);
    // Which run holds it: the nearest <w:t …> before and </w:t> after.
    $runStart = strrpos(substr($xml, 0, $at), '<w:t');
    $runEnd = strpos($xml, '</w:t>', $at);
    $runText = substr($xml, $runStart, $runEnd - $runStart);
    $open = strpos($runText, '>');
    printf("   run text: %s\n", htmlspecialchars(mb_substr($runText, $open + 1, 160)));
}
