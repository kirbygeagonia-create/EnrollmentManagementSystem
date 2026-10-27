<?php

/**
 * Mutation-proof for lane37-section-reference-sweep.php.
 *
 * A sweep that reports 0 problems has either checked every reference or cannot fail, and the two look identical
 * from here. So each verdict the sweep can give is provoked once, in a scratch copy of the document — the real
 * file is never opened for writing — and the unmutated copy has to stay clean in the same run.
 *
 *   A  wrong-chapter   a citation retargeted at another register  ("§25 P-14" → "§28 P-14") — the defect this
 *                      sweep exists to catch, because P-14's row lives in §25.4 and §28 has no such row.
 *   B  missing-section a pointer to a section that is not there   ("§12.1" → "§12.91").
 *   C  missing-id      the register row renamed, the citations left alone (only the row's own cell changes:
 *                      `>P-14<` occurs exactly once, while the string appears in prose many times).
 *   D  titled-escape   §19.3 is *titled* "…off by one, and are corrected (N-2)", which is the one acceptance
 *                      rule that could swallow a real defect; retitle it and "§19.3 (N-2)" must become
 *                      WRONG-LOCATION. Its needle is a run tail, not the sentence: the heading is split after
 *                      "one," so the whole phrase occurs 0 times in document.xml while the flat dump reads it
 *                      continuously — which is how this leg reported "cannot be built" for a mutant that was
 *                      always plantable.
 *   E  prompt-cue      a bare §number the document does not hold is only a defect when the sentence names no
 *                      other book. `prompt` joined the cue list when the §1.1 record row began citing
 *                      prompt §6.4 and prompt §7.9, so plant a section neither book has (prompt §91.4) and
 *                      require silence: the leg goes SURVIVED if the word leaves the list again.
 *   F  cue-removed     the same bytes with the word `prompt` taken out of that paragraph (all four copies of
 *                      it, counted before the write): the same nonexistent section must now fire. E and F
 *                      differ by one word in one paragraph, so together they prove the exemption is cue-driven
 *                      and not a blanket pardon for whichever paragraph holds a citation.
 *
 * usage: php storage/app/scratch/mutate-lane37-sweep.php
 */

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$real = $root.'/Documentation/EMS Complete Documentation.docx';
$sweep = str_replace('\\', '/', $root.'/storage/app/scratch/lane37-section-reference-sweep.php');
$scratchDir = $root.'/storage/app/scratch/mut-lane37';

if (! is_dir($scratchDir)) {
    mkdir($scratchDir, 0777, true);
}

$xmlOf = function (string $file): string {
    $z = new ZipArchive();
    $z->open($file);
    $x = $z->getFromName('word/document.xml');
    $z->close();

    return $x;
};

$write = function (string $file, string $xml) use ($real): void {
    copy($real, $file);
    $z = new ZipArchive();
    if ($z->open($file, ZipArchive::CREATE) !== true) {
        fwrite(STDERR, "cannot open {$file} for updating\n");
        exit(1);
    }
    $z->addFromString('word/document.xml', $xml);
    $z->close();
    $check = new ZipArchive();
    $check->open($file);
    $parts = count($check);
    if ($check->getFromName('word/document.xml') !== $xml || $parts !== 14) {
        fwrite(STDERR, "the mutant copy lost parts ({$parts}) — the harness itself is broken\n");
        exit(1);
    }
    $check->close();
};

$runSweep = function (string $file) use ($php, $sweep): array {
    $out = shell_exec(escapeshellarg(str_replace('/', '\\', $php)).' '.escapeshellarg($sweep).' '.escapeshellarg($file).' 2>&1');
    preg_match('/NO-SECTION (\d+), NO-ID (\d+), WRONG-LOCATION (\d+)/', (string) $out, $m);
    if (! isset($m[1])) {
        return [-1, -1, -1, (string) $out];
    }

    return [(int) $m[1], (int) $m[2], (int) $m[3], (string) $out];
};

$fails = 0;
$legs = 0;

