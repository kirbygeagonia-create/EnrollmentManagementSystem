<?php

/**
 * Lane 111: append the §28.3 G-52 wire cases to the six desk fixtures that already build the records.
 *
 * Each case drives a real desk GET and asks the NESTED student node which columns travelled. The source-side guard
 * (`DeskStudentLoadColumnsTest`) refuses a bare `'student'` literal and the seven queue classes assert the queue
 * payload; neither covers a record screen's props, and a column list is a claim about the `students` table that only
 * a 200 plus a key set can prove — the shape lane 108 met when a narrowed list named `id` and every sort case stayed
 * green because an order case reads row sequences and never asks the response for its status.
 *
 * A patcher rather than six hand edits because each insertion asserts its own precondition: the target ends on a
 * single class-closing brace, the method name occurs zero times, and the written file keeps its line count plus the
 * appended body. `{$helper}` is interpolated by the heredoc on purpose: the helper's namespace is one string, not six
 * copies that can each drift.
 */
$root = dirname(__DIR__, 3);

$helper = '\Tests\Support\DeskStudentPayload::assertNameOnly(';

$cases = [
    'tests/Feature/Clinic/ClinicControllerTest.php' => [<<<PHP
    #[Test]
    public function the_clinic_record_screen_ships_only_the_student_name(): void
    {
        \$this->actingAs(\$this->staffForOffice(11));
        \$enrollment = \$this->createEnrollment();

        \$props = \$this->get(route('clinic.show', \$enrollment))->assertOk()->viewData('page')['props'];

        // Clinic/Show paints the student's name line and the record's own fields; the person's demographics belong to
        // the record screen, not to every desk that reads one counter entry. §28.3 G-52.
        {$helper}\$enrollment->student, \$props['enrollment']['student'] ?? null, 'clinic.show', true);
    }
    PHP],

    'tests/Feature/ID/IDControllerTest.php' => [<<<PHP
    #[Test]
    public function the_id_request_screen_ships_only_the_student_name(): void
    {
        \$this->actingAs(\$this->staffForOffice(22));
        \$enrollment = \$this->createEnrollment();

        \$props = \$this->get(route('id.show', \$enrollment))->assertOk()->viewData('page')['props'];

        // The ID desk reads a name, a school number and a course for the card it orders. §28.3 G-52.
        {$helper}\$enrollment->student, \$props['enrollment']['student'] ?? null, 'id.show', true);
    }
    PHP],

    'tests/Feature/Accounting/PartPaymentAndRefundTest.php' => [<<<PHP
    #[Test]
    public function the_fee_sheet_screen_ships_only_the_student_name(): void
    {
        \$assessment = \$this->createOwingAssessment();

        \$props = \$this->actingAs(\$this->cashier)
            ->get(route('accounting.show', \$assessment))
            ->assertOk()
            ->viewData('page')['props'];

        // Five columns, not six: this screen is reached through the enrollment and Accounting/Show's own import graph
        // prints no name suffix, which is what the census read rather than a constant shared with the clinic desk.
        // §28.3 G-52.
        {$helper}\$assessment->enrollment->student, \$props['assessment']['enrollment']['student'] ?? null, 'accounting.show');
    }
    PHP],

    'tests/Feature/Assessment/ScholarshipWithdrawalTest.php' => [<<<PHP
    #[Test]
    public function the_assessment_screen_ships_only_the_student_name(): void
    {
        \$assessment = \$this->assess();

        \$props = \$this->actingAs(\$this->officer)
            ->get(route('assessment.show', \$assessment))
            ->assertOk()
            ->viewData('page')['props'];

        // Assessment/Show prints the sheet beside a name line; the person record is not this office's to pull whole.
        // §28.3 G-52.
        {$helper}\$assessment->enrollment->student, \$props['assessment']['enrollment']['student'] ?? null, 'assessment.show');
    }
    PHP],

    'tests/Feature/Blocking/BlockingControllerTest.php' => [<<<PHP
    #[Test]
    public function the_blocking_screen_ships_only_the_student_name_on_both_rosters(): void
    {
        \$this->actingAs(\$this->staffForOffice(5));

        \$fixture = \$this->createBlockWithSchedule();
        \$block = \$fixture['block'];
        \$schedule = \$fixture['schedule'];

        \$assigned = \$this->createEnrollment();
        \$this->post(route('blocking.assign', \$block), [
            'enrollmentIds' => [\$assigned->enrollmentId],
            'scheduleId' => \$schedule->scheduleId,
        ])->assertSessionHasNoErrors();

        \$props = \$this->get(route('blocking.show', \$block))->assertOk()->viewData('page')['props'];

        // One screen, two student reads, and they travel differently on purpose: the roster rides inside the block's
        // own eager load, so its node is the identity set; the assign pick-list is mapped by hand into four name
        // keys. Both are asserted, because a narrowed list that emptied the hand-built row would leave the roster
        // green. §28.3 G-52.
        {$helper}(
            \$assigned->student,
            \$props['block']['enrolledSubjects'][0]['enrollment']['student'] ?? null,
            'blocking.show (the block roster)',
            true,
        );

        // The pick-list only carries a row that is NOT seated in this block, so a second enrollment is what proves
        // the map — and the map's own key set is the claim, not a value comparison against a row nobody paints.
        \$candidate = \$this->createEnrollment();
        \$this->createWorkflowAtBlockingStep(\$candidate);

        \$second = \$this->get(route('blocking.show', \$block))->assertOk()->viewData('page')['props'];
        \$row = collect(\$second['eligibleEnrollments'])->firstWhere('enrollmentId', \$candidate->enrollmentId);
        \$this->assertNotNull(\$row, 'the unassigned enrollment never reached the assign pick-list, so this case would'
            .' be certifying an empty list');

        \$keys = array_keys(\$row['student']);
        sort(\$keys);
        \$this->assertSame(
            ['firstName', 'lastName', 'middleName', 'schoolIdNumber'],
            \$keys,
            'the assign pick-list map is a statement of what the desk paints; a demographic key arriving here is'
            .' §28.3 G-52 reopened'
        );
    }
    PHP],

    'tests/Feature/Print/PrintControllerTest.php' => [<<<PHP
    #[Test]
    public function the_three_registrar_print_pages_ship_only_the_student_name(): void
    {
        \$student = \$this->createStudent();
        \$enrollment = \$this->createEnrolledEnrollment(\$student);
        \$registrar = \$this->createStaffForOffice(1);

        // The paper is the claim here, not only the screen: each of the three documents leads with the student's name,
        // so a column list that dropped it would print a blank where the Registrar signs. Asserted per page rather
        // than once, because each action writes its own eager-load list and nothing shares them (§25.4's lesson).
        \$pages = [
            'registrar.print-certificate' => 'Registrar/PrintCertificate',
            'registrar.print-class-cards' => 'Registrar/PrintClassCards',
            'registrar.print-subject-load' => 'Registrar/PrintSubjectLoad',
        ];

        foreach (\$pages as \$routeName => \$component) {
            \$response = \$this->actingAs(\$registrar)->get(route(\$routeName, \$enrollment));
            \$props = \$response->assertOk()->viewData('page')['props'];
            \$this->assertSame(\$component, \$props['component']);

            {$helper}\$student, \$props['enrollment']['student'] ?? null, \$routeName, true);
        }
    }
    PHP],
];

