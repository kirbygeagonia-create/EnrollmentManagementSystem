<?php

/**
 * Lane 37 — sweep every `§NN` and `§NN <id>` reference in the authority docx against the document itself.
 *
 * Why this exists: `AuditRecordStates.php`, DEPLOYMENT.md and the UAT walkthrough all cited the key-less-print
 * hazard as "§28 G-9". §28's G-9 is the demo term offering no curriculum subject at the levels the students
 * sat; the print-log NULL limit is §25 P-14 — which is how the document cites its own finding in three other
 * places. The line sweep (`sweep-docx-citations.php`) only knows `File.php:NN`, and the meaning sweep
 * (`lane32-...`) only checks a cited *range* against the construct it names, so a reference that points at a
 * real section carrying a different finding passes both. This is the check for the other half of the citation
 * vocabulary; `lane37b-repo-citation-sweep.php` does the same for the 214 references the PHP and markdown
 * files point into the document.
 *
 * The inventory it judges against is `lane37-docx-inventory.php` — one reader of the document, shared by both
 * sweeps, because two builders drift and a stale one reports clean.
 *
 * Three citation forms exist in this document and they do not mean the same thing (measured, not assumed):
 *   space / tight  "§28 G-9", "§25 P-14", "§28.3 U-4", "§27.2 (E-1"  — the citation claims where the row lives;
 *   comma          "(§12.5, G-3)", "§5.2, U-1"                      — the document's convention for "the
 *                                                                   behaviour is at §NN, the finding is
 *                                                                   tracked as ID", so the row may live
 *                                                                   in another chapter entirely.
 * A bare §num that is not in this document is also not automatically a defect: the prose cites the acceptance
 * script's own sections too ("§2.8 of the acceptance script creates two"), so a miss is only a miss when
 * nothing in the sentence names the other document.
 *
 * Verdicts: NO-SECTION (no heading carries that number), NO-ID (no row carries that finding id anywhere),
 * WRONG-LOCATION (the id exists but not where the citation says). Read-only; a silent run is the pass.
 */

$root = dirname(__DIR__, 3);
// Flags are not paths: `$argv[1] ?? default` reads `--verbose` as the document to open, which is how two
// sibling tools failed their first run.
$args = array_values(array_filter(array_slice($argv, 1), fn ($a) => ! str_starts_with($a, '--')));
$verbose = in_array('--verbose', $argv, true);

$inv = require __DIR__.'/lane37-docx-inventory.php';
$sections = $inv['sections'];
$rowsById = $inv['rows'];
$blockTexts = $inv['blocks'];

$sectionsWithRows = [];
foreach ($rowsById as $id => $hits) {
    foreach ($hits as $h) {
        $sectionsWithRows[$h['section']] = true;
    }
}

$otherDocCues = ['walkthrough', 'acceptance script', 'storyboard', 'use-case', 'deployment'];

/** A §num that is not in this document is only a defect when the sentence does not name another document. */
$namesAnotherDocument = function (string $text) use ($otherDocCues): bool {
    foreach ($otherDocCues as $c) {
        if (stripos($text, $c) !== false) {
            return true;
        }
    }

    return false;
};

/** True when the number is a section that exists, a chapter whose subsections exist, or a row host. */
$sectionExists = function (string $sec) use ($sections, $sectionsWithRows): bool {
    if (isset($sections[$sec]) || isset($sectionsWithRows[$sec])) {
        return true;
    }
    foreach (array_keys($sections) as $s) {
        if (str_starts_with($s, $sec.'.')) {
            return true;
        }
    }

    return false;
};

$refRe = '/§\s*(\d+[A-Z]?(?:\.\d+)*)\s*([ ,(]{0,4})((?:[A-Z]{1,3}-\d{1,3})(?:\s*(?:[,…]{1,2}\s*(?:[A-Z]{1,3}-\d{1,3}))*)?)/u';
$idRe = '/([A-Z]{1,3}-\d{1,3})/u';

$counts = ['checked' => 0, 'NO-SECTION' => 0, 'NO-ID' => 0, 'WRONG-LOCATION' => 0];
$flags = [];
$convention = 0;

