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
    fwrite(STDERR, 'REFUSED: the pattern '.strtr($pattern, ['|' => '/']).' matched no document, so the run would be judging nothing.\n');
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

// What counts as a path citation. Two families, because a deliverable names an artefact both ways:
//  (a) a token with a directory part — `storage/app/scratch/lane164-out.txt`, `Documentation/DATABASE-DESIGN-ADS.md`;
//  (b) a **bare filename inside backticks** — `lane164-out.txt`, `ems-pre-live.sql`, `EMS Complete Documentation.docx`
//      (the spaced title is a whole path with spaces in it, so a pattern of non-space characters could never see it).
// Family (b) is resolved only against the tracked index, because a bare name has no directory to prove it.
$exts = 'php|md|txt|json|sql|log|ps1|mjs|docx|xml|jsx|tsx|js|neon|yml|yaml';
$patternPath = '(?<![\w/.-])((?:[A-Za-z0-9_.-]+\/)+[A-Za-z0-9_.-]+\.(?:'.$exts.'))';
// Delimited with `/`, NOT with backticks: written as '`(...)`' the backticks are the delimiter and vanish
// from the pattern, so the "code span" reader was really matching every bare filename anywhere in prose —
// and matching the tail of `bootstrap/app.php` as `app.php`, which then looked ambiguous.
$patternBare = '/`([A-Za-z0-9_][A-Za-z0-9 ._-]*\.(?:'.$exts.'))`/';
// A full path with a space in it — `Documentation/EMS Complete Documentation.docx` — is a real artefact this
// repository carries, and a pattern of non-space characters could never see it. Backticks are the boundary.
$patternSpaced = '/`([A-Za-z0-9_.-]+(?:\/[A-Za-z0-9 ._-]+)+\.(?:'.$exts.'))`/';

/**
 * Fenced code blocks are instructions, not claims that a file exists today: a usage block writes
 * `git ls-files > storage/app/scratch/laneNN-tracked.txt` about a file the reader is about to create.
 * They are skipped, and the skip is printed — an exemption a run does not show is the accepted-but-inert
 * defect in a new costume.
 */
$stripFences = static function (string $text, int &$skipped): string {
    $skipped += preg_match_all('/^```.*?^```/ms', $text);

    return preg_replace('/^```.*?^```/ms', '', $text) ?? $text;
};

// The bare-name resolver, and where its population comes from. With `--tracked=` the index is git's own list,
// which is what a clone receives — the exact question this gate asks. Without it the index is one walk of the
// working tree, and the run says so, because an index built here cannot say whether a path travels.
$index = [];
$indexSource = '';
if ($tracked !== null) {
    foreach (array_keys($tracked) as $tp) {
        $index[basename($tp)][] = $tp;
    }
    $indexSource = "git's index (".count($tracked).' paths)';
} else {
    $skip = ['vendor', 'node_modules', '.git', 'build', 'public/build'];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator('.', FilesystemIterator::SKIP_DOTS),
            static function ($cur) use ($skip): bool {
                if ($cur->isDir()) {
                    return ! in_array($cur->getBasename(), $skip, true);
                }

                return ! in_array(dirname(str_replace('\\', '/', $cur->getPathname())), $skip, true)
                    && ! str_contains(str_replace('\\', '/', $cur->getPathname()), '/vendor/')
                    && ! str_contains(str_replace('\\', '/', $cur->getPathname()), '/node_modules/');
            }
        )
    );
    $walked = 0;
    foreach ($it as $f) {
        if (! $f->isFile()) {
            continue;
        }
        $rel = trim(str_replace('\\', '/', substr($f->getPathname(), 2)), '/');
        $index[basename($rel)][] = $rel;
        $walked++;
    }
    $indexSource = 'one walk of the working tree ('.$walked.' files) — tracking NOT judged';
}
if ($index === []) {
    fwrite(STDERR, "REFUSED: the basename index read nothing, so every bare name would read MISSING.\n");
    exit(1);
}

// Paths that live outside this repository by design, each named by its class rather than by a list a
// later reader has to remember: the school's guide, the implementation prompt, the backup home, and the
// HEAD exports the repository deliberately ignores.
$externalClasses = [
    '#^docs/[A-Za-z0-9_]+\.md$#' => 'the guide\'s own deliverable path (docs/), which this repository answers from Documentation/',
    '#lane\d+-head-[A-Za-z0-9]+\.php$#' => 'a HEAD export, ignored by storage/app/.gitignore on purpose',
    '#Master_Implementation\.md$#' => 'the implementation prompt, held outside the tree',
    '#ADS_Final_Project_Guide[A-Za-z0-9 ._()-]*\.docx$#' => 'the school\'s guide docx, held outside the tree',
    '#ems-pre-[A-Za-z0-9 ._()-]*\.sql$#' => 'a pre-change dump, which lives in the backup folder and never in the repository',
    '#^EMS Complete Documentation\.docx$#' => 'the authority docx, cited by its own name',
    '#^[A-Z][A-Z0-9_]+\.md$#' => "the implementation prompt's own deliverable name, answered here by a file under Documentation/",
];

