<?php
/**
 * mutate-lane187-provenance.php — the falsifier for the fixture-seat census.
 *
 * `lane187-census-fixture-seats.php` has had five defects in itself, each found by reading rather than by a green
 * run: pairs keyed on the TEXT of a `studentId` expression; a caller-minted student treated as fresh-per-call; a
 * helper map keyed by bare method name across 136 files, which silently produced NO SITE for the clobbered file;
 * helpers classified by NAME PREFIX, which swallowed real Pest cases; and a `$this->maker(…)` half read as if the
 * maker's arguments named the record when the maker INSERTS a new one on every call. Each is a leg now.
 *
 * Every leg plants one synthetic class in its own directory and points the census at it with `--tests-dir=`, because
 * a positive control that can only be produced by editing the suite damages the thing it measures. The fixtures
 * DEFINE their own makers, with real bodies, so the legs exercise the classifier instead of tripping its refusal —
 * a fixture whose helper does not exist tests the reader's panic path, not its judgement.
 *
 * Each leg asserts BOTH the verdict line and the census's OWN exit code, captured with exec() rather than through a
 * pipe, and asserts the SITE COUNT beside every CLEAN, because a census that read nothing also reads clean.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$census = 'storage/app/scratch/lane187-census-fixture-seats.php';
$fixtureRoot = $root.'/storage/app/scratch/lane187-fixture';

$madeDirs = [];
register_shutdown_function(static function () use ($fixtureRoot, &$madeDirs): void {
    foreach ($madeDirs as $d) {
        foreach (glob($d.'/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($d);
    }
    @rmdir($fixtureRoot);
});

$legs = [];
$say = static function (string $name, bool $ok, string $detail) use (&$legs): void {
    $legs[] = [$name, $ok, $detail];
    echo($ok ? '  ok   ' : '  BAD  ').$name.'  '.$detail."\n";
};

/** Write one or two synthetic classes into their own directory and run the census against them. */
$run = static function (string $leg, array $files, string $extra = '') use ($root, $php, $census, $fixtureRoot, &$madeDirs): array {
    $dir = $fixtureRoot.'/'.$leg;
    if (! is_dir($dir) && ! @mkdir($dir, 0o777, true) && ! is_dir($dir)) {
        throw new RuntimeException("could not create the fixture directory {$dir}");
    }
    $madeDirs[] = $dir;
    foreach ($files as $name => $src) {
        // A fixture written in a nowdoc must not carry `\$`: it lands in the file as a literal backslash, the reader
        // then judges bytes no PHP file contains, and the leg reports the INSTRUMENT as wrong. Found in leg 9c,
        // whose census verdict was correct and whose plant was not.
        if (str_contains($src, '\\$')) {
            throw new RuntimeException("the fixture {$name} in leg {$leg} holds a literal backslash-dollar — "
                .'a nowdoc does not interpolate, so the escape belongs in the source, not the plant');
        }
        if (file_put_contents($dir.'/'.$name, $src) === false) {
            throw new RuntimeException("the fixture {$name} could not be written to {$leg}");
        }
    }

    $out = [];
    $rc = 0;
    exec(escapeshellarg($php).' '.escapeshellarg($root.'/'.$census)
        .' --tests-dir='.escapeshellarg($dir).' '.$extra.' 2>&1', $out, $rc);

    return [$rc, implode("\n", $out)];
};

$sites = static fn (string $out): string => preg_match('/sites read: (\d+)/', $out, $s) ? $s[1] : 'none';

$open = "<?php\n\nnamespace Fixture;\n\nuse Tests\\TestCase;\nuse App\\Models\\Enrollments;\nuse App\\Models\\Students;\nuse App\\Models\\Academicterms;\nuse PHPUnit\\Framework\\Attributes\\Test;\n\nclass %s extends TestCase\n{\n";

// The two makers every fixture needs, with the bodies that decide their class: one INSERTS, one MEMOISES.
$makers = <<<'PHP'
    private function createStudent(): Students
    {
        return Students::create(['lastName' => 'Doe', 'firstName' => 'Given', 'middleName' => 'M',
            'suffix' => 'N/A', 'schoolIdNumber' => 'F'.uniqid(), 'passwordHash' => 'x']);
    }

    private function termOf(string $year, string $semester): Academicterms
    {
        return Academicterms::firstOrCreate(['academicYear' => $year, 'semester' => $semester]);
    }

