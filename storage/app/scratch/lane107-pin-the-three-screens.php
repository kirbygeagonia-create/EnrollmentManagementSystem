<?php

/**
 * Lane 107 stage 2 — pin the three screens the ruling reached. Every needle must occur EXACTLY ONCE in its own file
 * or nothing is written; then each file is parsed, because an unwrapped test method is a silent loss of a case.
 */
$registrar = 'tests/Feature/Registrar/RegistrarQueueSortTest.php';
$assessment = 'tests/Feature/Sorting/AssessmentQueueSortTest.php';
$accounting = 'tests/Feature/Sorting/AccountingQueueSortTest.php';

// Both shapes are tolerated on purpose: an enum cast can reach the payload as the backing word or as
// {name,value}, and the screen reads `row.studentType?.value || row.studentType` for the same reason.
$word = "is_array(\$r['%s'] ?? null) ? (string) \$r['%s']['value'] : (string) (\$r['%s'] ?? '')";

$edits = [];

// ------------------------------------------------------------------ Registrar
$edits[] = [$registrar,
    "    private function assessed(string \$lastName, int \$yearLevel = 1, EnrollmentStatus \$status = EnrollmentStatus::Assessed): Enrollments",
    "    private function assessed(\n        string \$lastName,\n        int \$yearLevel = 1,\n        EnrollmentStatus \$status = EnrollmentStatus::Assessed,\n        string \$studentType = 'firstYear',\n        string \$academicStanding = 'regular',\n    ): Enrollments"];

$edits[] = [$registrar, <<<'PHP'
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'enrollmentStatus' => $status,
PHP, <<<'PHP'
            'studentType' => $studentType,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => $academicStanding,
            'enrollmentStatus' => $status,
PHP];

$registrarEnum = str_replace('%s', 'studentType', $word);
$registrarStanding = str_replace('%s', 'academicStanding', $word);
$edits[] = [$registrar, <<<'PHP'
        $this->assertSame(['name', 'schoolId', 'yearLevel', 'status', 'enrollmentId'], $sort['available']);
    }
}
PHP, <<<PHP
        \$this->assertSame(
            ['name', 'schoolId', 'yearLevel', 'status', 'studentType', 'academicStanding', 'enrollmentId'],
            \$sort['available']
        );
    }

    #[Test]
    public function the_enum_headers_order_the_words_this_connection_sorts_them_in(): void
    {
        // Three types and two standings, seated so the ENUM sequence matches neither id order: Bautista is created
        // second yet sorts first by type, and Magsaysay shares Zulueta's standing while differing in type. The
        // sequence is ASKED of the connection, because MySQL reads an ENUM by its declaration index while the suite's
        // varchar-with-CHECK reads the stored word — a literal typed here would be a reading of whichever server
        // answered, which is exactly what CI named on the clearance queue.
        \$this->assessed('Zulueta', 1, EnrollmentStatus::Assessed, 'transferee', 'irregular');
        \$this->assessed('Bautista', 2, EnrollmentStatus::Paid, 'firstYear', 'regular');
        \$this->assessed('Magsaysay', 1, EnrollmentStatus::Assessed, 'continuing', 'irregular');

        \$types = \$this->actingAs(\$this->registrar)
            ->get(route('registrar.index', ['sort' => 'studentType', 'direction' => 'asc']));
        \$this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'studentType', [
                StudentType::FirstYear->value,
                StudentType::Continuing->value,
                StudentType::Transferee->value,
            ]),
            array_map(fn (array \$r): string => {$registrarEnum}, \$this->rows(\$types))
        );

        \$standings = \$this->actingAs(\$this->registrar)
            ->get(route('registrar.index', ['sort' => 'academicStanding', 'direction' => 'asc']));
        \$this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'academicStanding', ['regular', 'irregular', 'irregular']),
            array_map(fn (array \$r): string => {$registrarStanding}, \$this->rows(\$standings))
        );
    }
}
PHP];

// ------------------------------------------------------------------ Assessment
$edits[] = [$assessment, "use Tests\\TestCase;", "use Tests\\Support\\EnumColumnOrder;\nuse Tests\\TestCase;"];