foreach ($blockTexts as $text) {
    if (! str_contains($text, '§')) {
        continue;
    }

    if (! preg_match_all($refRe, $text, $refs, PREG_SET_ORDER)) {
        // No finding id beside any §num here, so the only claim to test is that the section exists.
        preg_match_all('/§\s*(\d+[A-Z]?(?:\.\d+)*)/u', $text, $only);
        foreach ($only[1] ?? [] as $sec) {
            $counts['checked']++;
            if ($sectionExists($sec) || $namesAnotherDocument($text)) {
                continue;
            }
            $counts['NO-SECTION']++;
            $flags[] = ['NO-SECTION', $sec, null, $text];
        }

        continue;
    }

    foreach ($refs as $r) {
        $sec = $r[1];
        $sep = $r[2];
        $counts['checked']++;

        $chapter = explode('.', $sec)[0];
        if (! $sectionExists($sec) && ! $sectionExists($chapter)) {
            if ($namesAnotherDocument($text)) {
                continue;
            }
            $counts['NO-SECTION']++;
            $flags[] = ['NO-SECTION', $sec, null, $text];

            continue;
        }

        preg_match_all($idRe, $r[3], $ids);
        foreach ($ids[0] ?? [] as $id) {
            if (! isset($rowsById[$id])) {
                $counts['NO-ID']++;
                $flags[] = ['NO-ID', $sec, $id, $text];

                continue;
            }
            if (str_contains($sep, ',')) {
                $convention++;

                continue;      // behaviour-then-finding: the row is allowed to live elsewhere
            }

            $exact = array_filter($rowsById[$id], fn ($h) => $h['section'] === $sec);
            $sameChapter = array_filter($rowsById[$id], fn ($h) => $h['chapter'] === $chapter);
            // §19.3 is titled "Workflow step labels are off by one (N-2)": a section may take the finding's
            // name into its own heading, and then "§19.3 (N-2)" is the most exact citation there is. Without
            // this rule the sweep reported defects in a sentence that also says "N-2 in 28.3" three words on.
            $titled = isset($sections[$sec]) && preg_match('/\b'.preg_quote($id, '/').'\b/u', $sections[$sec]['title']) === 1;
            if ($sameChapter === [] && ! $titled) {
                $counts['WRONG-LOCATION']++;
                $where = implode(', ', array_map(fn ($h) => $h['section'].' '.$h['text'], $rowsById[$id]));
                $flags[] = ['WRONG-LOCATION', $sec, $id, $text.'  → the row sits at '.$where];
            } elseif (! $titled && $exact === [] && isset($sectionsWithRows[$sec])) {
                $counts['WRONG-LOCATION']++;
                $where = implode(', ', array_map(fn ($h) => $h['section'].' '.$h['text'], $sameChapter));
                $flags[] = ['WRONG-LOCATION', $sec, $id, $text.'  → cited as a register subsection, but the row sits at '.$where];
            }
        }
    }
}

printf("sections known: %d | finding rows indexed: %d | §-references checked: %d\n",
    count($sections), count($rowsById), $counts['checked']);

foreach ($flags as [$kind, $sec, $id, $text]) {
    printf("\n%s  §%s%s\n    %.220s\n", $kind, $sec, $id === null ? '' : ' '.$id, $text);
}

$bad = $counts['NO-SECTION'] + $counts['NO-ID'] + $counts['WRONG-LOCATION'];
printf("\n%d reference(s) checked — %d problem(s): NO-SECTION %d, NO-ID %d, WRONG-LOCATION %d"
    ." (%d comma-form citations accepted as the document's behaviour-then-finding convention)\n",
    $counts['checked'], $bad, $counts['NO-SECTION'], $counts['NO-ID'], $counts['WRONG-LOCATION'], $convention);

if ($verbose) {
    echo "\nknown sections:\n";
    foreach ($sections as $num => $s) {
        printf("  §%-9s %s\n", $num, mb_substr($s['title'], 0, 60));
    }
    echo "\nfinding ids and where they sit:\n";
    foreach ($rowsById as $id => $hs) {
        printf("  %-6s %s\n", $id, implode(' | ', array_map(fn ($h) => $h['section'].' '.$h['text'], $hs)));
    }
}

exit($bad === 0 ? 0 : 1);
