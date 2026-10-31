<?php

/**
 * Re-cut pass 333 so every figure the G-52 row states is READ from the census JSON rather than typed here.
 *
 * Why a patcher instead of direct edits: the pass's own strings carry typographic punctuation (curly apostrophe, em
 * dash, “Jr.”), and three consecutive needle edits on those bytes matched nothing. The replacement line is therefore
 * written with \u{2014}/\u{201C} escapes so this file's source stays ASCII while the bytes it writes into the
 * document are still exactly what the authority holds.
 *
 * Nothing is addressed by a remembered line ordinal — a replaced line would silently shift every index below it,
 * which is the lane 44 defect this repository already names. Each edit is a needle whose count is asserted, and a
 * needle that matches anything other than the number the patcher expects refuses the whole run before any write.
 *
 * Usage: php storage/app/scratch/lane108c-recut-pass333.php [--apply]
 */
$target = dirname(__DIR__).'/scratch/refresh-docx-pass333.php';
$apply = in_array('--apply', $argv, true);
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this patcher reads only --apply.\n");

        exit(1);
    }
}
$src = (string) file_get_contents($target);

// The `to` line of the debt sentence: identified by the figure it currently hard-codes, which only that line holds.
$debtNeedle = "'The same census read afterwards answers 13 rows";
$debtDistinct = '7 detail screens, 4 print paths and 2 legitimate editors';
if (substr_count($src, $debtNeedle) !== 2 || substr_count($src, $debtDistinct) !== 1) {
    fwrite(STDERR, sprintf("REFUSED: the debt sentence's needles are not the pair this patcher expects"
        ." (head %d, distinct-figure %d). The pass has already been re-cut, or its wording moved; re-read it.\n",
        substr_count($src, $debtNeedle), substr_count($src, $debtDistinct)));

    exit(1);
}
$debtTo = "        sprintf(\"The same census read afterwards answers %d rows \\u{2014} %d detail screens, %d print paths"
    ." and %d legitimate editors \\u{2014} which is the whole remaining debt, and"
    ." `tests/Feature/DeskQueue/DeskStudentLoadColumnsTest.php` holds it as a list that FAILS when a new bare load"
    ." appears and FAILS when a paid one stays in it, while `mutate-g52-desk-payload-tests.php` kills the leak and the"
    ." bad column by putting each shape back. %d of the detail screens and %d of the print paths also paint the name"
    ." SUFFIX, which a five-column list does not carry: the narrowing rewriter refuses any site whose page paints a"
    ." suffix rather than widening the set on its own, and the six-column name set is what those screens will get."
    ." The narrowing this batch already applied was then re-audited against the graph reader by"
    ." `lane108c-check-the-narrowed-lists.php`, which binds every narrowed column list to the page its own action"
    ." renders and refuses the run when that page names a column the list no longer carries; the audit states no count"
    ." in the register, because a reading of this box's scratch surface is not one the runner can reproduce.\","
    ." \$rowsN, \$detail, \$print, \$legit, \$sufDetail, \$sufPrint),";

// Replace the whole `to` line, from its opening quote to its trailing `),` on that line.
$debtLinePattern = "/^        'The same census read afterwards answers 13 rows — 7 detail screens.*$/m";
if (preg_match($debtLinePattern, $src) !== 1) {
    fwrite(STDERR, "REFUSED: the debt line does not match the shape this patcher replaces, so a partial rewrite"
        ." would leave the typed figures in the file.\n");

    exit(1);
}
$out = preg_replace($debtLinePattern, str_replace('$', '\\$', $debtTo), $src, 1);

// The population the row's first figure states becomes a named variable the sprintf reads.
$anchor = '$legit = count($json) - $detail - $print - ($kinds[\'LIST\'] ?? 0);';
if (substr_count($out, $anchor) !== 1) {
    fwrite(STDERR, "REFUSED: the census-reading anchor was found ".substr_count($out, $anchor)." times.\n");

    exit(1);
}
$out = str_replace($anchor, $anchor."\n\$rowsN = count(\$json);", $out);

