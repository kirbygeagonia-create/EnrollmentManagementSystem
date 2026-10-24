<?php

/**
 * Closes the remaining decision-sheet rows the owner ruled on 2026-10-24:
 * items 8, 10, 11, 3 and item 15's queue half. Each needle must match exactly once;
 * nothing is written unless all of them do.
 *
 *   php storage/app/scratch/lane69-close-sheet-rows.php          # dry run
 *   php storage/app/scratch/lane69-close-sheet-rows.php --apply  # write
 */

$path = dirname(__DIR__, 3).'/Documentation/School-Decision-Sheet-2026-10-23.md';
$apply = in_array('--apply', $argv, true);
$text = (string) file_get_contents($path);

$edits = [
    // Title markers, so the sheet's own convention (the ruling in the bracket) holds for every closed row.
    ['state student type and enrollment type?** `[§28.5, P-13]`',
        'state student type and enrollment type?** `[§28.5, P-13 — ANSWERED 2026-10-24]`'],
    ['on entry to the new program?** `[C-6]`',
        'on entry to the new program?** `[C-6 — ANSWERED 2026-10-24]`'],
    ['fresh scholarship determination every term?** `[C-7]`',
        'fresh scholarship determination every term?** `[C-7 — ANSWERED 2026-10-24]`'],
    ['**15. Two items the build cannot reach from this machine.** `[G-13, C-10]`',
        '**15. Two items the build cannot reach from this machine.** `[G-13 — ANSWERED 2026-10-23, C-10 — ANSWERED 2026-10-24]`'],
    // item 8 — the build's rule is confirmed, so the register's answer becomes the school's.
    ['- Answer: confirmed ☐ / exception should also not cover: ............. Initial: ..........',
        "- ~~Answer: confirmed ☐ / exception should also not cover: .............~~ — answered 2026-10-24:\n".
        '  **confirmed, as built.** A program change makes the student Irregular, arriving as a transferee does the same, and no'.
        " population is carved out of it. `StudentType::arrivesIrregular()` remains the single definition. Initial: .........."],
    // item 10 — no examination for a shifter.
    ['- Answer: confirmed, no examination ☐ / the department requires one: ............. Initial: ..........',
        "- ~~Answer: confirmed, no examination ☐ / the department requires one: .............~~ — answered 2026-10-24:\n".
        '  **confirmed, no examination.** A shifter is assessed through credit evaluation at stage 6 and the shift grant exempts the\n'.
        '  retention examination; `ShiftRequestFlowTest` pins both halves. A department that later wants an entrance paper asks for a\n'.
        '  rule, not a workaround. Initial: ..........'],
    // item 11 — every term, freshly determined.
    ['- Answer: every term ☐ / until expiry of the award ☐ / other: ............. Initial: ..........',
        "- ~~Answer: every term ☐ / until expiry of the award ☐ / other: .............~~ — answered 2026-10-24:\n".
        '  **every term.** The strictest reading, which is also the state the build forces: a continuing or shifter form carries no\n'.
        '  Assessment box, a grant is written against the assessment of the enrollment it covers, and nothing copies last term\'s grant\n'.
        '  forward — so such an enrollment is uncovered until the Scholarship desk acts, and that gap is the rule rather than a defect.\n'.
        '  No code change: the system already refuses to decide this for the desk. Initial: ..........'],
    // item 3 — the certificate keeps the wording the developer chose.
    ['- Answer: as printed ☐ / replacement wording attached ☐ Initial: ..........',
        "- ~~Answer: as printed ☐ / replacement wording attached ☐~~ — answered 2026-10-24:\n".
        '  **as printed.** The certificate states student type in its own words and enrollment type separately, and those sentences\n'.
        '  stand as the school\'s. §25 keeps them recorded as the build\'s wording now ruled in, and P-13 closes on that. Initial: ..........'],
    // item 15 — the queue half, whose whole subject is the in-app bell.
    ['  Answer: sync ☐ / a worker runs: ............. ☐ Initial: ..........',
        "  Answer: **sync ☑** / a worker runs: ............. ☐ — answered 2026-10-24, closing C-10 for this install.\n".
        '  Reading the code narrowed what the question was ever about: the only two queued listeners are `SendEnrollmentNotification`\n'.
        '  and `SendWorkflowNotification`, and `SendWorkflowNotification::notify()` does nothing but `Notifications::create(...)` —\n'.
        '  plain inserts of the in-app bell rows for staff accounts. There is no `app/Notifications/` directory, no mail channel, and\n'.
        '  PDF rendering has never been queued. So a worker exists here for one purpose: delivering the desk\'s bell. On `sync` it\n'.
        '  happens inside the request, which is why the bell shows what a desk just did; on `database` with no worker the rows simply\n'.
        '  never appear — the whole story of the 56 stale jobs. `.env` runs `sync`; `.env.example:70` still ships `database`, and\n'.
        '  `DEPLOYMENT.md` §5 carries the worker for whoever provisions a real server. Initial: ..........'],
];

$fails = 0;
foreach ($edits as $i => [$from, $to]) {
    $c = substr_count($text, $from);
    printf("  %s edit %d matched %d time(s): %s\n", $c === 1 ? 'ok  ' : 'BAD ', $i + 1, $c, str_replace("\n", ' ⏎ ', mb_substr($from, 0, 62)));
    if ($c !== 1) {
        $fails++;
    }
}

$staged = $text;
foreach ($edits as [$from, $to]) {
    $staged = str_replace($from, $to, $staged);
}
$open = preg_match_all('/^\*\*(\d+)\. /m', $staged, $m) ? count($m[1]) : 0;
printf("\n  items after the pass: %d (was %d)\n", $open, preg_match_all('/^\*\*(\d+)\. /m', $text, $m0) ? count($m0[1]) : 0);
printf("  ANSWERED markers: %d (was %d)\n", substr_count($staged, 'ANSWERED 2026-10-2'), substr_count($text, 'ANSWERED 2026-10-2'));

echo "\n{$fails} needle(s) did not match exactly once.\n";
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: five rows would be marked answered.\n";
    exit(0);
}
copy($path, $path.'.bak-pre-close-rows');
file_put_contents($path, $staged);
echo "WROTE {$path}\n";
