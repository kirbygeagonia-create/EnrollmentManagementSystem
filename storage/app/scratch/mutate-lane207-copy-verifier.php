<?php

/**
 * Falsifier for `lane207-verify-guide-copy.php`, the reader that answers "does the PAPER say what the answer file
 * says?". Every verdict it prints gets a plant:
 *
 *   control                    the delivered copy and answer file read VERIFIED, exit 0, with all twelve §IV rows
 *                              paired by name and agreeing;
 *   paper-un-ticks             `Demonstrated in test` is emptied on the paper for the rows that share that option line,
 *                              which is the owner's exact complaint. Eight concept rows carry that line verbatim, so
 *                              the leg asserts the RELATION (agree on 12 minus the rows it touched) rather than a
 *                              remembered number, and a one-row plant is impossible: a needle that occurs eight
 *                              times is not a needle;
 *   paper-ticks-N/A            the tick moves onto `N/A`, so a count-only census still sees exactly one ☑ per row and
 *                              the label pairing is the only thing that can see it;
 *   answers-un-ticks-one       the ANSWER file un-ticks one named concept, which must be reported as a label the
 *                              paper ticks alone, naming the concept;
 *   name-renamed / rows-gone   the answer file's concept is renamed, and separately stripped, so the pairing and the
 *                              population refusals both fire;
 *   sentinel-removed           the paper's defense answer is replaced, which the sentinel half must catch — the leg
 *                              that proves the sentinels read the document-wide text (a shadowed `$text` inside the
 *                              §IV loop once made this tool report its OWN sentinels ABSENT);
 *   unknown flag / missing copy / byte-identical copy.
 *
 * Plants go into copies under storage/app/scratch; the delivered artefacts are opened read-only and re-checked last.
 *
 * Usage: php storage/app/scratch/mutate-lane207-copy-verifier.php
 */
$root = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem';
$scratch = $root.'/storage/app/scratch';
$verifier = $scratch.'/lane207-verify-guide-copy.php';
$copyPath = $root.'/Documentation/ADS_Final_Project_Guide_EMS_ANSWERED.docx';
$answersPath = $root.'/Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md';
$made = [];
$bad = 0;
$legs = 0;

