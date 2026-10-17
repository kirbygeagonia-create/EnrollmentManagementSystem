<?php

/**
 * Pass 141 — G-10 closes: the ID desk keeps its finished work on its own screen.
 *
 * The row was written as an open finding with a measurement inside it, and that measurement still reads true
 * (validating signs the box, and the signed box leaves the queue). What changed is the last sentence of the
 * body and the row's owner column, so those two are what this pass edits — the mechanism description stays,
 * because a closed finding should still say what the defect was.
 *
 * Measured before writing: seven enrollments carry a signed ID box against the two still queued, and six of
 * the eight request rows are validated — all six outside the queue, so the only way back to one was typing
 * /id/N. The Validated tile could not have read otherwise than zero on that data, because it counted validated
 * rows inside a set defined by a PENDING box; it now counts the set its own tab shows.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass141.docx',
    [
        ['Open: no fix has been ruled.',
            'Closed 2026-10-17 by ruling, design A: the hub carries an Issued tab beside the queue, listing every '
            .'enrolled record whose ID box is signed — seven on this box against the two still queued — each row '
            .'linking to the same desk screen the queue does. Measured first: six of the eight request rows were '
            .'validated and all six sat outside the queue, so the desk could reopen one only by typing /id/N. The '
            .'Validated tile was part of the same blind spot: it counted validated rows inside a set defined by a '
            .'pending box, which is why it read zero on a database holding six; each tile now counts the set its own '
            .'tab shows, and a signed box with no request behind it is listed with ID Status reading None rather than '
            .'disappearing from the history.'],
        ['the hub keeps no history of finished ones',
            'the hub keeps its history on the second tab'],
        ['Developer, if the desk is to keep its own finished work on screen',
            'Closed by the owner’s ruling 2026-10-17 — the desk keeps its finished work on its own screen'],
    ],
    [],
    $apply
);