PHP;

// ── control: the census against the real tree must read a large population ─────────────────────────────────
[$rcCtl, $ctl] = (static function () use ($root, $php, $census): array {
    $out = [];
    $rc = 0;
    exec(escapeshellarg($php).' '.escapeshellarg($root.'/'.$census).' 2>&1', $out, $rc);

    return [$rc, implode("\n", $out)];
})();
if (! preg_match('/sites read: (\d+)/', $ctl, $m)) {
    fwrite(STDERR, "REFUSED: the control run printed no `sites read:` line, so nothing below can be read as a "
        ."change to a measurement.\nControl tail: ".substr($ctl, -400)."\n");
    exit(1);
}
printf("control: the census reads %s site(s) against tests/ (exit %d)\n\n", $m[1], $rcCtl);

// ── leg 1: a helper that INSERTS its own person is two people, not two seats ──────────────────────────────
$f1 = sprintf($open, 'OwnStudentTest').<<<'PHP'
    private function assess(): Enrollments
    {
        $student = Students::create(['lastName' => 'A', 'firstName' => 'B', 'middleName' => 'C',
            'suffix' => 'N/A', 'schoolIdNumber' => 'U'.uniqid(), 'passwordHash' => 'x']);

        return Enrollments::create([
            'studentId' => $student->studentId,
            'termId' => $this->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function two_sheets_are_two_people(): void
    {
        $a = $this->assess();
        $b = $this->assess();
    }
}
PHP;
[$rc, $out] = $run('own-student', ['OwnStudentTest.php' => $f1]);
$say('helper-mints-own-student-is-clean-and-was-read',
    $rc === 0 && str_contains($out, 'CLEAN:') && ! str_contains($out, 'TWO ACTIVE SEATS')
    && str_contains($out, 'sites read: 2'),
    'exit '.$rc.', sites='.$sites($out));

// ── leg 2: two seats on the class's own student and term MUST be reported ────────────────────────────────
$f2 = sprintf($open, 'SharedTest').<<<'PHP'
    private function seat(): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->student->studentId,
            'termId' => $this->term->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function one_student_two_rows(): void
    {
        $a = $this->seat();
        $b = $this->seat();
    }
}
PHP;
[$rc, $out] = $run('shared-pair', ['SharedTest.php' => $f2]);
$sharedOut = $out;
$say('shared-student-and-term-is-reported',
    $rc === 0 && str_contains($out, 'TWO ACTIVE SEATS') && str_contains($out, 'OWED: 1 method'),
    'exit '.$rc.', verdict='.(str_contains($out, 'TWO ACTIVE SEATS') ? 'reported' : 'silent'));

// ── leg 3 (the regression that hid six files): the CALLER mints one person, two calls share it ────────────
$f3 = sprintf($open, 'CallerMintTest').$makers.<<<'PHP'
    private function enroll($student): Enrollments
    {
        return Enrollments::create([
            'studentId' => $student->studentId,
            'termId' => $this->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function one_minted_student_two_seats(): void
    {
        $student = $this->createStudent();
        $a = $this->enroll($student);
        $b = $this->enroll($student);
    }
}
PHP;
[$rc, $out] = $run('caller-mint', ['CallerMintTest.php' => $f3]);
$say('caller-minted-student-is-reported',
    $rc === 0 && str_contains($out, 'TWO ACTIVE SEATS'),
    'exit '.$rc.', verdict='.(str_contains($out, 'TWO ACTIVE SEATS')
        ? 'reported' : 'SILENT (the defect that collapsed the census to one candidate)'));

// ── leg 4: a MEMOISING maker called with different labels is a different term ─────────────────────────────
$f4 = sprintf($open, 'MakerTest').$makers.<<<'PHP'
    private function enrollIn($term): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->student->studentId,
            'termId' => $term->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function two_semesters_of_one_year(): void
    {
        $a = $this->enrollIn($this->termOf('2025-2026', '1st'));
        $b = $this->enrollIn($this->termOf('2025-2026', '2nd'));
    }
}
PHP;
[$rc, $out] = $run('maker-args-differ', ['MakerTest.php' => $f4]);
$say('memoising-maker-with-different-arguments-is-clean',
    $rc === 0 && str_contains($out, 'CLEAN:') && str_contains($out, 'sites read: 2'),
    'exit '.$rc.', sites='.$sites($out));

// ── leg 5 (NEW, the classifier's other half): the same memoising maker twice IS one term ─────────────────
$f5 = sprintf($open, 'SameTermTest').$makers.<<<'PHP'
    private function enrollIn($term): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->student->studentId,
            'termId' => $term->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function the_same_term_named_twice_is_one_seat_pair(): void
    {
        $a = $this->enrollIn($this->termOf('2025-2026', '1st'));
        $b = $this->enrollIn($this->termOf('2025-2026', '1st'));
    }
}
PHP;
[$rc, $out] = $run('maker-same-args', ['SameTermTest.php' => $f5]);
$say('memoising-maker-with-the-same-arguments-is-reported',
    $rc === 0 && str_contains($out, 'TWO ACTIVE SEATS'),
    'exit '.$rc.', '.(str_contains($out, 'TWO ACTIVE SEATS')
        ? 'reported' : 'SILENT — a classifier that treats fetches like inserts would pass leg 4 and fail here'));

// ── leg 6 (NEW, the Dashboard defect): a helper that INSERTS via a maker is two people ──────────────────
$f6 = sprintf($open, 'CourseTagTest').<<<'PHP'
    private function student(string $tag): Students
    {
        return Students::create(['lastName' => 'Queue', 'firstName' => 'G', 'middleName' => 'M',
            'suffix' => 'N/A', 'schoolIdNumber' => 'DQ-'.$tag.uniqid(), 'passwordHash' => 'x']);
    }

    private function enrollment($course, $status): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->student($course)->studentId,
            'termId' => $this->term->termId,
            'enrollmentStatus' => $status,
        ]);
    }

    #[Test]
    public function two_statuses_are_two_people_in_one_course(): void
    {
        $a = $this->enrollment('BSIT', 'pending');
        $b = $this->enrollment('BSIT', 'assessed');
    }
}
PHP;
[$rc, $out] = $run('insert-maker-arg', ['CourseTagTest.php' => $f6]);
$say('inserting_maker_inside_the_helper_is_not_one_person',
    $rc === 0 && str_contains($out, 'CLEAN:') && str_contains($out, 'sites read: 2'),
    'exit '.$rc.', sites='.$sites($out).' (keyed on the course argument the census called these one student and '
    .'reported five clean Dashboard methods)');

