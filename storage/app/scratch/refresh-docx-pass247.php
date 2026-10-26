<?php

/**
 * Docx pass 247: the four Registrar-window answers the owner ruled on 2026-10-26, written where they were asked.
 *
 * All four are "as it stands", so no code file moves: the nine clearance lines stay on the student's copy, the slip
 * is accepted exactly as it prints, the desk print is the paper a window accepts, and a lost slip is paid as built.
 * Each of the four questions lives TWICE — §25.11 and §28.5 — which is the convention this document has kept since
 * pass 42, so every one of those sentences is rewritten with expect=2 rather than one of them being left asking.
 *
 * Two tallies were measured before being touched, not reasoned about. §29.4's "Open decisions 20" cell counts the
 * ROWS of §28.5 (21 lines read including its header), so closing four of them does not move it — writing 16 there
 * would have been the defect lane 61 keeps catching. §28.5's own paragraph said "four remain" in the present tense
 * and becomes past. And §25.4 P-19's row said items 1, 2, 4 and 5 "stay open on their own questions" — that tail is
 * this pass's own staleness from 246's reading, closed here.
 *
 *   php storage/app/scratch/refresh-docx-pass247.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass247.php --apply
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/backup-docx-pre-pass247.docx';
$apply = in_array('--apply', $argv, true);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the document cannot be opened as an archive.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    // 1. Which offices sign, and where — §25.11 and §28.5 carry the same sentence.
    [
        'Which offices SEAIT requires to sign, and what each one clears, is still the school’s to state — the seeder’s provisional wording is a placeholder the Registrar replaces in Reference Data.',
        'Ruled 2026-10-26: all nine lines are the school’s, in that order, and the student’s copy carries the whole '
            .'table, so the office that signs each line is visible on the paper the student holds. The sentences '
            .'remain editable on Reference Data → Clearance Requirements, which is a desk action, and the wording the '
            .'seeder wrote is now a placeholder the Registrar replaces rather than a question the register owes.',
        2,
    ],
    // 2. What the slip must contain to be accepted.
    [
        'So the answer SEAIT gives is the wording of the lines and the order of the signatures, not a column.',
        'Ruled 2026-10-26: as printed — the eight identity rows, the nine-line table and the signature block are what '
            .'the window accepts, so the field list is settled and what is left is wording. Wording is a Reference '
            .'Data edit on the requirement lines or a template edit on the labels, one file and one commit if either '
            .'is ever asked for.',
        2,
    ],
    // 4. Which render is the paper.
    [
        'Which of the two SEAIT accepts at a window, and whether the generated PDF is the file of record, is institutional — and php artisan ems:print-fidelity renders either for inspection.',
        'Ruled 2026-10-26: the desk print is the paper a window accepts, because it is the render that mints its '
            .'Document No. before the sheet is drawn and writes its own documentprintlog row, so the paper and the '
            .'log match. ems:print-fidelity passes no number and stays an inspection render — which is why its slip '
            .'shows seven identity rows where the desk’s shows eight, a difference §25.4 P-19 records rather than '
            .'smooths over.',
        2,
    ],
    // 5. Whether a lost slip is paid before it is replaced.
    [
        'and the ₱100.00 in the seeders is the developer’s placeholder.',
        'and the ₱100.00 in the seeders is the developer’s placeholder. Ruled 2026-10-26: as built — a lost slip is '
            .'paid for before it is replaced, at the amount the fee catalogue holds, and the free copy stays what it '
            .'is today, a second print while the row is still incomplete. No free-reissue condition was added, and '
            .'changing the amount remains a Fee Types edit rather than a release.',
        2,
    ],
    // §28.5's own tally sentence: four remain -> four remained, all four ruled.
    [
        'and four remain: items 1, 2, 4 and 5, each of which asks what the Registrar’s window accepts rather than what the software does. None of the twelve answers needed a release.',
        'and four remained: items 1, 2, 4 and 5, each of which asked what the Registrar’s window accepts rather than '
            .'what the software does. All four were ruled on 2026-10-26, and again none of them needed a release: the '
            .'nine lines stay on the student’s page, the slip is accepted exactly as it prints, the desk print is the '
            .'paper, and a lost slip is paid as built. What the registers still charge somebody is two rows — C-5 to '
            .'the owning departments and G-13 to whoever provisions the server — and each of those two now carries '
            .'its own instrument rather than a paragraph. No answer in this table has ever needed a release.',
        1,
    ],
    // §25.4 P-19's tail, which pointed at the four as still open.
    [
        'items 1, 2, 4 and 5 of the sheet stay open on their own questions.',
        'items 1, 2, 4 and 5 of the sheet were ruled on 2026-10-26 and §28.5 carries each answer with the date.',
        1,
    ],
    // §1.1's living row.
    [
        'moved text and instruments without adding a note to this cell.',
        'moved text and instruments without adding a note to this cell. Pass 247 (2026-10-26) wrote the four '
            .'Registrar-window answers the owner ruled the same day: the nine clearance lines stay on the student’s '
            .'copy, the slip is accepted exactly as it prints, the desk print is the paper a window accepts, and a '
            .'lost slip is paid as built — four rows of §25.11 and the same four again in §28.5, because a school '
            .'question lives in every register that asks it, and none of the four moves a line of code. The fidelity '
            .'sheet’s rows 1.6 and 1.1–1.5 are answered by the same two sentences. §29.4’s open-decisions cell was '
            .'left at twenty deliberately: it counts the rows of §28.5 (twenty-one read with its header), not the '
            .'unanswered ones, and a tally lowered because four rows gained a ruling would state a count that was '
            .'never true of either build.',
        1,
    ],
];

$fails = 0;
foreach ($edits as [$from, $to, $expect]) {
    $count = substr_count($xml, $from);
    if ($count !== $expect) {
        printf("BAD  expected %d copies, the archive holds %d — %s\n", $expect, $count, substr($from, 0, 64));
        $fails++;

        continue;
    }
    if ($from === $to) {
        printf("BAD  the replacement is identical to the needle — %s\n", substr($from, 0, 64));
        $fails++;

        continue;
    }
    printf("ok   %d copy(ies) of %s\n", $count, substr($from, 0, 62));
}

// The §28.5 row census the §1.1 sentence quotes ("twenty-one read with its header") is counted here, so the number
// in the document is this pass's own reading rather than a remembered one.
$dump = file($root.'/storage/app/scratch/docx-flat.txt') ?: [];
$in = false;
$sectionRows = 0;
foreach ($dump as $l) {
    $t = trim(preg_replace('/\s+/', ' ', (string) $l));
    if (! $in) {
        if (str_contains($t, '28.5 Consolidated list')) {
            $in = true;
        }

        continue;
    }
    if (preg_match('/^(ROW|P) \d+: \d+\./u', $t) === 1) {
        break;
    }
    if (str_starts_with($t, 'ROW ')) {
        $sectionRows++;
    }
}

if ($sectionRows !== 21) {
    printf("NOTHING WRITTEN: §28.5 reads %d ROW lines, and the sentence this pass writes says twenty-one.\n", $sectionRows);
    exit(1);
}
printf("ok   §28.5 reads %d ROW lines including its header, so §29.4's row count is unchanged by four rulings\n", $sectionRows);

if ($fails > 0) {
    exit("NOTHING WRITTEN: an assertion above failed, so the document was not touched.\n");
}

// The engine takes [from, to, anchor?, expect?] — a four-element LIST, and the expect slot is what lets one
// sentence that exists in two registers be corrected in both at once.
$pair = static fn (array $e): array => [$e[0], $e[1], null, $e[2]];
$out = docxApply($docx, $backup, array_map($pair, $edits), [], $apply);
printf("%s\n", $apply ? 'WROTE: '.($out ?? 'see the engine report') : 'DRY RUN: all seven needles match their expected counts');
