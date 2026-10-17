<?php

/**
 * Pass 141e — the owner's second ruling on G-10: a record opened from the history must return to it.
 *
 * The desk screen's back link went to /id, so an officer who opened a validated request from the Issued tab and
 * recorded a mismatch was dropped onto the pending list. show() now answers for the tab its own row link passed
 * (`?tab=issued`), and the link and its label follow it. An officer who opens /id/92 directly is answering for
 * the queue, which is what the screen said before the second tab existed.
 *
 * show() gained four lines, so every method below it moved — re-measured by construct AFTER Pint, which this
 * time wanted nothing (Pint PASS, no change), and read off the file rather than computed:
 *   create()        :160-186 → :164-190   opens at 164, its `}` at 190
 *   attachPhoto()   :191-221 → :195-225
 *   validate()      :244-263 → :248-267
 *   recordRemark()  :275-286 → :279-290   291 is the class brace
 *   bloodType       :168, :180 → :172, :184  the `required|in:A+,…` rule and the write that copies it
 *
 * The suite total is left for its own pass: it has to be read from the whole run that is going on now, not
 * predicted from the one test this batch adds.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass141e.docx',
    [
        ['IDController.php:160-186', 'IDController.php:164-190', null, 2],
        [':191-221', ':195-225'],
        [':244-263', ':248-267'],
        [':275-286', ':279-290'],
        ['IDController.php:168, :180', 'IDController.php:172, :184'],

        ['each tile now counts the set its own tab shows, and a signed box with no request behind it is listed '
            .'with ID Status reading None rather than disappearing from the history.',
            'each tile now counts the set its own tab shows, and a signed box with no request behind it is listed '
            .'with ID Status reading None rather than disappearing from the history. The desk screen a row opens '
            .'carries the tab that opened it, so an officer who records a mismatch against a finished request is '
            .'sent back to the list they were working — a history that dumps the reader onto the pending queue '
            .'after one visit is only half a path.'],

        ['A signed box with no request row beside it stays on that second tab with ID Status reading None, which '
            .'is what enrollment 53 (DEMO-2026-001) shows.',
            'A signed box with no request row beside it stays on that second tab with ID Status reading None, which '
            .'is what enrollment 53 (DEMO-2026-001) shows. Each row’s own screen returns to whichever tab opened '
            .'it.'],
    ],
    [],
    $apply
);
