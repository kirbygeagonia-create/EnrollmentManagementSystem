<?php

/**
 * Pass 109 — the citations this batch's line shifts invalidated, computed from the two commits.
 *
 * The rule in AGENTS.md is that a `File.php:NN` citation is a promise about text, so it is re-measured by
 * reading the text, not by arithmetic: take the span the citation named in the pre-batch file (`HEAD~1`),
 * find that same run of normalised lines in the file as it stands, and write the number it now lives at.
 * A span that cannot be found at all was edited *inside* by this batch, and this refuses to invent a
 * number for it — those are listed for a by-meaning pass instead.
 *
 * Two protections the probe taught me the hard way. A citation is matched at its original position first,
 * because a search for the earliest identical text reports a move for any span whose lines also occur
 * elsewhere — the self-test (before == after) caught exactly that. And a token is only swapped when the
 * bytes around it are not digits or a dash, so `X.php:69-69` can never be rewritten inside `X.php:692-692`.
 *
 * Dry run unless --apply. Nothing is written unless every planned edit is proven and none overlaps.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$files = [
    'EvaluationController.php' => 'app/Http/Controllers/Evaluation/EvaluationController.php',
    'ShiftRequestController.php' => 'app/Http/Controllers/Evaluation/ShiftRequestController.php',
    'AdmissionController.php' => 'app/Http/Controllers/Admission/AdmissionController.php',
    'ExamController.php' => 'app/Http/Controllers/Exam/ExamController.php',
    'StudentController.php' => 'app/Http/Controllers/StudentController.php',
    'EvaluationPolicy.php' => 'app/Policies/EvaluationPolicy.php',
    'StudentPolicy.php' => 'app/Policies/StudentPolicy.php',
];

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$norm = function (string $l): string {
    return trim(preg_replace('/\s+/', ' ', $l));
};

/**
 * The byte offset of $token in $xml when it stands alone as a citation, or false. Adjacent digits or a
 * dash mean this is a longer citation that happens to contain this one.
 */
$standalone = function (string $xml, string $token): array {
    $hits = [];
    $at = 0;
    while (($pos = strpos($xml, $token, $at)) !== false) {
        $before = $pos === 0 ? '' : $xml[$pos - 1];
        $after = $xml[$pos + strlen($token)] ?? '';
        if (! ctype_digit($before) && $before !== '.' && ! ctype_digit($after) && $after !== '-') {
            $hits[] = $pos;
        }
        $at = $pos + 1;
    }

    return $hits;
};

$edits = [];
$manual = [];
$fails = 0;

foreach ($files as $name => $rel) {
    $beforeRaw = shell_exec(sprintf('cd %s && git show HEAD~1:%s', escapeshellarg($root), escapeshellarg($rel)));
    if ($beforeRaw === null) {
        fwrite(STDERR, "cannot read HEAD~1:$rel\n");
        exit(1);
    }
    $before = array_map($norm, explode("\n", $beforeRaw));
    $after = array_map($norm, explode("\n", file_get_contents($root.'/'.$rel)));

    // Every citation token for this file, longest first, so a range is never rewritten as its own prefix.
    preg_match_all('/'.preg_quote($name, '/').':\d+(?:-\d+)?/', $xml, $found);
    $tokens = array_values(array_unique($found[0]));
    usort($tokens, fn ($a, $b) => strlen($b) <=> strlen($a));

    foreach ($tokens as $token) {
        $nums = array_map('intval', explode('-', substr($token, strlen($name) + 1)));
        $from = $nums[0];
        $to = $nums[1] ?? $nums[0];
        $len = $to - $from + 1;

        if ($to > count($before) - 1) {
            $manual[] = "$token — beyond the pre-batch file (".(count($before) - 1).' lines): stale before this batch';

            continue;
        }

        $span = array_slice($before, $from - 1, $len);

        $newAt = false;
        if (array_slice($after, $from - 1, $len) === $span) {
            $newAt = $from - 1;
        } else {
            for ($i = 0; $i + $len <= count($after); $i++) {
                if (array_slice($after, $i, $len) === $span) {
                    $newAt = $i;

                    break;
                }
            }
        }

        if ($newAt === false) {
            $manual[] = sprintf("%s — this batch edited inside the span; re-read the sentence (%s)",
                $token, mb_substr(implode(' ', array_filter($span)), 0, 80));

            continue;
        }

        $newFrom = $newAt + 1;
        $newTo = $newAt + $len;
        $replacement = $name.':'.$newFrom.($len > 1 ? '-'.$newTo : '');

        if ($newFrom === $from) {
            continue;   // the number still names the same text
        }

        $positions = $standalone($xml, $token);
        if ($positions === []) {
            $manual[] = "$token — every occurrence sits inside a longer citation, skip";

            continue;
        }

        foreach ($positions as $pos) {
            $edits[] = [$pos, strlen($token), $replacement, $token];
        }
    }
}

printf("citations to rewrite: %d   needing a by-meaning read: %d\n", count($edits), count($manual));
foreach ($manual as $m) {
    echo "  MANUAL  {$m}\n";
}

// Show the plan, collapsed per distinct old→new pair, with how many times it occurs.
$plan = [];
foreach ($edits as $e) {
    $key = $e[3].' → '.$e[2];
    $plan[$key] = ($plan[$key] ?? 0) + 1;
}
foreach ($plan as $key => $n) {
    printf("  %-60s %d occurrence(s)\n", $key, $n);
}

if ($edits === []) {
    echo "\nnothing to write\n";
    exit(0);
}

usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$previousStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $previousStart) {
        fwrite(STDERR, sprintf("REFUSE: edits overlap at %d and %d — one offset cannot carry two edits\n", $e[0], $previousStart));
        exit(1);
    }
    $previousStart = $e[0];
}

foreach ($edits as [$off, $len, $to, $label]) {
    $xml = substr_replace($xml, $to, $off, $len);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the document still parses' => simplexml_load_string($xml) !== false,
    // The byte length must not change at all except where a number gained a digit; this pass rewrites only
    // digits, so any other structural difference is a bug in the plan rather than in the document.
    'only digits changed' => preg_replace('/\d+/', '', $original) === preg_replace('/\d+/', '', $xml)
        || (printf("              old len %d, new len %d\n", strlen($original), strlen($xml)) && false),
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass109.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
