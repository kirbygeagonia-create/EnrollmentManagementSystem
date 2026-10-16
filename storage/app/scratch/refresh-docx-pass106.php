<?php

/**
 * Pass 106 — the two rulings of 2026-10-13, and the three places the document still described the
 * world as it was before them.
 *
 *   1. #91: the preflight inventory says the two DATETIME columns "carry" a MySQL CURRENT_TIMESTAMP
 *      default. After this batch they carried it — the tense is the claim, and the claim is now wrong.
 *   2/3. Two schema-dictionary rows still print CURRENT_TIMESTAMP in their Default cell. The third
 *      such row is `jobs.failed_at`, which keeps its default on purpose and must NOT be touched; that
 *      is why these two are edited by absolute offset, verified by reading the row they sit in, rather
 *      than by a global replace.
 *   4. #89: the department desks, recorded in the paragraph that already answers "who may act", because
 *      appending to an existing run injects no markup and reflowing no table is the safer edit.
 *
 * Offsets are re-derived from the file on every run and each target slice is checked before it is
 * queued; edits are applied from the highest offset downward so no earlier offset is shifted by a
 * later write — one offset carries exactly one edit.
 *
 * DRY RUN by default. --apply writes, and only after the guards pass; a backup is taken first.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$edits = [];

/** Record a failure the way the rest of this harness does: name it, keep going, refuse to write. */
$refuse = function (string $why) use (&$fails): void {
    fwrite(STDERR, "REFUSE: {$why}\n");
    $fails++;
};

// ---- 1. the preflight inventory, present tense made accurate ----
$from1 = 'which is what the two columns carrying a MySQL CURRENT_TIMESTAMP default would silently inherit';
$to1 = 'which is what the two columns that carried a MySQL CURRENT_TIMESTAMP default would have silently '
    .'inherited, until the ruling of 2026-10-13 took both defaults away and made the application the only '
    .'process that stamps a record';
$n1 = substr_count($xml, $from1);
if ($n1 !== 1) {
    $refuse("edit 1 anchor found {$n1} times, expected 1");
} else {
    $edits[] = [strpos($xml, $from1), strlen($from1), $to1, '1 the clock clause, re-timed'];
}

// ---- 2 and 3. the two dictionary rows, each located through the row it sits in ----
foreach ([['changedAt', 3148382], ['createdAt', 4037352]] as [$column, $hint]) {
    // The hint offset is where the value was when this pass was written; the row is re-found from it
    // rather than trusted, because any earlier edit shifts every byte below it.
    $at = strpos($xml, 'CURRENT_TIMESTAMP', max(0, $hint - 200000));
    if ($at === false) {
        $refuse("edit for {$column}: no CURRENT_TIMESTAMP near the recorded byte {$hint}");
        continue;
    }

    $rowStart = strrpos(substr($xml, 0, $at), '<w:tr');
    $rowEnd = strpos($xml, '</w:tr>', $at) + 7;
    $row = substr($xml, $rowStart, $rowEnd - $rowStart);

    $rowText = trim(preg_replace('/\s+/', ' ', strip_tags($row)));
    if (! str_starts_with($rowText, $column.' ') && ! str_contains(substr($rowText, 0, 40), $column)) {
        $refuse("edit for {$column}: the row found reads 「{$rowText}」 — wrong table, wrong row");
        continue;
    }

    // The Default cell's own run, inside that row only.
    $runAt = strpos($row, '>CURRENT_TIMESTAMP</w:t>');
    if ($runAt === false) {
        $refuse("edit for {$column}: no single run holds the value in that row");
        continue;
    }

    $abs = $rowStart + $runAt + 1;   // just past the '>' of <w:t ...>
    $len = strlen('CURRENT_TIMESTAMP');
    if (substr($xml, $abs, $len) !== 'CURRENT_TIMESTAMP') {
        $refuse("edit for {$column}: the slice at byte {$abs} is not the value itself");
        continue;
    }

    $edits[] = [$abs, $len, 'none (default removed 2026-10-13)', "row {$column}.Default"];
}

