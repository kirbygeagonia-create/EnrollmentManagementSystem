<?php

/**
 * Pass 295: §28.3 G-40 says what the batch built and what it did not.
 *
 * The row must NOT be closed. One mechanism and one queue exist; 35 of the 36 pages that render the shared table
 * still offer no order, and the gap's other half — two lists with no pagination — is untouched. The verdict cell
 * therefore still leads with `Open`, so §28.7's ordering, lane 61 and the open inventory keep reporting it, while
 * the substance changes from "sorting exists nowhere" to "the mechanism exists, here is the one screen that uses it
 * and what finishing costs".
 *
 * Usage: php storage/app/scratch/refresh-docx-pass295.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

$from = 'Open 2026-10-29 (phase 5). Prompt §14 asks for density controls — sorting, filtering, search, pagination, row actions. Filtering, search and row actions are built and instrumented (lane 46 owns the filter contract); sorting exists nowhere, which is a table-wide gap rather than a per-screen defect.';
$to = 'Open 2026-10-29 (phase 5), and CONTAINED the same day rather than closed. Prompt §14 asks for density controls — sorting, filtering, search, pagination, row actions. Filtering, search and row actions are built and instrumented (lane 46 owns the filter contract). Sorting now has one mechanism: `App\Support\ListSort` takes the column from the handler’s own allow-list and never from the request string, reduces the direction to asc or desc, joins a relation column under a private alias, appends the row’s own key so equal values cannot shuffle a record onto two pages, and returns what it applied so the handler can echo it. One shared control paints it: `DataTable.jsx` renders a header button only for a column the handler both listed and reported, so a screen cannot offer an order the server would ignore. It is wired on the Registrar approval queue and on nothing else — 35 of the 36 pages that use the shared table still offer no order, and two of those carry no pagination at all, which is this gap’s other half and still untouched. What remains is mechanical now rather than a design question: a sortable set per screen, the handler’s allow-list beside it, and the case that pins each.';

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$n = substr_count($xml, $from);
printf("needle occurs %d time(s) (want 1)\n", $n);
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: nothing written.\n");

    exit(1);
}
// The verdict cell must still lead with the open word, or three registers would read this gap as answered.
if (! str_starts_with($to, 'Open ')) {
    fwrite(STDERR, "REFUSED: the replacement no longer leads with an open verb.\n");

    exit(1);
}

docxApply($docx, $root.'/storage/app/scratch/pass295-backup-'.date('Ymd-His').'.docx', [[$from, $to, null, 1]], [], $apply);
