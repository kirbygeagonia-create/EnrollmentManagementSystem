<?php

/**
 * Moves the requirement matrix from seven wired tables to eight (the ID desk's validation queue).
 *
 * Six replacements. Each is asserted to occur EXACTLY ONCE before anything is written, because a half-applied
 * register is the worst docx-and-markdown defect this repository has: one section states the ruling while its twin
 * still asks the question, both sentences are live prose, and nothing is dissatisfied.
 *
 * Usage: php storage/app/scratch/lane103-move-the-matrix-to-eight.php [--apply]
 */

$file = dirname(__DIR__, 3).'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$apply = in_array('--apply', $argv, true);

$edits = [
    // (a) the §11 accessibility row: the count, and the third reason a column is withheld.
    [
        'seven screens use it and the rest still show headings with no order. The clinic queue is the first screen '
        .'where a column the desk can SEE was left out of that set on purpose: Student Type and Standing are ENUM '
        .'columns, and one header over an ENUM answers a different sequence on the school\'s MySQL than on the test '
        .'schema\'s varchar.',
        'eight screens use it and the rest still show headings with no order. The clinic queue was the first screen '
        .'where a column the desk can SEE was left out of that set on purpose: Student Type and Standing are ENUM '
        .'columns, and one header over an ENUM answers a different sequence on the school\'s MySQL than on the test '
        .'schema\'s varchar. The ID desk adds a third reason — its ID Status column paints the status of that row\'s '
        .'FIRST request, so ordering it would decide what two requests on one enrollment amount to, which the school '
        .'has never been asked.',
    ],
    // (b) the §14 density row.
    [
        'Seven of the 36 screens that render the shared table now sort and report the order they applied — the '
        .'Registrar queue, the Admission queue, the student directory, the campus clearance queue, the assessment '
        .'desk\'s fee sheets, the cashier\'s collection queue and the clinic\'s health queue; the other 29 **pages** '
        .'offer headings, filtering, search and row actions but no order. That 29 counts pages with no sortable header '
        .'and is a different set from the 29 below, which counts pages whose own action hands over a paginator — the '
        .'two numbers agree by coincidence, not by identity.',
        'Eight of the 36 screens that render the shared table now sort and report the order they applied — the '
        .'Registrar queue, the Admission queue, the student directory, the campus clearance queue, the assessment '
        .'desk\'s fee sheets, the cashier\'s collection queue, the clinic\'s health queue and the ID desk\'s '
        .'validation queue; the other 28 pages offer headings, filtering, search and row actions but no order. That 28 '
        .'counts pages with no sortable header, and is not the 29 below, which counts pages whose own action hands '
        .'over a paginator — a page can be paged and still unorderable, and the two sets are only partly alike.',
    ],
    // (c) the G40 row's tally clause.
    [
        'and the seven wired pages call it — 29 of the 36 pages using the shared table still offer no order.',
        'and the eight wired pages call it — 28 of the 36 pages using the shared table still offer no order.',
    ],
    // (d) the G40 row's own list of wired screens.
    [
        'The clinic health queue joined as the seventh table on 2026-10-30, and it is the first screen that withholds '
        .'a header because of a column\'s TYPE:',
        'The clinic health queue joined as the seventh table on 2026-10-30 and the ID desk\'s validation queue as the '
        .'eighth the same day. The clinic queue is the first screen that withholds a header because of a column\'s '
        .'TYPE:',
    ],
    // (e) the G40 row's closing account, extended with what the eighth table added.
    [
        'while the sequence itself is pinned by a fixture that writes four.',
        'while the sequence itself is pinned by a fixture that writes four. The eighth table is the first screen that '
        .'sends THREE controls an order must survive — the tab the desk stands on, the search, and the order — and a '
        .'tab is not a filter: it chooses which population the list is built from (first open box at office 22, '
        .'against a signed ID box), so the order is applied to the base the tab chose and never the other, which one '
        .'of the thirteen legs of `mutate-g40-id-sort-tests.php` plants directly (`order-applied-to-the-other-base`). '
        .'The tab must also come back: four keys travel in the filter echo now, and an un-echoed tab is the same reset '
        .'seen from the side a desk switches rather than types. This queue is long enough to show an order at the desk '
        .'itself: live `ems` holds two enrollments waiting at the ID office, and the running screen swapped them, with '
        .'the header state reading none, then ascending, then descending.',
    ],
    // (f) the Status cell.
    [
        '| Partly built: the mechanism exists and seven of thirty-six tables use it |',
        '| Partly built: the mechanism exists and eight of thirty-six tables use it |',
    ],
];

$src = (string) file_get_contents($file);
foreach ($edits as $i => [$from, $to]) {
    $h = substr_count($src, $from);
    if ($h !== 1) {
        fwrite(STDERR, sprintf("REFUSED: edit %d occurs %d time(s), want 1 — [%s]\n", $i, $h, mb_substr($from, 0, 70)));

        exit(1);
    }
    if (substr_count($src, $to) !== 0 && $to !== $from) {
        fwrite(STDERR, sprintf("REFUSED: edit %d's replacement is already present — [%s]\n", $i, mb_substr($to, 0, 70)));

        exit(1);
    }
    $src = str_replace($from, $to, $src);
}

printf("%s: %d edit(s) applied to the matrix\n", $apply ? 'APPLY' : 'DRY RUN', count($edits));
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

file_put_contents($file, $src);

$after = (string) file_get_contents($file);
foreach (['eight screens use it', 'the other 28 pages', 'the eight wired pages call it',
    'eighth the same day', 'order-applied-to-the-other-base', 'eight of thirty-six tables use it'] as $want) {
    if (substr_count($after, $want) < 1) {
        fwrite(STDERR, "REFUSED after write: [{$want}] is missing.\n");

        exit(1);
    }
}
echo "written; every new phrase read back.\n";
