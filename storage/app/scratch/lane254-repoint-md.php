<?php

/**
 * Lane 254 — re-point the markdown pointers this batch aged, each one proved against the line it names.
 *
 * `lane76-shift-cites.php` edits `word/document.xml` only, so the `.md` deliverables age in silence and
 * `repo-file-line-citations.php` refuses only a blank line or a past-end number (lane 88's defect). Six of the
 * twenty-three pairs below are not merely aged: they were one row short of their own claim BEFORE this batch,
 * which is why every bounds check agreed with them —
 *   `RetryOnDeadlock::run()` at `:483` (the call is 484), `:484` opens the transaction (485),
 *   `:485` locks the block row (486), `:500` then locks the room row (501), `:489` excludes dropped lines (490),
 *   and the matrix row's "the private method at `EvaluationController.php:296`", which sat inside `show()`'s
 *   retention block while `returningStudents()` was at 331.
 * Each of those goes to the line that carries the sentence's own claim, asserted below, not to old+32.
 *
 * A document carrying the `Pointer currency` marker is refused here: `UI-UX-Refinement-Task-Analysis-2026-09-17.md`
 * cites `EvaluationController.php:89-90`, and those digits are that day's record.
 *
 * Usage: php storage/app/scratch/lane254-repoint-md.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: this tool reads only --apply; got [{$arg}].\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$blocking = 'app/Http/Controllers/Blocking/BlockingController.php';
$evaluation = 'app/Http/Controllers/Evaluation/EvaluationController.php';

/** Each edit: [doc, needle, replacement, label, phpFile|null, [line => must-contain]]. */
$edits = [
    ['AGENTS.md', 'EvaluationController.php:689-694', 'EvaluationController.php:690-695',
        'proposeSubjects\' docblock/signature/brace/authorize window (+1)', $evaluation,
        [690 => 'Propose subject load from curriculum', 694 => 'proposeSubjects', 695 => '{']],
    ['AGENTS.md', 'EvaluationController.php:1005', 'EvaluationController.php:1021',
        'the second createWorkflow call site (+16)', $evaluation, [1021 => 'createWorkflow($enrollment)']],
    ['AGENTS.md', 'at :1004', 'at :1020',
        'the existence check behind it (+16)', $evaluation, [1020 => 'if (! $enrollment->enrollmentworkflow)']],
    ['Documentation/Prompt-Requirement-Matrix-2026-10-26.md', 'EvaluationController.php:296',
        'EvaluationController.php:332', 'returningStudents() — the method the prose names', $evaluation,
        [332 => 'private function returningStudents']],
    ['Documentation/Prompt-Requirement-Matrix-2026-10-26.md', '(`:304`, `:318`)', '(`:340`, `:354`)',
        'the select that reads the person and the map that hands the id back', $evaluation,
        [340 => "get(['studentId', 'schoolIdNumber'", 354 => "only(['studentId', 'schoolIdNumber'"]],

    ['Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md', 'BlockingController.php:486` locks the block row',
        'BlockingController.php:518` locks the block row', 'block row lock', $blocking,
        [518 => "Blocks::where('blockId', \$block->blockId)->lockForUpdate()"]],
    ['Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md', 'BlockingController.php:486` and `:500`',
        'BlockingController.php:518` and `:533`', 'the two locks, block then room', $blocking,
        [518 => 'lockForUpdate()->firstOrFail()', 533 => "Rooms::where('roomId', \$schedule->roomId)->lockForUpdate()"]],
    ['Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md', 'BlockingController.php:486`, as',
        'BlockingController.php:518`, as', 'the block row lock as the transaction\'s first act', $blocking,
        [518 => 'lockForUpdate()']],

    ['Documentation/DEFENCE-EVIDENCE-ADS.md', 'BlockingController.php:485` opens its',
        'BlockingController.php:517` opens its', 'DB::transaction inside assignStudents', $blocking,
        [517 => 'DB::transaction(function ()']],
    ['Documentation/DEFENCE-EVIDENCE-ADS.md', '(`:488-491`)', '(`:520-523`)',
        'the held-seat read taken under the lock', $blocking, [521 => '$currentEnrolled = Enrolledsubjects::where'] ],
    ['Documentation/DEFENCE-EVIDENCE-ADS.md', '`RetryOnDeadlock::run()` at `:483`',
        '`RetryOnDeadlock::run()` at `:516`', 'the retry wrapper (was one row early)', $blocking,
        [516 => 'RetryOnDeadlock::run(function ()']],

    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', 'BlockingController.php:472-474`',
        'BlockingController.php:504-506`', 'de-duplicate and sort into one canonical order', $blocking,
        [504 => 'array_unique(array_map(\'intval\'', 505 => 'sort($requested)', 506 => '$requestedCount = count($requested)']],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', 'canonical order; `:483` wraps',
        'canonical order; `:516` wraps', 'RetryOnDeadlock (was one row early)', $blocking,
        [516 => 'RetryOnDeadlock::run(']],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md',
        'RetryOnDeadlock::run()`; `:484` opens the transaction; `:485` locks the block row',
        'RetryOnDeadlock::run()`; `:517` opens the transaction; `:518` locks the block row',
        'the transaction open and the first lock (both were one row early)', $blocking,
        [517 => 'DB::transaction(function ()', 518 => 'Blocks::where(']],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', 'first act; `:488-491` re-counts',
        'first act; `:520-523` re-counts', 'the distinct held-seat count', $blocking,
        [523 => "->distinct('enrollmentId')"]],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', 'lock; `:492-496` refuses above',
        'lock; `:524-528` refuses above', 'the block-capacity refusal', $blocking,
        [525 => '$currentEnrolled + $requestedCount > $block->maxStudents']],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', 'maxStudents`; `:500` then locks the room row',
        'maxStudents`; `:533` then locks the room row', 'the room lock (was one row early)', $blocking,
        [533 => "Rooms::where('roomId', \$schedule->roomId)->lockForUpdate()"]],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', 'and `:502-510` counts',
        'and `:534-542` counts', 'the room count and refusal', $blocking,
        [535 => "Enrolledsubjects::where('scheduleId'", 539 => '$schedule->room->capacity)']],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', '(`:58` and `:489`)', '(`:58` and `:522`)',
        'the dropped-line exclusion on the write guard (was one row early)', $blocking,
        [522 => "where('status', '!=', EnrolledSubjectStatus::Dropped->value)"]],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', '`:488-491`, both excluding',
        '`:520-523`, both excluding', 'the same read, cited a second time', $blocking,
        [521 => '$currentEnrolled = Enrolledsubjects::where']],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', '`:502-505` counts',
        '`:534-537` counts', 'the one-scheduleId count the gap names', $blocking,
        [535 => "where('scheduleId', \$schedule->scheduleId)"]],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', 'BlockingController.php:486` (block) then `:500` (room)',
        'BlockingController.php:518` (block) then `:533` (room)', 'the fixed lock order', $blocking,
        [518 => 'Blocks::where(', 533 => 'Rooms::where(']],
    ['Documentation/REQUIREMENTS-TRACEABILITY-ADS.md', 'BlockingController.php:486` and `:500`',
        'BlockingController.php:518` and `:533`', 'the matrix\'s concurrency row', $blocking,
        [518 => 'lockForUpdate()->firstOrFail()', 533 => 'lockForUpdate()->first()']],
];