// The suffix counts must be read from the JSON too, or the sentence's new numbers are typed again.
$sufAnchor = 'printf("census: %d row(s)';
if (substr_count($out, $sufAnchor) !== 1) {
    fwrite(STDERR, "REFUSED: the census printf anchor was found ".substr_count($out, $sufAnchor)." times.\n");

    exit(1);
}
$sufRead = "\n// The suffix split is read from the same rows, per kind: a five-column name list drops \"Jr.\", and the"
   ."\n// register states how many screens would need the six-column set. The census prints this tally beside its own"
   ."\n// split, because a figure a document attributes to a tool has to be a line that tool writes (lane 59).\n"
   ."\$sufDetail = 0;\n\$sufPrint = 0;\nforeach (\$json as \$r) {\n"
    ."    if (! empty(\$r['readsSuffix'])) {\n        if (\$r['kind'] === 'DETAIL') {\n            \$sufDetail++;\n"
    ."        } elseif (\$r['kind'] === 'PRINT') {\n            \$sufPrint++;\n        }\n    }\n}\n"
    ."if (\$sufDetail > \$detail || \$sufPrint > \$print) {\n"
    ."    fwrite(STDERR, \"REFUSED: more suffix-painting rows than rows of that kind.\\n\");\n\n    exit(1);\n}\n";
$out = preg_replace('/'.preg_quote($sufAnchor, '/').'/', str_replace('$', '\\$', $sufRead).'$0', $out, 1);

// The figure guard judged BOTH replacements, so edit 0 — the widening sentence, which asserts no split — refused a
// correct pass. It now judges only the row that carries the numbers, and the numbers it checks are the derived ones.
$guardOld = '    if (! str_contains($to, "{$detail} detail screens")';
if (substr_count($out, $guardOld) !== 1) {
    fwrite(STDERR, "REFUSED: the figure guard's opening line was found ".substr_count($out, $guardOld)." times.\n");

    exit(1);
}
$out = str_replace($guardOld, '    if ($i === 1 && (! str_contains($to, "{$detail} detail screens")', $out);
$guardTail = '|| ! str_contains($to, "{$legit} legitimate")) {';
if (substr_count($out, $guardTail) !== 1) {
    fwrite(STDERR, "REFUSED: the figure guard's tail was found ".substr_count($out, $guardTail)." times.\n");

    exit(1);
}
$out = str_replace($guardTail, '|| ! str_contains($to, "{$legit} legitimate"))) {', $out);

// And the new numbers must be the JSON's own, checked against the written sentence the same way.
$extraGuard = "\n    if (\$i === 1 && (! str_contains(\$to, \"{\$sufDetail} of the detail screens\")"
    ." || ! str_contains(\$to, \"{\$sufPrint} of the print paths\"))) {\n"
    ."        // A sentence that states a count the instrument did not answer is the defect this pass exists to refuse.\n"
    ."        \$failures[] = \"edit {\$i} does not carry the census's own suffix split ({\$sufDetail}/{\$sufPrint})\";\n"
    ."    }";
$guardEnd = '    if (preg_match(\'/\\.php:\\d/u\', $to) === 1) {';
if (substr_count($out, $guardEnd) !== 1) {
    fwrite(STDERR, "REFUSED: the citation-shape guard was found ".substr_count($out, $guardEnd)." times.\n");

    exit(1);
}
$out = str_replace($guardEnd, $extraGuard."\n\n".$guardEnd, $out);

if (! $apply) {
    // Print what the sentence will say, so the dry run is a read rather than a guess.
    if (preg_match('/sprintf\("The same census read afterwards[^\n]*/', $out, $m) === 1) {
        echo "planned debt sentence (abbreviated):\n  ".substr($m[0], 0, 300)."…\n\n";
    }
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}
if (file_put_contents($target, $out) === false) {
    fwrite(STDERR, "REFUSED: could not write {$target}.\n");

    exit(1);
}
echo "patched: the G-52 row's figures, its suffix split, and the guard that checks them all come from the census JSON.\n";
