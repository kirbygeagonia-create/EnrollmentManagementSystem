<?php
// The body was refreshed on 2026-10-01; the package's own modified stamp must agree.
$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
$zip->open($src);
$core = $zip->getFromName('docProps/core.xml');
$zip->close();
$old = '<dcterms:modified xsi:type="dcterms:W3CDTF">2026-09-28T00:00:00Z</dcterms:modified>';
$new = '<dcterms:modified xsi:type="dcterms:W3CDTF">2026-10-01T00:00:00Z</dcterms:modified>';
if (substr_count($core, $old) !== 1) {
    fwrite(STDERR, "modified stamp not found exactly once — nothing written\n");
    exit(1);
}
$zip2 = new ZipArchive();
$zip2->open($src);
$zip2->addFromString('docProps/core.xml', str_replace($old, $new, $core));
$zip2->close();
echo "core.xml modified stamp moved to 2026-10-01\n";