// Stage 1: validate everything before writing anything — a half-re-pointed document is the half-applied register.
$contents = [];
foreach ($edits as $i => [$doc, $needle, $to, $label, $phpFile, $assert]) {
    if (! isset($contents[$doc])) {
        $path = $root.'/'.$doc;
        if (! is_file($path)) {
            $fail("edit {$i} names {$doc}, which is not on disk.");
        }
        $body = file_get_contents($path);
        // The MARKER, not the phrase: AGENTS.md explains the convention in prose and a check on the bare
        // words refuses to re-point the living file that needs it most (lane 196's own lesson, met here).
        if (is_string($body) && preg_match('/Pointer currency \(\d{4}-\d{2}-\d{2}\)/', $body) === 1) {
            $fail("edit {$i} would re-age a dated document ({$doc} carries the Pointer currency marker).");
        }
        $contents[$doc] = $body;
    }
    if ($needle === $to) {
        $fail("edit {$i} replaces its needle with itself.");
    }
    $n = substr_count((string) $contents[$doc], $needle);
    if ($n !== 1) {
        $fail("edit {$i} [{$label}] needle matches {$n} time(s) in {$doc}, expected exactly 1.");
    }
    if ($phpFile !== null) {
        $lines = explode("\n", (string) file_get_contents($root.'/'.$phpFile));
        foreach ($assert as $line => $must) {
            $text = trim($lines[(int) $line - 1] ?? '');
            if (! str_contains($text, (string) $must)) {
                $fail("edit {$i} [{$label}] would name {$phpFile}:{$line} for [{$must}] but the line reads [{$text}].");
            }
        }
    }
    // The new document text must not already be standing there under a different claim.
    if (substr_count((string) $contents[$doc], $to) !== 0) {
        $fail("edit {$i} [{$label}] replacement already occurs in {$doc} — read where it came from first.");
    }
    printf("stage 1 ok: edit %2d  %-52s  %s\n", $i, $doc, $label);
}

printf("\n%d pair(s) validated across %d document(s). %s\n",
    count($edits), count(array_unique(array_column($edits, 0))), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$written = [];
foreach ($edits as [$doc, $needle, $to]) {
    $contents[$doc] = str_replace($needle, $to, (string) $contents[$doc]);
}
foreach ($contents as $doc => $body) {
    if (file_put_contents($root.'/'.$doc, $body) === false) {
        $fail("could not write {$doc} after the edits were applied in memory.");
    }
    $written[] = basename($doc);
}

// Read back: every needle gone, every replacement present exactly once.
foreach ($edits as $i => [$doc, $needle, $to, $label]) {
    $body = file_get_contents($root.'/'.$doc);
    if (substr_count((string) $body, $needle) !== 0) {
        $fail("read-back: edit {$i} [{$label}] left its needle in {$doc}.");
    }
    if (substr_count((string) $body, $to) !== 1) {
        $fail("read-back: edit {$i} [{$label}] does not read back once in {$doc}.");
    }
}
printf("WROTE %d pointer pair(s) across %s\n", count($edits), implode(', ', $written));
