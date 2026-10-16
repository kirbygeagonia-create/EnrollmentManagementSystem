<?php

/**
 * Pass 136b — §29.4's family count, and the sentence that claimed the count could not drift.
 *
 * Pass 136 adds G-10 to §28.3, so the family row has to move. Reading it first found a drift that predates this
 * batch: the cell says 25, and the table it counts held 26 data rows (header excluded), measured by walking the
 * enclosing `<w:tbl>` in the XML and counting its `</w:tr>`. The sentence in the paragraph above it promises the
 * opposite — that the counts "are produced from the same source data the tables are rendered from, so the
 * arithmetic cannot fall out of step with the content". Nothing renders this document any more; every pass since
 * 1 has been a hand edit at an absolute offset. So the promise was false when read, and the honest correction is
 * to say what was measured rather than to leave a sentence that will be believed again.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['The counts below are produced from the same source data the tables are rendered from, so the arithmetic '
        .'cannot fall out of step with the content — if a row is added, its family count moves here without '
        .'anyone editing a number.',
        'The counts below were re-read from the rendered tables on 2026-10-16 rather than remembered, because the '
        .'claim this sentence used to make — that the arithmetic cannot fall out of step with the content, since '
        .'a row added to a table moves its family count here by itself — was false when it was last checked: the '
        .'§28.3 family row read 25 while that table held 26 data rows. Nothing renders this document any more; '
        .'every revision since the first has been a hand edit at an absolute offset, so the arithmetic is someone’s '
        .'responsibility and the re-reading is the check. After this batch’s G-10, §28.3 holds 27 data rows.'],
];

// The count cell, addressed through the row it lives in — "25" alone is not unique in a 199-page document.
$cellEdits = [
    ['Enforcement, reference-data, screen, authorization and notification gaps', '25', '27'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass136b.docx',
    $textEdits, $cellEdits, in_array('--apply', $argv, true));
