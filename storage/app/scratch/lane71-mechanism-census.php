<?php

/**
 * Lane 71, stage 1 (census only): what mechanism sentences does the document actually hold?
 *
 * G-31 (§28.3) records that a sentence stating what code does TODAY is judged by nothing: lane61 reacts to a status
 * word, lane60 to a figure, lane59 to a count a tool prints, the sweeps to line numbers. A checker for that class has
 * to be written from the shapes measured in the text, not from a vocabulary invented at the keyboard — lane 46's first
 * filter checker cleared the whole tree because it read only `route('x', {…})` and never `router.get(route('x'), {…})`,
 * and lane 50 passed every real call because it matched only the shape it had just fixed. So this stage prints
 * candidates, grouped, with counts, and judges nothing.
 *
 * A sentence is a MECHANISM CANDIDATE when it (a) carries a present-tense copula or action verb, (b) names code — a
 * backticked span, a `File.php:NN` citation, a `Class::method`, a camelCase column, or a quoted string — and (c) is
 * not dated: no "as of", no explicit date, no past-tense marker, no "(this issue)" row. Everything that fails (b) or
 * (c) lands in a named bucket, because a row that fell through a `continue` that counted nothing is exactly how lane
 * 70's gloss checker reported a clean document it had never examined.
 *
 * Usage: php storage/app/scratch/lane71-mechanism-census.php [--dump=path] [--show=20]
 */
$root = dirname(__DIR__, 3);
$dumpPath = $root.'/storage/app/scratch/docx-flat.txt';
$show = 20;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with((string) $a, '--dump=')) {
        $dumpPath = substr((string) $a, 7);
    } elseif (str_starts_with((string) $a, '--show=')) {
        $show = (int) substr((string) $a, 7);
    } else {
        printf("REFUSED: unknown argument [%s] — a flag that is accepted and inert makes a run look narrower than it is.\n", $a);
        exit(1);
    }
}
if (! is_file($dumpPath)) {
    printf("REFUSED: no dump at %s — run dump-docx-flat.php first (a missing dump reads as zero claims).\n", $dumpPath);
    exit(1);
}
$lines = explode("\n", (string) file_get_contents($dumpPath));

// Deliberately broad at stage 1: the point is to see what the document really says, then narrow with evidence.
$CODE = '/(?:`[^`]+`|[A-Za-z_]+\.php(?::\d+(?:-\d+)?)?|[A-Z][A-Za-z]+::[a-z][A-Za-z]*|\b[a-z][a-zA-Z_]{6,}\b|\x{201C}[^\x{201D}]{4,}\x{201D})/u';
$VERB = '/\b(?:is|are|was|were|writes?|stores?|holds?|reads?|refuses?|allows?|means?|carries?|names?|calls?|returns?|throws?|sets?|uses?|shows?|displays?|enforces?|gates?|checks?|applies?|leaves?|lives?|runs?|exists?|stands?|equals?|does|do|has|have|never|always|not)\b/u';
$DATED = '/(?:as of \d{4}|\bon \d{4}-\d{2}-\d{2}|\b\d{4}-\d{2}-\d{2}\b|\(this issue\)|when (?:it|that|this) was written|first version|it used to|before ruling|no longer|was corrected|Corrected \d{4}|since \d{4}-\d{2})/u';
$PAST = '/(?:\bwas \b|\bwere \b|\bhad been\b|\bused to\b|\bfixed\b|\breplaced\b|\bre-pointed\b|\bmoved to\b|\badded G-)/u';

$buckets = ['CANDIDATE' => [], 'no code name' => [], 'dated or history' => [], 'past tense' => [], 'no verb' => []];
$sentCount = 0;
foreach ($lines as $i => $line) {
    $t = trim((string) $line);
    if ($t === '' || preg_match('/^(ROW|P) \d+: /', $t) !== 1) {
        continue;
    }
    $t = (string) preg_replace('/^(ROW|P) \d+: /', '', $t);
    foreach (preg_split('/(?<=[.!?]) (?=[A-Z\x{201C}`])/u', $t) as $sent) {
        $sent = trim((string) $sent);
        if (mb_strlen($sent) < 24) {
            continue;
        }
        $sentCount++;
        if (preg_match($DATED, $sent) === 1) {
            $buckets['dated or history'][] = [$i + 1, $sent];
        } elseif (preg_match($PAST, $sent) === 1) {
            $buckets['past tense'][] = [$i + 1, $sent];
        } elseif (preg_match($VERB, $sent) !== 1) {
            $buckets['no verb'][] = [$i + 1, $sent];
        } elseif (preg_match($CODE, $sent) !== 1) {
            $buckets['no code name'][] = [$i + 1, $sent];
        } else {
            $buckets['CANDIDATE'][] = [$i + 1, $sent];
        }
    }
}

printf("%d sentence(s) read from %d dump lines.\n\n", $sentCount, count($lines));
foreach (['CANDIDATE', 'no code name', 'dated or history', 'past tense', 'no verb'] as $b) {
    printf("%-18s %5d\n", $b, count($buckets[$b]));
}
$total = 0;
foreach ($buckets as $b) {
    $total += count($b);
}
if ($total !== $sentCount) {
    printf("\nREFUSED: buckets sum to %d but %d sentences were read — a row fell through an uncounted path.\n", $total, $sentCount);
    exit(1);
}
if ($buckets['CANDIDATE'] === []) {
    printf("\nREFUSED: zero candidates — that is a broken read, not a clean document.\n");
    exit(1);
}

foreach (array_slice($buckets['CANDIDATE'], 0, $show) as [$ln, $s]) {
    printf("\nCAND line %d: %s\n", $ln, str_replace("\n", ' ', mb_substr($s, 0, 230)));
}
echo "\n(stage 1 judges nothing: this census is what decides what the checker can bind.)\n";