$cited = [];
$bare = [];
$byDesign = [];
$fences = 0;
foreach ($docs as $doc) {
    $text = $stripFences((string) file_get_contents($doc), $fences);
    // Strip line-number citation tails first: `RegistrarController.php:321` is a pointer to a LINE in a file the
    // line gate already judges, and a bare path plus digits would double-count it here.
    preg_match_all('#'.$patternPath.'#i', $text, $m);
    foreach ($m[1] as $p) {
        $exempt = null;
        foreach ($externalClasses as $rx => $why) {
            if (preg_match($rx, $p)) {
                $exempt = $why;
                break;
            }
        }
        if ($exempt !== null) {
            $byDesign[$p][] = basename($doc).' — '.$exempt;

            continue;
        }
        $cited[$p][] = basename($doc);
    }
    // A spaced title may also carry a directory part, so the bare family is tried on the whole span and
    // judged by the same rules; a span already counted by the path pattern is not counted twice.
    preg_match_all($patternBare, $text, $mb);
    foreach ($mb[1] as $p) {
        $p = trim($p);
        if (str_contains($p, '/') || isset($cited[$p])) {
            continue;
        }
        $bare[$p][] = basename($doc);
    }
    // Spaced full paths join the separator family, with the same exemption classes applied, so a title with a
    // space in it is judged rather than invisible.
    preg_match_all($patternSpaced, $text, $msp);
    foreach ($msp[1] as $p) {
        $p = trim($p);
        if (isset($cited[$p])) {
            continue;
        }
        $exempt = null;
        foreach ($externalClasses as $rx => $why) {
            if (preg_match($rx, $p)) {
                $exempt = $why;

                break;
            }
        }
        if ($exempt !== null) {
            $byDesign[$p][] = basename($doc).' — '.$exempt;

            continue;
        }
        $cited[$p][] = basename($doc);
    }
}

if ($cited === [] && $bare === []) {
    fwrite(STDERR, 'REFUSED: no path citations were found in '.count($docs)." document(s) — a reader that extracts nothing prints a clean verdict over silence.\n");
    exit(1);
}

$missing = [];
$untracked = [];
$ambiguous = [];
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

// Bare names are resolved by the index and never guessed: one hit judges the path it found, two hits is a claim
// the document has not made precise enough to check, and zero is a name no clone can open — unless the name
// belongs to an external class, which is exempted by class and printed with its reason. Existence is asked
// before class, so a tracked `DEPLOYMENT.md` is never swallowed by the prompt-name exemption.
foreach ($bare as $name => $where) {
    if (! isset($index[$name])) {
        $exempt = null;
        foreach ($externalClasses as $rx => $why) {
            if (preg_match($rx, $name)) {
                $exempt = $why;

                break;
            }
        }
        if ($exempt !== null) {
            $byDesign[$name][] = implode(', ', array_values(array_unique($where))).' — '.$exempt;

            continue;
        }
        $missing[$name] = array_values(array_unique($where));

        continue;
    }
    if (count($index[$name]) > 1) {
        $ambiguous[$name] = [array_values($index[$name]), array_values(array_unique($where))];

        continue;
    }
    $path = $index[$name][0];
    if (! is_file($path)) {
        $missing[$path] = array_values(array_unique($where));

        continue;
    }
    if ($tracked !== null && ! isset($tracked[$path])) {
        $untracked[$path] = array_values(array_unique($where));

        continue;
    }
    $ok++;
}

printf("documents read: %d; distinct path citations: %d (%d with a directory part, %d bare names)\n",
    count($docs), count($cited) + count($bare), count($cited), count($bare));
echo '  scope: fenced code blocks are not judged (they write commands, not claims) — '.$fences." block(s) skipped\n";
echo '  resolver: bare names answered by '.$indexSource."\n";
// The population's limit is printed, never implied.
echo "  scope: a basename held by more than one path is reported AMBIGUOUS rather than guessed\n";
echo "  scope limit: a span is extracted only from characters the pattern knows, so a citation written with an\n"
    ."               ellipsis (`tests/Feature/…/SomeTest.php`) or with a directory the writer never checked is\n"
    ."               NOT judged here at all. Write the path a resolver can follow, or the gate is silent about it.\n";
if ($ambiguous !== []) {
    foreach ($ambiguous as $n => [$paths, $where]) {
        echo '  AMBIGUOUS      '.str_pad($n, 44).'  matches '.count($paths).' tracked paths ('.implode(', ', array_slice($paths, 0, 3)).') — cited in '.implode(', ', $where)."\n";
    }
}
printf("on disk and tracked: %d; missing: %d; on disk but NOT in git: %d; ambiguous: %d\n",
    $ok, count($missing), $tracked === null ? 0 : count($untracked), count($ambiguous));

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

$problems = count($missing) + count($ambiguous) + ($tracked === null ? 0 : count($untracked));
if ($problems > 0) {
    echo "\nREFUSE-LEVEL findings: a deliverable that cites an artefact a reader cannot open — or that names a\n"
        ."basename two tracked files answer — is a claim about evidence, not an absence of evidence. Either\n"
        ."re-include the file by literal name, write the citation with its directory, or stop citing it.\n";
    exit(1);
}
echo "VERDICT: every path the ADS deliverables cite exists and travels with the repository.\n";