$apply = in_array('--apply', $argv, true);
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unknown argument {$arg}; this script reads only --apply.\n");

        exit(1);
    }
}

$problems = [];
$plan = [];

foreach ($cases as $rel => [$body]) {
    $path = $root.'/'.$rel;
    if (! is_file($path)) {
        $problems[] = 'no file '.$rel;

        continue;
    }
    $bytes = (string) file_get_contents($path);
    $start = strpos($body, 'public function ');
    $name = substr($body, $start + 16, strpos($body, '(): void') - $start - 16);
    if (($hits = substr_count($bytes, $name)) !== 0) {
        $problems[] = "{$rel}: {$name} already occurs {$hits} time(s) — refusing to duplicate a case";

        continue;
    }
    $lines = explode("\n", $bytes);
    $last = count($lines) - 1;
    while ($last >= 0 && trim($lines[$last]) === '') {
        $last--;
    }
    if (trim($lines[$last]) !== '}') {
        $problems[] = "{$rel}: the last content line reads '".trim($lines[$last])."', not a lone class brace";

        continue;
    }
    $offset = 0;
    for ($i = 0; $i < $last; $i++) {
        $offset += strlen($lines[$i]) + 1;
    }
    $plan[] = ['rel' => $rel, 'offset' => $offset, 'body' => "\n".$body."\n", 'name' => $name,
        'before' => $bytes, 'lines' => substr_count($bytes, "\n")];
    printf("  %-54s +%4d bytes  %s\n", $rel, strlen($body) + 1, $name);
}

if ($problems !== []) {
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", array_unique($problems))."\n");

    exit(1);
}

printf('planned %d case(s)%s', count($plan), PHP_EOL);
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

foreach ($plan as $p) {
    $path = $root.'/'.$p['rel'];
    $written = substr_replace($p['before'], $p['body'], $p['offset'], 0);
    file_put_contents($path, $written);
    $again = (string) file_get_contents($path);
    if ($again !== $written) {
        fwrite(STDERR, "REFUSED: {$p['rel']} did not come back as written.\n");

        exit(1);
    }
    if (substr_count($again, "\n") !== $p['lines'] + substr_count($p['body'], "\n")) {
        fwrite(STDERR, "REFUSED: {$p['rel']} moved ".substr_count($again, "\n").' lines; expected the body appended'
            ." and nothing else.\n");

        exit(1);
    }
    if (substr_count($again, 'class '.$p['name']) !== 0 || substr_count($again, $p['name']) !== 1) {
        fwrite(STDERR, "REFUSED: {$p['rel']} carries {$p['name']} ".substr_count($again, $p['name'])
            .' time(s), expected exactly the one new case.\n');

        exit(1);
    }
}
echo 'written, re-read.'.PHP_EOL;