register_shutdown_function(static function () use ($scratch, &$made): void {
    foreach ($made as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    $left = glob($scratch.'/lane207h-*');
    if ($left !== false && $left !== []) {
        echo "\nBAD   planted copies survive: ".implode(', ', array_map('basename', $left))."\n";
    }
});

$run = static function (string $cmd) use ($root): array {
    $out = [];
    $code = 0;
    exec('cd /d '.$root.' && '.$cmd.' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};
$q = static fn (string $p): string => '"'.$p.'"';

$expect = static function (string $leg, array $want, string $cmd) use (&$bad, &$legs, $run): void {
    $legs++;
    [$code, $out] = $run($cmd);
    $fails = [];
    foreach ($want as $key => $rule) {
        if ($key === 'exit') {
            if (($rule === 'zero' ? $code !== 0 : $code === 0)) {
                $fails[] = 'exit '.($rule === 'zero' ? 'must be 0' : 'must be non-zero').', got '.$code;
            }

            continue;
        }
        if ($rule === 'absent') {
            if (str_contains($out, $key)) {
                $fails[] = 'the output must not carry ['.$key.']';
            }

            continue;
        }
        if (! str_contains($out, $rule)) {
            $fails[] = 'expected to read ['.$rule.']';
        }
    }
    if ($fails === []) {
        echo str_pad($leg, 28).'ok    '.($want['exit'] === 'zero' ? 'clean' : 'refused the plant')."\n";

        return;
    }
    echo str_pad($leg, 28)."BAD   ".implode('; ', $fails)."\n".wordwrap(substr($out, 0, 400), 92, "\n      ", true)."\n";
    $bad++;
};

$bodyOf = static function (string $docx) use (&$bad): ?string {
    $z = new ZipArchive;
    if ($z->open($docx) !== true) {
        $bad++;
        echo "BAD   {$docx} did not open as an archive; the harness can plant nothing\n";

        return null;
    }
    $x = (string) $z->getFromName('word/document.xml');
    $z->close();

    return $x;
};
$writeDocx = static function (string $srcDocx, string $dstDocx, string $xml) use (&$bad): bool {
    if (! copy($srcDocx, $dstDocx)) {
        $bad++;
        echo "BAD   the copy could not be cloned into {$dstDocx}\n";

        return false;
    }
    $z = new ZipArchive;
    if ($z->open($dstDocx, ZipArchive::CREATE) !== true) {
        $bad++;
        echo "BAD   the planted copy did not open for writing\n";

        return false;
    }
    $z->addFromString('word/document.xml', $xml);
    $z->close();

    return true;
};

echo "=== control ===\n";
$expect('control', ['VERIFIED: 0 window defect(s)' => 'VERIFIED: 0 window defect(s)',
    'ticked labels agree on 12' => 'ticked labels agree on 12', 'exit' => 'zero'],
    $q(PHP_BINARY).' '.$q($verifier));

$baseXml = $bodyOf($copyPath);
$baseMd = (string) file_get_contents($answersPath);
if ($baseXml === null || $baseMd === '') {
    exit(1);
}

// The paper stores this option line with DOUBLE spaces (the writer's inserted text keeps the form's own spacing), so
// the needle is the stored bytes, not the collapsed text the flat reader prints — the difference between a plant that
// lands and one that silently matches nothing.
$shared = '☑ Implemented  ☑ Demonstrated in test  ☐ N/A (explain)';
$nShared = substr_count($baseXml, $shared);
if ($nShared < 1) {
    echo str_pad('paper-needle-age', 28)."BAD   the copy holds no row reading [{$shared}]; re-cut this leg from the artefact\n";
    $bad++;
    $legs++;
} else {
    // (1) un-tick the demonstration box on the paper, on every row that spells it this way.
    $untick = '☑ Implemented  ☐ Demonstrated in test  ☐ N/A (explain)';
    $f1 = $scratch.'/lane207h-untick.docx';
    if ($writeDocx($copyPath, $f1, str_replace($shared, $untick, $baseXml))) {
        $made[] = $f1;
        $expect('paper-un-ticks', ['MISSING on the paper' => 'MISSING on the paper',
            'demonstrated in test' => 'demonstrated in test',
            'ticked labels agree on '.(12 - $nShared) => 'ticked labels agree on '.(12 - $nShared),
            'exit' => 'nonzero'], $q(PHP_BINARY).' '.$q($verifier).' --copy='.$q($f1));
    }

    // (2) the tick moved onto `N/A`: still three boxes and exactly one ☑ per row, which is what a count-only census sees.
    $swap = '☐ Implemented  ☐ Demonstrated in test  ☑ N/A (explain)';
    $f2 = $scratch.'/lane207h-swap.docx';
    if ($writeDocx($copyPath, $f2, str_replace($shared, $swap, $baseXml))) {
        $made[] = $f2;
        $expect('paper-ticks-N/A', ['TICKED on the paper only' => 'TICKED on the paper only', 'exit' => 'nonzero'],
            $q(PHP_BINARY).' '.$q($verifier).' --copy='.$q($f2));
    }
}

// (3) the ANSWER file un-ticks one named concept: the disagreement must name it. The guide numbers Indexing as Row 8
//     and Query Optimization as Row 9 — read from the file, not recalled from the paper's order.
$row9 = '**Row 8 — Indexing.** ☑ Implemented ☑ Query plan/performance test ☐ N/A';
if (substr_count($baseMd, $row9) !== 1) {
    echo str_pad('answers-needle-age', 28)."BAD   the answer file does not spell Row 9 as [{$row9}]; re-cut this leg from the file\n";
    $bad++;
    $legs++;
} else {
    $f3 = $scratch.'/lane207h-answersuntick.md';
    file_put_contents($f3, str_replace($row9,
        '**Row 9 — Indexing.** ☑ Implemented ☐ Query plan/performance test ☐ N/A', $baseMd));
    $made[] = $f3;
    $expect('answers-un-ticks-one', ['TICKED on the paper only' => 'TICKED on the paper only',
        'query plan/performance test' => 'query plan/performance test',
        'ticked labels agree on 11' => 'ticked labels agree on 11', 'exit' => 'nonzero'],
        $q(PHP_BINARY).' '.$q($verifier).' --answers='.$q($f3));
}

// (4) a renamed concept: the pairing is by NAME, so twelve names must not all be found.
$row5 = '**Row 5 — Locking.**';
if (substr_count($baseMd, $row5) !== 1) {
    echo str_pad('rename-needle-age', 28)."BAD   the answer file holds {$row5} more than once or not at all; re-cut from the file\n";
    $bad++;
    $legs++;
} else {
    $f4 = $scratch.'/lane207h-renamed.md';
    file_put_contents($f4, str_replace($row5, '**Row 5 — Locking Behaviour.**', $baseMd));
    $made[] = $f4;
    $expect('name-renamed', ['is not one of the answer' => 'is not one of the answer', 'exit' => 'nonzero'],
        $q(PHP_BINARY).' '.$q($verifier).' --answers='.$q($f4));
}

// (5) the §IV rows stripped: a population refusal, not a claim about the paper.
$stripped = (string) preg_replace('/^\*\*Row \d+ — .*$/m', '', $baseMd);
$f5 = $scratch.'/lane207h-norows.md';
file_put_contents($f5, $stripped);
$made[] = $f5;
$expect('rows-gone-refuses-population', ['expected 12' => 'expected 12', 'exit' => 'nonzero'],
    $q(PHP_BINARY).' '.$q($verifier).' --answers='.$q($f5));

// (6) the defense-answer sentinel removed from the paper.
$needle = 'Defense answer:';
$at = strpos($baseXml, $needle);
if ($at === false) {
    echo str_pad('sentinel-needle-age', 28)."BAD   the copy prints no [{$needle}] at all — read the artefact before blaming the verifier\n";
    $bad++;
    $legs++;
} else {
    $f6 = $scratch.'/lane207h-nosentinel.docx';
    if ($writeDocx($copyPath, $f6, substr_replace($baseXml, 'Defense replacer:', $at, strlen($needle)))) {
        $made[] = $f6;
        $expect('sentinel-removed', ['ABSENT' => 'ABSENT', 'exit' => 'nonzero'],
            $q(PHP_BINARY).' '.$q($verifier).' --copy='.$q($f6));
    }
}

// (7) refusals by name, and a byte-identical copy that must change nothing.
$expect('unknown-flag', ['REFUSED: this tool does not read the flag' => 'REFUSED: this tool does not read the flag',
    'exit' => 'nonzero'], $q(PHP_BINARY).' '.$q($verifier).' --no-such-flag-at-all');
$expect('missing-copy', ['is not on disk' => 'is not on disk', 'exit' => 'nonzero'],
    $q(PHP_BINARY).' '.$q($verifier).' --copy='.$q($scratch.'/lane207h-absent.docx'));
$f7 = $scratch.'/lane207h-clean.docx';
if ($writeDocx($copyPath, $f7, $baseXml)) {
    $made[] = $f7;
    $expect('identical-copy-agrees', ['VERIFIED: 0 window defect(s)' => 'VERIFIED: 0 window defect(s)',
        'ticked labels agree on 12' => 'ticked labels agree on 12', 'exit' => 'zero'],
        $q(PHP_BINARY).' '.$q($verifier).' --copy='.$q($f7));
}

echo "\n=== control again, after every plant ===\n";
$expect('control-still-green', ['VERIFIED: 0 window defect(s)' => 'VERIFIED: 0 window defect(s)', 'exit' => 'zero'],
    $q(PHP_BINARY).' '.$q($verifier));

echo "\n";
echo $bad === 0 ? "ALL KILLED: {$legs} leg(s), 0 bad\n" : "{$bad} BAD of {$legs} leg(s)\n";
exit($bad === 0 ? 0 : 1);
