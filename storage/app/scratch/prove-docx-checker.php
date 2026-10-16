<?php
/**
 * Prove the OOXML checker is not vacuous: mutate a copy of the document in the three ways that
 * break it for Word, and require the checker to name each one. A green check on a file that would
 * fail these mutants is a check that measures nothing.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$checker = dirname(__DIR__, 3).'/storage/app/scratch/docx-ooxml-check.php';
$tmp = dirname(__DIR__, 3).'/storage/app/scratch/mutant';

function mutate(string $src, string $out, callable $fn): void
{
    $z = new ZipArchive();
    $z->open($src);
    $xml = $z->getFromName('word/document.xml');
    $z->close();
    $xml = $fn($xml);
    copy($src, $out);
    $z = new ZipArchive();
    $z->open($out, ZipArchive::CREATE);
    $z->addFromString('word/document.xml', $xml);
    $z->close();
}

$cases = [
    'control, unmutated' => [null, 0, null],
    'an mc:Ignorable prefix loses its declaration' => [
        fn ($x) => preg_replace('/\sxmlns:w14="[^"]*"/', '', $x, 1),
        1,
        'every mc:Ignorable prefix is declared',
    ],
    'a serializer-hoisted ns9: prefix appears' => [
        fn ($x) => str_replace('<w:body>', '<w:body><ns9:thing/>', $x),
        1,
        'undeclared: ns9',
    ],
    'word/styles.xml is gone' => [
        function ($x) {
            return $x;
        },
        1,
        'part present: word/styles.xml',
    ],
];

$php = PHP_BINARY;
$failures = 0;
foreach ($cases as $label => [$fn, $expectExit, $expectText]) {
    $out = $tmp.'-'.preg_replace('/\W+/', '-', $label).'.docx';
    if ($fn === null) {
        copy($src, $out);
    } elseif (str_contains($label, 'styles')) {
        copy($src, $out);
        $z = new ZipArchive();
        $z->open($out);
        $z->deleteName('word/styles.xml');
        $z->close();
    } else {
        mutate($src, $out, $fn);
    }

    $cmd = escapeshellarg($php).' '.escapeshellarg($checker).' '.escapeshellarg($out).' 2>&1';
    exec($cmd, $lines, $exit);
    $text = implode("\n", $lines);
    $lines = [];

    $okExit = $exit === $expectExit;
    $okText = $expectText === null || str_contains($text, $expectText);
    printf("%s  %-46s exit %d (expected %d)%s\n",
        $okExit && $okText ? 'ok  ' : 'FAIL', $label, $exit, $expectExit,
        $okText ? '' : " — did not report: {$expectText}");
    if (! $okExit || ! $okText) {
        $failures++;
        echo $text."\n---\n";
    }
    @unlink($out);
}

printf("\n%s\n", $failures === 0 ? 'the checker distinguishes a good file from each of three broken ones' : "{$failures} mutant(s) not caught");
exit($failures === 0 ? 0 : 1);