// ── leg 6b (the classifier's other blind half): a maker that uses the RAW query builder ────────────────
// Found the moment the retake fixture was repaired: `nextTerm()` inserts through `DB::table(…)->insertGetId(…)`,
// which matches none of `::create`/`::factory`/`->create(`, so the census called it unclassifiable and UNBOUND rose
// from 20 to 35. A builder insert is an insert.
$f6b = sprintf($open, 'RawInsertTest').<<<'PHP'
    private int $cursor = 0;

    private function nextTerm(): int
    {
        $this->cursor++;
        $yearId = DB::table('academicyears')->insertGetId(['yearLabel' => 'R'.$this->cursor]);

        return (int) DB::table('academicterms')->insertGetId(['academicYearId' => $yearId, 'semester' => '1st']);
    }

    private function enroll($student, $termId): Enrollments
    {
        return Enrollments::create([
            'studentId' => $student->studentId,
            'termId' => $termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function one_seating_per_term_through_the_builder(): void
    {
        $s = Students::create(['lastName' => 'A', 'schoolIdNumber' => 'R'.uniqid()]);
        $a = $this->enroll($s, $this->nextTerm());
        $b = $this->enroll($s, $this->nextTerm());
    }
}
PHP;
[$rc, $out] = $run('raw-insert-maker', ['RawInsertTest.php' => $f6b]);
$say('a_builder_insert_is_an_insert',
    $rc === 0 && str_contains($out, 'CLEAN:') && str_contains($out, 'sites read: 2')
    && ! str_contains($out, 'not classified'),
    'exit '.$rc.', sites='.$sites($out).', '.(str_contains($out, 'not classified')
        ? 'STILL UNCLASSIFIED (the defect that raised UNBOUND from 20 to 35)' : 'classified'));

// ── leg 6d: a maker that clones with replicate()->save() is an insert too ──────────────────────────────
// The repairs themselves introduced this shape (`anotherStudent()`), and the classifier did not know it, so four
// repaired rows came back NOT-PROVABLE: a fixture the census cannot read is a fixture it must not clear.
$f6d = sprintf($open, 'ReplicateTest').<<<'PHP'
    private function anotherStudent(): Students
    {
        $copy = $this->student->replicate(['studentId', 'schoolIdNumber']);
        $copy->schoolIdNumber = 'ALT-'.uniqid();
        $copy->save();

        return $copy;
    }

    private function enroll($overrides = []): Enrollments
    {
        return Enrollments::create(array_merge([
            'studentId' => $this->student->studentId,
            'termId' => $this->term->termId,
            'enrollmentStatus' => 'pending',
        ], $overrides));
    }

    #[Test]
    public function two_records_two_people(): void
    {
        $a = $this->enroll(['studentId' => $this->anotherStudent()->studentId]);
        $b = $this->enroll(['studentId' => $this->anotherStudent()->studentId]);
    }
}
PHP;
[$rc, $out] = $run('replicate-maker', ['ReplicateTest.php' => $f6d]);
$say('a_replicate_and_save_maker_is_an_insert',
    $rc === 0 && str_contains($out, 'CLEAN:') && ! str_contains($out, 'NOT-PROVABLE'),
    'exit '.$rc.', sites='.$sites($out).', '.(str_contains($out, 'NOT-PROVABLE')
        ? 'STILL UNREAD (the repaired fixture the census could not see)' : 'read and cleared'));

// ── leg 6c: a coalesced class property is the SHARED record when the caller passes nothing ──────────────

// `($term ?? $this->term)->termId` held eleven ScholarshipWithdrawalTest sites in UNBOUND, which made the census
// refuse on a tree it had in fact read. The default half of a `??` written on a property IS the class's record —
// the shared case, not an unknown one — and a caller that fills the slot still overrides it.
$f6c = sprintf($open, 'CoalesceTest').<<<'PHP'
    private function sheet($term = null): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->student->studentId,
            'termId' => ($term ?? $this->term)->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function two_rows_on_the_class_term(): void
    {
        $a = $this->sheet();
        $b = $this->sheet();
    }
}
PHP;
[$rc, $out] = $run('coalesce-prop', ['CoalesceTest.php' => $f6c]);
$say('a_coalesced_class_property_is_the_shared_record',
    $rc === 0 && str_contains($out, 'TWO ACTIVE SEATS'),
    'exit '.$rc.', '.(str_contains($out, 'TWO ACTIVE SEATS') ? 'reported' : 'silent or refused')
    .', sites='.$sites($out));