/**
 * BAD and SURVIVED are different findings and must not print the same word.
 *
 * SURVIVED means the sweep looked at the mutant and called it clean — the defect this harness exists to find.
 * BAD means the harness itself never reached a verdict: the sweep printed no tally, or a needle the leg is
 * built from is gone from the document. Reporting either as SURVIVED would blame the checker for the harness's
 * own age, which is how a mutant that cannot apply is mistaken for a rule that does not fire.
 */
$verdict = function (string $name, string $state, string $detail) use (&$fails, &$legs): void {
    $legs++;
    printf("  %-9s %-20s %s\n", $state, $name, $detail);
    if ($state !== 'PASS') {
        $fails++;
    }
};

$base = $xmlOf($real);
echo "=== lane 37 sweep mutation-proof (the real document is only ever read) ===\n";

// The same list the sweep carries, read from the sweep's own source rather than restated here: a harness with
// its own copy of a checker's vocabulary drifts from it and then judges a rule that no longer exists.
if (! preg_match('/\$otherDocCues\s*=\s*\[(.*?)\];/s', (string) file_get_contents($sweep), $cm)) {
    fwrite(STDERR, "REFUSED: cannot read \$otherDocCues out of lane37-section-reference-sweep.php — the cue legs would judge a list nobody maintains.\n");
    exit(1);
}
preg_match_all("/'([a-z][a-z -]*)'/", $cm[1], $cueMatches);
$cueWords = $cueMatches[1];
if ($cueWords === []) {
    fwrite(STDERR, "REFUSED: the cue list parsed to nothing from the sweep's own array.\n");
    exit(1);
}
printf("cue words the sweep reads (%d): %s\n\n", count($cueWords), implode(', ', $cueWords));

// control — the shipped document, copied exactly, must stay clean
$control = $scratchDir.'/control.docx';
$write($control, $base);
[$noSec, $noId, $wrong, $out] = $runSweep($control);
// Read the verdicts, not the word "checked": the sweep prints that word twice (its header and its summary),
// so an assertion on how often it appears is a test of the wording, not of the document.
if ($noSec < 0) {
    $verdict('control-clean', 'BAD', 'the sweep printed no tally for the unmutated copy: '.substr($out, 0, 200));
} else {
    $verdict('control-clean', $noSec === 0 && $noId === 0 && $wrong === 0 ? 'PASS' : 'BAD',
        'NO-SECTION '.$noSec.', NO-ID '.$noId.', WRONG-LOCATION '.$wrong);
}

/**
 * Each case: steps (paired needles, applied in order, every one pre-counted), the verdict that must answer,
 * and `fire` — true when that verdict must be non-zero, false when the mutant must stay clean.
 */
$cases = [
    'A wrong-chapter' => [
        [['§25 P-14', '§28 P-14']], 'wrong', true,
        'the cited chapter no longer holds the row',
    ],
    'B missing-section' => [
        [['§12.1', '§12.91']], 'noSec', true,
        'the pointer names a section that is not there',
    ],
    'C missing-id' => [
        [['>P-14<', '>P-14x<']], 'noId', true,
        'the register row was renamed under its citations',
    ],
    // The tail of the heading's last run, so the citation keeps naming the id it leans on while the heading
    // stops carrying it. The full sentence is three runs in the file and cannot be a needle.
    'D titled-escape' => [
        [[' and are corrected (N-2)</w:t>', ' and are corrected (N-9)</w:t>']], 'wrong', true,
        'the heading no longer carries the finding the citation leans on',
    ],
    // The cue list is what lets `prompt §91.4` pass; §91 exists in neither book, so only the word saves it.
    'E prompt-cue' => [
        [['prompt §6.4', 'prompt §91.4']], 'noSec', false,
        'a section the document does not hold, beside the word `prompt`, is not this document\'s defect',
    ],
    'F cue-removed' => [
        [
            ['prompt §6.4', '§91.4'],
            ['prompt', 'booklet'],
        ], 'noSec', true,
        'the same paragraph with no cue word left in it must charge the same missing section',
    ],
];

