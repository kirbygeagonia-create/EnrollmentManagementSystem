<?php
/**
 * Lane 107: re-take the per-class figures this batch made stale, FROM the delivered run's junit.
 *
 * The matrix's G40 cell and two AGENTS.md bullets each state a case count and an assertion count beside a class
 * name. This batch added a sequence case and a payload-leak case to three of those classes and removed two
 * assertions from two of them, so every one of those figures is now wrong — and a figure typed from memory is how
 * CI's `lane58` went red before. The new numbers are therefore READ from the junit of the run that produced them
 * and never typed here.
 *
 * Refusals, each named: a junit without one of the classes (it is not a whole-suite run), a needle that does not
 * match exactly as many times as stated, a replacement byte-identical to its needle, and any write that would
 * change a file's line count — a markdown table row is ONE line, and a wrapped row is a lost row.
 *
 * Usage: php storage/app/scratch/lane107-retake-class-figures.php --junit=<path> [--apply]
 */
$root = dirname(__DIR__, 3);

$junit = null;
$apply = false;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--junit=')) {
        $junit = substr($a, 8);
    } elseif ($a === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: this script reads --junit=<path> and --apply, got {$a}.\n");

        exit(1);
    }
}
if ($junit === null) {
    fwrite(STDERR, "REFUSED: --junit= is required — a figure read from no run is a figure typed from intent.\n");

    exit(1);
}
if (! is_file($root.'/'.$junit)) {
    fwrite(STDERR, "REFUSED: no junit at {$junit}.\n");

    exit(1);
}

$x = simplexml_load_file($root.'/'.$junit);
if ($x === false) {
    fwrite(STDERR, "REFUSED: the junit did not parse.\n");

    exit(1);
}

$figures = [];
$walk = static function ($node) use (&$walk, &$figures): void {
    foreach ($node->children() as $key => $c) {
        if ($key === 'testsuite') {
            $name = (string) $c['name'];
            if (str_contains($name, 'SortTest')) {
                // PHPUnit writes the class suite as `Tests\Feature\Sorting\AssessmentQueueSortTest`; the documents
                // name the FILE, so the key is the last namespace segment plus `.php`. The first cut of this script
                // omitted the suffix and therefore refused its own whole-suite junit.
                $leaf = basename(str_replace('\\', '/', $name));
                $figures[$leaf.'.php'] = [
                    'tests' => (int) $c['tests'],
                    'assertions' => (int) $c['assertions'],
                ];
            }
            $walk($c);
        } elseif ($key === 'testsuites') {
            $walk($c);
        }
    }
};
$walk($x);

$classes = [
    'AssessmentQueueSortTest.php',
    'AccountingQueueSortTest.php',
    'RegistrarQueueSortTest.php',
    'ClearanceQueueSortTest.php',
    'ClinicQueueSortTest.php',
    'IDQueueSortTest.php',
    'StudentsDirectorySortTest.php',
    'AdmissionQueueSortTest.php',
];
$missing = array_values(array_diff($classes, array_keys($figures)));
if ($missing !== []) {
    fwrite(STDERR, 'REFUSED: the junit answers no figure for '.implode(', ', $missing).' — it is not the whole-suite junit.'."\n");

    exit(1);
}

echo 'read from the delivered run:'."\n";
foreach ($classes as $c) {
    printf("  %-32s %d cases / %d assertions\n", $c, $figures[$c]['tests'], $figures[$c]['assertions']);
}

/**
 * [file, needle, class, expected needle count]
 *
 * The needles are the CURRENT spelling in each document and they age every time a figure is re-taken, so this list
 * is re-cut per batch rather than left as a memory of the last one — the first version of this file named the 2026-10-29
 * numbers after the 2026-10-30 run had already replaced them, and its exact-count guard is what made that a loud
 * refusal rather than a silent no-op.
 */
