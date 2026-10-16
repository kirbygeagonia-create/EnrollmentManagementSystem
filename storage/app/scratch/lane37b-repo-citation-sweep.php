<?php

/**
 * Lane 37, second half — every `§` reference in the repository's PHP and markdown, resolved against the
 * document (or documents) it is pointing at.
 *
 * The defect that started this was never in the docx: `AuditRecordStates.php`, DEPLOYMENT.md and the UAT
 * walkthrough all cited the key-less-print hazard as "§28 G-9", a real section and a real finding id that are
 * about something else entirely (G-9 is the demo term's empty curriculum; the print-log NULL limit is §25 P-14).
 * `lane37-section-reference-sweep.php` checks the document against itself; this checks the other 233 references
 * in the tree, which no tool has ever read.
 *
 * A reference is resolved in this order, and the first that holds is the answer:
 *   1. the citing file's own headings and table-row ids   — the walkthrough's §15.1 is its own, not the docx's;
 *   2. the audit document, but only when the sentence says "audit" — DEPLOYMENT.md §2's "(audit §3.5)";
 *   3. the docx: the section number must exist (as a heading, as a chapter, or as a subsection that hosts
 *      finding rows), and any finding id named in the same breath must exist and — unless a comma separates
 *      them, which is the document's behaviour-then-finding convention — live in the cited chapter.
 * Anything that resolves nowhere is UNRESOLVED, which is the class that hides a false claim.
 *
 * Read-only; prints nothing when the tree is clean.
 */

$root = dirname(__DIR__, 3);
$inv = require __DIR__.'/lane37-docx-inventory.php';
$docxSections = $inv['sections'];
$docxRows = $inv['rows'];

/** Numbered headings, table-row ids, and §-labelled bullets inside one markdown file — its own frame. */
$selfInventory = function (string $path): array {
    $text = (string) file_get_contents($path);
    $nums = [];
    if (preg_match_all('/^#{1,6}\s+(\d+[A-Z]?(?:\.\d+)*)\b/m', $text, $m)) {
        foreach ($m[1] as $n) {
            $nums[$n] = true;
        }
    }
    if (preg_match_all('/^\|\s*(\d+[A-Z]?(?:\.\d+)?[a-z]?)\s*\|/m', $text, $m2)) {
        foreach ($m2[1] as $n) {
            $nums[$n] = true;
        }
    }
    // The audit document labels its findings in bold bullets — `- **§3.5 …**` — and DEPLOYMENT.md cites them
    // as "(audit §3.5)". Without this the audit corpus was empty, every audit pointer fell back to
    // DEPLOYMENT's own chapter numbers, and the tool looked clean because it could not see the target at all.
    if (preg_match_all('/\*\*§(\d+[A-Z]?(?:\.\d+)*)/m', $text, $m3)) {
        foreach ($m3[1] as $n) {
            $nums[$n] = true;
        }
    }

    return $nums;
};

$files = [];
foreach (['app', 'tools', 'tests', 'Documentation'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\.(php|md)$/', $f->getFilename())) {
            $files[] = $f->getPathname();
        }
    }
}
foreach ([$root.'/AGENTS.md', $root.'/DEPLOYMENT.md', $root.'/README.md'] as $f) {
    if (is_file($f)) {
        $files[] = $f;
    }
}

$auditPath = $root.'/Documentation/Audit-Remediation-2026-08-29.md';
$auditNums = is_file($auditPath) ? $selfInventory($auditPath) : [];

// Group 1 is the word "audit" in front of the mark: a reference that names its document must resolve in that
// document, and a dangling "(audit §9.9)" must not be rescued by DEPLOYMENT.md happening to have its own §9.
$refRe = '/(audit\s*)?§\s*(\d+[A-Z]?(?:\.\d+)?[a-z]?)\s*([ ,(]{0,4})((?:[A-Z]{1,3}-\d{1,3})+)?/iu';
$counts = ['checked' => 0, 'UNRESOLVED' => 0, 'NO-ID' => 0, 'WRONG-LOCATION' => 0, 'UNLABELLED' => 0, 'self' => 0, 'audit' => 0, 'docx' => 0, 'other' => 0];
$flags = [];

// Every markdown file's own numbering, so a pointer into the acceptance script from a PHP comment can be
// resolved — and then reported when the sentence does not say which document it means.
$corpus = [];
foreach ($files as $path) {
    if (preg_match('/\.md$/', $path)) {
        $corpus[str_replace('\\', '/', substr($path, strlen($root) + 1))] = $selfInventory($path);
    }
}