$edits[] = [$assessment,
    "    private function sheet(string \$lastName, string \$schoolId, float \$assessed, float \$owing): Studentassessments",
    "    private function sheet(\n        string \$lastName,\n        string \$schoolId,\n        float \$assessed,\n        float \$owing,\n        string \$studentType = 'firstYear',\n        string \$academicStanding = 'regular',\n    ): Studentassessments"];

$edits[] = [$assessment, <<<'PHP'
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
PHP, <<<'PHP'
            'studentType' => $studentType,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => $academicStanding,
PHP];

$assessType = str_replace('%s', 'studentType', $word);
$assessStanding = str_replace('%s', 'academicStanding', $word);
$edits[] = [$assessment, <<<'PHP'
        // Payment Status is painted on the screen from the balance against the total, Course is a name the desk
        // reads but does not work the queue by, and Student Type and Standing belong to the enrollment rather than
        // to the sheet. A header the handler would not obey is the defect §28.3 owns for filters, so the keys are
        // absent rather than quietly ignored.
        foreach (['paymentStatus', 'course', 'studentType', 'academicStanding'] as $notOffered) {
            $this->assertNotContains($notOffered, $available, "{$notOffered} is not a column this query orders");
        }
        $this->assertSame(['name', 'schoolId', 'amount', 'balance', 'assessmentId'], $available);
    }
PHP, <<<PHP
        // Payment Status is painted on the screen from the balance against the total, so no order over the sheet can
        // answer it, and Course is a name the desk reads but does not work the queue by. Student Type and Standing
        // WERE in this list until the owner ruled 2026-10-31 that an ENUM may be sorted: they live on the enrollment
        // the sheet points at, which is one hop the surname sort already builds — reachability was the rule all
        // along, and the driver was never a good reason.
        foreach (['paymentStatus', 'course'] as \$notOffered) {
            \$this->assertNotContains(\$notOffered, \$available, "{\$notOffered} is not a column this query orders");
        }
        \$this->assertSame(
            ['name', 'schoolId', 'amount', 'balance', 'studentType', 'academicStanding', 'assessmentId'],
            \$available
        );
    }

    #[Test]
    public function the_enum_headers_reach_the_enrollment_through_the_same_hop_and_order_the_words_this_connection_holds(): void
    {
        // A bare column name with a hop is the shape here: ListSort qualifies it with the join's own alias, so this
        // case is also the only thing that proves the handler did not spell `enrollments.studentType` and ask the
        // query for a table it never joined — which would be a 500 at the desk, not a wrong sequence.
        \$this->sheet('Zulueta', 'AS-010', 7000, 1000, 'transferee', 'irregular');
        \$this->sheet('Bautista', 'AS-011', 5000, 3000, 'firstYear', 'regular');
        \$this->sheet('Magsaysay', 'AS-012', 6000, 2000, 'continuing', 'irregular');

        \$types = \$this->actingAs(\$this->officer)
            ->get(route('assessment.index', ['sort' => 'studentType', 'direction' => 'asc']));
        \$this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'studentType', [
                StudentType::FirstYear->value,
                StudentType::Continuing->value,
                StudentType::Transferee->value,
            ]),
            array_map(fn (array \$r): string => {$assessType}, \$this->rows(\$types))
        );

        \$standings = \$this->actingAs(\$this->officer)
            ->get(route('assessment.index', ['sort' => 'academicStanding', 'direction' => 'asc']));
        \$this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'academicStanding', ['regular', 'irregular', 'irregular']),
            array_map(fn (array \$r): string => {$assessStanding}, \$this->rows(\$standings))
        );
    }
PHP];

// ------------------------------------------------------------------ Accounting
$edits[] = [$accounting, "use Tests\\TestCase;", "use Tests\\Support\\EnumColumnOrder;\nuse Tests\\TestCase;"];

$edits[] = [$accounting,
    "        float \$covered = 0,\n    ): Studentassessments {",
    "        float \$covered = 0,\n        string \$studentType = 'firstYear',\n        string \$academicStanding = 'regular',\n    ): Studentassessments {"];

$edits[] = [$accounting, <<<'PHP'
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
PHP, <<<'PHP'
            'studentType' => $studentType,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => $academicStanding,
PHP];

