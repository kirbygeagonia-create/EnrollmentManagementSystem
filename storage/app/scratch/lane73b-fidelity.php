<?php

/**
 * Record the two rulings that answer the fidelity sheet's remaining office rows, and the packet's §C state.
 *
 * Item 1's ruling (the nine lines stay on the student's page) answers the 2026-10-10 sheet's row 1.6, and item 2's
 * (the slip is accepted as printed) answers 1.1–1.5 — one answer, both records, which is the convention the packet
 * already states. The sheet's own dated readings are not re-aged: the rulings are added, the columns are left alone.
 * Every replacement is asserted to match exactly once, and the box counts are printed from the result rather than
 * from arithmetic, because the packet quotes them.
 */
$root = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem';
$targets = [
    'fidelity' => $root.'/Documentation/Print-Fidelity-Comparison-2026-10-10.md',
    'packet' => $root.'/Documentation/Registrar-Window-Packet-2026-10-26.md',
];

$edits = [
    'fidelity' => [
        [
            '| 1.6 | Is the per-office requirement checklist on the same sheet as the student\'s copy, or a separate document? | | ☐ |',
            '| 1.6 | Is the per-office requirement checklist on the same sheet as the student\'s copy, or a separate document? | **Answered 2026-10-26 by the owner: the same sheet.** The nine lines stay on the student\'s page, so the office that signs each one is visible on the copy the student carries — decision-sheet item 1 | ☑ |',
        ],
        [
            '| 1.1–1.5 | Do the slip\'s labels follow the paper (`Full Name`, `Date`, `Registrar In-charge (Signature over Printed Name) & Date`, semester-in-title, `2nd Year`) or does the paper follow the system? | | ☐ |',
            '| 1.1–1.5 | Do the slip\'s labels follow the paper (`Full Name`, `Date`, `Registrar In-charge (Signature over Printed Name) & Date`, semester-in-title, `2nd Year`) or does the paper follow the system? | **Answered 2026-10-26 by the owner: the system\'s, as printed** — the eight identity rows, the nine-line table and the signature block are what the window accepts; the copy designation and the course-code wording were already built (P-19, 2026-10-23) and the level spelling was ruled 2026-10-24 | ☑ |',
        ],
        [
            "Two rows of this file have been acted on. **2.6**, the doubled weekday, which the owner ruled on\n2026-10-11 and the card template now prints once — it was the only row that was a defect rather\nthan a preference, so it did not need the office's hand. And **2.1–2.9**, the whole class-card shape\nquestion, which the owner ruled on 2026-10-26 *as the system prints* (§2 above records what that keeps\nand what it therefore does not adopt from the paper). Every remaining row above is still a paper\ndecision first and a code change second, and the code follows the ruling — not the other way\nround.",
            "Four rows of this file have been acted on. **2.6**, the doubled weekday, which the owner ruled on\n2026-10-11 and the card template now prints once — it was the only row that was a defect rather\nthan a preference, so it did not need the office's hand. And three ruled on 2026-10-26: **2.1–2.9**, the\nwhole class-card shape question, *as the system prints* (§2 above records what that keeps and what it\ntherefore does not adopt from the paper); **1.6**, the per-office table, which stays on the student's page;\nand **1.1–1.5**, the slip's labels, which the window accepts as printed. Every remaining row above is still\na paper decision first and a code change second, and the code follows the ruling — not the other way\nround.",
        ],
    ],
    'packet' => [
        [
            '| 1.6 | Is the per-office checklist on the same sheet as the student\'s copy, or separate? | open | **open** — this is item 1 above |',
            '| 1.6 | Is the per-office checklist on the same sheet as the student\'s copy, or separate? | **ANSWERED 2026-10-26** | nothing owed — the table stays on the student\'s page, ruled as item 1 below |',
        ],
        [
            '| 1.1–1.5 | Do the slip\'s labels follow the paper, or the paper the system? | partly ruled: the copy designation and `(BSIT) … - 2nd Year` are built (P-19, 2026-10-23; level spelling 2026-10-24) | **open** for `Full Name` vs `Student Name`, the bare `Date` row, the "Registrar In-charge (Signature over Printed Name) & Date" caption, semester-in-title — this is item 2 above |',
            '| 1.1–1.5 | Do the slip\'s labels follow the paper, or the paper the system? | partly ruled: the copy designation and `(BSIT) … - 2nd Year` are built (P-19, 2026-10-23; level spelling 2026-10-24) | **ANSWERED 2026-10-26** — the rest is accepted as printed, ruled as item 2 below; the labels remain editable without a release |',
        ],
        [
            "Measured rather than kept track of: `lane73-packet-evidence.php` counts that sheet at **8 unticked boxes and 2\nchecked ones**.",
            'Measured rather than kept track of: `lane73-packet-evidence.php` counts that sheet at **6 unticked boxes and 4\nchecked ones**.',
        ],
    ],
];

$fails = 0;
$texts = [];
foreach ($targets as $key => $path) {
    $texts[$key] = (string) file_get_contents($path);
}

foreach ($edits as $key => $rows) {
    foreach ($rows as [$from, $to]) {
        $n = substr_count($texts[$key], $from);
        if ($n !== 1) {
            printf("BAD  %s: %d match(es) for %.66s\n", $key, $n, $from);
            $fails++;
        }
    }
}

if ($fails > 0) {
    exit("NOTHING WRITTEN.\n");
}

foreach ($edits as $key => $rows) {
    foreach ($rows as [$from, $to]) {
        $texts[$key] = str_replace($from, $to, $texts[$key]);
    }
    file_put_contents($targets[$key], $texts[$key]);
    printf("wrote %d edit(s) into %s\n", count($rows), basename($targets[$key]));
}

printf("fidelity sheet now: ☐=%d ☑=%d | packet now: ☐=%d ☑=%d\n",
    substr_count($texts['fidelity'], '☐'), substr_count($texts['fidelity'], '☑'),
    substr_count($texts['packet'], '☐'), substr_count($texts['packet'], '☑'));
