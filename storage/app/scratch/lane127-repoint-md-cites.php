<?php

/**
 * Which markdown pointers into a file this batch moved, and where each one lands now.
 *
 * `lane76-shift-cites.php` re-points the authority docx and nothing else, and `repo-file-line-citations.php`
 * refuses only a line past the end or a blank one — so a pointer left three rows short by an inserted block keeps
 * landing on live code in the right file and reports clean (lane 88's defect, met again). This reads the two
 * versions of each touched file and matches each cited line by its own bytes, which is the only way a re-point is a
 * measurement rather than arithmetic. It writes nothing.
 *
 * usage: php storage/app/scratch/lane127-repoint-md-cites.php <relative/file> [...]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$touched = array_values(array_filter(array_slice($argv ?? [], 1), fn ($a) => ! str_starts_with($a, '--')));
if ($touched === []) {
    fwrite(STDERR, "REFUSED: name at least one file this batch moved; the tool matches cited lines against its bytes.\n");

    exit(1);
}

// The prose the citation gate reads, widened the same way the gate is widened.
$docs = [
    'AGENTS.md',
    'DEPLOYMENT.md',
    'Documentation/Per-Office-UAT-Walkthrough.md',
    'Documentation/Demo-Day-Guide.md',
    'Documentation/Offline-Presentation-Guide.md',
    'Documentation/Prompt-Requirement-Matrix-2026-10-26.md',
    'Documentation/Prompt-Completion-Report-2026-10-27.md',
    'Documentation/Registrar-Window-Packet-2026-10-26.md',
    'Documentation/System-Design-Quality-Review.md',
    'Documentation/UI-Workflow-Conformance-Audit-2026-09-17.md',
    'Documentation/UI-UX-Refinement-Task-Analysis-2026-09-17.md',
];

/** HEAD's copy of a file, exported to a scratch path because shell_exec cannot reach git from PHP on this box. */
$headExport = function (string $rel): ?string {
    $out = 'storage/app/scratch/lane127-head-'.preg_replace('/[^\w.]/', '_', basename($rel));
    exec('git show HEAD:'.escapeshellarg($rel).' > '.escapeshellarg($out).' 2>&1', $lines, $rc);
    if ($rc !== 0 || ! is_file($out)) {
        return null;
    }

    return (string) file_get_contents($out);
};

$citeRe = '/\b((?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js)):(\d+)(?:-(\d+))?/';

$byBasename = [];
foreach ($touched as $rel) {
    $byBasename[basename($rel)] = $rel;
}

$reported = 0;
$moved = 0;
foreach ($docs as $doc) {
    if (! is_file($root.'/'.$doc)) {
        continue;
    }
    $text = (string) file_get_contents($root.'/'.$doc);
    preg_match_all($citeRe, $text, $m, PREG_SET_ORDER);
    foreach ($m as $x) {
        [$name, $from, $to] = [$x[1], (int) $x[2], ($x[3] ?? '') === '' ? (int) $x[2] : (int) $x[3]];
        $rel = $byBasename[basename($name)] ?? null;
        if ($rel === null) {
            continue;
        }
        if (! is_file($root.'/'.$rel)) {
            printf("  NO FILE       %s:%d-%d cited in %s\n", $name, $from, $to, $doc);
            $reported++;

            continue;
        }
        $headBody = $headExport($rel);
        if ($headBody === null) {
            printf("  NO HEAD       %s — the export failed, so nothing can be compared\n", $rel);
            $reported++;

            continue;
        }
        $headLines = explode("\n", $headBody);
        $liveLines = explode("\n", (string) file_get_contents($root.'/'.$rel));

        $needle = trim($headLines[$from - 1] ?? '');
        if ($needle === '') {
            printf("  HEAD BLANK    %s:%d — HEAD itself has nothing on that row, so the pointer predates this batch\n", $name, $from);
            $reported++;

            continue;
        }
        $hits = [];
        foreach ($liveLines as $i => $line) {
            if (trim($line) === $needle) {
                $hits[] = $i + 1;
            }
        }
        $span = $to > $from ? "-{$to}" : '';
        if ($hits === []) {
            printf("  GONE          %-58s %s:%d%s  (in %s)  HEAD text: %s\n", '', $name, $from, $span, $doc, substr($needle, 0, 70));
            $reported++;
        } elseif (count($hits) === 1 && $hits[0] !== $from) {
            printf("  MOVED         %-58s %s:%d%s -> %d%s  (in %s)\n", substr($needle, 0, 56), $name, $from, $span, $hits[0], $to > $from ? '-'.($hits[0] + ($to - $from)) : '', $doc);
            $moved++;
            $reported++;
        } elseif (count($hits) === 1) {
            // unchanged: the cited row still holds the same bytes
        } else {
            printf("  AMBIGUOUS     %-58s %s:%d%s matches %d rows (%s) in %s\n", substr($needle, 0, 56), $name, $from, $span, count($hits), implode(',', array_slice($hits, 0, 6)), $doc);
            $reported++;
        }
    }
}

printf("\n%d pointer(s) reported across %d touched file(s); %d moved cleanly, %d need a reader\n",
    $reported, count($touched), $moved, $reported - $moved);

exit(0);