// ---- 4. #89, appended to the paragraph that already answers who may act ----
$anchor = 'because the two desks examine the same students.';
$at4 = strpos($xml, $anchor);
if ($at4 === false || substr_count($xml, $anchor) !== 1) {
    $refuse('the "who may act" paragraph no longer ends where this pass expects');
} else {
    $closing = strpos($xml, '</w:t>', $at4);
    if ($closing === false || $closing - ($at4 + strlen($anchor)) > 10) {
        $refuse('the anchor is not inside a text run that ends right after it');
    } else {
        $ruling = ' Ruled 2026-10-13 on the last open desk question, and the answer is the college that owns '
            .'the program: DevReferenceDataSeeder now seats one Academic Department account per college in '
            .'academicunits — six colleges on the installed dataset — each at office 7 with that college as its '
            .'unitId and the enum RbacSeeder maps to Instructor + DeptEvaluator, the pairing holding '
            .'evaluation.view, evaluation.create and evaluation.sign. Measured first, because the mismatch was '
            .'not where it looked: no seeded staff row carried a unitId at all, so office7_head and three '
            .'instructors evaluated every program from one anonymous desk while the launcher keyed the card to '
            .'the Guidance office id 4 — which is how the head of the owning department, the one account '
            .'holding both rights, was refused the screen it signs. The card now answers to the same right its '
            .'own route asks, the way clearance, students and the catalogs already do, and the demo pipeline '
            .'resolves each evaluator from the course the row already names and refuses with the command that '
            .'fixes it when a college has no desk. What this does not do is rewrite history: the 29 enrollments '
            .'that already credit office4_head, a Guidance account with no evaluation.sign, keep doing so until '
            .'the owner rules on amending stored records, and rehearsing the chain on a copy showed exactly '
            .'that split — two rows written by the changed pipeline landed on dept.ctic, the CTIC desk its '
            .'BSIT students belong to, and the older rows stayed where they were. Seating the desks is what '
            .'surfaced a second finding: on a fresh database seeded in the documented order, 10 of 17 accounts '
            .'ended with no roles at all, every office head among them, because RbacSeeder derives roles from '
            .'the enum before the dev seeder creates those accounts — the reason the §2.8 head-only UAT rows '
            .'could not be performed, now closed by re-deriving the mapping at the end of that seeder.';
        $edits[] = [$at4 + strlen($anchor), 0, $ruling, '4 the #89 ruling, appended in place'];
    }
}

printf("edits planned: %d\n", count($edits));
foreach ($edits as [$off, $len, $to, $label]) {
    printf("  %-34s byte %8d  replace %4d chars with %4d\n", $label, $off, $len, strlen($to));
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

// Two edits must never share an offset, and no edit may land inside another edit's span.
usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$previousStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $previousStart) {
        $refuse(sprintf('edits overlap at %d and %d', $e[0], $previousStart));
    }
    $previousStart = $e[0];
}
if ($fails > 0) {
    exit(1);
}

foreach ($edits as [$off, $len, $to, $label]) {
    $xml = substr_replace($xml, $to, $off, $len);
    printf("applied  %s\n", $label);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stray markdown crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    // prose (1, reworded) + jobs.failed_at (1, untouched) — the two dictionary rows are gone
    'CURRENT_TIMESTAMP reduced to the two honest uses' => substr_count($xml, 'CURRENT_TIMESTAMP') === 2,
    // The two dictionary cells that keep a database default must be untouched. A first attempt read the
    // row after the FIRST mention of failed_at, which is not the schema row — the word appears earlier
    // in prose — so the guard failed on a document that was correct. Scan every row instead.
    'the failed_at dictionary row keeps its default' => (function () use ($xml): bool {
        foreach (preg_split('/<w:tr\b/', $xml) as $row) {
            // Splitting on `<w:tr` leaves the tag's own closing `>` at the head of the slice, and cell
            // text concatenates with no separator: this row strips to ">failed_attimestampnoCURRENT_…".
            $text = ltrim(trim(preg_replace('/\s+/', ' ', strip_tags($row))), '>');
            if (str_starts_with($text, 'failed_at') && str_contains($text, 'CURRENT_TIMESTAMP')) {
                return true;
            }
        }

        return false;
    })(),
    'both dictionary cells now say none' => substr_count($xml, 'none (default removed 2026-10-13)') === 2,
    'the ruling is present once' => substr_count($xml, 'Ruled 2026-10-13 on the last open desk question') === 1,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

echo $fails === 0 ? "\nall edits and guards ok\n" : "\n{$fails} problem(s)\n";
if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass106.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
