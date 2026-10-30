<?php

/**
 * Read-only: does the SECOND number of a comma-list citation land on a live line?
 *
 * `repo-file-line-citations.php` takes only the first token of `File.php:154,195` — the limitation AGENTS.md already
 * prints for the docx sweep — so every comma list in the matrix is unjudged by the gate. This opens each one.
 *
 * Usage: php storage/app/scratch/lane97-probe-comma-cites.php
 */
$root = dirname(__DIR__, 3);

$files = [];
$paths = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
    if (! $f->isFile() || preg_match('#^(/\.git/|vendor/|node_modules/|storage/logs/|public/build/)#', '/'.$rel)) {
        continue;
    }
    if (preg_match('/\.(php|jsx|js)$/', $f->getFilename())) {
        // TWO indexes, one key space each: a basename and a relative path are different words, and a single array
        // holding both makes `isset($files['EvaluationPolicy.php'])` true for a name that resolves to three files.
        $files[$f->getFilename()][] = $rel;
        $paths[$rel] = true;
    }
}
$candCount = 0;
foreach ($files as $k => $v) {
    if (! isset($paths[$k])) {
        $candCount++;
    }
}
unset($k, $v);
if ($candCount === 0) {
    fwrite(STDERR, "REFUSED: the basename index is empty, so the walk read no source file.\n");

    exit(1);
}

$docs = [
    'Documentation/Prompt-Requirement-Matrix-2026-10-26.md',
    'Documentation/Prompt-Completion-Report-2026-10-27.md',
    'AGENTS.md',
];

$pattern = '/([A-Za-z0-9_\/\\\\.-]+\.(?:php|jsx|js)):(\d+),(\d+)/';
$claims = [];
foreach ($docs as $doc) {
    foreach (explode("\n", file_get_contents($root.'/'.$doc)) as $ln => $line) {
        if (preg_match_all($pattern, $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $one) {
                $claims[] = [$doc, $ln + 1, $one[1], $one[2], $one[3], trim($line)];
            }
        }
    }
}

if ($claims === []) {
    fwrite(STDERR, "REFUSED: no comma-list citation matched; the population is empty, which is not a clean result.\n");

    exit(1);
}

$bad = 0;
foreach ($claims as [$doc, $dline, $file, $a, $b, $sentence]) {
    $rel = str_replace('\\', '/', $file);
    if (isset($paths[$rel])) {
        $target = $rel;
    } else {
        $cands = array_values(array_unique($files[basename($rel)] ?? []));
        // A HEAD export copied under storage/app/scratch is not a second target — same tie-break
        // repo-file-line-citations.php applies, or the citation is accused of an ambiguity belonging to an artefact.
        $cands = array_values(array_filter(
            $cands,
            static fn (string $h): bool => ! str_contains($h, 'storage/app/scratch/')
        ));
        if ($cands === []) {
            printf("NO-FILE   %s:%d  [%s] resolves nowhere\n", $doc, $dline, $file);
            $bad++;

            continue;
        }
        if (count($cands) > 1) {
            printf("AMBIGUOUS %s:%d  [%s] is %s\n", $doc, $dline, $file, implode(' | ', $cands));
            $bad++;

            continue;
        }
        $target = $cands[0];
    }
    printf("claim     %s:%d  %s\n", $doc, $dline, mb_substr(preg_replace('/\s+/', ' ', $sentence), 0, 150));
    $lines = explode("\n", file_get_contents($root.'/'.$target));
    foreach ([$a, $b] as $n) {
        $text = $lines[(int) $n - 1] ?? null;
        if ($text === null) {
            printf("PAST-END  %s:%d  %s:%s (file has %d lines)\n", $doc, $dline, basename($target), $n, count($lines));
            $bad++;
        } elseif (trim($text) === '') {
            printf("BLANK     %s:%d  %s:%s\n", $doc, $dline, basename($target), $n);
            $bad++;
        } else {
            printf("live      %-34s :%-5s %s\n", basename($target), $n, trim(mb_substr($text, 0, 78)));
        }
    }
    echo "\n";
}

printf("%d comma-list citation(s) opened; %d refused.\n", count($claims), $bad);
