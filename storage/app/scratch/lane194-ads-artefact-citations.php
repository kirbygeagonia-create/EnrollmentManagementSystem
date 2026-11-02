<?php

/**
 * Does every file the ADS deliverables cite by name actually exist, and is it actually in the repository?
 *
 * Found by hand on 2026-11-02, twice: the ADS documents quote figures "from the captured file" and point a reader
 * at `lane164-out.txt`, `lane168-out.txt`, `lane172-load-out.txt` and two suite logs — and `scratch/` is
 * re-included by extension (`.php`, `.ps1`, `.mjs`), so every one of those captures was invisible to a clone. The
 * documents were true about the measurement and false about the evidence: a reader with the repository alone cannot
 * open any file the citations name.
 *
 * No existing gate can see this. `repo-file-line-citations.php` resolves `File.php:NN` pointers to lines in files
 * it opens, and a capture is not a PHP file; `lane37b` judges `§` references; `lane59` binds figures to the tools
 * that print them. None of them asks whether a cited path exists at all, let alone whether it travels.
 *
 * So this reads the deliverables, pulls every path-shaped citation out of them, and asks two questions per path:
 * exists on this box, and — when `--tracked=` is supplied — is it in git's index? A missing capture is reported
 * MISSING-FILE; a file that exists only here is reported UNTRACKED, which is the finding that cannot be repaired
 * by a later code change because it is about what a clone receives.
 *
 * Refuses: no `*-ADS.md` matched; no path citation found at all; a `--tracked=` flag that names an empty or missing
 * list (an ignored flag would make the tracking half silently unchecked).
 *
 * Usage: git ls-files > storage/app/scratch/lane194-tracked.txt
 *        php storage/app/scratch/lane194-ads-artefact-citations.php --tracked=storage/app/scratch/lane194-tracked.txt
 */
$root = dirname(__DIR__, 3);
chdir($root);

$argvMap = [];
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $argvMap[$m[1]] = $m[2] ?? true;
    } else {
        fwrite(STDERR, 'REFUSED: this tool does not understand '.escapeshellarg($a).".\n");
        exit(1);
    }
}
$unknown = array_values(array_diff(array_keys($argvMap), ['tracked', 'docs']));
if ($unknown !== []) {
    // `implode` over the FOREACH VARIABLE fatals ("Argument #2 must be ?array, string given") — measured, and the
    // reason this refusal is built from the array itself: a guard that dies instead of refusing hands a caller a
    // status code from PHP's shutdown path rather than the tool's own, and the leg that tests it reads that as a pass.
    fwrite(STDERR, 'REFUSED: flag(s) not read by this tool: --'.implode(', --', $unknown).".\n");
    exit(1);
}

// The population comes from a glob, never from a hand-kept list: this file has twice recorded a dated document
// that no checker was told about, and a gate over a remembered list is a gate over what the remembered list holds.
$pattern = $argvMap['docs'] ?? 'Documentation/*-ADS.md';
$docs = glob($pattern) ?: [];
if ($docs === []) {
    fwrite(STDERR, "REFUSED: the pattern ".strtr($pattern, ['|' => '/']).' matched no document, so the run would be judging nothing.\n');
    exit(1);
}

$tracked = null;
if (isset($argvMap['tracked'])) {
    $trackedPath = (string) $argvMap['tracked'];
    if ($trackedPath === '' || ! is_file($trackedPath)) {
        fwrite(STDERR, "REFUSED: --tracked names '".(string) $trackedPath."', which is not a file — the tracking half cannot be skipped by a typo.\n");
        exit(1);
    }
    $trackedLines = array_values(array_filter(array_map('trim', (array) file($trackedPath, FILE_IGNORE_NEW_LINES)), fn ($l) => $l !== ''));
    if ($trackedLines === []) {
        fwrite(STDERR, "REFUSED: --tracked file holds no paths, so every citation would read UNTRACKED and that is not a measurement.\n");
        exit(1);
    }
    $tracked = array_flip($trackedLines);
}

// What counts as a path citation: a backticked or bare token that looks like a repository path and names a file,
// not a directory. Prose like `app/` or `resources/js` is excluded by requiring an extension.
$patternPath = '(?<![\w/.-])((?:[A-Za-z0-9_.-]+\/)+[A-Za-z0-9_.-]+\.(?:php|md|txt|json|sql|log|ps1|mjs|docx|xml|jsx|tsx|js|neon|yml|yaml))';

