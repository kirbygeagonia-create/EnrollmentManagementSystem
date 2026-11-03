<?php

/**
 * Teach `lane59-instrument-counts.php` the difference between a TOTAL and a MEMBERSHIP claim before the widened
 * document scope is allowed to judge anything.
 *
 * Binding the eleven `Documentation/*-ADS.md` deliverables found four STALE figures on its first run. Three of them
 * are the checker's own over-reach: "weighs both as two of its rules", "carries the same two rules", "carries the
 * same two boundaries as rules" state that two NAMED rules exist inside the audit — a membership claim — and the
 * binder matched the noun "rules" to the command's `Rules run: 20` total. A false STALE is not a harmless warning: it
 * sends a reader to re-type a sentence that is true, which is how lane 60's re-aged register row was born. The fourth
 * was a figure quoted out of a past-tense changelog line.
 *
 * So the classifier now reads the words AROUND the figure, the way lane 58 and lane 72 already do, and a membership
 * claim is counted and PRINTED as declined — an exemption nobody sees is an off switch.
 *
 * Usage: php storage/app/scratch/lane243-patch-59-membership.php
 */
$root = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem';
$f = $root.'/storage/app/scratch/lane59-instrument-counts.php';
$s = (string) file_get_contents($f);

$pairs = [
    // 1. the classifier, declared beside the other context classes
    [
        "// The figure plus up to three following words: the phrase, not the last noun, decides which reading answers it.\n\$claimRe = '/('.\$NUMNC.')((?:\\s+[A-Za-z§\\/&-]+){1,3})/';",
        "// A figure plus up to three following words: the phrase, not the last noun, decides which reading answers it.\n"
        ."\$claimRe = '/('.\$NUMNC.')((?:\\s+[A-Za-z§\\/&-]+){1,3})/';\n"
        ."// Membership, not total: \"two OF ITS rules\", \"the SAME two rules\", \"both AS rules\" all name which members\n"
        ."// exist inside a register; none states how many the register holds. The words sit BEFORE and AFTER the figure,\n"
        ."// so the sentence is searched rather than the phrase, and a claim of this shape is declined and counted rather\n"
        ."// than judged — the same discipline lane 58 applies to a class total and lane 72 to a comparison.\n"
        ."\$MEMBERSHIP_RE = '/\\b(of its|the same|as rules|as rule|rules listed|rules named|rules among)\\b/i';",
    ],
    // 2. the counter
    [
        "\$matched = 0;\n\$ciUnmeasured = [];",
        "\$matched = 0;\n\$membership = 0;\n\$ciUnmeasured = [];",
    ],
    // 3. the skip, placed where the phrase has just been taken off the figure
    [
        "                    \$part = null;\n                    foreach (\$labels as \$pattern => \$p) {",
        "                    if (preg_match(\$MEMBERSHIP_RE, \$sentence) === 1) {\n"
        ."                        \$membership++;\n"
        ."\n"
        ."                        continue;\n"
        ."                    }\n"
        ."                    \$part = null;\n"
        ."                    foreach (\$labels as \$pattern => \$p) {",
    ],
    // 4. the printed decline. The skip sits BEFORE $bound++, so a membership figure is invisible to every existing
    // tally — and an exemption no line reports is an off switch, which is the exact defect lane 68 met when a checker
    // tallied blind spots and printed none. The footer names the count and says it is a queue, not a clearance.
    [
        "    \$bound, \$judged, \$matched, \$stale, \$unbound, \$history);",
        "    \$bound, \$judged, \$matched, \$stale, \$unbound, \$history);\n"
        ."if (\$membership > 0) {\n"
        ."    printf(\"  DECLINED %d figure(s) name WHICH members a register holds, not how many the register holds.\n\"\n"
        ."        .\"           This binder answers totals, so these were neither judged nor cleared: read them.\\n\", \$membership);\n"
        ."}",
    ],
];

foreach ($pairs as $i => [$from, $to]) {
    $c = substr_count($s, $from);
    if ($c !== 1) {
        fwrite(STDERR, "REFUSED: pair ".($i + 1)." matched {$c} times, expected exactly 1 — the checker's bytes have aged.\n");
        exit(1);
    }
    $s = str_replace($from, $to, $s);
}

if (file_put_contents($f, $s) === false) {
    fwrite(STDERR, "REFUSED: the patch could not be written.\n");
    exit(1);
}
echo "patched lane59: membership claims are declined and counted, not judged\n";
