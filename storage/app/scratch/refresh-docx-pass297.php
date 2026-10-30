<?php

/**
 * Pass 297: §28.3 G-40's pagination half says what is measured and what is not.
 *
 * The row has carried "two carry no pagination at all" since the sweep wrote it, and this batch repeated it into
 * §1.1 and the matrix. Measuring it today produced two refusals before any number: a probe comparing backslash page
 * paths to forward-slash render names matched 0 of 36 pages, and its corrected second run claimed 24 unpaginated —
 * both wrong, because a whole-file `paginate(` match cannot see WHICH action hands over WHICH list. Checked per
 * action, the ID hub and the student directory both paginate, and so does the audit log the second run had called
 * unpaginated.
 *
 * So the register now states the census the join produces and names the instrument that produced it: thirty-six desk
 * tables resolve into twenty-nine that receive a paginator from their own action, three that receive a plain
 * collection, and four whose rows the browser maps itself. None of the three is a work queue, which is what the row's
 * "two queues" had been reaching for without ever measuring it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass297.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

$from = 'and two of those carry no pagination at all, which is this gap’s other half and still untouched';
$to = 'and the gap’s other half — pagination — has now been weighed rather than recalled. That earlier reading, '
    .'that two queues carry none, was never measured, and the probe written to measure it failed twice before it '
    .'said anything true: it compared backslash page paths to forward-slash render names and resolved nothing, then '
    .'reported twenty-four unpaginated by asking only whether the controller FILE held a paginator, which cannot see '
    .'which action hands over which list and condemned the ID hub, the student directory and the audit log while all '
    .'three paginate. Read at the join where the claim actually lives — the page’s own rows expression, the render '
    .'that serves that page, and the assignment inside that one action, with every reshape and comment-aware scan '
    .'resolved rather than skipped — the thirty-six tables using the shared component resolve into twenty-nine that '
    .'receive a paginator from their own index action, three that receive a plain collection (Accounting’s daily '
    .'report, one curriculum’s subject list, and the settings table) and four whose rows the browser maps over the '
    .'prop it was given; none of the three is a work queue, since a day’s receipts, the subjects of one curriculum '
    .'and the school’s own settings rows are bounded sets by design. `lane90-probe-pagination.php` is the instrument, '
    .'it prints every row it judges and refuses a census when an unread third of them cannot be resolved. What is '
    .'open in this gap is therefore the sorting half alone: one of those twenty-nine queues sorts on the server today';

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

docxApply($docx, $root.'/storage/app/scratch/pass297-backup-'.date('Ymd-His').'.docx', [[$from, $to, null, 1]], [], $apply);