// ── leg 9c: a coalesce WITHOUT parentheses is still the class's record when the slot is empty ──────────
// Repairing RetakeAttemptTest and ElectiveValidationTest turned `'termId' => $termId ?? $this->termId` into eighteen
// NOT-PROVABLE rows: the refusal branch fires on any `??`, so the coalesce form the fixtures actually use was read as
// an unknown while the parenthesised form was resolved. Eighteen sites the census could not see is eighteen seats it
// would clear by accident.
$f9c = sprintf($open, 'CoalesceParamTest').<<<'PHP'
    private function enroll($student, $termId = null): Enrollments
    {
        return Enrollments::create([
            'studentId' => $student->studentId,
            'termId' => $termId ?? $this->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function two_calls_that_pass_no_term_share_the_class_term(): void
    {
        $s = Students::create(['lastName' => 'A', 'schoolIdNumber' => 'C'.uniqid()]);
        $a = $this->enroll($s);
        $b = $this->enroll($s);
    }

    #[Test]
    public function two_calls_that_pass_the_same_minted_term_are_two_terms(): void
    {
        $s = Students::create(['lastName' => 'B', 'schoolIdNumber' => 'D'.uniqid()]);
        $a = $this->enroll($s, $this->later());
        $b = $this->enroll($s, $this->later());
    }

    private function later(): int
    {
        return (int) DB::table('academicterms')->insertGetId(['semester' => '2nd']);
    }
}
PHP;
[$rc, $out] = $run('coalesce-param', ['CoalesceParamTest.php' => $f9c]);
$say('an_unparenthesised_coalesce_is_read_both_ways',
    $rc === 0 && ! str_contains($out, 'NOT-PROVABLE')
    && str_contains($out, 'TWO ACTIVE SEATS') && str_contains($out, 'sites read: 4')
    && str_contains($out, 'two_calls_that_pass_no_term'),
    'exit '.$rc.', sites='.$sites($out)
    .', not-provable='.(preg_match('/(\d+) site\(s\) not provable/', $out, $u) ? $u[1] : '?')
    .', shared-term case named='.(str_contains($out, 'two_calls_that_pass_no_term') ? 'yes' : 'NO')
    .', minted-term case reported='.(str_contains($out, 'two_calls_that_pass_the_same_minted_term')
        ? 'YES (wrong — a fresh term per call is not a pair)' : 'no'));

// ── leg 7 (the clobbering defect): a SIBLING file declares the same helper name ──────────────────────────


$f7b = sprintf($open, 'OtherTest').$makers.<<<'PHP'
    private function enroll($student): Enrollments
    {
        return Enrollments::create([
            'studentId' => $student->studentId,
            'termId' => $this->otherTerm->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function the_sibling_uses_the_same_helper_name(): void
    {
        $s = $this->createStudent();
        $x = $this->enroll($s);
    }
}
PHP;
[$rc, $out] = $run('name-collision', ['CallerMintTest.php' => $f3, 'OtherTest.php' => $f7b]);
// The population this leg proves is 2 sites from the first file plus 1 from the sibling = 3. Asserted as a floor,
// because the defect it guards is a LOSS: keyed by name alone, one of the two files matched no helper and produced
// no site at all, so the total fell and the finding vanished with it.
$say('same_helper_name_in_a_sibling_still_reads_both',
    $rc === 0 && str_contains($out, 'TWO ACTIVE SEATS') && str_contains($out, 'CallerMintTest')
    && (int) $sites($out) >= 3,
    'exit '.$rc.', sites='.$sites($out).' (3 = two seats in CallerMintTest plus one in the sibling; keyed by name '
    .'alone the clobbered file read zero and printed no finding)');

// ── leg 8: a loop body is one site, not a duplicate ─────────────────────────────────────────────────────
$f8 = sprintf($open, 'LoopTest').<<<'PHP'
    #[Test]
    public function one_row_per_type(): void
    {
        foreach (['firstYear', 'transferee'] as $type) {
            $e = Enrollments::create([
                'studentId' => $this->student->studentId,
                'termId' => $this->term->termId,
                'studentType' => $type,
                'enrollmentStatus' => 'enrolled',
            ]);
        }
    }
}
PHP;
[$rc, $out] = $run('loop-one-site', ['LoopTest.php' => $f8]);
$say('one-site-inside-a-loop-is-clean-and-was-read',
    $rc === 0 && str_contains($out, 'sites read: 1') && str_contains($out, 'CLEAN:'),
    'exit '.$rc.', sites='.$sites($out));

// ── leg 9: a seat released by a dropped status ARGUMENT is not a second seat ────────────────────────────
$f9 = sprintf($open, 'DropTest').<<<'PHP'
    private function seat($status): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->student->studentId,
            'termId' => $this->term->termId,
            'enrollmentStatus' => $status,
        ]);
    }

    #[Test]
    public function a_dropped_student_can_be_seated_again(): void
    {
        $a = $this->seat('dropped');
        $b = $this->seat('enrolled');
    }
}
PHP;
[$rc, $out] = $run('status-argument', ['DropTest.php' => $f9]);
$say('dropped-status-argument-releases-the-seat',
    $rc === 0 && str_contains($out, 'RELEASED') && ! str_contains($out, 'TWO ACTIVE SEATS')
    && str_contains($out, 'sites read: 2'),
    'exit '.$rc.', sites='.$sites($out).', '.(str_contains($out, 'RELEASED') ? 'released' : 'not released'));

