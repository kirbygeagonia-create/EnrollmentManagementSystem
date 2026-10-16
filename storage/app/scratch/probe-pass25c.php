<?php
$xml = file_get_contents(dirname(__DIR__, 3).'/storage/app/scratch/document-pass25.xml');
foreach (['2026-10-04 (this issue)', 'clinic.recordAssessment', 'AuthServiceProvider.php:163-168', 'AuthServiceProvider.php:173-177'] as $n) {
    printf("%3d  %s\n", substr_count($xml, $n), $n);
}
