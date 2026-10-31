<?php

/**
 * Every `File.php:NN` / `File.jsx:NN-MM` citation in the repository's own prose, checked against the files.
 *
 * `sweep-docx-citations.php` does this for the authority docx (554 citations), and AGENTS.md says the whole
 * document must be swept after any batch that moves lines. But AGENTS.md and DEPLOYMENT.md also carry
 * citations of their own — and the ones in AGENTS.md were written today, by me, about a file that had already
 * grown twice. Nothing read them. A checker for the document that excludes the instructions is the same shape
 * as a test suite that excludes `tools/`.
 *
 * Three claims per citation, the same three the docx sweep makes: the file exists, the range ends inside it,
 * and the first line of the range has something on it. It does NOT check that the sentence is about what the
 * lines contain — that reading is the human half, and no regex here can do it honestly.
 *
 * usage: php storage/app/scratch/repo-file-line-citations.php [file ...]
 *        (defaults to the prose files an owner or a desk is actually sent — the instruction files, the guides,
 *        the two third-party assessments, the dated sheets and packets, and the four long-lived reviews. No
 *        count is stated, because this list grows with each document issued and a figure in a tool's own header
 *        is a claim about the day it was written. A dated document that matches the glob but is not on the list
 *        is a refusal, not an absence.)
 */
$root = dirname(__DIR__, 3);
$args = array_values(array_filter(array_slice($argv ?? [], 1), fn ($a) => ! str_starts_with($a, '--')));
$targets = $args !== [] ? $args : [
    'AGENTS.md',
    'DEPLOYMENT.md',
    'Documentation/Per-Office-UAT-Walkthrough.md',
    'Documentation/Demo-Day-Guide.md',
    'Documentation/Offline-Presentation-Guide.md',
    'SEAIT-EMS-Round3-Current-State.md',
    'Documentation/Audit-Remediation-2026-08-29.md',
    // The sheets are the papers an office actually ticks, and every row of the print ones is a line
    // reference into a template. No instrument read them until 2026-10-24, which is why three of the
    // 2026-10-11 sheet's pointers had drifted onto another block when the ruling above them was built.
    'Documentation/School-Decision-Sheet-2026-10-23.md',
    'Documentation/School-Decision-Sheet-2026-10-27.md',
    'Documentation/Registrar-Print-Decision-Sheet-2026-10-11.md',
    'Documentation/Print-Fidelity-Comparison-2026-10-10.md',
    'Documentation/Registrar-Window-Packet-2026-10-26.md',
    // The department half of C-5, issued 2026-10-26: it quotes the two lines that make the flag bite, so a method
    // moved in AdmissionPolicy or the reference-data controller silently breaks an ask a department is answering.
    'Documentation/Department-Board-Exam-Confirmation-2026-10-26.md',
    // The requirement-to-code matrix for the master development prompt, issued 2026-10-26. It names the line that
    // carries each behaviour it classifies, so it is a document an owner is sent — and a pointer that lands on a
    // live line belonging to something else is exactly what this sweep exists to refuse.
    'Documentation/Prompt-Requirement-Matrix-2026-10-26.md',
    // The four long-lived review documents. Measured 2026-10-31: 25 `File.php:NN` pointers sit in them — 18 in the
    // UI/Workflow conformance audit, 4 each in the UI/UX refinement analysis and the system-design quality review —
    // and none was ever read, because this list named only the owner-facing sheets. They cite
    // `ReferenceDataController.php` and `UserManagementController.php` by line, so wiring one more sortable table
    // ages their pointers in silence: the hand-kept-list blind spot, arriving in the gate that exists to catch it.
    'Documentation/UI-Workflow-Conformance-Audit-2026-09-17.md',
    'Documentation/UI-UX-Refinement-Task-Analysis-2026-09-17.md',
    'Documentation/System-Design-Quality-Review.md',
    'Documentation/Functional-Decomposition-Diagram.md',
    // The §16 final report and the sheet issued with the prompt audit of 2026-10-29. Both are sent to
    // an owner, so both are read; the guard below is what stops the next dated document from being
    // silently left out of this list, which is how the 2026-10-11 sheet's three drifted pointers and
    // the matrix's own shorthand paths went unnoticed.
    'Documentation/Prompt-Completion-Report-2026-10-27.md',
    'Documentation/School-Decision-Sheet-2026-10-29.md',
];

