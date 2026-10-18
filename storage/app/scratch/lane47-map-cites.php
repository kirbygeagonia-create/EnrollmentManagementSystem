<?php

/**
 * Lane 47 — map every citation in the document that points into a file this batch edited, using the diff's own
 * hunk boundaries rather than a single shift per file.
 *
 * Why not one shift: this batch both inserted rows (comments, aggregates) and rewrote them, so a citation whose
 * range straddles an edit keeps its start and moves its end. `git diff -U0` states exactly where each hunk
 * begins and how wide it grew, which turns any old line into its new line piecewise. A citation is then judged
 * by content: if the old block still appears verbatim at the mapped lines, the number moves; if the block was
 * changed by the edit, the claim has to be re-read by a human, and this script says which ones.
 *
 * The bare continuations are included — the document writes `File.php:584-594 , :598-617`, and a scan for
 * `File.php:NN` alone leaves the companion pointing at the old file.
 *
 * Read-only. --apply rewrites document.xml with the mapping it proved.
 *
 * Usage: php storage/app/scratch/lane47-map-cites.php [--apply]
 */

$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);

$files = [];
foreach (explode("\n", (string) shell_exec('cd /d '.escapeshellarg($root).' && git diff --name-only -- app 2>nul')) as $line) {
    $line = trim(str_replace('\\', '/', $line));
    if ($line !== '' && str_ends_with($line, '.php')) {
        $files[] = $line;
    }
}
if ($files === []) {
    fwrite(STDERR, "FATAL: git diff sees no edited files under app/ — is the batch already committed?\n");
    exit(1);
}

$doc = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');

// Citations the document wrote today, describing code this batch added. They exist only in the working tree,
// so mapping them against HEAD is a category error; the sweep at the end is what proves they land on real lines.
$authoredToday = [
    'EvaluationController.php:103-121',
    'BlockingController.php:68-79',
    'UserManagementController.php:508-527',
];

// One citation names a method that grew rather than moved: §10.5 cites the whole of EvaluationController::index
// as the queue's only filter. Its first line is unchanged and its last is 39 rows lower, so the range is
// re-pointed at the query that states the claim — measured today at :85-94 — not slid by arithmetic.
$pinned = [
    'EvaluationController.php:70-116' => ['to' => 'EvaluationController.php:85-94', 'why' => 'the method grew; the filter line is the claim'],
];

$replacements = [];
$manual = [];
$review = [];
$total = 0;

foreach ($files as $path) {
    $name = basename($path);
    $oldRaw = (string) shell_exec('cd /d '.escapeshellarg($root).' && git show HEAD:'.escapeshellarg($path).' 2>nul');
    // Normalise line endings on both sides: git answers with the stored LF text while the working tree is a
    // Windows checkout with CRLF, and an unnormalised comparison calls every citation "changed in place" —
    // which reads as 30 citations needing a human when the answer is a byte that renders as nothing.
    $old = explode("\n", str_replace("\r", '', $oldRaw));
    $newText = str_replace("\r", '', (string) file_get_contents($root.'/'.$path));
    $new = explode("\n", $newText);

    // hunk list: [oldStart(1-based), oldCount, newStart(1-based), newCount]
    $hunks = [];
    preg_match_all('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/m',
        (string) shell_exec('cd /d '.escapeshellarg($root).' && git diff -U0 -- '.escapeshellarg($path).' 2>nul'),
        $h, PREG_SET_ORDER);
    foreach ($h as $x) {
        $hunks[] = [(int) $x[1], (int) ($x[2] ?? 1), (int) $x[3], (int) ($x[4] ?? 1)];
    }

    /** Old line -> new line, piecewise across the hunks. */
    $map = function (int $line) use ($hunks): array {
        $delta = 0;
        foreach ($hunks as [$os, $oc, $ns, $nc]) {
            if ($line < $os) {
                break;
            }
            if ($line < $os + $oc) {
                // inside a changed span: the start maps to the hunk's new start; anything past it is uncertain
                return [$ns + ($line - $os), $delta + ($nc - $oc), $line - $os < $nc];
            }
            $delta += $nc - $oc;
        }

        return [$line + $delta, $delta, true];
    };

    preg_match_all('/'.preg_quote($name, '/').':(\d+)(?:-(\d+))?([^§\n]{0,140})/', $doc, $hits, PREG_SET_ORDER);
    if ($hits === []) {
        continue;
    }

    $cited = [];
    foreach ($hits as $h2) {
        $cited[] = [(int) $h2[1], ($h2[2] ?? '') !== '' ? (int) $h2[2] : null, false];
        // the tail is scanned for bare continuations only up to the next file name, because the document
        // writes `A.php:584-594 , :598-617` — and also `A.php:103-121; B.php:68-79`, where B's number is a
        // citation of its own and must not be read as a continuation of A
        $tail = substr($h2[3], 0, 140);
        if (preg_match('/[A-Z]\w*\.php:/', $tail, $cut, PREG_OFFSET_CAPTURE) && $cut[0][1] > 0) {
            $tail = substr($tail, 0, $cut[0][1]);
        }
        if (preg_match_all('/(?<![\w.]): ?(\d+)(?:-(\d+))?/', $tail, $bare, PREG_SET_ORDER)) {
            foreach ($bare as $b) {
                if ((int) $b[1] > 0) {
                    $cited[] = [(int) $b[1], ($b[2] ?? '') !== '' ? (int) $b[2] : null, true];
                }
            }
        }
    }

    $seen = [];
    foreach ($cited as [$start, $end, $isBare]) {
        $key = $start.'-'.$end;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $total++;
        $stop = $end ?? $start;
        $label = $name.':'.$start.($end !== null ? '-'.$end : '');

        if (in_array($label, $authoredToday, true)) {
            printf("%-34s written today — describes new code, left as is\n", $label);

            continue;
        }
        if (isset($pinned[$label])) {
            $replacements[$label] = $pinned[$label]['to'];
            printf("%-34s -> %-30s re-read by hand: %s\n", $label, $pinned[$label]['to'], $pinned[$label]['why']);

            continue;
        }

        $block = implode("\n", array_slice($old, $start - 1, $stop - $start + 1));

        // A citation that already pointed at a blank line in HEAD is not this batch's drift and must not be
        // "fixed" by arithmetic: nothing moves a claim that was never on a line. It is listed for a reader.
        if (trim($old[$start - 1]) === '') {
            $review[] = sprintf('%-34s%s already cited a blank line at HEAD — pre-existing drift, not this batch',
                $label, $isBare ? ' (bare)' : '');
            printf("  REVIEW %s\n", end($review));

            continue;
        }

        // The hunk arithmetic is a candidate, never a verdict: this batch both inserted rows and rewrote them,
        // so a net delta per file is wrong for a range that straddles an edit and the removals do not cancel
        // the additions where a particular citation sits. What decides is whether the cited text is sitting at
        // the position claimed — and if not, whether it is sitting somewhere else, found by content.
        [$newStart, $delta, $clean] = $map($start);
        [$newStop] = $map($stop);
        if ($newStop < $newStart) {
            $newStop = $newStart;
        }
        $landed = implode("\n", array_slice($new, $newStart - 1, $newStop - $newStart + 1));

        if ($block !== $landed) {
            $pos = strpos($newText, $block);
            $times = $pos === false ? 0 : substr_count($newText, $block);
            if ($times !== 1) {
                $manual[] = sprintf('%-34s matched %d time(s) in the new file — re-read the sentence, do not move the number',
                    $label, $times);
                printf("  MANUAL %s\n", end($manual));

                continue;
            }
            $newStart = substr_count(substr($newText, 0, $pos), "\n") + 1;
            $newStop = $end === null ? $newStart : $newStart + ($stop - $start);
            $delta = $newStart - $start;
            $clean = true;
        }

        $to = $name.':'.$newStart.($end !== null ? '-'.$newStop : '');

        // Checked before the "it did not move" shortcut: a citation that lands on a blank line is a false
        // pointer whether or not this batch moved it, and skipping unchanged numbers is how a sweep finding
        // survives the very tool sent to clear it.
        if (! $clean || trim($new[$newStart - 1] ?? '') === '') {
            $manual[] = sprintf('%-34s -> %-24s lands on a blank line or inside a deleted span — re-read it', $label, $to);
            printf("  MANUAL %s\n", end($manual));

            continue;
        }
        if ($to === $label) {
            continue;
        }

        $replacements[$label] = $to;
        printf("%-34s -> %-24s (%+d) content verified identical\n", $label, $to, $delta);
        printf("     opens on: %s\n", trim($new[$newStart - 1] ?? '?'));
    }
}

