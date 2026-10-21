<?php

/**
 * Lane 57 — move every markdown claim that the two new rules and the ceiling just invalidated.
 *
 * The rule count is stated in three files and derived from none of them, which is the same failure this repository
 * has now met in §28.3's size, in the demo report's `Ledger —` count, and in the "four partial rows" that lane 56f
 * re-took: a number written where it can be edited but not measured goes stale the day someone adds a rule. Each
 * needle here is matched exactly once or the file is left alone and the run refuses.
 *
 * Line endings are the file's own. `.gitattributes` says `* text=auto eol=lf`, and `git ls-files --eol` answers
 * AGENTS.md as `w/lf` and DEPLOYMENT.md as `w/crlf` — the first draft of this script spelled both as \r\n and
 * matched nothing in the LF file.
 */

$root = dirname(__DIR__, 3);
$failures = 0;

function edit(string $path, string $needleLf, string $replacementLf, string $what, int &$failures): void
{
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        fwrite(STDERR, "REFUSED: cannot read {$path}\n");
        exit(1);
    }
    $eol = str_contains($bytes, "\r\n") ? "\r\n" : "\n";
    $needle = str_replace("\n", $eol, $needleLf);
    $replacement = str_replace("\n", $eol, $replacementLf);
    $hits = substr_count($bytes, $needle);

    if ($hits !== 1) {
        fwrite(STDERR, "FAIL: the {$what} needle matched {$hits} time(s), expected exactly 1 — nothing written to "
            .basename($path)."\n");
        $failures++;

        return;
    }

    file_put_contents($path, str_replace($needle, $replacement, $bytes));
    $after = (string) file_get_contents($path);
    $stray = $eol === "\r\n" ? preg_match_all("/(?<!\r)\n/", $after) : substr_count($after, "\r\n");
    printf("  ok   %s (%+d bytes, %s endings, %d stray)\n", $what, strlen($replacement) - strlen($needle),
        $eol === "\r\n" ? 'CRLF' : 'LF', $stray);
    if ($stray !== 0) {
        fwrite(STDERR, "FAIL: {$path} now carries the ending that file does not use\n");
        $failures++;
    }
}

// ---------------------------------------------------------------- AGENTS.md
$agents = $root.'/AGENTS.md';

edit($agents,
    "two of its seventeen rules (`collidingSchedules('block')` and `('resource')`)",
    "two of its nineteen rules (`collidingSchedules('block')` and `('resource')`)",
    'AGENTS.md timetable rule count', $failures);

edit($agents,
    "  governs the rest of the lines: `DB_DATABASE=ems_l54_a php storage/app/scratch/mutate-lane54-report.php`.\n",
    "  governs the rest of the lines: `DB_DATABASE=ems_l54_a php storage/app/scratch/mutate-lane54-report.php`.\n"
    ."- **After any batch that touches `AccountingController::record()`'s amount, or a money rule in\n"
    ."  `ems:audit:record-states`**, run `DB_DATABASE=<scratch schema> php storage/app/scratch/mutate-lane57-overcollect.php`.\n"
    ."  It plants the two states the lane added — a receipt above the amount the sheet owes, and an enrollment left\n"
    ."  `paid` after its money went — and requires each to fail the audit and name its own label, then swaps HEAD's\n"
    ."  `AccountingController.php` over the working file and drives the same POST through the HTTP kernel, requiring\n"
    ."  the old bytes to accept the money and these to refuse it with the ceiling in the message. The tree is\n"
    ."  restored by hash in a `finally`, because a half-swapped controller makes every later verdict a lie. It refuses\n"
    ."  rather than reporting a pass: on live `ems`, on a schema with no owing enrollment, and on a mutation that\n"
    ."  touched no row. The planter is `storage/app/scratch/lane57-plant-state.php`; the desk act is\n"
    ."  `storage/app/scratch/lane57-overcollect-state.php`, and its `revert` walks the enrollment back through the\n"
    ."  state machine rather than only deleting the receipt — the half-revert is what produced §28.3 G-27.\n",
    'AGENTS.md lane 57 gate bullet', $failures);

