<?php

/**
 * Pass 305: §24's notification-payload paragraph stops describing a build that no longer exists.
 *
 * The paragraph says the listeners "pass an already-encoded string … so Laravel encodes it a second time. Read
 * back, data is a JSON string, not the map the bell template expects. … Fix: pass the array, not json_encode()."
 * The fix landed: `json_encode` appears 0 times in either listener, and each writes the payload as the array the
 * cast expects. Every bounds checker cleared the paragraph for months because its three line numbers still name
 * live lines of the right files — the shape AGENTS.md calls a wrong target, reached from a mechanism sentence.
 *
 * Two writes, in this order and for a reason. The paragraph's third citation is a bare `:57` sitting alone in a
 * Consolas run, so no contiguous text needle can reach it: that half is a raw splice of the two runs (", " and
 * ":57"), done first and written back with byte verification. The engine then re-reads the file from disk, so its
 * `text` edits apply over the spliced document — a pass that rewrote `document.xml` in memory and handed the file
 * to docxApply would have written nothing at all.
 *
 * Every new line number is asserted against the line it names before the write, which is the only thing that
 * separates a re-point from a re-guess.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass305.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

// ── the live lines the new pointers will name ─────────────────────────────────────────────────
$assertions = [
    'app/Listeners/SendEnrollmentNotification.php' => [
        72 => "'data' => \$data",
    ],
    'app/Listeners/SendWorkflowNotification.php' => [
        95 => "'data' => array_merge([",
    ],
    'app/Models/Notifications.php' => [
        13 => 'protected function casts(): array',
        21 => '}',
    ],
];

foreach ($assertions as $rel => $lines) {
    $body = explode("\n", (string) file_get_contents($root.'/'.$rel));
    foreach ($lines as $n => $claim) {
        $at = $body[$n - 1] ?? '(past end)';
        if (! str_contains($at, $claim)) {
            fwrite(STDERR, "REFUSED: {$rel} line {$n} reads [".trim($at)."], which does not carry [{$claim}].\n");

            exit(1);
        }
        echo 'assert '.basename($rel)." {$n}: ".trim($at)."\n";
    }
}

// json_encode must be gone from both listeners, or the paragraph's claim is not stale and this pass is wrong.
foreach (['app/Listeners/SendEnrollmentNotification.php', 'app/Listeners/SendWorkflowNotification.php'] as $rel) {
    $hits = substr_count((string) file_get_contents($root.'/'.$rel), 'json_encode');
    if ($hits !== 0) {
        fwrite(STDERR, "REFUSED: {$rel} still contains json_encode {$hits} time(s); the paragraph may be true after all.\n");

        exit(1);
    }
    echo 'assert json_encode absent from '.basename($rel)."\n";
}

// ── stage 1: the splice that no text needle can do ────────────────────────────────────────────
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answered an error code, not true).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$anchorRun = 'SendWorkflowNotification.php:45</w:t></w:r>';
if (substr_count($xml, $anchorRun) !== 1) {
    fwrite(STDERR, 'REFUSED: the run-tail anchor ['.substr($anchorRun, 0, 34).'] occurs '
        .substr_count($xml, $anchorRun)." time(s), expected exactly 1.\n");

    exit(1);
}

$at = strpos($xml, $anchorRun) + strlen($anchorRun);
$rest = substr($xml, $at);
if (preg_match('/^(<w:r>.*?<\/w:r>)(<w:r>.*?<\/w:r>)/s', $rest, $m) !== 1) {
    fwrite(STDERR, "REFUSED: the two runs after the anchor are not both <w:r> blocks — read the paragraph before splicing.\n");

    exit(1);
}

$commaRun = $m[1];
$fiftySevenRun = $m[2];
if (! preg_match('/^\s*,\s*$/u', strip_tags($commaRun)) || trim(strip_tags($fiftySevenRun)) !== ':57') {
    fwrite(STDERR, 'REFUSED: the spliced runs read ['.$commaRun.'] and ['.$fiftySevenRun
        .'] — not the ", " and ":57" this pass was told to remove.\n');

    exit(1);
}

$follows = substr($rest, strlen($commaRun.$fiftySevenRun), 80);
if (! str_contains($follows, '), so Laravel encodes it a second time')) {
    fwrite(STDERR, 'REFUSED: what follows the two runs is not the sentence this pass expects: ['
        .substr($follows, 0, 60)."].\n");

    exit(1);
}

$spliced = substr($xml, 0, $at).substr($rest, strlen($commaRun.$fiftySevenRun));

// A splice must not change the document's element census beyond the two runs it removed.
foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>'], ['<w:r', '</w:r>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $spliced);
    $b = substr_count($spliced, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the spliced document is unbalanced in {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}
if (simplexml_load_string($spliced) === false) {
    fwrite(STDERR, "REFUSED: the spliced document.xml no longer parses.\n");

    exit(1);
}
if (substr_count($spliced, ':57') >= substr_count($xml, ':57')) {
    fwrite(STDERR, 'REFUSED: the splice removed no ":57" (it occurs '
        .substr_count($spliced, ':57')." time(s) against ".substr_count($xml, ':57').").\n");

    exit(1);
}

printf("stage 1: splices out the \", \" and \":57\" runs (%d -> %d bytes)\n", strlen($xml), strlen($spliced));

$backup = $root.'/storage/app/scratch/pass305-backup-'.date('Ymd-His').'.docx';
if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

if ($apply) {
    $write = new ZipArchive;
    if ($write->open($docx) !== true) {
        fwrite(STDERR, "REFUSED: the docx will not open for the splice.\n");

        exit(1);
    }
    $write->addFromString('word/document.xml', $spliced);
    $write->close();

    $back = new ZipArchive;
    $back->open($docx);
    $round = (string) $back->getFromName('word/document.xml');
    $back->close();
    if ($round !== $spliced) {
        fwrite(STDERR, "REFUSED: the bytes read back differ from the bytes written; backup is {$backup}.\n");

        exit(1);
    }
    echo "stage 1 written and verified byte-for-byte.\n";
}

// ── stage 2: the prose and pointer edits, over the spliced document ──────────────────────────
$edits = [
    ['Payload encoding is also wrong', 'Payload encoding was wrong'],
    ['but both listeners pass an already-encoded string', 'and both listeners now pass the array itself'],
    ['json_encode([...])', 'the writes are'],
    ['SendEnrollmentNotification.php:38', 'SendEnrollmentNotification.php:72'],
    ['SendWorkflowNotification.php:45', 'SendWorkflowNotification.php:95'],
    ['so Laravel encodes it a second time', 'so the cast is the only encoder'],
    [' is a JSON string, not the map the bell template expects', ' is the map the bell template expects, never a JSON string'],
    ['and is the only correctly-shaped content in the table', 'and was the only correctly-shaped content in the table before the fix'],
    ['Fix: pass the array, not ', 'N-4 closed: the repair was to pass the array, not '],
    // The same sentence's model pointer starts on `$fillable` and stops one line before `casts()`'s closing brace.
    // Reading the file gives the method's own span, which is what the sentence claims.
    ['Notifications.php:11-20', 'Notifications.php:13-21'],
];

$check = new ZipArchive;
if ($check->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx for the stage-2 assertions.\n");

    exit(1);
}
$now = $apply ? (string) $check->getFromName('word/document.xml') : $spliced;
$check->close();

foreach ($edits as [$needle, $want]) {
    $hits = substr_count($now, $needle);
    if ($hits !== 1) {
        fwrite(STDERR, 'REFUSED: ['.mb_substr($needle, 0, 52).'] occurs '.$hits
            ." time(s) in the document stage 2 will edit, expected exactly 1 — nothing written.\n");

        exit(1);
    }
}
echo 'stage 2: '.count($edits)." text needles each occur exactly once\n";

printf("%s: %d text edits on the payload paragraph\n", $apply ? 'APPLY' : 'DRY RUN', count($edits));

docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass305-dry.docx', $edits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$final = new ZipArchive;
$final->open($docx);
$after = (string) $final->getFromName('word/document.xml');
$final->close();

// Delta expectations, not document-wide absence. Several of these tokens already occur elsewhere in the register —
// `SendEnrollmentNotification.php:72` is cited by the after-commit row, and "a second time" is ordinary English —
// so asserting a zero would refuse a correct write, which is the mistake AGENTS.md records for a re-taken number.
$delta = [
    'Payload encoding is also wrong' => -1,
    'already-encoded string' => -1,
    'a second time' => -1,
    'json_encode([...])' => -1,
    'SendEnrollmentNotification.php:38' => -1,
    'SendWorkflowNotification.php:45' => -1,
    'Fix: pass the array' => -1,
    'the only correctly-shaped content in the table before the fix' => +1,
    'SendEnrollmentNotification.php:72' => +1,
    'SendWorkflowNotification.php:95' => +1,
    'the cast is the only encoder' => +1,
    'Notifications.php:11-20' => -1,
    'Notifications.php:13-21' => +1,
    ':57' => 0,
];

foreach ($delta as $token => $change) {
    $before = substr_count($now, $token);
    $afterN = substr_count($after, $token);
    if ($afterN !== $before + $change) {
        fwrite(STDERR, sprintf(
            "REFUSED: [%s] went %d -> %d, expected %d; backup is %s.\n",
            $token,
            $before,
            $afterN,
            $before + $change,
            $backup
        ));

        exit(1);
    }
    printf("after: %-56s %d -> %d (%+d)\n", $token, $before, $afterN, $change);
}

// ":57" must fall by exactly the one run stage 1 spliced, which stage 2 never touches.
$fiftySeven = substr_count($xml, ':57');
if (substr_count($after, ':57') !== $fiftySeven - 1) {
    fwrite(STDERR, 'REFUSED: the bare ":57" went '.substr_count($after, ':57')." against the original {$fiftySeven}; the splice did not land as intended.\n");

    exit(1);
}
printf("after: %-56s %d -> %d (the splice)\n", ':57 (whole document)', $fiftySeven, substr_count($after, ':57'));

echo "WRITTEN and verified against the archive.\n";
