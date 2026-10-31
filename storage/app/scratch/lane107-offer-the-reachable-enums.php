<?php

/**
 * Lane 107 — offer the reachable ENUM headers on the three wired screens that still withheld them for the reason the
 * owner reversed on 2026-10-31. Every needle must match EXACTLY ONCE or nothing is written.
 */
$edits = [];

// ---------------------------------------------------------------- Registrar: the two columns are on its own base table
$edits[] = ['app/Http/Controllers/Registrar/RegistrarController.php', <<<'PHP'
            'status' => ['column' => 'enrollments.enrollmentStatus'],
            'enrollmentId' => ['column' => 'enrollments.enrollmentId'],
PHP, <<<'PHP'
            'status' => ['column' => 'enrollments.enrollmentStatus'],
            // Ruled 2026-10-31: a desk may sort an ENUM, because on the school's MySQL the order is the declaration
            // order the school itself wrote into the migration. Both columns sit on the row this queue reads, so the
            // only reason ever given to leave them out — that a header would ask this query for something it cannot
            // answer — does not apply here. The case asks the connection which order its column has.
            'studentType' => ['column' => 'enrollments.studentType'],
            'academicStanding' => ['column' => 'enrollments.academicStanding'],
            'enrollmentId' => ['column' => 'enrollments.enrollmentId'],
PHP];

