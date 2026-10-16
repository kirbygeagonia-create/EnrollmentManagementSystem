<?php

/**
 * The three citations this batch may have moved: what the document claims, and what the file says now.
 *
 * For each, print the sentence the cite sits in (so the claim is visible, not just the number), then the
 * cited lines with their enclosing method — resolved by walking the file's braces, not by a backwards
 * regex that grabs whichever `function` keyword appeared last.
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$targets = [
    ['Auditlogs.php', 16, 25, $root.'/app/Models/Auditlogs.php'],
    ['UserManagementController.php', 380, 397, $root.'/app/Http/Controllers/Admin/UserManagementController.php'],
    ['MegaAppLauncher.jsx', 133, 133, $root.'/resources/js/Components/navigation/MegaAppLauncher.jsx'],
];

/** The method a line sits inside, by brace depth over the file's function declarations. */
$enclosing = function (array $lines, int $at) use (&$enclosing): string {
    $best = '(file scope)';
    foreach ($lines as $i => $line) {
        if ($i + 1 >= $at) {
            break;
        }
        if (preg_match('/(public |protected |private |static )*function (\w+)\s*\(/', $line, $m) === 1) {
            $best = $m[2].' (declared line '.($i + 1).')';
        }
        if (preg_match('/^\s*(export default function|function) (\w+)/', $line, $m) === 1) {
            $best = $m[2].' (declared line '.($i + 1).')';
        }
        if (preg_match("/^\s*(\w+):\s*(async )?\(/", $line, $m) === 1) {
            $best = 'object method '.$m[1].' (line '.($i + 1).')';
        }
    }

    return $best;
};

foreach ($targets as [$file, $from, $to, $path]) {
    $cite = $file.':'.$from.($to > $from ? '-'.$to : '');
    $at = strpos($xml, $cite);
    echo "\n══════════════════════════════════════════════════════════════════════\n";
    echo "{$cite}   (occurrences: ".substr_count($xml, $cite).")\n";
    if ($at !== false) {
        $sentence = preg_replace('/\s+/', ' ', strip_tags(substr($xml, max(0, $at - 700), 1000)));
        echo "  DOCUMENT SAYS: …".mb_substr($sentence, max(0, mb_strlen($sentence) - 330))."…\n";
    }

    $lines = file($path);
    printf("  FILE NOW: %d lines\n", count($lines));
    printf("  enclosing method of line %d: %s\n", $from, $enclosing($lines, $from));
    foreach (range($from, min($to, count($lines))) as $ln) {
        printf("    %4d  %s\n", $ln, rtrim($lines[$ln - 1]));
    }
}