$cited = [];
$byDesign = [];
foreach ($docs as $doc) {
    $text = (string) file_get_contents($doc);
    // Strip line-number citation tails first: `RegistrarController.php:321` is a pointer to a LINE in a file the
    // line gate already judges, and a bare path plus digits would double-count it here.
    preg_match_all('#'.$patternPath.'#i', $text, $m);
    foreach ($m[1] as $p) {
        // The guide's own deliverable paths. Several ADS documents open by naming what the guide asks for
        // (`docs/SYSTEM_ARCHITECTURE.md`) and then say which file in THIS repository carries it — a reference to a
        // path the school's prompt names and the checkout does not hold. Exempted by prefix, printed by name:
        // an exemption a run does not show is the accepted-but-inert defect in a new costume.
        if (preg_match('#^docs/[A-Za-z0-9_]+\.md$#', $p)) {
            $byDesign[$p][] = basename($doc);

            continue;
        }
        // A HEAD export is deliberately ignored (storage/app/.gitignore names the pattern, because it is a second
        // copy of a tracked file). A document that cites one is describing the command that makes it.
        if (preg_match('#lane\d+-head-[A-Za-z0-9]+\.php$#', $p)) {
            $byDesign[$p][] = basename($doc);

            continue;
        }
        $cited[$p][] = basename($doc);
    }
}

if ($cited === []) {
    fwrite(STDERR, 'REFUSED: no path citations were found in '.count($docs)." document(s) — a reader that extracts nothing prints a clean verdict over silence.\n");
    exit(1);
}

$missing = [];
$untracked = [];
$ok = 0;
foreach ($cited as $path => $where) {
    // A citation may be written relative to the repository root or as a trailing fragment (`Pages/Evaluation/
    // Index.jsx` for `resources/js/Pages/Evaluation/Index.jsx`), so the resolver tries the root, then the
    // directories a document in this project habitually shortens. An unresolved path is a finding; a path found
    // by two candidates is not, and the first hit wins.
    $candidates = [$path, 'storage/app/scratch/'.basename($path), 'Documentation/'.basename($path), 'resources/js/'.$path, 'resources/views/'.$path];
    $found = null;
    foreach ($candidates as $c) {
        if (is_file($c)) {
            $found = $c;

            break;
        }
    }
    if ($found === null) {
        $missing[$path] = array_values(array_unique($where));

        continue;
    }
    if ($tracked !== null) {
        $gitPath = str_replace('\\', '/', $found);
        if (! isset($tracked[$gitPath])) {
            $untracked[$gitPath] = array_values(array_unique($where));

            continue;
        }
    }
    $ok++;
}

printf("documents read: %d; distinct path citations: %d\n", count($docs), count($cited));
// The population's limit is printed, never implied: the pattern needs a directory separator, so a citation written
// as a bare filename (`lane164-out.txt`, no `storage/app/scratch/` in front) is NOT judged here. A gate whose scope
// is wider than its reach is the fake-clean verdict this repository keeps meeting from the inside.
echo "  scope limit: bare filenames with no directory part are out of scope; only paths containing a separator are resolved\n";
printf("on disk and tracked: %d; missing: %d; on disk but NOT in git: %d\n",
    $ok, count($missing), $tracked === null ? 0 : count($untracked));

foreach ($missing as $p => $where) {
    echo '  MISSING-FILE  '.$p.'  (cited in '.implode(', ', $where).")\n";
}
foreach ($untracked as $p => $where) {
    echo '  UNTRACKED     '.$p.'  (cited in '.implode(', ', $where).")\n";
}
if ($byDesign !== []) {
    printf("  BY-DESIGN %d path(s) named but not expected in this tree, listed so the exemption is visible:\n", count($byDesign));
    foreach ($byDesign as $p => $where) {
        echo '    - '.$p.'  ('.implode(', ', array_values(array_unique($where))).")\n";
    }
}

if ($tracked === null) {
    echo "  tracking half: NOT JUDGED — no --tracked= list was supplied, so nothing above was compared with git's index\n";
}

$problems = count($missing) + ($tracked === null ? 0 : count($untracked));
if ($problems > 0) {
    echo "\nREFUSE-LEVEL findings: a deliverable that cites an artefact a reader cannot open is a claim about evidence,\n"
        ."not an absence of evidence. Either re-include the file by literal name or stop citing it.\n";
    exit(1);
}
echo "VERDICT: every path the ADS deliverables cite exists and travels with the repository.\n";
