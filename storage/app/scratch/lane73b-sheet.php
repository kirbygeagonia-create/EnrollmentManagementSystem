<?php

/**
 * Mark the four Registrar-window items ANSWERED on the 2026-10-23 school decision sheet, with what each answer
 * keeps. Every replacement is asserted to match exactly once; the file is written only if all of them do.
 */
$file = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem/Documentation/School-Decision-Sheet-2026-10-23.md';
$src = (string) file_get_contents($file);

$edits = [
    ['**1. Which offices must sign a clearance slip, and what does each one clear?** `[§28.5]`',
        '**1. Which offices must sign a clearance slip, and what does each one clear?** `[§28.5 — ANSWERED 2026-10-26]`'],
    ['- Answer: the nine lines are correct ☐ / amended on the attached page ☐ / **the table belongs on an internal copy, not the student\'s (name it) ☐** / other: .............
  Initial: ..........',
        '- ~~Answer: the nine lines are correct ☐ / amended on the attached page ☐ / the table belongs on an internal copy, not the student\'s ☐ / other ☐~~ — answered **2026-10-26: the nine lines are correct, in that order, and the table stays on the student\'s page**, so the office that signs each line is visible on the copy the student carries. The sentences remain a Reference Data edit on Reference Data → Clearance Requirements, which is a desk action and not an open question. Initial: ..........'],

    ['**2. What must a printed slip contain to be accepted at the Registrar\'s window?** `[§28.5, P-8, P-10, P-19]`',
        '**2. What must a printed slip contain to be accepted at the Registrar\'s window?** `[§28.5, P-8, P-10, P-19 — ANSWERED 2026-10-26]`'],
    ['- Answer: as printed ☐ / **match the student copy: take the number, the status word, the "date to be signed" and the document number off the student\'s page ☐** (the copy designation and the course code are already built, ruled 2026-10-23) / wording amended ☐ / order amended ☐ Initial: ..........',
        '- ~~Answer: as printed ☐ / match the student copy ☐ / wording amended ☐ / order amended ☐~~ — answered **2026-10-26: as printed.** The eight identity rows, the nine-line table and the signature block are what the window accepts, so the field list is settled and what is left is wording — a Reference Data edit on the requirement lines, or a template edit on the labels, one file and one commit if either is ever asked for. Initial: ..........'],

    ['**4. Is the generated PDF, or the desk screen, the paper a window accepts?** `[§28.5, P-19]`',
        '**4. Is the generated PDF, or the desk screen, the paper a window accepts?** `[§28.5, P-19 — ANSWERED 2026-10-26]`'],
    ['- Answer: desk print ☐ / generated PDF ☐ / either, PDF is the record ☐ / **a second copy exists; name it: ................................ ☐** Initial: ..........',
        '- ~~Answer: desk print ☐ / generated PDF ☐ / either ☐ / a second copy exists ☐~~ — answered **2026-10-26: the desk print is the paper.** It is the render that mints its Document No. before the sheet is drawn and writes its own `documentprintlog` row, so the paper and the log match; `ems:print-fidelity` passes no number and stays an inspection render, which is why its slip shows seven identity rows where the desk\'s shows eight. No second copy was named. Initial: ..........'],

    ['**5. Must every lost clearance slip be paid for before it is replaced?** `[§28.5, P-12]`',
        '**5. Must every lost clearance slip be paid for before it is replaced?** `[§28.5, P-12 — ANSWERED 2026-10-26]`'],
    ['- Answer: as built ☐ / free reissue allowed when: ............. Initial: ..........',
        '- ~~Answer: as built ☐ / free reissue allowed when: .............~~ — answered **2026-10-26: as built.** A lost slip is paid before it is replaced, at the amount the fee catalogue holds (₱100.00 today, a `feetypes` row), and the free copy stays what it is today — a second print while the row is still `incomplete`. No free-reissue condition was added, and changing the amount stays a Fee Types edit rather than a release. Initial: ..........'],

    ['still outstanding. **Item 16 was then answered a second time, on 2026-10-24:**',
        'still outstanding. **The last four came back on 2026-10-26** — items **1**, **2**, **4** and **5**, each ruled *as it stands*, which is why not one of the sixteen answers has ever needed a release. **Item 16 was then answered a second time, on 2026-10-24:**'],
];

$fails = 0;
foreach ($edits as [$from, $to]) {
    $n = substr_count($src, $from);
    if ($n !== 1) {
        printf("BAD  %d match(es) for: %.72s\n", $n, $from);
        $fails++;

        continue;
    }
    printf("ok   %.72s\n", $from);
}

if ($fails > 0) {
    exit("NOTHING WRITTEN.\n");
}

foreach ($edits as [$from, $to]) {
    $src = str_replace($from, $to, $src);
}

if (! str_contains($src, '**The last four came back on 2026-10-26**')) {
    exit("REFUSED: the header sentence did not land.\n");
}

file_put_contents($file, $src);
printf("wrote %d edit(s) into %s\n", count($edits), basename($file));
printf('checked boxes now: %d   unchecked: %d   ANSWERED markers: %d'."\n",
    substr_count($src, '☑'), substr_count($src, '☐'), substr_count($src, 'ANSWERED'));