$acctType = str_replace('%s', 'studentType', $word);
$acctStanding = str_replace('%s', 'academicStanding', $word);
$edits[] = [$accounting, <<<'PHP'
        // 'Covered By' prints a SUM of two columns, and Course, Student Type and Standing belong to the enrollment
        // or the program rather than to the row the cashier collects against. Absent from the list rather than
        // offered and ignored, because a header the handler would not obey is the same defect §28.3 owns for filters.
        foreach (['coveredBy', 'course', 'studentType', 'academicStanding'] as $derived) {
            $this->assertNotContains($derived, $available, "{$derived} is not a column this query orders");
        }
        $this->assertSame(['name', 'schoolId', 'amount', 'balance', 'date', 'assessmentId'], $available);
    }
PHP, <<<PHP
        // 'Covered By' prints a SUM of two columns and Course names a program the cashier does not work by, so the
        // query cannot answer either. Student Type and Standing left this list on 2026-10-31, when the owner ruled
        // that an ENUM may be sorted: they sit on the enrollment one hop away, and the hop is what the surname sort
        // already builds. Absent rather than offered-and-ignored is still the rule for what remains here.
        foreach (['coveredBy', 'course'] as \$derived) {
            \$this->assertNotContains(\$derived, \$available, "{\$derived} is not a column this query orders");
        }
        \$this->assertSame(
            ['name', 'schoolId', 'amount', 'balance', 'date', 'studentType', 'academicStanding', 'assessmentId'],
            \$available
        );
    }

    #[Test]
    public function the_enum_headers_order_the_words_this_connection_sorts_them_in(): void
    {
        // Same shape as the assessment desk: a bare column plus the enrollments hop, so the sequence the screen
        // shows is the one this connection holds rather than the one whoever wrote the test had in mind.
        \$this->sheet('Zulueta', 'AC-010', 7000, 1000, '2026-09-01', 0, 'transferee', 'irregular');
        \$this->sheet('Bautista', 'AC-011', 5000, 3000, '2026-09-02', 0, 'firstYear', 'regular');
        \$this->sheet('Magsaysay', 'AC-012', 6000, 2000, '2026-09-03', 0, 'continuing', 'irregular');

        \$types = \$this->actingAs(\$this->cashier)
            ->get(route('accounting.index', ['sort' => 'studentType', 'direction' => 'asc']));
        \$this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'studentType', [
                StudentType::FirstYear->value,
                StudentType::Continuing->value,
                StudentType::Transferee->value,
            ]),
            array_map(fn (array \$r): string => {$acctType}, \$this->rows(\$types))
        );

        \$standings = \$this->actingAs(\$this->cashier)
            ->get(route('accounting.index', ['sort' => 'academicStanding', 'direction' => 'asc']));
        \$this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'academicStanding', ['regular', 'irregular', 'irregular']),
            array_map(fn (array \$r): string => {$acctStanding}, \$this->rows(\$standings))
        );
    }
PHP];

// ------------------------------------------------------------------ stage 1
$contents = [];
$bad = [];
foreach ($edits as $i => [$file, $from, $to]) {
    if (! array_key_exists($file, $contents)) {
        $loaded = @file_get_contents($file);
        if ($loaded === false || $loaded === '') {
            $bad[] = "edit ".($i + 1).": {$file} is missing or empty";

            continue;
        }
        $contents[$file] = $loaded;
    }
    $n = substr_count($contents[$file], $from);
    if ($n !== 1) {
        $bad[] = sprintf('edit %d in %s matched %d time(s), expected exactly 1', $i + 1, $file, $n);
    }
    if ($from === $to) {
        $bad[] = 'edit '.($i + 1).' replaces a needle with itself';
    }
}
if ($bad !== []) {
    fwrite(STDERR, "REFUSED — nothing was written:\n  ".implode("\n  ", $bad)."\n");
    exit(1);
}

// ------------------------------------------------------------------ stage 2
$before = $contents;
foreach ($edits as [$file, $from, $to]) {
    $contents[$file] = str_replace($from, $to, $contents[$file]);
}
foreach ($contents as $file => $bytes) {
    if ($bytes === $before[$file]) {
        fwrite(STDERR, "REFUSED: {$file} did not change.\n");
        exit(1);
    }
    file_put_contents($file, $bytes);
    echo 'wrote '.$file."\n";
}
echo 'applied '.count($edits)." edit(s) across ".count($contents)." file(s)\n";