printf("\n%d citation(s) into edited files; %d rewritten by content; %d unresolved; %d already drifting at HEAD.\n",
    $total, count($replacements), count($manual), count($review));
foreach ($manual as $m) {
    echo "  MANUAL  ".$m."\n";
}
foreach ($review as $r) {
    echo "  REVIEW  ".$r."\n";
}
if ($manual !== [] || $review !== []) {
    echo "\nNOTE — the numbers above are left untouched. A bare continuation (`, :272`) is only as trustworthy as\n"
        ."      the file name it follows, and lane 32 already found one whose nearest range belonged to another\n"
        ."      file: moving it would corrupt a claim. The line sweep is run again afterwards, and anything still\n"
        ."      landing on a blank line is re-read one sentence at a time.\n";
}
if (! $apply) {
    echo "\nDRY RUN — pass --apply to write the docx\n";
    exit(0);
}

// Longest first: `File.php:559` is a prefix of `File.php:559-587`, and rewriting the short form first would
// leave the long form's tail stale and unmatchable.
uksort($replacements, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$original = $xml;

$misses = [];
foreach ($replacements as $from => $to) {
    $count = substr_count($xml, $from);
    if ($count === 0) {
        $misses[] = $from;

        continue;
    }
    $xml = str_replace($from, $to, $xml);
}

libxml_use_internal_errors(true);
$doc2 = new DOMDocument;
if ($doc2->loadXML($xml) !== true) {
    fwrite(STDERR, "document.xml no longer parses — nothing written\n");
    exit(1);
}

copy($root.'/Documentation/EMS Complete Documentation.docx', $root.'/storage/app/scratch/docx-backup-2026-10-18-pass144b.docx');
$z1 = new ZipArchive();
$z1->open($root.'/Documentation/EMS Complete Documentation.docx', ZipArchive::CREATE);
$z1->addFromString('word/document.xml', $xml);
$z1->close();

$z2 = new ZipArchive();
$z2->open($root.'/Documentation/EMS Complete Documentation.docx');
$onDisk = $z2->getFromName('word/document.xml');
$parts = count($z2);
$z2->close();
if ($onDisk !== $xml) {
    fwrite(STDERR, "the bytes on disk are not the rewritten document.xml — restore the backup\n");
    exit(1);
}

printf("WROTE %d citation(s), %d bytes changed, %d parts%s\n", count($replacements), strlen($xml) - strlen($original), $parts,
    $misses === [] ? '' : ' | NOT FOUND AS SINGLE RUNS: '.implode(', ', $misses));
