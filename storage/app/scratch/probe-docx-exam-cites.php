<?php
/**
 * Every ExamController.php / ExamPolicy.php / AdmissionPolicy.php line citation the document
 * still carries, with the line it points at read from the file.
 *
 * Two defects today were citation-shaped: a range that moved when the method grew, and a range
 * that named a method nobody calls. Both are cheap to catch mechanically and expensive to leave
 * in the authority document, so this reads each cite and prints the real code beside it.
 *
 * Nothing is written.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
$z->open($docx);
$xml = $z->getFromName('word/document.xml');
$z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));

$files = [
    'ExamController.php' => 'app/Http/Controllers/Exam/ExamController.php',
    'ExamPolicy.php' => 'app/Policies/ExamPolicy.php',
    'AdmissionPolicy.php' => 'app/Policies/AdmissionPolicy.php',
];

// The method that owns a line: the last "function name(" at or above it.
$owner = function (string $path, int $line): string {
    $src = file($path);
    $name = '(none)';
    for ($i = 0; $i < min($line, count($src)); $i++) {
        if (preg_match('/function\s+(\w+)/', $src[$i], $m)) {
            $name = $m[1];
        }
    }

    return $name.'('.implode(' | ', array_map('trim', array_slice(
        array_map(fn ($l) => trim($l), array_slice($src, $line - 1, 2)), 0, 2))));
};

$seen = [];
foreach ($files as $needle => $path) {
    preg_match_all('/'.preg_quote($needle, '/').':(\d+)(?:-(\d+))?\s*\)?/', $plain, $mm, PREG_SET_ORDER);
    foreach ($mm as $m) {
        $key = $needle.':'.$m[1].($m[2] ?? '');
        if (isset($seen[$key])) {
            $seen[$key]['n']++;

            continue;
        }
        $line = (int) $m[1];
        $seen[$key] = ['n' => 1, 'path' => $path, 'line' => $line,
            'end' => isset($m[2]) ? (int) $m[2] : $line];
    }
}

$total = ['ExamController.php' => count(file($files['ExamController.php'])),
    'ExamPolicy.php' => count(file($files['ExamPolicy.php'])),
    'AdmissionPolicy.php' => count(file($files['AdmissionPolicy.php']))];

foreach ($seen as $key => $s) {
    $short = basename($s['path']);
    $beyond = $s['end'] > $total[$short] ? '  ⚑ PAST END OF FILE' : '';
    printf("%-40s x%d  owner: %-28s%s\n", $key, $s['n'], mb_substr($owner($s['path'], $s['line']), 0, 90), $beyond);
}