edit($agents,
    "- **A receipt's status word is a claim about the account, not about the money** (lane 56).",
    "- **A receipt states money the school keeps, and a status states a moment** (lane 57). `record()` bounded\n"
    ."  `amount` only from below, and `outstandingBalance()` answers `max(0, …)`, so one POST of ₱19,000 against a\n"
    ."  ₱17,500 sheet wrote the receipt, marked it `paid` (the honest word — nothing was left owing), stored a\n"
    ."  balance of ₱0.00, and left ₱1,500 in the drawer against nothing. Ruled 2026-10-20: the OR may not state more\n"
    ."  than the account owes, so the guard refuses above the balance and names the figure, and the screen that used\n"
    ."  to compute change due now caps at the same number — a promise and a server must not disagree (§17).\n"
    ."  `ems:audit:record-states` carries the state as its eighteenth rule, measured against *what is owed*\n"
    ."  (assessed less coverage less waived), not the assessed total, so a full receipt over a covered account is\n"
    ."  named too. The lane's second rule is its mirror: the probe that planted the first un-planted it by deleting\n"
    ."  the row and recomputing the balance, never walking the enrollment's status back — leaving a schema saying\n"
    ."  `paid` over the whole fee while all eighteen other rules read 0. So the nineteenth rule asks whether a\n"
    ."  `paid` status still matches its money, scoped to `paid` alone: the machine walks `paid → enrolled` on\n"
    ."  purpose, and a rule that swept every later status in would report normal progress as broken.\n"
    ."- **A receipt's status word is a claim about the account, not about the money** (lane 56).",
    'AGENTS.md lane 57 fact bullet', $failures);

// ---------------------------------------------------------------- DEPLOYMENT.md
$deploy = $root.'/DEPLOYMENT.md';

edit($deploy,
    "   seventeen rules. Two of the seventeen are the timetable: two schedules of one block\n",
    "   nineteen rules. Two of the nineteen are the timetable: two schedules of one block\n",
    'DEPLOYMENT.md rule count', $failures);

edit($deploy,
    "   has refused on its form since that form existed, counted here so a write path that bypasses the form is\n"
    ."   visible too (ruled 2026-10-19). A non-zero count means a write path regressed or a row\n",
    "   has refused on its form since that form existed, counted here so a write path that bypasses the form is\n"
    ."   visible too (ruled 2026-10-19). Two more are the money pair of 2026-10-20: a fee sheet whose held receipts\n"
    ."   come to more than it is owed — measured against assessed less coverage less waived, not the assessed total,\n"
    ."   because the clamp in `outstandingBalance()` hides an over-collection from every screen — and an enrollment\n"
    ."   still saying `paid` after the money behind that status has gone. The desk can no longer write the first\n"
    ."   (`record()` refuses an amount above the balance) and the second is what the probe that planted the first\n"
    ."   discovered by un-planting it badly. A non-zero count means a write path regressed or a row\n",
    'DEPLOYMENT.md money pair', $failures);

edit($deploy,
    "   the status column. Two of those seventeen are different in kind and only ever `note`, because each counts\n",
    "   the status column. Two of those nineteen are different in kind and only ever `note`, because each counts\n",
    'DEPLOYMENT.md note-kind count', $failures);

edit($deploy,
    "   green. The other fifteen fail the run, and the third of the once-noted rules joined them on 2026-10-15:\n",
    "   green. The other seventeen fail the run, and the third of the once-noted rules joined them on 2026-10-15:\n",
    'DEPLOYMENT.md failing-rule count', $failures);

// ---------------------------------------------------------------- the demo-day guide
$guide = $root.'/Documentation/Offline-Presentation-Guide.md';
edit($guide,
    "17 now — lane 53 added a bare fee sheet and an unrecorded payment step on 2026-10-18, lane 55 the two\n"
    ."timetable collisions on 2026-10-19, and lane 56 a receipt claiming settlement over a balance still owing):",
    "19 now — lane 53 added a bare fee sheet and an unrecorded payment step on 2026-10-18, lane 55 the two\n"
    ."timetable collisions on 2026-10-19, lane 56 a receipt claiming settlement over a balance still owing, and lane\n"
    ."57 a receipt above the amount the sheet owes and a `paid` status whose money had already left):",
    'the guide’s rule count', $failures);

if ($failures > 0) {
    printf("\n%d edit(s) did not apply — read the files before quoting any of these counts.\n", $failures);
    exit(1);
}

echo "\nAll three files now state nineteen rules, and the lane 57 rulings are written where the code can be checked against them.\n";
exit(0);
