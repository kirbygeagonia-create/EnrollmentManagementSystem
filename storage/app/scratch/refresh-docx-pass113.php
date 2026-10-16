<?php

/**
 * Pass 113 — the citations this batch moved, and the ruling it adds.
 *
 * bootstrap/app.php gained one `use` line at the top and a seventeen-line render callback in the middle, so
 * every citation into it drifted — and the sweep tool cannot see the second half of that: it proves a range
 * is in bounds and not blank, and 47-60 still satisfies both while pointing at shouldRenderJsonWhen and the
 * first lines of a callback. Each was re-measured by meaning, from the sentence beside it:
 *
 *   1. bootstrap/app.php:47-60 (three places: the E-2 edge-case row, §25's P-row, and the Registrar Approve
 *      row) — all three say the state machine's refusal is "rendered as the generic stage message". That is
 *      the InvalidStateTransitionException callback, now 56-69.
 *   2. bootstrap/app.php:148-161 — the sentence about a server with no browser whose refusal "reaches the
 *      desk as the usual calm message naming both fixes": the PrintRendererMissing callback, now 165-178.
 *   3. One sentence added to the college-boundary paragraph, because the batch changed what the desk of the
 *      refused roster actually reads. That paragraph is where the refusal was ruled on 2026-10-13, and the
 *      old text is now incomplete: the boundary refused, and refused *by name*, but the name never arrived.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$edits = [];

$refuse = function (string $why) use (&$fails): void {
    fwrite(STDERR, "REFUSE: {$why}\n");
    $fails++;
};

/** Every occurrence of an anchor becomes its own edit at its own offset. */
$swapAll = function (string $from, string $to, string $label, int $expect, array $asserts) use ($xml, &$edits, $root, &$refuse): void {
    $n = substr_count($xml, $from);
    if ($n !== $expect) {
        $refuse("{$label}: anchor appears {$n} time(s), expected {$expect}");

        return;
    }

    // Verify the new range by reading the file, once — every occurrence cites the same construct.
    [, $range] = explode(':', $to, 2);
    $nums = array_map('intval', explode('-', $range));
    $path = $root.'/'.explode(':', $from)[0];
    if (! is_file($path)) {
        $refuse("{$label}: cannot locate {$path}");

        return;
    }
    $lines = file($path);
    $span = implode(' ', array_map('trim', array_slice($lines, $nums[0] - 1, ($nums[1] ?? $nums[0]) - $nums[0] + 1)));
    foreach ($asserts as $must) {
        if (! str_contains($span, $must)) {
            $refuse("{$label}: the new range {$to} does not hold 「{$must}」");
        }
    }
    if (count($lines) < $nums[1]) {
        $refuse("{$label}: {$to} runs past the end of the file (".count($lines).' lines)');

        return;
    }

    $at = 0;
    while (($at = strpos($xml, $from, $at)) !== false) {
        $edits[] = [$at, strlen($from), $to, $label];
        $at += strlen($from);
    }
};

$swapAll('bootstrap/app.php:47-60', 'bootstrap/app.php:56-69',
    'the generic stage message', 3, [
        'InvalidStateTransitionException $e',
        'expected stage of the enrollment workflow',
        'return redirect()',
    ]);

$swapAll('bootstrap/app.php:148-161', 'bootstrap/app.php:165-178',
    'the no-browser refusal', 1, [
        'PrintRendererMissing $e',
        'no browser to draw it with',
        'EMS_CHROME_PATH',
    ]);

// 3. the new sentence, hung on the last clause of the boundary paragraph so it reads as what it is: the
//    second half of the refusal ruled there.
$tail = 'the dropdown agreeing with the policy it feeds.';
$added = ' The same boundary owed the desk one more thing, measured on 2026-10-13 and fixed that day: it '
    .'refused by name and the name never arrived, because the 403 page interpolated nothing and every '
    .'abort that said why printed a bare Access denied. The page prints the reason now, '
    .'App\Support\DenialReason is the one place that decides whether a message is a reason at all rather '
    .'than Laravel’s placeholder for a policy denial, and bootstrap/app.php returns the same words as JSON '
    .'to a caller that asked for them — which is how the roster refusal above is read, since a screen that '
    .'loads its own candidates has no page to land on: Exam/Create.jsx asks for JSON, checks the status, '
    .'and shows the refusal where an empty list used to report that the college had nobody. A reason is '
    .'built from a stored course code, so the page escapes it, and six tests with five mutants pin both '
    .'halves — a refusal that said why, and a bare denial that still prints only its own copy.';

if (substr_count($xml, $tail) !== 1) {
    $refuse('the boundary paragraph no longer ends where this pass expects');
} else {
    $edits[] = [strpos($xml, $tail), strlen($tail), $tail.$added, 'the refusal reason'];
}

printf("\nedits planned: %d\n", count($edits));
foreach ($edits as [$off, $len, $to, $label]) {
    printf("  %-26s byte %8d  → %s\n", $label, $off, strlen($to) > 90 ? substr($to, 0, 88).'…' : $to);
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$previousStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $previousStart) {
        $refuse(sprintf('edits overlap at %d and %d', $e[0], $previousStart));
    }
    $previousStart = $e[0];
}
if ($fails > 0) {
    exit(1);
}

foreach ($edits as [$off, $len, $to, $label]) {
    $xml = substr_replace($xml, $to, $off, $len);
    printf("applied  %s\n", $label);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no markdown backticks crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the four drifted citations are gone' => substr_count($xml, 'bootstrap/app.php:47-60') === 0
        && substr_count($xml, 'bootstrap/app.php:148-161') === 0,
    'the four corrections are present' => substr_count($xml, 'bootstrap/app.php:56-69') === 3
        && substr_count($xml, 'bootstrap/app.php:165-178') === 1,
    'the new sentence landed once' => substr_count($xml, 'App\Support\DenialReason') === 1
        && substr_count($xml, 'bare Access denied') === 1,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass113.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
