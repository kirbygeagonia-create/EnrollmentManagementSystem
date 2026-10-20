<?php

/**
 * Lane 55 — measure what `detectConflicts()` does not ask, without leaving a red tree behind.
 *
 * Three questions, each written as the behaviour a school would expect, appended to the real Blocking test class
 * so they run on its fixtures, then removed. A probe that PASSES tells us the guard already answers it; a probe
 * that goes RED is a measured gap, not an opinion. The pristine bytes are held in memory and restored in a
 * `finally`, so an interrupted run cannot leave a failing test in the tree — that is how a mutant harness is
 * built here, and the same discipline applies to a probe that edits a real test file.
 *
 * Usage: php storage/app/scratch/lane55-probe-guard.php
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$target = $root.'/tests/Feature/Blocking/BlockingControllerTest.php';

$pristine = (string) file_get_contents($target);
$hash0 = hash('sha256', $pristine);
$eol = str_contains($pristine, "\r\n") ? "\r\n" : "\n";

$probes = <<<'PHP'

    // ---- lane 55 probes, temporary ----

    #[Test]
    public function probe_the_room_branch_reports_what_it_built(): void
    {
        $this->actingAs($this->staffForOffice(5));
        $fixture = $this->createBlockWithSchedule();
        $temp = new Schedules([
            'blockId' => $fixture['block']->blockId,
            'subjectId' => Subjects::skip(1)->firstOrFail()->subjectId,
            'instructorId' => $this->staffForOffice(4)->userId,
            'roomId' => $fixture['room']->roomId,
        ]);
        $q = Schedulemeetings::whereHas('schedule', fn ($w) => $w->where('roomId', $temp->roomId))
            ->where('dayOfWeek', 'Monday')
            ->where(fn ($w) => $w->where('startTime', '<', '10:00')->where('endTime', '>', '09:00'))
            ->where('meetingId', '!=', null);
        $this->assertFalse(true, "attributes=".json_encode($temp->getAttributes())." fillable=".json_encode(Schedules::class ? (array) (new Schedules)->getFillable() : [])." sql=".$q->toRawSql()." exists=".var_export($q->exists(), true)." storedRoomId=".var_export(Schedules::first()?->roomId, true));
    }


    #[Test]
    public function probe_the_room_branch_refuses_a_room_taken_by_another_officer(): void
    {
        // The positive control, and it runs first: the create-path test the class already has clashes on the
        // instructor *and* the room and asserts the instructor's message, so nothing so far has proved the
        // room branch can fire at all. If this passes, a red result below is about the block, not about a
        // fixture that never reached the controller.
        $this->actingAs($this->staffForOffice(5));

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];
        $otherOfficer = $this->staffForOffice(4);

        $response = $this->post(route('blocking.schedules.store', $block), [
            'subjectId' => Subjects::skip(1)->firstOrFail()->subjectId,
            'instructorId' => $otherOfficer->userId,
            'roomId' => $fixture['room']->roomId,
            'meetings' => [
                ['dayOfWeek' => 'Monday', 'startTime' => '09:00', 'endTime' => '10:00'],
            ],
        ]);

        file_put_contents(base_path('/storage/app/scratch/lane55-diag.txt'), json_encode([
            'status' => $response->getStatusCode(),
            'errors' => session('errors')?->all(),
            'keys' => array_keys(session('errors')?->getMessages() ?? []),
            'schedules_for_block' => Schedules::where('blockId', $block->blockId)->count(),
            'room_id_sent' => $fixture['room']->roomId,
            'meetings' => Schedulemeetings::get(['meetingId','scheduleId','dayOfWeek','startTime','endTime'])->toArray(),
        ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $response->assertSessionHasErrors('conflicts', null, 'the room branch never fired: a room was double-booked');
        $this->assertStringContainsString(
            'Room conflict',
            session('errors')->get('conflicts')[0] ?? '',
            'the refusal did not name the room'
        );
    }

    #[Test]
    public function probe_a_block_is_never_in_two_rooms_at_the_same_hour(): void
    {
        // One block, two subjects, two rooms, two officers, one hour. `schedules.store` is scoped to the block
        // in the URL, so this is the ordinary way a timetable is built — and nothing asks the block's own hour.
        $this->actingAs($this->staffForOffice(5));

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];
        $otherOfficer = $this->staffForOffice(4);

        $response = $this->post(route('blocking.schedules.store', $block), [
            'subjectId' => Subjects::skip(1)->firstOrFail()->subjectId,
            'instructorId' => $otherOfficer->userId,
            'roomId' => Rooms::skip(1)->firstOrFail()->roomId,
            'meetings' => [
                ['dayOfWeek' => 'Monday', 'startTime' => '09:00', 'endTime' => '10:00'],
            ],
        ]);

        $response->assertSessionHasErrors('conflicts', null, 'the same block was booked into two rooms at one hour');
    }

    #[Test]
    public function probe_a_retired_term_does_not_block_this_term_s_room(): void
    {
        // The opposite direction: a meeting in one term must not refuse a save in another, because `schedules`
        // carries no term and the conflict query filters on nothing else either. Asserting the row was created
        // as well as the absence of an error, so a request that died for an unrelated reason cannot read as a
        // pass.
        $this->actingAs($this->staffForOffice(5));

        $past = $this->createBlockWithSchedule();
        $yearId = Academicterms::firstOrFail('academicYearId')->academicYearId;
        $secondTerm = Academicterms::create([
            'academicYearId' => $yearId,
            'semester' => '1st',
            'startDate' => '2026-01-01',
            'endDate' => '2026-05-30',
        ]);

        $blockB = Blocks::create([
            'courseId' => $past['block']->courseId,
            'termId' => $secondTerm->termId,
            'yearLevel' => 1,
            'blockName' => 'Other Term Block '.uniqid(),
            'maxStudents' => 40,
        ]);

        $response = $this->post(route('blocking.schedules.store', $blockB), [
            'subjectId' => Subjects::skip(1)->firstOrFail()->subjectId,
            'instructorId' => $past['instructor']->userId,
            'roomId' => $past['room']->roomId,
            'meetings' => [
                ['dayOfWeek' => 'Monday', 'startTime' => '09:00', 'endTime' => '10:00'],
            ],
        ]);

        $this->assertSame(
            1,
            Schedules::where('blockId', $blockB->blockId)->count(),
            'the other term’s schedule was not saved: '.json_encode(session('errors')?->all() ?? [])
        );
    }
}
PHP;

$probeMethods = [
    'probe_the_room_branch_reports_what_it_built',
    'probe_the_room_branch_refuses_a_room_taken_by_another_officer',
    'probe_a_block_is_never_in_two_rooms_at_the_same_hour',
    'probe_a_retired_term_does_not_block_this_term_s_room',
];

// Replace the class's final closing brace with the probes (asserted exactly once).
$lastBrace = strrpos($pristine, '}');
if ($lastBrace === false) {
    fwrite(STDERR, "REFUSED: cannot find the class's closing brace.\n");
    exit(1);
}
$mutated = substr($pristine, 0, $lastBrace).str_replace("\n", $eol, $probes);
if ($mutated === $pristine) {
    fwrite(STDERR, "REFUSED: the probe text changed nothing.\n");
    exit(1);
}

$finished = false;
$restore = function () use ($target, $pristine, $hash0): void {
    file_put_contents($target, $pristine);
    if (hash('sha256', (string) file_get_contents($target)) !== $hash0) {
        fwrite(STDERR, "BAD: the restore did not reproduce the original bytes.\n");
    }
};
register_shutdown_function(function () use ($restore, &$finished): void {
    if (! $finished) {
        fwrite(STDERR, "The run ended early — restoring the test file.\n");
        $restore();
    }
});

file_put_contents($target, $mutated);
printf("Probes appended (%d bytes added). Running each in its own process.\n\n", strlen($mutated) - strlen($pristine));

$lines = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($target)));
foreach ($probeMethods as $method) {
    $found = false;
    foreach ($lines as $l) {
        if (str_contains($l, "function {$method}")) {
            $found = true;
            break;
        }
    }
    if (! $found) {
        printf("  BAD  %s is not in the file — nothing was measured\n", $method);
    }
}

$exit = 0;
foreach ($probeMethods as $method) {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' artisan test --filter='.escapeshellarg($method).' 2>&1', $out, $code);
    $log = implode("\n", $out);
    $summary = '';
    foreach (explode("\n", $log) as $line) {
        if (str_contains($line, 'Tests:')) {
            $summary = trim($line);
        }
    }
    $verdict = str_contains($summary, '1 passed') ? 'PASSED — the guard already answers this'
        : (str_contains($summary, '1 failed') ? 'RED — measured gap' : 'NO VERDICT: '.$summary);
    printf("  %-46s %s\n", $method, $verdict);
    if ($summary === '') {
        printf("      the run printed no Tests: line; exit %d\n%s\n", $code, substr($log, -400));
        $exit = 1;
        continue;
    }
    foreach (explode("\n", $log) as $line) {
        if (str_contains($line, 'the ') || str_contains($line, 'Failed asserting') || str_contains($line, 'attributes=')) {
            printf("      %s\n", trim(preg_replace('/\e\[[0-9;]*m/', '', $line)));
        }
    }
}

$restore();
$finished = true;

printf("\nTest file restored: %s\n", hash('sha256', (string) file_get_contents($target)) === $hash0 ? 'bytes identical' : 'BYTES DIFFER — check the tree');
exit($exit);