/** The `<w:p>` holding a phrase, flattened the way the sweep flattens its blocks. */
$paragraphOf = function (string $xml, string $needle) use ($cueWords): array {
    $p = strpos($xml, $needle);
    if ($p === false) {
        return [null, []];
    }
    $start = max((int) strrpos(substr($xml, 0, $p), '<w:p '), (int) strrpos(substr($xml, 0, $p), '<w:p>'));
    $end = strpos($xml, '</w:p>', $p);
    preg_match_all('#<w:t(?:\s[^>]*)?>(.*?)</w:t>#s', substr($xml, $start, $end - $start), $tm);
    $text = html_entity_decode(implode(' ', $tm[1]), ENT_QUOTES | ENT_HTML5);
    $cues = array_values(array_filter($cueWords, fn ($c) => stripos($text, $c) !== false));

    return [$text, $cues];
};

foreach ($cases as $name => [$steps, $which, $fire, $why]) {
    $file = $scratchDir.'/'.strtolower(str_replace(' ', '-', $name)).'.docx';
    $missing = [];
    foreach ($steps as [$from, $to]) {
        if (($c = substr_count($base, $from)) < 1) {
            $missing[] = "[{$from}]";
        }
    }
    if ($missing !== []) {
        $verdict($name, 'BAD', 'needle(s) '.implode(', ', $missing).' not contiguous in document.xml — the mutant cannot be built');

        continue;
    }
    $xml = $base;
    foreach ($steps as [$from, $to]) {
        $xml = str_replace($from, $to, $xml);
    }
    if ($xml === $base) {
        $verdict($name, 'BAD', 'every needle was found but the mutation left the bytes identical');

        continue;
    }
    // F must leave the paragraph holding the planted section with no cue at all, or its verdict would say
    // nothing about the cue list — the word may sit anywhere in the same block, so the block is what is tested.
    if ($name === 'F cue-removed') {
        [$paraText, $cuesLeft] = $paragraphOf($xml, '§91.4');
        if ($cuesLeft !== []) {
            $verdict($name, 'BAD', 'the paragraph still carries a cue ('.implode(', ', $cuesLeft).') — '.
                substr($paraText, 0, 90).'…');

            continue;
        }
        if ($paraText === null) {
            $verdict($name, 'BAD', 'the planted §91.4 is not in the mutated bytes');

            continue;
        }
    }
    // E's premise is the same paragraph WITH its cue: prove the exemption is being read, not merely accepted.
    if ($name === 'E prompt-cue') {
        [, $cuesHere] = $paragraphOf($xml, 'prompt §91.4');
        if (! in_array('prompt', $cuesHere, true)) {
            $verdict($name, 'BAD', 'the paragraph holding `prompt §91.4` does not carry the word the sweep is meant to read');

            continue;
        }
    }
    $write($file, $xml);
    [$ns, $ni, $wl] = $runSweep($file);
    if ($ns < 0) {
        $verdict($name, 'BAD', 'the sweep printed no tally for this mutant');

        continue;
    }
    $counts = ['noSec' => $ns, 'noId' => $ni, 'wrong' => $wl];
    $fired = $counts[$which] > 0;
    $ok = $fired === $fire;
    // The mutant must be caught by the verdict it targets and not merely by *some* noise.
    $verdict($name, $ok ? 'PASS' : 'SURVIVED', sprintf(
        '%d step(s); NO-SECTION %d, NO-ID %d, WRONG-LOCATION %d — expected %s %s — %s',
        count($steps), $ns, $ni, $wl, $which, $fire ? 'to fire' : 'silent', $why
    ));
}

// A leg that never reached a verdict is a leg the reader would otherwise count as clean.
$expected = count($cases) + 1;
if ($legs !== $expected) {
    printf("\nBAD: %d leg(s) reported for %d expected (control + %d cases) — the run is incomplete.\n", $legs, $expected, count($cases));
    exit(1);
}

printf("\n%s\n", $fails === 0
    ? 'every rule can fail, and the unmutated document is clean under the same run'
    : "{$fails} of {$legs} leg(s) did not behave — the sweep's silence proves nothing until each is explained");
exit($fails === 0 ? 0 : 1);
