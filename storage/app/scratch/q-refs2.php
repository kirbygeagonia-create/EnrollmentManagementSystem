<?php
$z = new ZipArchive(); $z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
foreach (['Partly done 2026-10-01', 'though the row carries a NULL enrollment', '§25.10', '(§26)'] as $n) {
    printf("%-40s %d\n", $n, substr_count($x, $n));
}
$p = strpos($x, 'Partly done 2026-10-01');
$s = strrpos(substr($x, 0, $p), '<w:t'); $e = strpos($x, '</w:t>', $p);
echo "\nthe row I wrote:\n".trim(substr($x, $s, $e - $s))."\n";
