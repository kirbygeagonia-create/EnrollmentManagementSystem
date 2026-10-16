<?php
/**
 * Structural validator for word/document.xml — the check that "it parses" cannot make.
 *
 * simplexml will happily parse <w:p><w:tbl/></w:p>, and Word will call that corrupt and offer to
 * repair the file. This walks the part with XMLReader and asserts the block-level nesting the
 * OOXML content model requires. Run it on a file and on its backups to find which pass broke it.
 */
$targets = array_slice($argv, 1);

if ($targets === []) {
    echo "usage: php docx-structure-check.php <file.docx> [...]\n";
    exit(2);
}

// child => parents that would be illegal for it. Note what is LEGAL: a table directly in the body
// is normal (it is a block-level child), and so is a paragraph in the body. Getting this wrong is
// how an earlier draft of this file reported every version of the document as broken.
$illegal = [
    'w:tbl' => ['w:p', 'w:tcPr', 'w:r', 'w:trPr', 'w:tblPr', 'w:tblGrid'],
    'w:tr'  => ['w:p', 'w:tblPr', 'w:tblGrid', 'w:tc', 'w:r', 'w:body'],
    'w:tc'  => ['w:p', 'w:trPr', 'w:tc', 'w:r', 'w:tblPr', 'w:tblGrid', 'w:body'],
    'w:p'   => ['w:p', 'w:r', 'w:tcPr', 'w:trPr', 'w:tblPr', 'w:tblGrid', 'w:tbl'],
    'w:t'   => ['w:t'],
];

foreach ($targets as $path) {
    if (! is_readable($path)) {
        echo "MISSING  {$path}\n";
        continue;
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        echo "NOT A ZIP  {$path}\n";
        continue;
    }
    $xml = $zip->getFromName('word/document.xml');
    $entries = $zip->numFiles;
    $zip->close();

    $reader = new XMLReader();
    $reader->XML($xml);

    $stack = [];
    $problems = [];
    $counts = [];

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT) {
            if ($reader->nodeType === XMLReader::END_ELEMENT) {
                array_pop($stack);
            }

            continue;
        }

        $name = $reader->name;
        $parent = end($stack);
        $stack[] = $name;
        $counts[$name] = ($counts[$name] ?? 0) + 1;

        if ($parent !== false && isset($illegal[$name]) && in_array($parent, $illegal[$name], true)) {
            if (count($problems) < 8) {
                $problems[] = "<{$name}> directly inside <{$parent}> at depth ".$reader->depth;
            }
        }

        // <w:pPr/> and friends emit no END_ELEMENT, so a stack that forgets them drifts by one
        // every time one appears — which is exactly how this check first reported a nesting
        // fault in every version of the file, including ones Word opens happily.
        if ($reader->isEmptyElement) {
            array_pop($stack);
        }
    }
    $reader->close();

    // Stray text under a block element is rarer and this file is 5.7M characters, so it is checked
    // only when asked for rather than on every version in a bisect.
    $stray = 0;
    if (in_array('--stray', $argv, true) && preg_match_all('#<w:p(?: [^>]*)>(.*?)</w:p>#s', $xml, $mm)) {
        foreach ($mm[1] as $chunk) {
            if (preg_match('/>[^<>]+<\/w:p>$|<w:p(?: [^>]*)>[^<]+/', $chunk)) {
                $stray++;
            }
        }
    }

    printf(
        "%-58s entries=%-3d bytes=%-8d paragraphs=%-5d tables=%-4d  %s\n",
        basename($path),
        $entries,
        strlen($xml),
        $counts['w:p'] ?? 0,
        $counts['w:tbl'] ?? 0,
        $problems === [] ? 'NESTING OK' : 'NESTING BROKEN: '.implode('; ', $problems)
    );

    if ($stray > 0) {
        echo "   note: {$stray} block element(s) carry bare text outside a run\n";
    }
}
