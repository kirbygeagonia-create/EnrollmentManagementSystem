<?php

/**
 * Pass 177 — lane 57's cap is now proved on the running desk, and the row says how.
 *
 * G-26 asserted "the guard names the figure it refuses above, and the notice now caps at the same number". Until today
 * that had been proved by a feature test and by a POST through the HTTP kernel, never by opening the Accounting screen
 * — which is the shape lane 62 refuses to leave: a claim about an artefact that no one read on the artefact.
 *
 * Measured 2026-10-22 in the browser as office2_head on http://localhost:8080/accounting/82 (Laragon Apache answered;
 * `curl -I` shows Server: Apache/2.4.66), against a live `ems` account holding ₱17,500.00:
 *   - the amount input carries min="0.01" max="17500" step="0.01", and its value is prefilled to the full balance;
 *   - setting it to 19000 fires input.validity.rangeOverflow and makes the form invalid, and the amber line renders
 *     "⚠ This OR may record at most ₱17,500.00 — hand the extra ₱1,500.00 back at the counter before filing.";
 *   - with that max removed in the DOM and the same figure submitted, the server's sentence appears inline:
 *     "A receipt may not exceed what this account owes — ₱17,500.00 outstanding. Record that figure and hand the rest
 *     back at the counter." — twice, once as the toast and once under the field (lane 28's two surfaces);
 *   - and the refusal wrote nothing: payments stayed at 15 rows, zero rows match the test OR number, zero audit rows
 *     mention it, and assessment 82 still reads ₱17,500.00.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$proof = 'Seen on the running desk on 2026-10-22 as `office2_head` at /accounting/82, where the sheet holds '
    .'₱17,500.00: the amount input arrives min 0.01 and max 17500, so typing 19000 fails the browser’s own range '
    .'check and raises the line that this OR may record at most ₱17,500.00 and ₱1,500.00 must go back; with that '
    .'max stripped in the DOM and the same figure submitted, the server answers inline — “A receipt may not exceed '
    .'what this account owes — ₱17,500.00 outstanding.” — once as the toast and once beneath the field. The refusal '
    .'wrote nothing: `payments` gained no row, no row carries the number the attempt used, no audit row mentions it, '
    .'and the balance still reads ₱17,500.00. A ceiling the screen states and the server enforces with the same '
    .'figure is what makes the counter’s change a non-event.';

$textEdits = [
    [
        'The guard names the figure it refuses above, and the notice now caps at the same number instead of promising '
            .'change.',
        'The guard names the figure it refuses above, and the notice now caps at the same number instead of promising '
            .'change. '.$proof,
    ],
];

docxApply($src, $root.'/storage/app/scratch/pass177-backup.docx', $textEdits, [], $apply);
