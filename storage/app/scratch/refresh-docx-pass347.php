<?php

/**
 * Docx pass 347 — lane 114 moved the department's queue predicate into `App\Support\DeskQueues`.
 *
 * Three citation families and one register row.
 *
 * (1) Two sentences cite "the Evaluation queue's only filter" / "the queue filter" at
 *     `EvaluationController.php:90-99` and a register cell cites it as "queue at :97-99". Those lines no longer
 *     hold that predicate: the list now asks `DeskQueues::evaluation()`, which carries it. Re-pointing them at the
 *     controller's *new* line numbers would have been the wrong-target defect — the window there is the eager-load
 *     and the search, not the filter — so each one moves to the class that owns the rule.
 * (2) `:1135-1176` (confirmClearance(), two copies) aged by the one `use` line Pint filed for the new call. The
 *     shift tool deferred it as BARE-DEFERRED because the nearest name on its dump line is another file's, so it is
 *     re-taken here with both endpoints asserted byte-for-byte against HEAD's exported copy.
 * (3) §28.3 G-46's row states how many lists ask the class and how many cards are deliberately narrower. Both
 *     figures are membership claims, and this batch moved each by one — so the counts move with the rows, which is
 *     the defect lane 61 and lane 64 exist to refuse.
 * (4) §1.1's day row gains the fifth half, with the admission queue's exception stated as a measurement.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass347.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass347-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

// ── Stage 1: assert the lines each new pointer names, in the file as it stands now ────────────────────────────
$assertions = [];
$fail = function (string $why) use (&$assertions): void {
    $assertions[] = 'FAIL '.$why;
};

$queues = file($root.'/app/Support/DeskQueues.php');
if (trim((string) ($queues[86] ?? '')) !== 'public static function evaluation(?Staffusers $desk): Builder') {
    $fail('DeskQueues.php line 87 is not evaluation()’s signature: '.trim((string) ($queues[86] ?? '')));
}
if (trim((string) ($queues[95] ?? '')) !== '}') {
    $fail('DeskQueues.php line 96 is not the closing brace: '.trim((string) ($queues[95] ?? '')));
}
if (! str_contains((string) ($queues[91] ?? ''), ")->whereIn('enrollmentStatus', [")) {
    $fail('DeskQueues.php line 92 does not open the status filter the sentences claim');
}

$eval = file($root.'/app/Http/Controllers/Evaluation/EvaluationController.php');
$head = file($root.'/storage/app/scratch/lane114-head-EvaluationController.php');
if (! str_contains((string) ($eval[96] ?? ''), 'DeskQueues::evaluation($desk)')) {
    $fail('EvaluationController.php line 97 is not the new call: '.trim((string) ($eval[96] ?? '')));
}
// The deferred window: HEAD's 1135 and 1176 must be byte-identical to the working 1136 and 1177 — a +1 shift is
// only true if the construct itself was not edited.
foreach ([[1135, 1136], [1176, 1177]] as [$h, $w]) {
    if (($head[$h - 1] ?? null) === null || ($head[$h - 1] ?? '') !== ($eval[$w - 1] ?? '')) {
        $fail("working line {$w} is not byte-identical to HEAD’s {$h}");
    }
}
foreach ($assertions as $a) {
    echo $a."\n";
}
if ($assertions !== []) {
    echo "NOTHING WRITTEN — a cited line does not carry the claim.\n";

    exit(1);
}
echo "cited lines verified: DeskQueues.php:87-96 is evaluation()’s whole method, EvaluationController.php:97 ".
    "is the call, and the confirmClearance() window moved by exactly one line with no edit inside it\n";

// ── Stage 2: the edits ───────────────────────────────────────────────────────────────────────────────────────
$filter = ['EvaluationController.php:90-99', 'DeskQueues.php:87-96', null, 2];
$bare = [':97-99', 'DeskQueues.php:87-96', null, 1];
$window = [':1135-1176', ':1136-1177', null, 2];
$joined = ['because three more lists joined that call', 'because four more lists joined that call', null, 1];
$which = ['the assessment queue, the registrar queue and the cashier’s queue base each take their rows',
    'the assessment queue, the registrar queue, the cashier’s queue base and the department’s evaluation queue '.
    'each take their rows', null, 1];
$narrower = ['Two cards stay deliberately narrower than the page beneath them — the cashier’s counts the owing '.
    'sheets and the clearance card the pending rows — so those two are not to be unified',
    'Three cards stay deliberately narrower than the page beneath them — the cashier’s counts the owing sheets, '.
    'the clearance card the pending rows, and the admission card the applications still awaiting a decision while '.
    'its page also lists the saved drafts — so those three are not to be unified', null, 1];
$day = ['belongs to a different file’s comma list.',
    'belongs to a different file’s comma list. The day’s fifth half made the department’s evaluation queue the '.
    'fourth desk list to ask that class for its rows, and that is a true match rather than a widening: the helper '.
    'already carried both states the desk has always listed. The admission queue was weighed and left alone — '.
    'measured, not recalled, because its index() applies no status filter at all, so building the page from a card '.
    'that counts only pending applications would hide draft, approved and rejected rows from a desk. Two of the '.
    'class’s docblocks now say which list calls them and which deliberately does not, the structural case that reads '.
    'each action’s body grew by that fourth list, and its harness gained a leg that puts the predicate back into the '.
    'controller — a change that leaves every number equal, so only that case can see it.', null, 1];

$textEdits = [$filter, $bare, $window, $joined, $which, $narrower, $day];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$bad = 0;
foreach ($textEdits as $i => [$from, $to, , $want]) {
    $n = substr_count($xml, $from);
    printf('edit %d: needle occurs %d time(s), want %d — %s%s', $i, $n, $want, substr($from, 0, 46), PHP_EOL);
    if ($n !== $want) {
        $bad++;
    }
    if (str_contains($to, '<') || str_contains($to, '>')) {
        $bad++;
        echo "  edit {$i} carries a tag character\n";
    }
}
if ($bad > 0) {
    $applied = true;
    foreach ($textEdits as [$from, $to]) {
        // An append embeds its own needle, so the retired form is expected to SURVIVE as the head of the new
        // sentence; every other edit must leave its needle behind. (pass 341's per-edit residual rule.)
        $residual = str_starts_with($to, $from) ? substr_count($xml, $from) : 0;
        if (substr_count($xml, $to) !== 1 || substr_count($xml, $from) !== $residual) {
            $applied = false;
        }
    }
    echo $applied ? "ALREADY APPLIED.\n" : "REFUSED — nothing written.\n";

    exit($applied ? 0 : 1);
}

docxApply($docx, $backup, $textEdits, [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

// ── Stage 3: read the file back ──────────────────────────────────────────────────────────────────────────────
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the written docx did not open.\n");
}
$after = html_entity_decode((string) $zip->getFromName('word/document.xml'), ENT_QUOTES | ENT_HTML5);
$zip->close();

$expect = [
    'EvaluationController.php:90-99' => 0,
    ':97-99' => 0,
    ':1135-1176' => 0,
    'because three more lists joined that call' => 0,
    'Two cards stay deliberately narrower' => 0,
    'DeskQueues.php:87-96' => 3,
    ':1136-1177' => 2,
    'because four more lists joined that call' => 1,
    'Three cards stay deliberately narrower' => 1,
    'The day’s fifth half' => 1,
];
$bad = 0;
foreach ($expect as $needle => $want) {
    $n = substr_count($after, $needle);
    if ($n !== $want) {
        $bad++;
        printf('  %s occurs %d time(s), want %d%s', $needle, $n, $want, PHP_EOL);
    }
}
if ($bad > 0) {
    copy($backup, $docx);
    exit("REFUSED: the written document does not read back as intended; restored from {$backup}.\n");
}
echo 'written, re-read: the queue filter is cited to the class that owns it, the deferred window is shifted by '.
    "the one line the import added, and the register counts the four lists and the three narrower cards.\n";
