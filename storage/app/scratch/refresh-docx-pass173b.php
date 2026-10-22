<?php

/**
 * Pass 173b — the §1.1 row called 129 "unique-to-nothing indexes", and 129 is every index in the schema.
 *
 * lane60-probe-ratios.php answers indexes = 129 (all of them) and the unique tally is nine domain keys over seven
 * tables, which §23.6.1 states and lane44-unique-keys.php proves. Writing the total with the adjective belonging to
 * the unique count is the same defect this lane exists to catch: a figure that does not name what it counted.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$R = json_decode((string) file_get_contents($root.'/storage/app/scratch/lane60-readings.json'), true);
if (($R['database'] ?? '') !== 'ems' || empty($R['indexes']) || ! empty($R['failedReadings'])) {
    exit("REFUSED: the readings are not a clean live-ems run.\n");
}
$idx = (int) $R['indexes'];

docxApply($src, $root.'/storage/app/scratch/pass173b-backup.docx', [
    ['129 unique-to-nothing indexes', $idx.' indexes'],
], [], $apply);