// A dated owner-facing document that is not in the list above is a citation blind spot, not an absence
// of citations. Refuse loudly rather than reporting a clean sweep over files this tool never opened.
// Only when the whole set is being scanned: a scoped run names its own files on purpose.
if ($args === []) {
    $dated = glob($root.'/Documentation/{School-Decision-Sheet-*,Prompt-Requirement-Matrix-*,Prompt-Completion-Report-*,Registrar-Window-Packet-*,Department-Board-Exam-Confirmation-*,Registrar-Print-Decision-Sheet-*,Print-Fidelity-Comparison-*}', GLOB_BRACE);
    $unlisted = [];
    foreach ((array) $dated as $f) {
        $rel = 'Documentation/'.basename($f);
        if (! in_array($rel, $targets, true)) {
            $unlisted[] = $rel;
        }
    }
    if ($unlisted !== []) {
        fwrite(STDERR, 'REFUSED: '.count($unlisted).' dated owner-facing document(s) exist but are not scanned: '
            .implode(', ', $unlisted)."\n  Add the file to the list above, or delete it. An unread document cannot be a clean one.\n");

        exit(1);
    }
}

$index = null;
/** Every file the repository's own prose can cite, indexed by basename. */
$buildIndex = function () use ($root, &$index): array {
    if ($index !== null) {
        return $index;
    }
    $index = [];
    foreach (['app', 'resources', 'tests', 'tools', 'database', 'routes', 'config', 'bootstrap', 'public', 'storage/app/scratch', 'Documentation', '.github'] as $dir) {
        if (! is_dir($root.'/'.$dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! $f->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            $index[$f->getFilename()][] = $rel;
        }
    }

    return $index;
};

/**
 * Resolve a cited name. A name that carries a path is taken literally — this repository's prose always knows
 * the path it means. A bare basename is looked up, and more than one hit is reported as AMBIGUOUS rather than
 * guessed at: the docx sweep once returned a real-looking "past end of file" for a citation the document had
 * already qualified, because its resolver answered with whichever `Index.jsx` the walk reached first.
 *
 * @return array{0: ?string, 1: list<string>} the chosen path (null when unresolvable) and every candidate
 */
$resolve = function (string $name) use ($root, $buildIndex): array {
    if (str_contains($name, '/') && is_file($root.'/'.ltrim($name, '/'))) {
        return [ltrim($name, '/'), []];
    }

    // A name with a slash is not always a repository path. The September review documents write
    // `Assessment/Show.jsx` and `ID/Index.jsx` for files under `resources/js/Pages`, and taking those literally
    // answered NO SUCH FILE for five files that all exist — the citation charged to the document for the reader's
    // own gap, which is lane 70's unresolved-key lesson met from the other side. Resolve a slashed name by
    // suffix, and keep the rule that only one hit may be inferred: zero is NOT-FOUND, several are AMBIGUOUS,
    // exactly one is the file the prose means.
    $tail = str_contains($name, '/') ? '/'.ltrim($name, '/') : null;
    $hits = [];
    foreach ($buildIndex() as $basename => $paths) {
        foreach ($paths as $p) {
            $normalised = '/'.ltrim(str_replace('\\', '/', $p), '/');
            if ($tail === null ? $basename === $name : str_ends_with($normalised, $tail)) {
                $hits[] = $p;
            }
        }
    }

    // A HEAD export copied under `storage/app/scratch/` (the lane 51/53/55/56/57/79 harnesses take them by
    // hand because `shell_exec` cannot reach git from PHP here, and `storage/app/.gitignore` hides them from
    // the remote on purpose) is not a second file prose could mean. When the index holds a real path and a
    // scratch copy of the same basename, the ambiguity belongs to the export, so the copy is dropped rather
    // than reported as a defect in the citation — and a basename whose ONLY hits are scratch copies is still
    // reported, because then the prose genuinely names nothing the tree resolves.
    if (count($hits) > 1) {
        $real = array_values(array_filter(
            $hits,
            static fn (string $h): bool => ! str_contains(str_replace('\\', '/', $h), 'storage/app/scratch/')
        ));

        if ($real !== []) {
            $hits = $real;
        }
    }
    if (count($hits) === 1) {
        return [$hits[0], $hits];
    }

    return [null, $hits];
};

// The name may carry slashes (resources/js/Pages/X.jsx) or none (Preflight.php); the resolver below is
// deliberately stricter than the docx sweep's basename walk, because a prose citation in this repository
// always knows the path it means — an ambiguous basename here is a defect in the citation, not in the tool.
$fileRe = '/\b((?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js|json|neon|xml|md|sql|ps1)):(\d+)(?:-(\d+))?/';

// Flags are read here and an unknown one is refused by name. The argument filter above silently drops anything
// starting with `--`, which is how a flag can look honoured while the walk never sees it — the accepted-but-inert
// defect this repository has met in five of its own tools. `--strict-dated` exists so a harness can prove the
// rule below is read rather than tolerated: with it, a dated record is judged exactly like live prose.
$strictDated = false;
foreach (array_slice($argv ?? [], 1) as $a) {
    if ($a === '--strict-dated') {
        $strictDated = true;

        continue;
    }
    if (str_starts_with($a, '--')) {
        fwrite(STDERR, "REFUSED: unknown flag {$a}; this tool reads --strict-dated and named files.\n");

        exit(1);
    }
}

$problems = 0;
$ambiguous = 0;
$checked = 0;
$lineCache = [];

// A dated record is a reading, not a claim about today. Two September analysis documents carry a
// "Pointer currency" note saying their `File.php:NN` numbers are the 2026-09-17 layout — several of the
// constructs they cite no longer exist because the findings were CLOSED — so re-typing those numbers would
// make a dated row state a position that was never true on its own day, while leaving them judged makes the
// gate permanently red and teaches everyone to ignore it. Both readings are wrong; this is the third: the
// pointers are reported, named, and not counted against the run. lane 72's HISTORY bucket, applied here.
$DATED_MARKER = 'Pointer currency (2026-10-31)';
$datedReported = 0;
$datedDocs = [];

foreach ($targets as $rel) {
    $path = $root.'/'.ltrim($rel, '/');
    if (! is_file($path)) {
        printf("  MISSING TARGET  %s\n", $rel);
        $problems++;

        continue;
    }
    $text = (string) file_get_contents($path);
    $isDated = ! $strictDated && str_contains($text, $DATED_MARKER);
    preg_match_all($fileRe, $text, $m, PREG_SET_ORDER);

    foreach ($m as $x) {
        [$name, $from] = [$x[1], (int) $x[2]];
        $to = ($x[3] ?? '') === '' ? $from : (int) $x[3];
        $token = $name.':'.$from.($to > $from ? '-'.$to : '');

        [$chosen, $hits] = $resolve($name);
        if ($chosen === null && $hits !== []) {
            $ambiguous++;
            printf("  AMBIGUOUS     %-46s basename matches %d files (%s) — name the path  (in %s)\n",
                $token, count($hits), implode(', ', array_slice($hits, 0, 3)), $rel);

            continue;
        }
        if ($chosen === null) {
            if ($isDated) {
                $datedReported++;
                $datedDocs[$rel] = true;
                printf("  DATED         %-46s cited in a dated record (%s) — reported, not judged\n", $token, $rel);

                continue;
            }
            $problems++;
            printf("  NO SUCH FILE  %-46s cited in %s\n", $token, $rel);

            continue;
        }

        $lines = $lineCache[$chosen] ??= (file($root.'/'.$chosen) ?: []);
        $checked++;

        if ($to > count($lines)) {
            if ($isDated) {
                $datedReported++;
                $datedDocs[$rel] = true;
                printf("  DATED         %-46s cites %d-%d in a %d-line file (%s) — reported, not judged\n", $token, $from, $to, count($lines), $rel);

                continue;
            }
            printf("  PAST END      %-46s cites %d-%d in a %d-line file  (in %s)\n", $token, $from, $to, count($lines), $rel);
            $problems++;

            continue;
        }
        if (trim((string) $lines[$from - 1]) === '') {
            if ($isDated) {
                $datedReported++;
                $datedDocs[$rel] = true;
                printf("  DATED         %-46s cites a blank line %d (%s) — reported, not judged\n", $token, $from, $rel);

                continue;
            }
            printf("  EMPTY LINE    %-46s cites a blank line %d  (in %s)\n", $token, $from, $rel);
            $problems++;
        }
    }
}

printf("\n%d citation(s) resolved and checked across %d file(s); %d problem(s); %d ambiguous basename(s); %d dated-record pointer(s)%s\n",
    $checked, count($targets), $problems, $ambiguous, $datedReported,
    $datedDocs === [] ? '' : ' in '.implode(', ', array_keys($datedDocs)));

if ($strictDated && $datedReported > 0) {
    fwrite(STDERR, "REFUSED: --strict-dated was asked but the dated rule still reported — the flag is not reaching the walk.\n");

    exit(1);
}

if (! $strictDated && $datedReported > 0 && count($datedDocs) === 0) {
    // A count with no document behind it is the two-definitions-of-one-word defect arriving in the summary.
    fwrite(STDERR, "REFUSED: {$datedReported} dated pointer(s) reported but no document named.\n");

    exit(1);
}

exit($problems === 0 ? 0 : 1);