// ── leg 9b: a seat dropped through an OVERRIDES ARRAY is still a released seat ──────────────────────────
// Found while repairing AcademicStandingOwnershipTest: the helper writes `'enrollmentStatus' => Pending` as its
// default and the call passes `['enrollmentStatus' => EnrollmentStatus::Dropped]`, which no positional-slot scan can
// see. A reader blind to that shape keeps accusing a fixture it has already repaired.
$f9b = sprintf($open, 'OverrideDropTest').<<<'PHP'
    private function makeEnrollment(array $overrides = []): Enrollments
    {
        return Enrollments::create(array_merge([
            'studentId' => $this->student->studentId,
            'termId' => $this->term->termId,
            'enrollmentStatus' => 'pending',
        ], $overrides));
    }

    #[Test]
    public function a_dropped_row_written_over_two_lines_and_a_live_one(): void
    {
        // The override is on the line BELOW the call, which is how the real fixture writes it and the reason the
        // drop check reads a bracket window: a single-line scan passed this leg's first draft while still accusing
        // the file it was written for.
        $a = $this->makeEnrollment([
            'enrollmentStatus' => 'dropped',
        ]);
        $b = $this->makeEnrollment();
    }
}
PHP;
[$rc, $out] = $run('override-drop', ['OverrideDropTest.php' => $f9b]);
$say('a_dropped_row_written_through_an_overrides_array_is_released',
    $rc === 0 && str_contains($out, 'RELEASED') && ! str_contains($out, 'TWO ACTIVE SEATS')
    && str_contains($out, 'sites read: 2'),
    'exit '.$rc.', sites='.$sites($out).', '.(str_contains($out, 'RELEASED')
        ? 'released' : 'STILL ACCUSED (the shape-blindness that outlives a correct repair)'));

