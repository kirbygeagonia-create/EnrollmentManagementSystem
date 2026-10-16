<?php
$z = new ZipArchive(); $z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
$tail = ' it sequences per (enrollmentId, documentType) under lockForUpdate() and the unique index uq_documentprintlog_issue rejects a collision, so the number means';
$tailEnd = 'and cannot mean two things at once.';
printf("tail head count: %d\ntail end count: %d\n(§26) count: %d\n", substr_count($x, $tail), substr_count($x, $tailEnd), substr_count($x, '(§26)'));
