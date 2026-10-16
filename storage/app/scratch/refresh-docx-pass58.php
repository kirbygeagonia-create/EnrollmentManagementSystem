<?php
/**
 * Pass 58 — the front-end citations, read against the components, and one paragraph of mine that
 * asserted a copy fix before the copy was fixed.
 *
 * Nine .jsx cites point at lines holding unrelated code (a badge column citing a useMemo, a
 * clearance note citing an <svg>, the seal citing a closing anchor tag). Each was resolved to the
 * line that actually renders the thing the sentence claims. Two rows also carried stale facts:
 * §28.3 U-4 was closed on 2026-10-01 but §5 still described the Registrar summary as mislabelled,
 * and pass 48 claimed the Evaluation screen no longer says the clearance gate lives at Phase 5 —
 * it did say it, at Evaluation/Show.jsx:340, contradicting the desk's own Clearance Pass Slip
 * panel 340 lines below. That sentence was corrected in the component the same morning.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['Evaluation/Index.jsx:34-36', 'Evaluation/Index.jsx:85-88'],
    ['Evaluation/Index.jsx:23', 'Evaluation/Index.jsx:74', null, 2],
    ['Registrar/Show.jsx:318', 'Registrar/Show.jsx:243-254 , :450'],
    ['Clearance/Index.jsx:499', 'Clearance/Index.jsx:528'],
    ['Admission/Show.jsx:427', 'Admission/Show.jsx:275'],
    ['Evaluation/Show.jsx:252', 'Evaluation/Show.jsx:340'],
    ['Registrar/Index.jsx:106', 'Registrar/Index.jsx:133-134'],
    ['PrintLayout.jsx:20', 'PrintLayout.jsx:28'],
    ['WorkflowStepper.jsx:30', 'WorkflowStepper.jsx:12'],
];

$cellEdits = [
    // §15 — the Evaluation screen's own note, which contradicted the panel beneath it.
    ['used to say exactly that', 'The Evaluation screen used to say exactly that',
     'The Evaluation screen’s flow note said “the clearance gate is enforced at Phase 5, not here” ( Evaluation/Show.jsx:340 ) while the same page, further down, asks the desk to confirm the slip — two sentences on one screen contradicting each other until 2026-10-07, when the note was corrected to point the officer at that panel. What the desk actually does is real: approvedPassSlip() looks for an approved, received slip in the window now accepting ( EvaluationController.php:921-943 ), and confirmClearance() records the officer’s confirmation as the verdict’s last clause ( :945-993 , permission evaluation.clearance.confirm ). A returning student with an unfinished clearance is stopped at Evaluation and again at the Registrar, not after the fees have been assessed and paid.', 1],
];

$paraEdits = [
    // §5 — U-4 closed; the surviving “Phase 6” belongs to the next desk, not this one.
    ['summary control is labelled',
     'The Registrar desk’s own labels agree: its summary control reads “Workflow Phase — Phase 5” and its page badge reads “Phase 5 · Registrar Central Desk” ( Registrar/Index.jsx:133-134 , :92 ). That is §28.3 U-4 closed on 2026-10-01 — the screen no longer contradicts itself about which phase the Registrar sits in. The “Phase 6” that does survive in the chrome belongs to Blocking, the desk after this one ( DeskSubNav.jsx:71 , MegaAppLauncher.jsx:133 , AuthenticatedLayout.jsx:19 ), and a reader who meets it in a navigation label is looking at the right number for the wrong desk only if they expected the Registrar to be sixth.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass58.docx', $textEdits, $cellEdits, $apply, $paraEdits);