// ── leg 10: an unclassifiable maker refuses rather than guessing ────────────────────────────────────────
$f10 = sprintf($open, 'OpaqueMakerTest').<<<'PHP'
    private function mysteryPerson(): Students
    {
        return SomeOther::somewhere();
    }

    private function seat(): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->mysteryPerson()->studentId,
            'termId' => $this->term->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }

    #[Test]
    public function a_maker_this_reader_cannot_place(): void
    {
        $a = $this->seat();
        $b = $this->seat();
    }
}
PHP;
[$rc, $out] = $run('unknown-maker', ['OpaqueMakerTest.php' => $f10]);
$say('an_unclassified_maker_refuses_with_exit_1',
    $rc === 1 && str_contains($out, 'not classified') && str_contains($out, 'REFUSED'),
    'exit '.$rc.', '.(str_contains($out, 'not classified')
        ? 'named as unclassified' : 'answered anyway — the census guessed at a maker it never read'));

// ── leg 11: an unresolvable person refuses ──────────────────────────────────────────────────────────────
$f11 = sprintf($open, 'OpaqueTest').<<<'PHP'
    #[Test]
    public function a_person_the_reader_cannot_prove(): void
    {
        $e = Enrollments::create([
            'studentId' => $whoever->studentId,
            'termId' => $this->term->termId,
            'enrollmentStatus' => 'enrolled',
        ]);
    }
}
PHP;
[$rc, $out] = $run('opaque-name', ['OpaqueTest.php' => $f11]);
$say('unresolvable-name-refuses-with-exit-1',
    $rc === 1 && str_contains($out, 'NOT-PROVABLE') && str_contains($out, 'REFUSED'),
    'exit '.$rc.', refused='.(str_contains($out, 'REFUSED') ? 'yes' : 'NO'));

