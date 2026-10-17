<?php

/**
 * Pass 141b — the citations this batch moved, the §18.3 sentence the tabs made true, and §1.1's register.
 *
 * IDController.php grew 55 lines inside index() (the two tab bases, the tile helper), so every method below it
 * moved. Each new number was read off the construct the prose names, not computed by adding 55:
 *
 *   create()        :104-130 → :159-185   opens at 159, its `}` at 185, attachPhoto()'s docblock at 187.
 *   attachPhoto()   :135-165 → :190-220
 *   validate()      :188-207 → :243-262
 *   recordRemark()  :219-230 → :274-285   286 is the class brace, so the range stops at 285.
 *   bloodType       :112, :124 → :167, :179  the `required|in:A+,…` rule and the write that copies it.
 *
 * The :104-130 citation is in TWO places — §18.3's step table and the 2026-10-10 revision row — and both name
 * create(), so both move. The bare `:135-165` and `:188-207` carry no file name and so answer to no checker;
 * they were found by reading the table, and are worth prefixing next time the section is rewritten.
 *
 * The two new figures in §18.3's sentence were measured on live ems before writing: the Issued tab shows seven
 * enrolled records against the two the queue holds, and enrollment 53 (DEMO-2026-001) is one of the seven with
 * zero idrequests rows — the signed box with nothing behind it that the ID Status column now prints as None.
 * The row's own closure sentence is re-measured too: six of the eight request rows are validated, all six carry
 * a photo, and none sits in the queue.
 *
 * §1.1's revision row still reports G-10 as open and does not mention the two checkers added after it was
 * written; both are corrected here. The §18.3 sentence about the two tabs could not ride on a text needle: that
 * paragraph ends `validatedDate` in a Consolas run and its full stop in a plain one, so no sentence-spanning
 * string exists to replace — it goes in as an inserted paragraph after it, and the container guard sees the
 * single `<w:p>` pair it contributes. The suite total is deliberately NOT touched by this pass: it moves when
 * the whole run finishes, and a count has to be read, not predicted.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass141b.docx',
    [
        ['IDController.php:104-130', 'IDController.php:159-185', null, 2],
        [':135-165', ':190-220'],
        [':188-207', ':243-262'],
        ['IDController.php:219-230', 'IDController.php:274-285'],
        ['IDController.php:112, :124', 'IDController.php:167, :179'],

        ['Measured first: six of the eight request rows were validated and all six sat outside the queue, so the '
            .'desk could reopen one only by typing /id/N.',
            'Measured again on 2026-10-17, the day of the ruling: six of the eight request rows are validated, all '
            .'six carry a face photo, and none of them sits in the two-row queue — so each was reachable only by '
            .'typing /id/N, and a seventh signed box belongs to a student with no request row at all.'],

        ['Two things came back from that work still open and both are the owner’s: G-10, the ID hub cannot show a '
            .'request it has already validated, and a host clock the school will feel on demo day —',
            'G-10, the finding that the ID hub could not show a request it had already validated, was closed on '
            .'2026-10-17 by the owner’s ruling (design A): the desk’s screen gained an Issued tab beside the queue '
            .'and each status tile now counts the set its own tab shows, because the old Validated tile asked one '
            .'enrollment to hold a signed ID box and a pending one at once and so read zero on a database holding '
            .'six. One thing came back still open, and it is the owner’s: a host clock the school will feel on demo '
            .'day —'],

        ['CI gained a step that runs the schema-independent half of the claim checkers',
            'Two more checkers came from the schema rather than from the guides. '
            .'storage/app/scratch/lane43-delete-guards.php asks the database what each delete will refuse and the '
            .'source what each handler names: across 19 delete sites it found the shift docket missing from a '
            .'course delete and from an officer’s historical-activity check, and both are guarded now and pinned by '
            .'a test that asserts the refusal’s own wording rather than a generic flash. '
            .'lane44-unique-keys.php compares §23.6.1’s list of enforced uniqueness with information_schema, which '
            .'found the print-log issuance key was never told — the section counted eight keys where the server '
            .'holds nine. CI gained a step that runs the schema-independent half of the claim checkers'],
    ],
    [],
    $apply,
    [],
    [
        [
            'anchor' => 'not as a per-student permanent record',
            'pos' => 'after',
            'xml' => '<w:p><w:pPr><w:spacing w:after="110"/></w:pPr><w:r><w:t xml:space="preserve">'
                .'The desk’s own screen has carried two tabs since 2026-10-17, when G-10 closed: Awaiting '
                .'validation lists the enrollments whose earliest pending box is this office’s, and Issued lists '
                .'every enrolled record whose ID box is signed — seven on this box against the two the queue holds. '
                .'A signed box with no request row beside it stays on that second tab with ID Status reading None, '
                .'which is what enrollment 53 (DEMO-2026-001) shows.</w:t></w:r></w:p>',
        ],
    ]
);
