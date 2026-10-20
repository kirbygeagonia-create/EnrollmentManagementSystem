<?php

/**
 * Lane 56e — restate the count of `Ledger —` report lines in the two markdown files that carry it.
 *
 * Measured, not remembered: `git show HEAD:tools/seed_full_demo_pipeline.php` defines six `'Ledger —' =>` keys and
 * the working tree defines seven, while AGENTS.md and DEPLOYMENT.md both said "four" — which is the number of lines
 * lane 54's harness owns, quietly written as the number the report has. A count a row can change without editing its
 * neighbour is the failure this repository has now met three times (§28.3's size, the audit's rule count, here), so
 * each statement is re-taken from the pipeline's own bytes and says which part of the count is whose.
 *
 * Line endings are the file's own, not the script's: `.gitattributes` declares `* text=auto eol=lf`, `git ls-files
 * --eol` reports AGENTS.md and the presentation guide as `w/lf` and DEPLOYMENT.md as `w/crlf`, and the first run of
 * this script matched the AGENTS.md needle zero times because it spelled the breaks out as \r\n. Each needle is
 * therefore written with \n and translated to whatever the file actually uses, and a replacement that would introduce
 * the minority ending into a file is refused. An edit whose needle is already gone is reported as APPLIED rather than
 * failed, so this script can be re-run after a partial pass without inventing a second copy of the sentence.
 */
$root = dirname(__DIR__, 3);
$failures = 0;

/**
 * Applies one exact-text edit, in the file's own line ending, and refuses a needle that is not unique.
 */
function edit(string $path, string $needleLf, string $replacementLf, string $what, int &$failures): void
{
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        fwrite(STDERR, "REFUSED: cannot read {$path}\n");
        exit(1);
    }

    $eol = str_contains($bytes, "\r\n") ? "\r\n" : "\n";
    $needle = str_replace("\n", $eol, $needleLf);
    $replacement = str_replace("\n", $eol, $replacementLf);
    $hits = substr_count($bytes, $needle);

    if ($hits === 0) {
        if (substr_count($bytes, $replacement) === 1) {
            printf("  ok   %s: already applied (%s line endings)\n", $what, $eol === "\r\n" ? 'CRLF' : 'LF');

            return;
        }
        fwrite(STDERR, "FAIL: the {$what} needle matched nothing and its replacement is not present — {$path} was not touched\n");
        $failures++;

        return;
    }

    if ($hits > 1) {
        fwrite(STDERR, "FAIL: the {$what} needle matched {$hits} times, expected 1 — nothing was written to {$path}\n");
        $failures++;

        return;
    }

    if (file_put_contents($path, str_replace($needle, $replacement, $bytes)) === false) {
        fwrite(STDERR, "FAIL: the write to {$path} did not land\n");
        $failures++;

        return;
    }

    $after = (string) file_get_contents($path);
    $wrong = $eol === "\r\n"
        ? preg_match_all("/(?<!\r)\n/", $after)
        : substr_count($after, "\r\n");
    if ($wrong !== 0) {
        fwrite(STDERR, "FAIL: {$path} now carries {$wrong} line(s) in the ending this file does not use\n");
        $failures++;
    }

    printf("  ok   %s: matched once, %+d bytes, %s endings, %d stray-ending line(s)\n",
        $what, strlen($replacement) - strlen($needle), $eol === "\r\n" ? 'CRLF' : 'LF', $wrong);
}

$agentsOld = "and read every case. The report's four\n"
    ."  `Ledger —` lines (a fee sheet with a total and no lines, a paid step this run had to reconstruct, a returning\n"
    ."  claim with no year behind it, receipts exceeding the assessment) are the only view the tool has of its own\n"
    ."  output, and before lane 54 it repaired lane 53's two impossible states in silence.";
$agentsNew = "and read every case. The report prints seven `Ledger —` lines\n"
    ."  today: six since lane 54 and a seventh since lane 56 added the receipt it had to re-mark. The four this\n"
    ."  harness owns (a fee sheet with a total and no lines, a paid step this run had to reconstruct, a returning\n"
    ."  claim with no year behind it, receipts exceeding the assessment) are the only view the tool has of its own\n"
    ."  output, and before lane 54 it repaired lane 53's two impossible states in silence.";
edit($root.'/AGENTS.md', $agentsOld, $agentsNew, 'AGENTS.md report-count sentence', $failures);

$deployOld = "Since lane 54 the report prints four `Ledger —`\n"
    ."lines — a fee sheet with a total and no lines, a paid step this run had to reconstruct, a returning claim with no\n"
    ."year behind it, and receipts exceeding their sheet — and any one of them non-zero makes the run exit 1. Before\n";
$deployNew = "Since lane 54 the report has printed a `Ledger —`\n"
    ."line for each state it repairs: six then — a fee sheet with a total and no lines, a paid step this run had to\n"
    ."reconstruct, a returning claim with no year behind it, and receipts exceeding their sheet, beside the two it\n"
    ."already had — and a seventh since lane 56, the receipt it had to re-mark. Any one of them non-zero makes the\n"
    ."run exit 1. Before\n";
edit($root.'/DEPLOYMENT.md', $deployOld, $deployNew, 'DEPLOYMENT.md report-count sentence', $failures);

if ($failures > 0) {
    printf("\n%d edit(s) did not apply — read the files before believing either count.\n", $failures);
    exit(1);
}

echo "\nBoth markdown files now state the count the pipeline's own bytes carry (6 at HEAD, 7 in the working tree).\n";
exit(0);
