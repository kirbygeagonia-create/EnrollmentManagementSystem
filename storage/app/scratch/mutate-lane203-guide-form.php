<?php

/**
 * Falsifier for `lane203-fill-guide-form.php`, the writer that fills the school's ADS guide form.
 *
 * Every leg plants its defect in a THROWAWAY COPY of the form and of the answer file and hands those copies to
 * the writer through `--guide=` / `--answers=`. A harness that mutated the tracked answer file would leave its
 * own next measurement reading a tree that already carries the defect, and the school's blank form must never
 * be the thing a test edits. Each leg also writes to its OWN output path, so a file one leg left behind cannot
 * make the next refuse "the output already exists" for a reason it never claimed.
 *
 * Each leg requires exit 1 AND the sentence that names its own cause: a refusal that prints a different
 * sentence is a different refusal that happens to share the status code. The control runs first, on pristine
 * copies, and re-reads the form's hash afterwards, because a writer that edits the form it was handed has
 * destroyed the school's original.
 *
 * WITHHELD, and the reason is the record: the window guard (an applicability row outside its own heading's
 * span) has no plant that keeps both counts at 24 while breaking the pairing — swapping two rows leaves each
 * inside some window. That leg would be decoration, so it is not here.
 *
 * Usage: php storage/app/scratch/mutate-lane203-guide-form.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$exe = PHP_BINARY;
$self = $root.'/storage/app/scratch/lane203-fill-guide-form.php';
$srcGuide = 'C:/Users/ADMIN/Downloads/ADS_Final_Project_Guide_EMS_updated.docx';
$srcAnswers = $root.'/Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md';
$dir = $root.'/storage/app/scratch/lane203-fixtures';

if (! is_file($srcGuide)) {
    fwrite(STDERR, "REFUSED: the school's form {$srcGuide} is not on disk; nothing can be planted against a missing fixture.\n");
    exit(1);
}
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}
foreach ((glob($dir.'/lane203-*') ?: []) as $f) {
    if (is_file($f)) {
        unlink($f);
    }
}

$made = [];
register_shutdown_function(static function () use ($dir): void {
    foreach ((glob($dir.'/lane203-*') ?: []) as $f) {
        if (is_file($f)) {
            echo 'LEFTOVER: '.basename($f)."\n";
        }
    }
});

$bad = 0;
$legs = 0;

$fresh = static function (string $tag) use ($srcGuide, $srcAnswers, $dir): array {
    $g = $dir.'/lane203-'.$tag.'-guide.docx';
    $a = $dir.'/lane203-'.$tag.'-answers.md';
    $o = $dir.'/lane203-'.$tag.'-out.docx';
    copy($srcGuide, $g);
    copy($srcAnswers, $a);

    return [$g, $a, $o];
};

$docOf = static function (string $file): string {
    $z = new ZipArchive;
    if ($z->open($file) !== true) {
        throw new RuntimeException("fixture {$file} did not open as an archive");
    }
    $x = (string) $z->getFromName('word/document.xml');
    $z->close();

    return $x;
};

$putDoc = static function (string $file, string $xml): void {
    $z = new ZipArchive;
    if ($z->open($file) !== true) {
        throw new RuntimeException("fixture {$file} did not open for writing");
    }
    $z->addFromString('word/document.xml', $xml);
    $z->close();
};

$run = static function (string $args) use ($exe): array {
    $out = [];
    exec('"'.$exe.'" '.$args.' 2>&1', $out, $code);

    return [(int) $code, implode("\n", $out)];
};

$landed = static function (string $name, bool $ok, string $why) use (&$bad, &$legs): bool {
    $legs++;
    if (! $ok) {
        echo str_pad($name, 34).'BAD   '.$why."\n";
        $bad++;

        return false;
    }

    return true;
};

$expectRefusal = static function (string $name, string $needle, string $args) use ($run, &$bad, &$legs): void {
    $legs++;
    [$code, $out] = $run($args);
    if ($code === 0) {
        echo str_pad($name, 34)."BAD   exited 0; expected a refusal\n";
        $bad++;

        return;
    }
    if (! str_contains($out, $needle)) {
        echo str_pad($name, 34)."BAD   exit {$code} but did not print:\n        {$needle}\n        got: "
            .str_replace("\n", ' / ', mb_substr($out, 0, 240))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 34)."ok    exit {$code}, refused by name\n";
};

$q = static fn (string $s): string => '"'.$s.'"';

// ---------------------------------------------------------------- 1. control: it writes, and leaves the form alone
[$cg, $ca, $co] = $fresh('control');
$shaBefore = hash_file('sha256', $cg);
$legs++;
[$code, $out] = $run($q($self).' --guide="'.$cg.'" --answers="'.$ca.'" --out="'.$co.'" --apply');
if ($code !== 0 || ! str_contains($out, 'WROTE')) {
    echo str_pad('control-writes', 34)."BAD   exit {$code}: ".str_replace("\n", ' / ', mb_substr($out, 0, 260))."\n";
    $bad++;
} elseif (! preg_match('/expected (\d+) ticks placed/', $out, $m)) {
    echo str_pad('control-writes', 34)."BAD   the run printed no tick expectation to weigh\n";
    $bad++;
} else {
    $want = (int) $m[1];
    $xml = $docOf($co);
    $got = substr_count($xml, '☑');
    if ($want < 36) {
        echo str_pad('control-writes', 34)."BAD   the plan selected {$want} ticks for 36 rows, so a row goes unticked\n";
        $bad++;
    } elseif ($got !== $want) {
        echo str_pad('control-writes', 34)."BAD   the copy holds {$got} ☑ while the plan said {$want}\n";
        $bad++;
    } elseif (hash_file('sha256', $cg) !== $shaBefore) {
        echo str_pad('control-writes', 34)."BAD   the writer changed the FORM it was given\n";
        $bad++;
    } elseif (! str_contains($xml, 'Defense answer:') || ! str_contains($xml, 'Kirby A. Geagonia')) {
        echo str_pad('control-writes', 34)."BAD   the copy is missing the §VI or §VII answer text\n";
        $bad++;
    } else {
        echo str_pad('control-writes', 34)."ok    {$got} ☑ placed, input form unchanged, §VI and §VII text present\n";
    }
}

// ---------------------------------------------------------------- 2. a flag the tool does not read is refused by name
$expectRefusal('flag-refused', 'REFUSED: unreadable argument --no-such-flag-at-all', $q($self).' --no-such-flag-at-all');

// ---------------------------------------------------------------- 3. the answer file short one problem
[$g, $a, $o] = $fresh('shortproblem');
$md = (string) file_get_contents($a);
$cut = (string) preg_replace('/^### Problem 7 — .*$/m', '', $md, 1);
if ($landed('problem-count', $cut !== $md, 'the plant matched no `### Problem 7 —` heading')) {
    file_put_contents($a, $cut);
    $expectRefusal('problem-count', 'the answer file parsed 23 problems, expected 24',
        $q($self).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
}

// ---------------------------------------------------------------- 4. a problem marks two applicability options
[$g, $a, $o] = $fresh('twoticks');
$md = (string) file_get_contents($a);
$one = '**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A';
if ($landed('applic-two-ticks', str_contains($md, $one), 'the answer file no longer spells Problem 1 the way this plant expects')) {
    file_put_contents($a, str_replace($one, '**Applicability:** ☑ Applies to our system ☑ Partially applies ☐ N/A', $md));
    $expectRefusal('applic-two-ticks', 'marks 2 options ☑, expected exactly 1',
        $q($self).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
}

// ---------------------------------------------------------------- 5. a verdict word the form has no box for
[$g, $a, $o] = $fresh('unknownoption');
$md = (string) file_get_contents($a);
if ($landed('option-not-on-form', str_contains($md, $one), 'the plant needle is not in the answer file')) {
    file_put_contents($a, str_replace($one, '**Applicability:** ☑ Applies to our whole workflow ☐ Partially applies ☐ N/A', $md));
    $expectRefusal('option-not-on-form', 'the form offers `Applies to our system` but the answer file names no option',
        $q($self).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
}

// ---------------------------------------------------------------- 6. a concept row whose name is not the guide's
[$g, $a, $o] = $fresh('conceptname');
$md = (string) file_get_contents($a);
$name3 = '**Row 3 — ACID Properties.**';
if ($landed('concept-name-mismatch', str_contains($md, $name3), 'the plant needle is not in the answer file')) {
    file_put_contents($a, str_replace($name3, '**Row 3 — Locking.**', $md));
    $expectRefusal('concept-name-mismatch',
        "the answer file names `Locking` where the guide's option row number 3 sits under the name `ACID Properties`",
        $q($self).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
}

// ---------------------------------------------------------------- 7. a concept verdict the guide's own row cannot show
[$g, $a, $o] = $fresh('conceptnobox');
$md = (string) file_get_contents($a);
$acid = $name3.' ☐ Implemented ☑ Explained ☑ Demonstrated in test ☐ N/A';
if ($landed('concept-no-box-for-verdict', str_contains($md, $acid), 'the plant needle is not in the answer file')) {
    // The guide's ACID row holds no `Implemented` box, so moving the tick onto that word must stop the run —
    // this is precisely what ticking by position would have done silently.
    file_put_contents($a, str_replace($acid, $name3.' ☑ Implemented ☐ Explained ☐ Demonstrated in test ☐ N/A', $md));
    $expectRefusal('concept-no-box-for-verdict', 'concept row 3 (ACID Properties) marks no box ☑ in the answer file',
        $q($self).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
}

// ---------------------------------------------------------------- 8. the §V answer list short one item
[$g, $a, $o] = $fresh('shortfive');
$md = (string) file_get_contents($a);
$hit = (string) preg_replace('/^- \*Test scenario:\*[^\n]*(\n(?!- \*|\*\*|#)[^\n]*)*/m', '', $md, 1);
if ($landed('section-V-short', $hit !== $md, 'the plant matched no `- *Test scenario:*` sub-item')) {
    file_put_contents($a, $hit);
    $expectRefusal('section-V-short', 'the answer file holds 24 §V sub-item answers',
        $q($self).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
}

// ---------------------------------------------------------------- 9. a §VII line with no underscores left to fill
[$g, $a, $o] = $fresh('nounder');
$xml = $docOf($g);
$found = preg_match('#<w:p[ ](?:(?!</w:p>).)*?Student 1:(?:(?!</w:p>).)*?</w:p>#s', $xml, $p1, PREG_OFFSET_CAPTURE);
$ok9 = $found === 1 && str_contains($p1[0][0], '____');
if ($landed('fill-no-underscores', $ok9, 'the Student 1 paragraph or its underscore run is not readable in the fixture')) {
    $fixed = (string) preg_replace('/_{2,}/', 'the name the school will type', $p1[0][0], 1);
    $putDoc($g, (string) substr_replace($xml, $fixed, (int) $p1[0][1], strlen($p1[0][0])));
    $expectRefusal('fill-no-underscores', 'holds no run of two or more underscores',
        $q($self).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
}

// ---------------------------------------------------------------- 10. a §VII label that appears twice
[$g, $a, $o] = $fresh('twolabels');
$xml = $docOf($g);
$found = preg_match('#<w:p[ ](?:(?!</w:p>).)*?Instructor:(?:(?!</w:p>).)*?</w:p>#s', $xml, $p2, PREG_OFFSET_CAPTURE);
if ($landed('fill-label-duplicated', $found === 1, 'no Instructor paragraph readable in the fixture')) {
    // Duplicate the paragraph verbatim beside itself: the writer addresses each fill-in by a line only it owns,
    // so two copies must stop the run rather than let it paint the wrong one.
    $putDoc($g, (string) substr_replace($xml, $p2[0][0], (int) $p2[0][1], 0));
    $expectRefusal('fill-label-duplicated', 'the §VII label Instructor appears in 2 paragraphs',
        $q($self).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
}

// ---------------------------------------------------------------- 11. the read-back catches the writer's own retired modifier
[$g, $a, $o] = $fresh('blocktitle');
$copyTool = $dir.'/lane203-blocktitle-writer.php';
$srcTool = (string) file_get_contents($self);
$live = "if (! preg_match_all('/^### Problem (\\d+) — (.*)\$/m', \$md, \$hb, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {";
$plantLine = "if (! preg_match_all('/^### Problem (\\d+) — (.*?)(?=^### Problem \\d+ |^## IV\\.)/ms', \$md, \$hb, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {";
if ($landed('readback-catches-block-title', str_contains($srcTool, $live), 'the writer no longer carries the heading parse this plant replaces')) {
    // The `s` modifier re-added lets the "title" capture cross newlines, so every paper line built from it
    // re-inserts the answer file's own `☐ Applies to our system` row — the defect this writer really met.
    file_put_contents($copyTool, str_replace($live, $plantLine, $srcTool));
    $expectRefusal('readback-catches-block-title', 'the copy answers 48 applicability rows',
        $q($copyTool).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
}

// ---------------------------------------------------------------- 12. a required analysis field goes missing
[$g, $a, $o] = $fresh('nofield');
$md = (string) file_get_contents($a);
$at12 = strpos($md, "\n**Evidence:** ");
if ($landed('field-required', $at12 !== false, 'no `**Evidence:** ` field readable in the answer file')) {
    $lineEnd = strpos($md, "\n**", $at12 + 1);
    if ($lineEnd === false) {
        echo str_pad('field-required', 34)."BAD   the Evidence line has no following bold label to cut against\n";
        $bad++;
    } else {
        // Delete the whole field, not only its text: the writer must refuse rather than print a cell the guide asks for.
        file_put_contents($a, substr($md, 0, $at12).substr($md, $lineEnd));
        $expectRefusal('field-required', 'the answer file carries no Evidence, so the form would show a field the guide asks for',
            $q($self).' --guide="'.$g.'" --answers="'.$a.'" --out="'.$o.'" --apply');
    }
}

// ---------------------------------------------------------------- 13-14. the window verifier, both directions
// `lane207` proves the delivered copy says the right thing UNDER each problem, so its positive control is the
// unanswered original: every window must come back short. A reader that also clears a blank form certifies anything.
$legs++;
[$code, $out] = $run($q($root.'/storage/app/scratch/lane207-verify-guide-copy.php').' --copy="'.$srcGuide.'"');
if ($code === 0) {
    echo str_pad('verifier-refuses-blank-form', 34)."BAD   the window verifier cleared the school's UNANSWERED form\n";
    $bad++;
} elseif (! str_contains($out, 'REFUSED') || preg_match('/(\d+) window defect/', $out, $dm) !== 1 || (int) $dm[1] < 20) {
    echo str_pad('verifier-refuses-blank-form', 34).'BAD   exit '.$code.' but reported '.($dm[1] ?? 'no').' defects, expected at least 20'."\n";
    $bad++;
} else {
    echo str_pad('verifier-refuses-blank-form', 34)."ok    exit {$code}, ".$dm[1].' defects on the blank form'."\n";
}
$legs++;
[$code, $out] = $run($q($root.'/storage/app/scratch/lane207-verify-guide-copy.php'));
if ($code !== 0 || ! str_contains($out, 'VERIFIED')) {
    echo str_pad('verifier-clears-copy', 34)."BAD   exit {$code}: ".str_replace("\n", ' / ', mb_substr($out, -260))."\n";
    $bad++;
} else {
    echo str_pad('verifier-clears-copy', 34)."ok    the delivered copy reads verified window by window\n";
}

foreach ((glob($dir.'/lane203-*') ?: []) as $f) {
    if (is_file($f)) {
        unlink($f);
    }
}
printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