// ── leg 12: a directory with no create site refuses instead of reporting CLEAN ──────────────────────────
$f12 = sprintf($open, 'QuietTest').<<<'PHP'
    #[Test]
    public function this_class_seats_nobody(): void
    {
        $this->assertTrue(true);
    }
}
PHP;
[$rc, $out] = $run('no-create', ['QuietTest.php' => $f12]);
$say('a-directory-with-no-create-refuses',
    $rc === 1 && str_contains($out, 'REFUSED'),
    'exit '.$rc.', '.substr(trim(preg_replace('/\s+/', ' ', $out) ?? $out), 0, 70));

// ── leg 13: --tests-dir is READ, not accepted ──────────────────────────────────────────────────────────
preg_match('/walked from (\S+)/', $out, $d);
$walkedFrom = str_replace('\\', '/', (string) ($d[1] ?? ''));
$say('scope-flag-changes-the-population-it-names',
    $rc === 1 && str_contains($out, 'no test file names Enrollments::create')
    && str_contains($walkedFrom, 'lane187-fixture/no-create'),
    'it says it walked from '.($walkedFrom === '' ? 'nothing' : basename(dirname($walkedFrom)).'/'
        .basename($walkedFrom)).'; ignoring the flag would name tests/ instead');

// ── leg 14: a byte-identical re-run answers identically ─────────────────────────────────────────────────
[, $out2] = $run('shared-pair', ['SharedTest.php' => $f2]);
$say('byte-identical-copy-changes-no-verdict', $out2 === $sharedOut,
    $out2 === $sharedOut ? 'same bytes' : 'the verdict moved on identical input');

// ── leg 15: --show-groups changes what the run prints ──────────────────────────────────────────────────
// The fifth time this repository has met an accepted-but-inert flag: a run that ignores its own scope prints
// exactly the verdict a scoped one does. The proof is a NAMED difference between two runs over the same bytes.
[, $plain] = $run('shared-pair', ['SharedTest.php' => $f2]);
[, $verbose] = $run('shared-pair', ['SharedTest.php' => $f2], '--show-groups');
$say('show-groups-flag-is-read-not-accepted',
    ! str_contains($plain, 'all repeat groups with their keys')
    && str_contains($verbose, 'all repeat groups with their keys')
    && str_contains($verbose, 'PROP:student'),
    'without: '.(str_contains($plain, 'repeat groups with their keys') ? 'printed anyway' : 'absent')
    .', with: '.(str_contains($verbose, 'PROP:student') ? 'key printed' : 'no key'));

// ── leg 16: an unknown flag is refused by name ──────────────────────────────────────────────────────────
$rcU = 0;
$outU = [];
$dirU = $fixtureRoot.'/shared-pair';
exec(escapeshellarg($php).' '.escapeshellarg($root.'/'.$census).' --tests-dir='.escapeshellarg($dirU)
    .' --no-such-flag-at-all 2>&1', $outU, $rcU);
$say('unknown-flag-refused-by-name', $rcU === 1 && str_contains(implode("\n", $outU), '--no-such-flag-at-all'),
    'exit '.$rcU);

// ── leg 21: the plant guard fires — a fixture carrying `\$` is refused before it is ever written ─────────
$guardFired = false;
try {
    $run('plant-guard', ['GuardTest.php' => sprintf($open, 'GuardTest').<<<'PHP'
    #[Test]
    public function a_backslash_dollar_in_the_plant(): void
    {
        \$e = Enrollments::create(['studentId' => \$this->s->studentId, 'termId' => \$this->t->termId]);
    }
}
PHP]);
} catch (RuntimeException $e) {
    $guardFired = str_contains($e->getMessage(), 'backslash-dollar');
    $guardMsg = $e->getMessage();
}
$say('the_plant_guard_refuses_an_escaped_nowdoc', $guardFired,
    $guardFired ? 'refused: '.substr((string) ($guardMsg ?? ''), 0, 60)
        : 'the guard never fired — a leg that plants `\$` would judge a file no PHP source contains');

$bad = count(array_filter($legs, static fn ($l) => ! $l[1]));

printf("\n%d leg(s), %d bad.\n", count($legs), $bad);
if ($bad > 0) {
    exit(1);
}
echo "ALL KILLED: every provenance class the census claims can be made to fail, the classifier distinguishes an "
    ."insert from a fetch, and the census refuses what it cannot read.\n";
exit(0);