$edits[] = ['resources/js/Pages/Registrar/Index.jsx', <<<'PHP'
        { key: 'studentType', label: 'Student Type', render: (row) => (
PHP, <<<'PHP'
        // Student Type and Standing are sortable even though both are ENUM columns: on the school's server an ENUM
        // sorts in the order the values were declared, which is the order the school wrote down.
        { key: 'studentType', label: 'Student Type', sortKey: 'studentType', render: (row) => (
PHP];

$edits[] = ['resources/js/Pages/Registrar/Index.jsx', <<<'PHP'
        { key: 'academicStanding', label: 'Final Standing', render: (row) => (
PHP, <<<'PHP'
        { key: 'academicStanding', label: 'Final Standing', sortKey: 'academicStanding', render: (row) => (
PHP];

// ---------------------------------------------------------------- Assessment: reachable through the enrollment hop
$edits[] = ['app/Http/Controllers/Assessment/AssessmentController.php', <<<'PHP'
            'balance' => ['column' => 'studentassessments.remainingBalance'],
            'assessmentId' => ['column' => 'studentassessments.assessmentId'],
PHP, <<<'PHP'
            'balance' => ['column' => 'studentassessments.remainingBalance'],
            // Ruled 2026-10-31: a desk may sort an ENUM — on the school's server it orders by the declaration the
            // school wrote. These two live on the enrollment a fee sheet points at, so they travel through the hop
            // the surname sort already builds. The column is spelled BARE on purpose: a hop is joined under an alias
            // (`srt0`) and ListSort obeys an already-qualified name as written, so `enrollments.studentType` here
            // would ask this query for a table it never joined — a 500 at the desk, not a wrong order.
            'studentType' => [
                'column' => 'studentType',
                'join' => 'enrollments',
                'foreign' => 'enrollmentId',
                'local' => 'studentassessments.enrollmentId',
            ],
            'academicStanding' => [
                'column' => 'academicStanding',
                'join' => 'enrollments',
                'foreign' => 'enrollmentId',
                'local' => 'studentassessments.enrollmentId',
            ],
            'assessmentId' => ['column' => 'studentassessments.assessmentId'],
PHP];

$edits[] = ['resources/js/Pages/Assessment/Index.jsx', <<<'PHP'
        // Course, Student Type and Standing carry no sortKey: the first is reachable but not how this queue is
        // worked, and the other two are read off the enrollment the sheet already points at. Payment Status is
        // derived from the balance against the total on the screen, so ordering by balance orders it too. A key
        // the handler does not list is a header that would reset while the queue stayed ordered.
        { key: 'enrollment.studentType', label: 'Student Type', render: (row) => (
PHP, <<<'PHP'
        // Course carries no sortKey: it is reachable but not how this queue is worked. Payment Status is derived from
        // the balance against the total on the screen, so ordering by balance orders it too. Student Type and Standing
        // HAD no header until the owner ruled 2026-10-31 that an ENUM may be sorted, because on the school's server it
        // orders by the declaration the school itself wrote — and the handler reaches both through the enrollment hop
        // a name sort already builds.
        { key: 'enrollment.studentType', label: 'Student Type', sortKey: 'studentType', render: (row) => (
PHP];

$edits[] = ['resources/js/Pages/Assessment/Index.jsx', <<<'PHP'
        { key: 'enrollment.academicStanding', label: 'Standing', render: (row) => (
PHP, <<<'PHP'
        { key: 'enrollment.academicStanding', label: 'Standing', sortKey: 'academicStanding', render: (row) => (
PHP];

// ---------------------------------------------------------------- Accounting: the same hop, the same screen promise
$edits[] = ['app/Http/Controllers/Accounting/AccountingController.php', <<<'PHP'
            'date' => ['column' => 'studentassessments.assessmentDate'],
            'assessmentId' => ['column' => 'studentassessments.assessmentId'],
PHP, <<<'PHP'
            'date' => ['column' => 'studentassessments.assessmentDate'],
            // Ruled 2026-10-31: an ENUM may carry a header. Both columns sit on the enrollment one hop away, spelled
            // BARE so ListSort qualifies them with that hop's own alias rather than asking this query for an
            // `enrollments` table it joined under a different name.
            'studentType' => [
                'column' => 'studentType',
                'join' => 'enrollments',
                'foreign' => 'enrollmentId',
                'local' => 'studentassessments.enrollmentId',
            ],
            'academicStanding' => [
                'column' => 'academicStanding',
                'join' => 'enrollments',
                'foreign' => 'enrollmentId',
                'local' => 'studentassessments.enrollmentId',
            ],
            'assessmentId' => ['column' => 'studentassessments.assessmentId'],
PHP];

$edits[] = ['resources/js/Pages/Accounting/Index.jsx', <<<'PHP'
        // Course, Student Type and Standing carry no sortKey: the first names a relation the cashier does not work
        // by, and the other two belong to the enrollment the sheet points at rather than to the row listed here.
PHP, <<<'PHP'
        // Course carries no sortKey: it names a relation the cashier does not work by. Student Type and Standing did
        // withhold a header until the owner ruled 2026-10-31 that an ENUM may be sorted — the school's server orders
        // it by the declaration the school wrote — and the handler reaches both through the enrollment hop a name
        // sort already builds. Payment Status stays out because the screen derives it from the balance.
PHP];

$edits[] = ['resources/js/Pages/Accounting/Index.jsx', <<<'PHP'
        { key: 'enrollment.studentType', label: 'Student Type', render: (row) => (
PHP, <<<'PHP'
        { key: 'enrollment.studentType', label: 'Student Type', sortKey: 'studentType', render: (row) => (
PHP];

$edits[] = ['resources/js/Pages/Accounting/Index.jsx', <<<'PHP'
        { key: 'enrollment.academicStanding', label: 'Standing', render: (row) => (
PHP, <<<'PHP'
        { key: 'enrollment.academicStanding', label: 'Standing', sortKey: 'academicStanding', render: (row) => (
PHP];

// ---------------------------------------------------------------- stage 1: assert every needle before writing anything
$contents = [];
$bad = [];
foreach ($edits as $i => [$file, $from, $to]) {
    if (! array_key_exists($file, $contents)) {
        $contents[$file] = (string) @file_get_contents($file);
        if ($contents[$file] === '') {
            $bad[] = 'edit '.($i + 1).": {$file} is missing or empty";

            continue;
        }
    }
    $n = substr_count($contents[$file], $from);
    if ($n !== 1) {
        $bad[] = sprintf('edit %d in %s matched %d time(s), expected exactly 1', $i + 1, $file, $n);
    }
}

if ($bad !== []) {
    fwrite(STDERR, "REFUSED — nothing was written:\n  ".implode("\n  ", $bad)."\n");
    exit(1);
}

// ---------------------------------------------------------------- stage 2: apply
$bytesBefore = $contents;
foreach ($edits as [$file, $from, $to]) {
    $contents[$file] = str_replace($from, $to, $contents[$file]);
}
foreach ($contents as $file => $bytes) {
    if ($bytes === $bytesBefore[$file]) {
        fwrite(STDERR, "REFUSED: {$file} did not change.\n");
        exit(1);
    }
    file_put_contents($file, $bytes);
    echo 'wrote '.$file."\n";
}
echo 'applied '.count($edits)." edit(s) across ".count($contents)." file(s)\n";