$edits = [
    ['Documentation/Prompt-Requirement-Matrix-2026-10-26.md', 'AssessmentQueueSortTest.php` answers 11 cases / 44 assertions', 'AssessmentQueueSortTest.php', 1],
    ['Documentation/Prompt-Requirement-Matrix-2026-10-26.md', 'AccountingQueueSortTest.php` answers 12 cases / 47 assertions', 'AccountingQueueSortTest.php', 1],
    ['Documentation/Prompt-Requirement-Matrix-2026-10-26.md', 'AdmissionQueueSortTest.php` 5 cases / 17 assertions', 'AdmissionQueueSortTest.php', 1],
    ['Documentation/Prompt-Requirement-Matrix-2026-10-26.md', 'ClearanceQueueSortTest.php` answers 9 cases / 32 assertions', 'ClearanceQueueSortTest.php', 1],
    ['Documentation/Prompt-Requirement-Matrix-2026-10-26.md', 'IDQueueSortTest.php` (11 cases / 41 assertions)', 'IDQueueSortTest.php', 1],
    ['AGENTS.md', 'RegistrarQueueSortTest.php` (8 cases, 31 assertions)', 'RegistrarQueueSortTest.php', 1],
    ['AGENTS.md', 'delivered run at 8 cases / 31 assertions', 'RegistrarQueueSortTest.php', 1],
    ['AGENTS.md', 'AdmissionQueueSortTest.php` (5 cases / 17 assertions)', 'AdmissionQueueSortTest.php', 1],
    ['AGENTS.md', 'ClinicQueueSortTest.php` (11 cases / 36 assertions)', 'ClinicQueueSortTest.php', 1],
    ['AGENTS.md', 'IDQueueSortTest.php` (11 cases / 41 assertions)', 'IDQueueSortTest.php', 2],
];

$failures = [];
$skipped = [];
$plan = [];
$byFile = [];
foreach ($edits as [$file, $needle, $class, $want]) {
    $path = $root.'/'.$file;
    if (! is_file($path)) {
        $failures[] = "no file {$file}";
        continue;
    }
    $bytes = (string) file_get_contents($path);
    $hits = substr_count($bytes, $needle);
    if ($hits !== $want) {
        $failures[] = sprintf('%s: needle matched %d time(s), wanted %d — %s', $file, $hits, $want, $needle);
        continue;
    }
    $new = preg_replace(
        '/\d+ cases( \/ |, )\d+ assertions/',
        $figures[$class]['tests'].' cases${1}'.$figures[$class]['assertions'].' assertions',
        $needle
    );
    if ($new === $needle) {
        // The document already carries the delivered figure for this class. That is a SKIP, not a refusal: one
        // batch moves four classes and leaves three untouched, and a tool that aborts the whole run because some
        // figures are already true is a tool that cannot be used to check that they are.
        $skipped[] = "{$file}: already states {$class} ".$figures[$class]['tests'].'/'.$figures[$class]['assertions'];
        continue;
    }
    $plan[] = ['file' => $file, 'from' => $needle, 'to' => $new, 'class' => $class];
    $byFile[$file][] = [$needle, $new];
}

if ($failures !== []) {
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}
if ($plan === []) {
    // Nothing to write is a legitimate outcome — but only when every needle still resolves. If the plan is empty
    // because no needle matched at all, the list has aged and the run must say so rather than report a clean pass.
    if ($skipped === []) {
        fwrite(STDERR, "REFUSED: no needle produced either a replacement or an already-correct figure, so this list"
            ." reads nothing — re-cut the needles against the documents as they now stand.\n");

        exit(1);
    }
    echo "nothing to write: every class figure the list names already equals the delivered run.\n";
    foreach ($skipped as $s) {
        echo '  skip: '.$s."\n";
    }

    exit(0);
}
foreach ($skipped as $s) {
    echo '  skip: '.$s."\n";
}

printf('%d replacement(s) planned over %d file(s).', count($plan), count($byFile));
echo PHP_EOL;
foreach ($plan as $p) {
    preg_match('/\d+ cases( \/ |, )\d+ assertions/', $p['to'], $m);
    printf("  %-46s %-28s -> %s\n", basename($p['file']), $p['class'], $m[0]);
}
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

foreach ($byFile as $file => $list) {
    $path = $root.'/'.$file;
    $bytes = (string) file_get_contents($path);
    $linesBefore = substr_count($bytes, "\n");
    foreach ($list as [$from, $to]) {
        $bytes = str_replace($from, $to, $bytes);
    }
    if (substr_count($bytes, "\n") !== $linesBefore) {
        fwrite(STDERR, "REFUSED: writing {$file} would change its line count (a wrapped table row is a lost row).\n");

        exit(1);
    }
    file_put_contents($path, $bytes);
}

$bad = 0;
foreach ($plan as $p) {
    $after = (string) file_get_contents($root.'/'.$p['file']);
    if (substr_count($after, $p['from']) !== 0) {
        printf("  LEFTOVER %s in %s\n", $p['from'], $p['file']);
        $bad++;
    }
    if (substr_count($after, $p['to']) < 1) {
        printf("  MISSING  new figure for {$p['class']} did not land in {$p['file']}\n");
        $bad++;
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: the write is incomplete — {$bad} check(s) failed.\n");

    exit(1);
}
echo 'written, re-read, and no retired figure survives.'."\n";
