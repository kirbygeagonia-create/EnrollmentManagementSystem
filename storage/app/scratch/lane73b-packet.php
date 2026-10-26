<?php

/**
 * Mark the packet's four §A rows ANSWERED and correct the sentences that counted them as owed.
 *
 * The packet is dated 2026-10-26 and the four answers came the same day, so this is not re-aging a reading — the
 * rows say what was asked, and now carry what was ruled. Every needle is asserted to match exactly once.
 */
$path = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem/Documentation/Registrar-Window-Packet-2026-10-26.md';
$src = (string) file_get_contents($path);

$edits = [
    [
        '### 1. Where does each of the nine offices actually sign? `[§28.5 · sheet item 1]`',
        '### 1. Where does each of the nine offices actually sign? `[§28.5 · sheet item 1 — ANSWERED 2026-10-26]`',
    ],
    [
        "- ☐ These nine lines and this order are the school's — the table stays on the student's page.",
        "- ~~☐ These nine lines and this order are the school's — the table stays on the student's page.~~ **ANSWERED: this one.** The nine lines stay, in this order, on the copy the student holds, so the office that signs each line is visible on the paper.",
    ],
    [
        "- ☐ The table belongs on an **internal** copy; the student's page keeps one signature line (name the offices that still print: .............).",
        "- ~~☐ The table belongs on an **internal** copy; the student's page keeps one signature line.~~ Not chosen.",
    ],
    [
        '- ☐ Amended on the attached page ☐ Other: .............  Initial: ..........',
        '- ~~☐ Amended on the attached page ☐ Other~~ None. Initial: the owner, in conversation, 2026-10-26',
    ],
    [
        '### 2. What must the printed slip contain to be accepted at the window? `[§28.5 · P-8 · P-10 · sheet item 2]`',
        '### 2. What must the printed slip contain to be accepted at the window? `[§28.5 · P-8 · P-10 · sheet item 2 — ANSWERED 2026-10-26]`',
    ],
    [
        '- ☐ As printed — the window accepts all eight rows.',
        '- ~~☐ As printed — the window accepts all eight rows.~~ **ANSWERED: this one.** Eight identity rows, the nine-line table and the signature block, as the desk prints them.',
    ],
    [
        '### 4. Is the generated PDF or the desk screen the paper a window accepts? `[§28.5 · P-19 · sheet item 4]`',
        '### 4. Is the generated PDF or the desk screen the paper a window accepts? `[§28.5 · P-19 · sheet item 4 — ANSWERED 2026-10-26]`',
    ],
    [
        '- ☐ The desk print is the paper. ☐ The generated PDF is the paper.',
        '- ~~☐ The desk print is the paper.~~ **ANSWERED: the desk print** — it mints its Document No. before the render and writes its own `documentprintlog` row, so paper and log match. ☐ The generated PDF is the paper. Not chosen; it stays the inspection render.',
    ],
    [
        '### 5. Must every lost slip be paid for before it is replaced? `[§28.5 · P-12 · sheet item 5]`',
        '### 5. Must every lost slip be paid for before it is replaced? `[§28.5 · P-12 · sheet item 5 — ANSWERED 2026-10-26]`',
    ],
    [
        '- ☐ As built — a lost slip is paid, at the amount Reference Data carries.',
        '- ~~☐ As built — a lost slip is paid, at the amount Reference Data carries.~~ **ANSWERED: this one.** A lost slip is paid before it is replaced; the free copy stays the not-yet-complete case, and no free-reissue condition was added.',
    ],
    [
        '## A. The four rows decision-sheet items 1, 2, 4, 5 still ask',
        '## A. The four rows decision-sheet items 1, 2, 4, 5 asked — all four ruled 2026-10-26',
    ],
];

$fails = 0;
foreach ($edits as [$from, $to]) {
    if ($from === $to) {
        echo "BAD  an edit is a no-op\n";
        $fails++;

        continue;
    }
    $n = substr_count($src, $from);
    if ($n !== 1) {
        printf("BAD  %d match(es) for %.70s\n", $n, $from);
        $fails++;
    }
}

if ($fails > 0) {
    exit("NOTHING WRITTEN.\n");
}

foreach ($edits as [$from, $to]) {
    $src = str_replace($from, $to, $src);
}

file_put_contents($path, $src);
printf("wrote %d edit(s); ANSWERED markers now %d, ☐ %d\n", count($edits), substr_count($src, 'ANSWERED'), substr_count($src, '☐'));
