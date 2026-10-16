<?php
/**
 * Prove docx-structure-check.php can actually see a fault: take the real document, inject one
 * illegal nesting into a copy, and require the checker to stay quiet on the original and shout on
 * the mutant. A checker that has never been seen to fail is not evidence of anything.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$mutant = $root.'/storage/app/scratch/docx-mutant-nesting.docx';

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

// Put a paragraph inside a run — the shape Word refuses to open without a repair. The document's
// first <w:r> is a TOC field, so the needle has to be one that actually occurs.
$needle = '<w:r>';
$at = strpos($xml, $needle);

if ($at === false) {
    fwrite(STDERR, "cannot find a run to corrupt\n");
    exit(1);
}

$broken = substr_replace($xml, '<w:r><w:rPr><w:b/></w:rPr><w:p><w:r><w:t>injected</w:t></w:r></w:p>', $at, strlen($needle));
copy($src, $mutant);

$z2 = new ZipArchive();
$z2->open($mutant);
$z2->addFromString('word/document.xml', $broken);
$z2->close();

echo "mutant written (one <w:p> placed inside a <w:r>)\n";
echo "run the checker on both files now:\n";
echo '  php storage/app/scratch/docx-structure-check.php "Documentation/EMS Complete Documentation.docx" storage/app/scratch/docx-mutant-nesting.docx'."\n";