foreach ($files as $path) {
    if (realpath($path) === realpath($auditPath)) {
        continue;      // the audit document is a reference frame, not a citer of itself
    }
    $text = (string) file_get_contents($path);
    if (! str_contains($text, '§')) {
        continue;
    }
    $self = $selfInventory($path);
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));

    foreach (preg_split('/(?<=[.。]) (?=[A-Z(])|\n(?=[|\-#*])/', $text) ?: [] as $sentence) {
        if (! str_contains($sentence, '§')) {
            continue;
        }
        preg_match_all($refRe, $sentence, $refs, PREG_SET_ORDER);
        foreach ($refs as $r) {
            $namesAudit = isset($r[1]) && $r[1] !== '';
            $sec = $r[2];
            $sep = $r[3] ?? '';
            $id = $r[4] ?? '';
            $counts['checked']++;

            // A reference that names its document resolves there or nowhere: "(audit §3.5)" pointing at
            // DEPLOYMENT's own chapter 3 would be a false clean, which is the exact failure this half of the
            // lane was built to avoid.
            if ($namesAudit) {
                if (isset($auditNums[$sec])) {
                    $counts['audit']++;
                    $resolvedBy = 'audit';
                } else {
                    $counts['UNRESOLVED']++;
                    $flags[] = ['UNRESOLVED', $rel, $sec, $id, 'names the audit document, which has no §'.$sec.': '
                        .preg_replace('/\s+/u', ' ', mb_substr(trim($sentence), 0, 170))];

                    continue;
                }
            } elseif (isset($self[$sec]) || isset($self[explode('.', $sec)[0]])) {
                $counts['self']++;
                $resolvedBy = 'self';
            } elseif (isset($docxSections[$sec]) || isset($docxSections[explode('.', $sec)[0]])) {
                $counts['docx']++;
                $resolvedBy = 'docx';
            } else {
                // A subsection the heading regex missed still resolves if its chapter is real.
                $chapter = explode('.', $sec)[0];
                $anyChild = false;
                foreach (array_keys($docxSections) as $s) {
                    if (str_starts_with($s, $chapter.'.')) {
                        $anyChild = true;
                    }
                }
                if ($anyChild) {
                    $counts['docx']++;
                    $resolvedBy = 'docx';
                } else {
                    // Somewhere else in the tree numbers its steps this way — the acceptance script's §14R.2
                    // is real, and a PHP comment may cite it. Resolving it is not the same as citing it well:
                    // if the sentence does not name the document, a reader has one place to look and it is the
                    // wrong one, which is the UNLABELLED class below.
                    $elsewhere = [];
                    foreach ($corpus as $otherRel => $nums) {
                        if ($otherRel === $rel) {
                            continue;
                        }
                        if (isset($nums[$sec]) || isset($nums[$chapter]) || isset($nums[$chapter.'.'])) {
                            $elsewhere[] = $otherRel;
                        }
                    }
                    if ($elsewhere !== []) {
                        $counts['other']++;
                        $resolvedBy = 'other';
                        $namedHere = false;
                        foreach (['walkthrough', 'acceptance script', 'storyboard', 'use-case', 'audit', 'DEPLOYMENT'] as $cue) {
                            if (stripos($sentence, $cue) !== false) {
                                $namedHere = true;
                            }
                        }
                        if (! $namedHere) {
                            $counts['UNLABELLED']++;
                            $flags[] = ['UNLABELLED', $rel, $sec, $id,
                                '§'.$sec.' is '.$elsewhere[0].'’s own numbering; nothing in the sentence says so: '
                                .preg_replace('/\s+/u', ' ', mb_substr(trim($sentence), 0, 170))];
                        }

                        continue;
                    }

                    $counts['UNRESOLVED']++;
                    $flags[] = ['UNRESOLVED', $rel, $sec, $id, preg_replace('/\s+/u', ' ', mb_substr(trim($sentence), 0, 190))];

                    continue;
                }
            }

            if ($id === '' || $resolvedBy !== 'docx') {
                continue;
            }
            if (! isset($docxRows[$id])) {
                $counts['NO-ID']++;
                $flags[] = ['NO-ID', $rel, $sec, $id, preg_replace('/\s+/u', ' ', mb_substr(trim($sentence), 0, 190))];

                continue;
            }
            if (str_contains($sep, ',')) {
                continue;      // behaviour-then-finding: the row may live anywhere
            }
            $chapter = explode('.', $sec)[0];
            $sameChapter = array_filter($docxRows[$id], fn ($h) => $h['chapter'] === $chapter);
            $titled = isset($docxSections[$sec]) && preg_match('/\b'.preg_quote($id, '/').'\b/u', $docxSections[$sec]['title']) === 1;
            if ($sameChapter === [] && ! $titled) {
                $counts['WRONG-LOCATION']++;
                $where = implode(', ', array_map(fn ($h) => $h['section'].' '.$h['text'], $docxRows[$id]));
                $flags[] = ['WRONG-LOCATION', $rel, $sec, $id,
                    preg_replace('/\s+/u', ' ', mb_substr(trim($sentence), 0, 190)).'  → '.$id.' sits at '.$where];
            }
        }
    }
}

foreach ($flags as [$kind, $rel, $sec, $id, $sentence]) {
    printf("\n%s  %s  §%s%s\n    %s\n", $kind, $rel, $sec, $id === '' ? '' : ' '.$id, $sentence);
}

$bad = $counts['UNRESOLVED'] + $counts['NO-ID'] + $counts['WRONG-LOCATION'];
printf("\n%d reference(s) in %d file(s): %d problem(s) — UNRESOLVED %d, NO-ID %d, WRONG-LOCATION %d"
    ." | resolved by self %d, audit %d, docx %d, another file %d"
    ." | %d pointer(s) that resolve only in another document's numbering without saying so (a queue to read, not a failure)\n",
    $counts['checked'], count($files), $bad, $counts['UNRESOLVED'], $counts['NO-ID'], $counts['WRONG-LOCATION'],
    $counts['self'], $counts['audit'], $counts['docx'], $counts['other'], $counts['UNLABELLED']);

exit($bad === 0 ? 0 : 1);
