<?php

/**
 * Lane 53 — every citation this batch's code edits invalidated, and the shift each one needs.
 *
 * `lane46-probe-exam-cites.php` proved the method for one controller with a HEAD copy written by hand; this is
 * the same test with the HEAD copy taken from git itself, so it can be run over any file a batch touched.
 * `shell_exec` cannot reach git from PHP on this box (measured in lane 50), so HEAD is exported first.
 *
 * Three questions per file:
 *  1. Which ranges does the corpus cite, including the BARE continuations (`:267-289`) that name no file?
 *  2. For each, which shift leaves every cited line byte-identical between HEAD and the working copy? A shift
 *     found this way is a measurement; arithmetic over hunk headers is a guess, and lane 51's probe that kept
 *     the first byte-identical offset mis-moved eight pointers.
 *  3. Is that shift unique? Two ranges that both match at +6 and +7 are reported AMBIGUOUS and go into the
 *     reading queue — a pointer moved on a coin flip is a false citation with the confidence of a true one.
 *
 * Read-only.
 *
 *   php storage/app/scratch/lane53-probe-cite-shift.php [File.php ...]
 */

$root = dirname(__DIR__, 3);
$mysqlDump = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe';

$targets = array_values(array_filter(array_slice($argv, 1), static fn ($a) => ! str_starts_with($a, '--')));

if ($targets === []) {
    $targets = ['tools/seed_full_demo_pipeline.php', 'app/Console/Commands/AuditRecordStates.php'];
}

$corpus = [];

foreach (['Documentation/EMS Complete Documentation.docx', 'AGENTS.md', 'DEPLOYMENT.md',
    'Documentation/Per-Office-UAT-Walkthrough.md', 'Documentation/Demo-Day-Guide.md',
    'Documentation/Offline-Presentation-Guide.md'] as $doc) {
    $path = $root.'/'.$doc;

    if (! is_file($path)) {
        fwrite(STDERR, "REFUSE: the corpus names a missing file: $doc\n");

        exit(1);
    }

    if (str_ends_with($path, '.docx')) {
        $dump = $root.'/storage/app/scratch/lane53-head-docx.xml';
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            fwrite(STDERR, "REFUSE: cannot open $doc\n");

            exit(1);
        }

        file_put_contents($dump, (string) $zip->getFromName('word/document.xml'));
        $zip->close();
        $corpus[$doc] = (string) file_get_contents($dump);

        continue;
    }

    $corpus[$doc] = (string) file_get_contents($path);
}

$queue = [];
$pointers = 0;

foreach ($targets as $file) {
    $workPath = $root.'/'.$file;

    if (! is_file($workPath)) {
        fwrite(STDERR, "REFUSE: no working copy of $file\n");

        exit(1);
    }

    // HEAD's copy must be exported by the caller, because `shell_exec` cannot reach git from PHP on this box
    // (measured in lane 50) and a probe that shells out for content and receives an empty string prints the
    // same clean verdict a real measurement would.
    $headPath = $root.'/storage/app/scratch/lane53-head-'.str_replace('/', '-', $file);

    if (! is_file($headPath) || trim((string) file_get_contents($headPath)) === '') {
        fwrite(STDERR, "REFUSE: no HEAD export at ".basename($headPath)."\n  Run: git show HEAD:$file > storage/app/scratch/".basename($headPath)."\n");

        exit(1);
    }

    $head = explode("\n", rtrim((string) file_get_contents($headPath), "\n"));
    $work = explode("\n", rtrim((string) file_get_contents($workPath), "\n"));
    $basename = basename($file);

    if (count($work) === count($head)) {
        printf("=== %s — %d lines, unchanged length\n", $file, count($work));
    } else {
        printf("=== %s — HEAD %d lines → working %d lines (%+d)\n", $file, count($head), count($work), count($work) - count($head));
    }

    // Every citation, including bare continuations: a `File.php:NN` scan misses `the two box rules at :267-289`,
    // and a global replace on the bare number would move a number belonging to a DIFFERENT file in the same
    // sentence. So a bare range is joined to the nearest preceding file name in the same window.
    foreach ($corpus as $doc => $text) {
        preg_match_all('/([A-Za-z0-9_.\-\/]*?\.(?:php|jsx|js|py|blade\.php))?:(\d+)(?:-(\d+))?/', $text, $hits, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($hits as $h) {
            $named = $h[1][0];
            $at = $h[0][1];
            $start = (int) $h[2][0];
            $end = (isset($h[3][0]) && $h[3][0] !== '') ? (int) $h[3][0] : $start;

            if ($named === '') {
                // A bare `:NN` — attribute it only to the file this probe is measuring, and only when that
                // file's name appears shortly BEFORE it in the same sentence (never after: that is the
                // attribution rule the document already follows for `:748-756 builds each line from …`).
                $window = substr($text, max(0, $at - 260), $at > 260 ? 260 : $at);

                if (! str_contains($window, $basename)) {
                    continue;
                }
            } elseif (! str_ends_with($named, $basename)) {
                continue;
            }

            if ($end < 20 || $start > 4000 || $end < $start) {
                continue; // years, section numbers, page counts
            }

            // Which shift makes every cited HEAD line byte-equal to the working line at that offset?
            $candidates = [];

            for ($s = -40; $s <= 200; $s++) {
                $ok = true;

                for ($i = $start; $i <= min($end, $start + 40); $i++) {
                    $wi = $i + $s;

                    if (! isset($head[$i - 1]) || ! isset($work[$wi - 1])) {
                        $ok = false;

                        break;
                    }

                    if ($head[$i - 1] !== $work[$wi - 1]) {
                        $ok = false;

                        break;
                    }
                }

                if ($ok) {
                    $candidates[] = $s;
                }
            }

            $who = $named === '' ? 'bare '.$basename : $named;
            $pointers++;

            if ($candidates === []) {
                printf("  NO SHIFT  %-34s %s:%d-%d  — the cited text itself moved; re-read it (%s)\n", $doc, $who, $start, $end, implode(',', array_slice($head, max(0, $start - 1), 1)));
                $queue[] = "$doc  $who:$start-$end  NO SHIFT";

                continue;
            }

            if (count($candidates) > 1) {
                printf("  AMBIGUOUS %-34s %s:%d-%d  shifts match at [%s] — a byte-blind span, read the sentence\n", $doc, $who, $start, $end, implode(',', $candidates));
                $queue[] = "$doc  $who:$start-$end  AMBIGUOUS [".implode(',', $candidates).']';

                continue;
            }

            $s = $candidates[0];
            printf("  %-10s %-34s %s:%d-%d → :%d-%d\n", $s === 0 ? 'STAYS' : 'MOVE', $doc, $who, $start, $end, $start + $s, $end + $s);
        }
    }
}

printf("\n%d pointer(s) judged, %d in the reading queue.\n", $pointers, count($queue));

foreach ($queue as $q) {
    echo "  queue: $q\n";
}

if (str_contains(implode(' ', $argv), '--exit-nonzero-on-queue')) {
    exit($queue === [] ? 0 : 1);
}
