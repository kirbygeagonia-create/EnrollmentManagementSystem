<?php

/**
 * Pass 324: §28.3 G-40's verdict cell follows the ENUM-sort ruling.
 *
 * The owner ruled on 2026-10-31 that a desk MAY be given a header over an ENUM column — on the school's server that
 * order is the declaration order the school wrote into the migration, which is the workflow order a queue wants — and
 * that the consequence falls on the test: the expectation is read from the connection, never typed. This pass rewrites
 * the three present-tense claims in the LIVING register cell that said the opposite, and records that the Registrar's
 * Status header, offered since the first wiring and answered by no case, is pinned now.
 *
 * The two §1.1 rows written earlier today are dated records of the rule as it then stood and are not re-aged;
 * pass 324b adds the row that says the ruling reversed them.
 *
 * Each needle is CUT OUT OF document.xml between two short anchors rather than retyped: the document writes
 * typographic apostrophes and em dashes, and a hand-typed 1,400-character needle is a needle that silently matches
 * nothing — which is what the first draft of this pass did. The cut span is refused if it carries an entity.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass324.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$cut = static function (string $xml, string $start, string $end) use (&$cut): string {
    $s = strpos($xml, $start);
    if ($s === false || substr_count($xml, $start) !== 1) {
        fwrite(STDERR, "REFUSED: the start anchor [{$start}] occurs ".substr_count($xml, $start)." time(s), want exactly one.\n");

        exit(1);
    }
    $e = strpos($xml, $end, $s);
    if ($e === false) {
        fwrite(STDERR, "REFUSED: the end anchor [{$end}] was not found after its start.\n");

        exit(1);
    }
    $span = substr($xml, $s, $e + strlen($end) - $s);
    if (str_contains($span, '&')) {
        fwrite(STDERR, "REFUSED: the cut span carries an entity, so a plain text edit would not round-trip it.\n");

        exit(1);
    }

    return $span;
};

$edits = [
    [
        $cut($xml, 'The seventh table, the clinic', 'rather than trusting the comment.'),
        'The seventh table, the clinic’s health queue, was built under a rule the owner reversed on 2026-10-31: an '
        .'ENUM column MAY carry a header, because the order the school’s server applies is the declaration order the '
        .'school itself wrote into the migration — firstYear, continuing, transferee, shifter — which is the order a '
        .'desk wants and not an accident of the driver. What the ruling moves is the test rather than the screen: the '
        .'suite compiles the same enum() into a varchar with a CHECK and sorts the stored word, so the expected '
        .'sequence is read from the connection by one shared helper instead of typed into a case, and a literal in a '
        .'test is now something a checker can falsify rather than a memory of one server. The clinic desk therefore '
        .'carries a header for every column its query can answer and none for Clinic Status, which is painted from '
        .'whether a record exists on the row rather than stored in it.',
        'was built under a rule the owner reversed',
    ],
    [
        $cut($xml, 'ID Status joins Student Type', 'absent rather than invented.'),
        'ID Status is the one column this desk sees and its handler will not order, and for a reason no ENUM column '
        .'raises: it paints the status of this row’s FIRST request, and ordering by it would mean deciding what two '
        .'requests on one enrollment amount to — a question the school has never been asked, so the header is absent '
        .'rather than invented. The same ruling closed a gap at the first desk ever wired: the Registrar’s Status '
        .'header has been offered since 2026-10-29 and answered by no case, and it now derives its expectation from '
        .'the connection too, over a fixture that interleaves Paid and Assessed rows so neither id order can satisfy it.',
        'The same ruling closed a gap at the first desk ever wired',
    ],
    [
        $cut($xml, 'and the three columns the handler withholds', 'showed no control at all'),
        'and the one column this handler withholds showed no control at all',
        'the one column this handler withholds',
    ],
];

foreach ($edits as [$from, $want, $guard]) {
    if (substr_count($xml, $from) !== 1) {
        fwrite(STDERR, "REFUSED: the cut span occurs ".substr_count($xml, $from)." time(s), want 1 — [".mb_substr($from, 0, 60)."…]\n");

        exit(1);
    }
    if (substr_count($xml, $guard) !== 0) {
        fwrite(STDERR, "REFUSED: the new fragment [{$guard}] is already present — this pass has run.\n");

        exit(1);
    }
}

docxApply(
    $docx,
    $root.'/storage/app/scratch/pass324-tmp-'.date('Ymd-His').'.docx',
    array_map(static fn (array $e): array => [$e[0], $e[1], null, 1], $edits),
    [],
    $apply
);

if (! $apply) {
    echo "\nDRY RUN — pass 324 wrote nothing.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach (['was built under a rule the owner reversed', 'The same ruling closed a gap at the first desk ever wired',
    'the one column this handler withholds'] as $want) {
    if (substr_count($after, $want) !== 1) {
        fwrite(STDERR, "REFUSED after write: [{$want}] occurs ".substr_count($after, $want).", want 1.\n");

        exit(1);
    }
}
foreach (['withholds a visible column because of its TYPE', 'ID Status joins Student Type',
    'the three columns the handler withholds'] as $gone) {
    if (substr_count($after, $gone) !== 0) {
        fwrite(STDERR, "REFUSED after write: the retired claim [{$gone}] is still in the living register.\n");

        exit(1);
    }
}

echo "\npass 324 applied: G-40 states the ruling — an ENUM column may be sorted, and the expectation is asked of the server.\n";
