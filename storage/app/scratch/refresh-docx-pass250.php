<?php

/**
 * Docx pass 250 — G-2: resumable Draft intake. THIS SCRIPT'S BODY IS GONE; the run it records happened.
 *
 * What pass 250 did, in the order it did it:
 *   stage 1  asserted 9 live line facts in AdmissionController.php / Admission/Show.jsx and compared every
 *            cited routes/web.php line byte-for-byte against the HEAD export (`+3` uniform, verified);
 *   stage 2  re-pointed 14 distinct AdmissionController.php tokens (+2, +13, +48, +143 — never arithmetic) and
 *            18 routes/web.php pointers, plus §8.3's two bare continuations, re-taken by reading;
 *   stage 3  rewrote five paragraphs by their flattened text (§8.3, §8.4, §8.5, §8.6 and §24's `admissions —
 *            N columns` heading) and two run-boundary cells (§23.8's `451`, §27's enum list);
 *   stage 4  cloned two table rows: §24's `draftedDate | date | yes` after the admissionStatus row, and a §1.1
 *            revision row; stage 7 then read the part back and counted 28 needles.
 *
 * Its post-write census reported 27 of 28 expectations satisfied and refused on the 28th — §24's heading came out
 * `admissions  —2 columns`, because collapsing three runs into one kept the FIRST run's spacing. That refusal is
 * why the defect is not in the document: pass 250b (refresh-docx-pass250b.php) measured the chapter's two real
 * spacing families, found exactly one heading in neither, and repaired it with its own verify-after-read.
 *
 * The body itself was overwritten later in the same session by a mis-pathed Write. Rewriting it from memory
 * would produce a script that never ran while looking like the record of one that did, so it is not here.
 * The evidence of the run is: the written document (pass 250b's backup, and pass250-backup-*.docx beside it),
 * the sweep suite re-run green over it (citation sweep 740 / 161 files / 0 line problems, lane 37 913
 * references / 0 problems, lane 37b 523 / 0, repo-file-line-citations 123 / 0, lane 60 54 match / 0 stale
 * with all 63 §24 headings agreeing with the server, lane 64, lane 72 12 / 0 stale, lane 61 silent, lane 65
 * 2 honest blind spots, lane 59 9 judged / 9 match), the OOXML contract CLEAN, verify-docx CONSISTENT, and
 * Word opening a copy with alerts on: OPENED CLEAN, 228 pages, 185 tables.
 *
 * Lesson recorded in AGENTS.md: a one-shot pass script is an artefact of its own run. This file must not be
 * deleted — it is the marker of the gap — and a future pass must not be numbered 250 again.
 */

fwrite(STDERR, "This file records pass 250; its executable body was lost to a mis-pathed write in the same\n"
    ."session that ran it. See the header for what it did and where its verification lives.\n"
    ."The corrective pass that finished the job is refresh-docx-pass250b.php.\n");
exit(1);
