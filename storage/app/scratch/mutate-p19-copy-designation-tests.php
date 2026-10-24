<?php

/**
 * Mutants for P-19's tests (tests/Feature/Print/ClearanceSlipCopyDesignationTest.php).
 *
 * P-19 was ruled 2026-10-23: keep the nine-office table, add the copy designation and the school's
 * course-code wording to the face of the slip. The defects planted here are the ways that ruling can be
 * lost — the mark dropped from the paper, the mark present only in metadata that no window reads, the code
 * prefix removed from one surface but not the other, the year level re-spelled into a second vocabulary,
 * and a program label built unconditionally over a term that holds no enrollment.
 *
 * Every case restores the tree in a `finally`, because a half-planted blade makes each later verdict a
 * lie, and a case whose anchor matches nothing reports BAD rather than a pass.
 *
 *   php storage/app/scratch/mutate-p19-copy-designation-tests.php
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = escapeshellarg(getenv('PHP') ?: 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe');
$file = 'tests/Feature/Print/ClearanceSlipCopyDesignationTest.php';
$blade = $root.'/resources/views/prints/clearance-slip.blade.php';
$jsx = $root.'/resources/js/Pages/Clearance/PrintSlip.jsx';

$run = function (?string $method) use ($php, $file): array {
    $cmd = $php.' artisan test '.escapeshellarg($file);
    if ($method !== null) {
        $cmd .= ' --filter '.escapeshellarg($method);
    }
    $cmd .= ' 2>&1';
    // Pest colourises its summary, and the escape sequences sit between "Tests:" and the figure.
    $out = preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));
    if (! preg_match('/Tests:\s+([0-9]+) (passed|failed|incomplete)/', $out, $m)) {
        return ['verdict' => 'BROKEN', 'line' => 'no Tests: line — '.substr(trim($out), -220)];
    }

    return ['verdict' => $m[2] === 'passed' ? 'GREEN' : 'RED', 'line' => trim($m[0])];
};

$restore = [];
$plant = function (array $edits) use (&$restore): ?string {
    foreach ($edits as [$path, $from, $to]) {
        $bytes = (string) file_get_contents($path);
        $restore[] = [$path, $bytes];
        if (substr_count($bytes, $from) !== 1) {
            return 'anchor matched '.substr_count($bytes, $from).' times in '.basename($path);
        }
        file_put_contents($path, str_replace($from, $to, $bytes));
        if ((string) file_get_contents($path) === $bytes) {
            return 'the mutation left '.basename($path).' byte-identical';
        }
    }

    return null;
};
$unplant = function () use (&$restore): void {
    foreach (array_reverse($restore) as [$path, $bytes]) {
        file_put_contents($path, $bytes);
    }
    $restore = [];
};

$fail = 0;
$say = function (bool $ok, string $what) use (&$fail): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fail++;
    }
};

$copyMark = 'Clearance Slip (Student\'s Copy)';
$titleRow = '<div class="title">'.$copyMark.'</div>';
$headTitle = '<title>Clearance Slip - {{ $clearance->student->schoolIdNumber }}</title>';
$codeLine = "? '('.\$slipCourse->courseCode.') '.\$slipCourse->courseName";
$labelBlock = "        \$slipCourseLabel = \$slipCourse === null\n            ? 'N/A'\n            : (\$slipCourse->courseCode !== null && \$slipCourse->courseCode !== ''\n                "
    .$codeLine."\n                : \$slipCourse->courseName);";
$levelExpr = "'Year '.\$termEnrollment->yearLevel";

$cases = [
    [
        'the copy mark is dropped from the paper',
        'test_the_printed_slip_says_whose_copy_it_is',
        [[$blade, $titleRow, '<div class="title">Clearance Slip</div>']],
        'a slip that does not say whose copy it is, which is the defect P-19 was opened for',
    ],
    [
        'the mark survives only in metadata',
        'test_the_printed_slip_says_whose_copy_it_is',
        [
            [$blade, $titleRow, '<div class="title">Clearance Slip</div>'],
            [$blade, $headTitle, '<title>'.$copyMark.' - {{ $clearance->student->schoolIdNumber }}</title>'],
        ],
        'a <title> element is not paper — this is the lane 67 trap, and the test must not be satisfied by it',
    ],
    [
        'the course code is dropped from the PDF',
        'test_the_program_is_led_by_its_course_code_the_way_the_school_writes_it',
        [[$blade, $codeLine, '? $slipCourse->courseName']],
        'the program prints without its code, so a window cannot see which program it names',
    ],
    [
        'the year level is re-spelled into a second vocabulary',
        'test_the_program_is_led_by_its_course_code_the_way_the_school_writes_it',
        [[$blade, $levelExpr, '$termEnrollment->yearLevel.\'th Year\'']],
        'the ruling added the code, not another spelling of the level; six pages once carried four',
    ],
    [
        'the desk page loses the mark while the PDF keeps it',
        'test_the_desk_page_marks_the_same_copy_and_names_the_same_program',
        [[$jsx, 'title="'.$copyMark.'"', 'title="Clearance Slip"']],
        'the screen and the paper a student walks away with disagree about which copy they are',
    ],
    [
        'the desk page drops the code while the blade keeps it',
        'test_the_desk_page_marks_the_same_copy_and_names_the_same_program',
        [[$jsx, '? `(${slipCourse.courseCode}) ${slipCourse.courseName}`', '? slipCourse.courseName']],
        'one surface leads with the code and the other does not, and no instrument joins the two',
    ],
    [
        'the program label is built unconditionally over a null course',
        'test_a_slip_that_names_no_program_still_marks_its_copy',
        [[$blade, $labelBlock, '        $slipCourseLabel = \'(\'.($slipCourse?->courseCode ?? \'\').\') \'.($slipCourse?->courseName ?? \'\');']],
        'a term holding no enrollment prints "( )" where the paper should state the absence',
    ],
];

echo "=== the control: the real tree, every case green ===\n";
$control = $run(null);
$say($control['verdict'] === 'GREEN', 'control — '.$control['line']);
if ($control['verdict'] !== 'GREEN') {
    echo "\nREFUSED: the control is not green, so no planted failure here would mean anything.\n";
    exit(1);
}

echo "\n=== each defect must be caught by the test that names it ===\n";
foreach ($cases as [$what, $method, $edits, $why]) {
    $problem = $plant($edits);
    if ($problem !== null) {
        $unplant();
        $say(false, $what.': BAD — the mutation could not be applied ('.$problem.')');

        continue;
    }

    $verdict = $run($method);
    $unplant();

    $say($verdict['verdict'] === 'RED', str_pad($what, 56).$verdict['verdict'].' — '.$why);
}

echo "\n=== the tree is back as it was ===\n";
foreach ([[$blade, $titleRow], [$blade, $codeLine], [$jsx, 'title="'.$copyMark.'"']] as [$path, $needle]) {
    $say(substr_count((string) file_get_contents($path), $needle) === 1, basename($path).' holds its '.substr($needle, 0, 28).'… once');
}

$again = $run(null);
$say($again['verdict'] === 'GREEN', 'restored tree, re-run — '.$again['line']);

if ($fail > 0) {
    printf("\n%d P-19 case(s) misbehaved.\n", $fail);
    exit(1);
}
echo "\nAll P-19 cases behaved.\n";
