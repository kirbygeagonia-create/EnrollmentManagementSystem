<?php
$xml = file_get_contents(dirname(__DIR__, 3).'/storage/app/scratch/document-pass25.xml');
$needles = [
    'the desk confirms identity data and the photo.',
    'A completed record can be corrected and reopened',
    'and the day it was stamped',
    'the previous box must be completed',
    'no desk role holds clearance.view.',
    'face-photo validation, and card release',
    'and §24 states which column listings are not yet re-generated.',
    'because the seeder pairs an office head with their desk role.',
    '§22.9',
    'clinic.record, clinic.reopen, clinic.update',
];
foreach ($needles as $n) {
    printf("%3d  %s\n", substr_count($xml, $n), $n);
}
