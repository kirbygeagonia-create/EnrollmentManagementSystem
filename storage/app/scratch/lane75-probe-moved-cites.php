<?php

/**
 * Where does every citation into one moved file actually land now?
 *
 * A batch that inserts lines above a cited construct leaves the old number pointing at live code
 * that belongs to something else — which is the one defect shape a bounds sweep cannot see, because
 * the line still exists. This probe answers it by bytes: it takes the text HEAD held at each cited
 * range, finds that same text in the working copy, and prints the range that carries it now — plus
 * what the OLD number now reads, so a silent mis-target is visible instead of merely plausible.
 *
 * Usage: php storage/app/scratch/lane75-probe-moved-cites.php --file=RegistrarController.php \
 *            --head=storage/app/scratch/lane75-head-RegistrarController.php \
 *            --live=app/Http/Controllers/Registrar/RegistrarController.php
 *
 * It refuses an unknown flag by name and refuses a file with no citations: a run that read nothing
 * must never print like a clean one.
 */
$root = dirname(__DIR__, 3);

$args = [];
$known = ['file', 'head', 'live', 'dump'];
foreach (array_slice($argv, 1) as $arg) {
    if (! preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/', $arg, $m)) {
        fwrite(STDERR, "REFUSED: unrecognised argument {$arg}; this tool reads only --".implode('=', $known).".\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: --{$m[1]} is not a flag this tool reads (known: --".implode(', --', $known).").\n");
        exit(1);
    }
    $args[$m[1]] = $m[2] ?? true;
}

foreach (['file', 'head', 'live'] as $required) {
    if (! isset($args[$required]) || $args[$required] === true) {
        fwrite(STDERR, "REFUSED: --{$required}= is required.\n");
        exit(1);
    }
}

$dumpPath = $root.'/storage/app/scratch/'.($args['dump'] ?? 'docx-flat.txt');
$dump = (string) file_get_contents($dumpPath);
$headPath = $root.'/'.$args['head'];
$livePath = $root.'/'.$args['live'];

if (trim($dump) === '') {
    fwrite(STDERR, "REFUSED: the dump at {$dumpPath} is empty — re-run dump-docx-flat.php.\n");
    exit(1);
}

$split = static fn (string $p): array => explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($p)));
$head = $split($headPath);
$live = $split($livePath);
if (count($head) < 2 || count($live) < 2) {
    fwrite(STDERR, "REFUSED: could not read both versions (head ".count($head)." lines, live ".count($live)." lines).\n");
    exit(1);
}

$file = (string) $args['file'];
preg_match_all('/'.preg_quote($file, '/').':(\d+)(?:-(\d+))?/', $dump, $all, PREG_SET_ORDER);

$ranges = [];
foreach ($all as $m) {
    $from = (int) $m[1];
    $to = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $from;
    $ranges[$from.'-'.$to] = [$from, $to, substr_count($dump, $m[0])];
}

if ($ranges === []) {
    fwrite(STDERR, "REFUSED: the dump cites no range in {$file} — there is nothing to re-point, which is not the same as a clean run.\n");
    exit(1);
}

echo 'citations into '.$file.': '.count($ranges)." distinct range(s)\n";

$moved = 0;
$stays = 0;
$problems = 0;
$liveText = implode("\n", $live);
foreach ($ranges as [$from, $to, $copies]) {
    $windowRaw = array_slice($head, $from - 1, $to - $from + 1);
    $windowTrimmed = array_values(array_filter(array_map('trim', $windowRaw), fn (string $l) => $l !== ''));
    if ($windowTrimmed === []) {
        echo sprintf("BLANK  :%d-%d  HEAD held no text there at all, so the pointer was never true\n", $from, $to);
        $problems++;

        continue;
    }

    // Searched as a WINDOW OF LINES, not as a substring of the joined text: the first version of this
    // probe searched `"\n".$needle."\n"` and counted newlines before the match, which reported every
    // delta one row too high — a probe that is confidently wrong by one is the exact defect class this
    // repository keeps refusing to leave in a citation.
    $needle = array_slice($head, $from - 1, $to - $from + 1);
    $height = count($needle);
    $hits = [];
    for ($i = 0; $i + $height <= count($live); $i++) {
        if (array_slice($live, $i, $height) === $needle) {
            $hits[] = $i + 1;
        }
    }

    if (count($hits) === 0) {
        echo sprintf("GONE   :%d-%d  (%d cop%s) the text HEAD held there is not in the working file\n", $from, $to, $copies, $copies === 1 ? 'y' : 'ies');
        $problems++;
        continue;
    }

    $shifted = array_values(array_unique(array_map(fn (int $h) => $h - $from, $hits)));
    if (count($hits) > 1 && count($shifted) > 1) {
        echo sprintf("AMBIG  :%d-%d  matched at %s — resolve by reading the sentence\n", $from, $to, implode(', ', $hits));
        $problems++;

        continue;
    }

    $newFrom = $hits[0];
    $newTo = $newFrom + ($to - $from);
    $liveAtOld = array_map('trim', array_slice($live, $from - 1, $to - $from + 1));
    $liveAtOld = array_values(array_filter($liveAtOld, fn (string $l) => $l !== ''));

    if ($newFrom === $from) {
        echo sprintf("stay   :%d-%d  (%d cop%s)\n", $from, $to, $copies, $copies === 1 ? 'y' : 'ies');
        $stays++;

        continue;
    }

    $moved++;
    echo sprintf("MOVE   :%d-%d -> :%d-%d  (%d cop%s)  delta %+d\n", $from, $to, $newFrom, $newTo, $copies, $copies === 1 ? 'y' : 'ies', $newFrom - $from);
    echo '       lands on now: '.substr(trim(implode(' | ', $liveAtOld)), 0, 150)."\n";
}

echo sprintf("\n%d moved, %d stay, %d need reading. The bounds sweep cannot see any of the MOVE rows.\n", $moved, $stays, $problems);
exit($moved > 0 || $problems > 0 ? 1 : 0);
