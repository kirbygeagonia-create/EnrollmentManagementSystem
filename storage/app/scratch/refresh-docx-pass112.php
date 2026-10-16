<?php

/**
 * Pass 112 — four citations the construct-level check proved wrong, after reading each one.
 *
 * The whole-document line sweep (pass 111) is geometry: file exists, range in bounds, first line not blank.
 * The next check asks whether each cited range sits inside the method its sentence names. It reported 13
 * candidates and nine were its own confusion — naive brace counting ends a method early on `"{$x}"`
 * interpolation, an Eloquent scope is cited as `accepting()` when the declaration reads `scopeAccepting`,
 * and a citation may legitimately start in the docblock above the method. So each candidate was read
 * before being believed, and these four are the ones the document actually has wrong:
 *
 *   1. ShiftRequestController.php:45 — the sentence describes the three things filing refuses; line 45 is
 *      inside the class docblock (`class … extends Controller` is at 46) and `store()` is at 121, with the
 *      three refusals between 137 and 179.
 *   2. WorkflowService.php:126-131 — the sentence says the signed event fires "after the box is saved";
 *      126-131 is the PIPELINE_ORDER constant, while the save and `event(new WorkflowStepSigned(…))` are
 *      258-262 inside signStep().
 *   3. app/Services/WorkflowService.php:27 — the row names stepsFor() and "the box set, their order, the
 *      unset() that drops the Assessment box"; line 27 is the closing line of another method's docblock,
 *      and stepsFor() is 52-83.
 *   4. A method *named* wrong beside a range that is right: the sentence about labels shifting by one
 *      cites WorkflowService.php:31-39, which is the label array — but that array is `stepLabels()`,
 *      declared at 30, not `stepsFor()`. The number stays; the name is corrected. The phrase
 *      `WorkflowService::stepsFor()` appears three times in the document, so the target is chosen
 *      positionally — the one before the `:31-39` whose own clause says "labels by one" — and the guard
 *      requires the other two to still be there.
 *
 * PrintService.php:268 was on the candidate list and is NOT changed: the row cites it for the issue row
 * written inside the card print, and line 268 is exactly that `recordIssue(...)` call. The probe had read
 * `printClassCards()` from the same crowded cell and demanded it inside a line that quotes a different
 * method.
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

/** @param list/array $asserts statements that must be inside the new range */
$swap = function (string $from, string $to, string $label, array $asserts, int $expect = 1) use ($xml, &$edits, $root, &$refuse): void {
    $n = substr_count($xml, $from);
    if ($n !== $expect) {
        $refuse("{$label}: anchor appears {$n} time(s), expected {$expect}");

        return;
    }
    $edits[] = [strpos($xml, $from), strlen($from), $to, $label];

    [, $range] = explode(':', $to, 2);
    $nums = array_map('intval', explode('-', $range));
    // A citation may carry its folder (`app/Services/WorkflowService.php:52-83`), in which case the path is
    // already relative to the repository root; only a bare basename needs a search.
    $file = explode(':', $to)[0];
    $path = str_contains($file, '/') && is_file($root.'/'.$file) ? $root.'/'.$file : null;
    if ($path === null) {
        foreach (['app/Http/Controllers/Evaluation', 'app/Services', 'app/Support', 'app'] as $dir) {
            if (is_file($root.'/'.$dir.'/'.$file)) {
                $path = $root.'/'.$dir.'/'.$file;

                break;
            }
        }
    }
    if ($path === null) {
        $refuse("{$label}: cannot locate {$file} to verify the new range");

        return;
    }
    $lines = file($path);
    $span = implode(' ', array_map('trim', array_slice($lines, $nums[0] - 1, ($nums[1] ?? $nums[0]) - $nums[0] + 1)));
    foreach ($asserts as $must) {
        if (! str_contains($span, $must)) {
            $refuse("{$label}: the new range {$to} does not hold 「{$must}」");
        }
    }
};

$swap('ShiftRequestController.php:45', 'ShiftRequestController.php:137-179',
    'the three filing refusals', [
        'holds no enrollment to leave',
        'there is nothing to shift',
        'a second paper would ask for the same decision twice',
    ]);

$swap('WorkflowService.php:126-131', 'WorkflowService.php:258-262',
    'signStep saves then announces', [
        '$step->save();',
        'event(new WorkflowStepSigned($workflow, $step, $signedBy))',
    ]);

$swap('app/Services/WorkflowService.php:27', 'app/Services/WorkflowService.php:52-83',
    'stepsFor is the box set', [
        'function stepsFor(',
        'OfficeId::Scholarship->value',
    ]);

// 4. the rename, found by position rather than by text: the `:31-39` in the sentence about labels, and the
    //   `stepsFor()` immediately before it.
$labelSentence = 'WorkflowService.php:31-39';
$at = 0;
$target = false;
while (($at = strpos($xml, $labelSentence, $at)) !== false) {
    $before = substr($xml, max(0, $at - 420), min(420, $at));
    if (str_contains($before, 'label')) {
        $target = $at;

        break;
    }
    $at += strlen($labelSentence);
}
if ($target === false) {
    $refuse('the labels-by-one sentence no longer sits where this pass expects');
} else {
    $phrase = 'WorkflowService::stepsFor';
    $phraseAt = strrpos(substr($xml, 0, $target), $phrase);
    if ($phraseAt === false || $target - $phraseAt > 420) {
        $refuse('no stepsFor() phrase precedes that citation within the sentence');
    } else {
        $edits[] = [$phraseAt, strlen($phrase), 'WorkflowService::stepLabels', 'the label array, renamed'];
        printf("planned  the label-array rename at byte %d (%d chars before its own cite)\n",
            $phraseAt, $target - $phraseAt);
        // Print the sentence the rename lands in. A positional rule is only as good as the reading of it,
        // and the run should show the words rather than ask the next person to trust the offset.
        printf("         in the sentence: …%s…\n", mb_substr(trim(preg_replace('/\s+/', ' ',
            strip_tags(substr($xml, max(0, $phraseAt - 260), 480)))), 0, 300));
    }
}

printf("\nedits planned: %d\n", count($edits));
foreach ($edits as [$off, $len, $to, $label]) {
    printf("  %-34s byte %8d  → %s\n", $label, $off, $to);
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
    'the three wrong citations are gone' => substr_count($xml, 'ShiftRequestController.php:45') === 0
        && substr_count($xml, 'WorkflowService.php:126-131') === 0
        && substr_count($xml, 'app/Services/WorkflowService.php:27') === 0,
    'the four corrections are present once' => substr_count($xml, 'ShiftRequestController.php:137-179') === 1
        && substr_count($xml, 'WorkflowService.php:258-262') === 1
        && substr_count($xml, 'app/Services/WorkflowService.php:52-83') === 1,
    // The rename touched one of the five mentions (measured, not assumed); the other four still describe
    // stepsFor, which is a real method with real citations of its own.
    'stepsFor still named where it is the subject' => substr_count($xml, 'WorkflowService::stepsFor') === 4,
    'stepLabels now named where the labels are the subject' => substr_count($xml, 'WorkflowService::stepLabels') === 2,
    'the ranges that stay, stay' => substr_count($xml, 'WorkflowService.php:31-39') === 3,
    'PrintService.php:268 is left alone' => substr_count($xml, 'PrintService.php:268') === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass112.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
