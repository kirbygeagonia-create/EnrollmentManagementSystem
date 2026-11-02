<?php

/**
 * Pass 408 — §29.4's count of the school-question register is re-taken from the register, not from arithmetic.
 *
 * Pass 407 inserted two questions into §28.5; this pass moves the cell that says how many §28.5 holds. The number is
 * read out of `lane212-weigh-question-register.php`'s own output — the instrument that counts the rows physically
 * and compares them with the cell — because a figure typed from "twenty-six plus two" is the same failure the
 * register keeps refusing: the count that grows while the list does not, and the list that grows while the count
 * does not (lane 61's register-row lesson, lane 64's membership defect).
 *
 * The pass refuses when the weigher already agrees (nothing to correct, and a pass that writes anyway would be
 * reporting a change it did not make) and when either of its two numbers cannot be parsed.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass408.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$weigher = 'storage/app/scratch/lane212-weigh-question-register.php';

$out = [];
$code = 0;
exec('"'.PHP_BINARY.'" '.$weigher.' 2>&1', $out, $code);
$report = implode("\n", $out);
echo trim($report)."\n";

if (! preg_match('/holds (\d+) question rows/', $report, $asked) || ! preg_match('/cell states (\d+)/', $report, $stated)) {
    $fail('the weigher printed no row count or no cell value, so this pass has no measurement to write. Its output is above.');
}
if ((int) $asked[1] === (int) $stated[1]) {
    $fail('the weigher already agrees ('.$asked[1].' both sides); this pass exists to repair a disagreement and would write a no-op.');
}

$cellEdits = [['Open decisions', $stated[1], $asked[1]]];

printf("%swriting §29.4's cell from %s to %s\n", $apply ? '' : 'DRY RUN — ', $stated[1], $asked[1]);

$engineBackup = $root.'/storage/app/scratch/pass408-engine-backup.docx';
docxApply($docx, $engineBackup, [], $cellEdits, $apply);

if (! $apply) {
    exit(0);
}

// Read back with the same instrument, so the claim "the cell now equals the rows" is the checker's verdict and not
// this pass's arithmetic.
$after = [];
$code = 0;
exec('"'.PHP_BINARY.'" '.$weigher.' 2>&1', $after, $code);
$afterReport = implode("\n", $after);
echo $afterReport."\n";
if ($code !== 0) {
    copy($engineBackup, $docx);
    $fail('the weigher still disagrees after the write — restored from the engine backup.');
}

echo "written, and the register now weighs itself.\n";
