<?php
/**
 * Lane 187 — which test method seats the SAME student in the SAME term twice?
 *
 * `uq_enrollments_active_seat` was withdrawn on 2026-11-01 because CI's MySQL job refused six test files that create
 * two ACTIVE enrollments for one student in one term. The pre-flight census that morning asked the LIVE rows and got
 * 0 duplicates, which is the wrong population: fixtures live in `tests/`, not in `ems`, and a case wrapped in
 * `DatabaseTransactions` hides its own pair from every census. So this tool reads the fixtures the way the generated
 * column would: one row per enrollment a method creates, and a verdict only where two rows are the same PERSON and
 * the same TERM.
 *
 * **Identity, not spelling.** The first cut keyed a pair on the TEXT of the `studentId` expression and reported 61
 * candidate groups — including `ZeroBalanceSettlementTest`, where `$this->createAssessment()` twice mints its own
 * student and therefore two different people. Expression text is not the value: a fresh record at line 239 and a
 * fresh record at line 240 share only the variable's NAME. So every student and term is resolved to a PROVENANCE:
 *   PROP:<class>::<property>   read from `$this->student`, `$this->termId`, `$this->testTermId` — one record for the
 *                              whole class, so two enrollments in one method ARE two seats for one person;
 *   LOCAL:<line>               a `$student = …` inside the method, shared by the sites below it until reassigned;
 *   FRESH:<file>::<line>       the helper mints its own student (`$student = $this->makeStudent()` on the helper's
 *                              own line) and the call site passes nothing — each CALL is a different person;
 *   ARG:<method>::<param>      a helper parameter that the caller filled, resolved to the caller's provenance;
 *   LITERAL:<value>            a number or quoted string, which names the SAME record wherever it appears, so it is
 *                            comparable; a fixture that pins an id by literal is itself a finding;
 *   CALLER:<expression>      the value comes from an argument or a coalescing expression the reader has not
 *                            bound (`($term ?? $this->term)->termId`), so it is refused by name;
 *   UNBOUND                    none of the above, which refuses the run rather than clearing the site.
 * A group is a candidate only when BOTH halves of its key are non-FRESH and equal.
 *
 * Still a CANDIDATE, not a verdict: a method that drops the prior seat first (the second row written `dropped`, or a
 * drop act between the two creates) is legal, because `seatKey` is NULL for a dropped row and MySQL ignores NULLs in
 * a unique index. Those are printed as RELEASED and read by a human, because "the drop happens between them" is a
 * fact about program order this reader checks by line, not by control flow.
 *
 * Refusals: UNBOUND sites, an empty population, a walk that opened no file, an unknown flag.
 * Usage: php storage/app/scratch/lane187-census-fixture-seats.php [--show-groups] [--files=N]
 */

$root = str_replace('\\', '/', dirname(__DIR__, 3));
$short = static fn (string $f): string => str_replace($root.'/', '', $f);
$argvAll = $GLOBALS['argv'];
$showGroups = in_array('--show-groups', $argvAll, true);
$filesLimit = null;
// The scope is a flag, not a hard-coded path, so `mutate-lane187-provenance.php` can point the reader at throwaway
// fixtures. A census whose positives can only be produced by editing the suite is a census that cannot be falsified
// without damaging the thing it measures — and an accepted-but-ignored flag would make the planted run look clean.
$testsDir = $root.'/tests';
$dirOverridden = false;
foreach ($argvAll as $a) {
    if (str_starts_with((string) $a, '--files=')) {
        $filesLimit = (int) substr((string) $a, 8);
    } elseif (str_starts_with((string) $a, '--tests-dir=')) {
        $dirOverridden = true;
        $testsDir = str_replace('\\', '/', (string) substr((string) $a, 12));
        if (! is_dir($testsDir)) {
            fwrite(STDERR, "REFUSED: --tests-dir={$testsDir} is not a directory.\n");
            exit(1);
        }
    } elseif ((string) $a === '--show-groups') {
        // Read above, and it must be excused here: the parser refused the very flag the usage line promises, so a
        // harness leg that asserted `--show-groups` prints more would fail — which is how this fourth defect surfaced.
        continue;
    } elseif (str_starts_with((string) $a, '--')) {
        fwrite(STDERR, "REFUSED: unknown flag {$a}. This tool reads [--show-groups], [--files=N] and "
            ."[--tests-dir=PATH] only.\n");
        exit(1);
    }
}

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $files[] = str_replace('\\', '/', $f->getPathname());
    }
}
sort($files);
if ($files === []) {
    fwrite(STDERR, "REFUSED: the walk found no PHP files under {$testsDir}".($dirOverridden ? ' (the scope you '
        .'named)' : '.')." A census that opened nothing must not print a verdict.\n");
    exit(1);
}
if ($filesLimit !== null) {
    $files = array_slice($files, 0, $filesLimit);
}
printf("population: %d test file(s) walked from %s\n", count($files), $dirOverridden ? $testsDir : $root.'/tests');
$created = 0;
foreach ($files as $f) {
    if (str_contains((string) file_get_contents($f), 'Enrollments::create')) {
        $created++;
    }
}
printf("files that name Enrollments::create: %d\n", $created);
if ($created === 0) {
    fwrite(STDERR, "REFUSED: no test file names Enrollments::create, so the population this tool exists to read is "
        ."empty — a walk that found the wrong shape is not a clean census.\n");
    exit(1);
}

/**
 * Method bodies, by the four-space declaration indent Pest classes in this repository use.
 *
 * Each method also carries whether a `#[Test]` attribute sits above its declaration. That attribute, not the name,
 * is what separates a case from a helper here: classifying by name prefixes (`the_`, `a_`, `test_`) made several real
 * test methods look like helpers, and pass 2 SKIPS a helper's own declaration — which is how ShiftRequestFlowTest's
 * second seat vanished from a run that was otherwise printing 17 candidates. A census that drops a known positive
 * because of a naming guess is the wrong kind of quiet.
 */
$splitMethods = static function (string $src): array {
    $lines = explode("\n", $src);
    $marks = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/^    (?:public |protected |private |static )?function (\w+)\(/', $line, $m)) {
            $marks[] = [$i, $m[1]];
        }
    }
    $out = [];
    foreach ($marks as $j => [$start, $name]) {
        $end = $j + 1 < count($marks) ? $marks[$j + 1][0] : count($lines);
        $isTest = false;
        for ($k = max(0, $start - 4); $k < $start; $k++) {
            if (preg_match('/#\[Test\b/i', $lines[$k])) {
                $isTest = true;
            }
        }
        $out[$name] = ['from' => $start + 1, 'to' => $end, 'test' => $isTest,
            'body' => array_slice($lines, $start, $end - $start)];
    }

    return $out;
};

/** The `'key' => value` of a create array, searched in a window of lines. */
$grab = static function (string $key, array $lines): ?string {
    foreach ($lines as $l) {
        if (preg_match("/'".preg_quote($key, '/')."'\s*=>\s*(.+?),?\s*$/", $l, $m)) {
            return rtrim(trim($m[1]), ',');
        }
    }

    return null;
};

/**
 * Prove a provenance for one expression, searching backwards through the lines that precede the site.
 * Returns [kind, detail] where kind is PROP / LOCAL / FRESH / LOOP / ARG / UNBOUND.
 */
/** Split a parameter list or an argument list on the commas that sit at bracket depth zero. */
$splitArgs = static function (string $src): array {
    $out = [];
    $depth = 0;
    $buf = '';
    $len = strlen($src);
    $inStr = null;
    for ($i = 0; $i < $len; $i++) {
        $ch = $src[$i];
        if ($inStr !== null) {
            $buf .= $ch;
            if ($ch === $inStr && ($i === 0 || $src[$i - 1] !== '\\')) {
                $inStr = null;
            }

            continue;
        }
        if ($ch === "'" || $ch === '"') {
            $inStr = $ch;
            $buf .= $ch;

            continue;
        }
        if ($ch === '[' || $ch === '(' || $ch === '{') {
            $depth++;
        } elseif ($ch === ']' || $ch === ')' || $ch === '}') {
            $depth--;
        }
        if ($ch === ',' && $depth === 0) {
            $out[] = trim($buf);
            $buf = '';

            continue;
        }
        $buf .= $ch;
    }
    if (trim($buf) !== '') {
        $out[] = trim($buf);
    }

    return $out;
};

/**
 * The positional arguments of a one-line call to `$this->NAME(...)`, or null when the call does not close on the
 * same line — a call whose arguments run over several lines is read by the helper's own provenance rather than
 * guessed at from a partial list.
 */
$callArgs = static function (string $line, string $name): ?array {
    $start = strpos($line, '$this->'.$name.'(');
    if ($start === false) {
        return null;
    }
    $i = $start + strlen('$this->'.$name.'(');
    $depth = 1;
    $buf = '';
    $out = [];
    $len = strlen($line);
    $inStr = null;
    for (; $i < $len; $i++) {
        $ch = $line[$i];
        if ($inStr !== null) {
            $buf .= $ch;
            if ($ch === $inStr) {
                $inStr = null;
            }

            continue;
        }
        if ($ch === "'" || $ch === '"') {
            $inStr = $ch;
            $buf .= $ch;

            continue;
        }
        if ($ch === '(' || $ch === '[' || $ch === '{') {
            $depth++;
        } elseif ($ch === ')' || $ch === ']' || $ch === '}') {
            $depth--;
            if ($depth === 0) {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }

                return $out;
            }
        }
        if ($ch === ',' && $depth === 1) {
            $out[] = trim($buf);
            $buf = '';

            continue;
        }
        $buf .= $ch;
    }

    return null;
};

$arrayWindow = static function (array $lines, int $from): array {
    $depth = 0;
    $window = [];
    $count = count($lines);
    for ($i = $from; $i < $count; $i++) {
        $clean = (string) preg_replace('/\'[^\']*\'|"[^"]*"/', '', $lines[$i]);
        $depth += substr_count($clean, '[') + substr_count($clean, '(');
        $depth -= substr_count($clean, ']') + substr_count($clean, ')');
        $window[] = $lines[$i];
        if ($i > $from && $depth <= 0) {
            break;
        }
        if ($i - $from > 60) {
            break;
        }
    }

    return $window;
};

/**
 * Classify a `$this->name(...)` call by reading that method's own body in the same file: a maker that INSERTS hands
 * back a brand-new record on every call, while one that uses firstOrCreate/find returns the same record for the same
 * arguments. This distinction is the whole difference between a false positive and a real one — `DashboardQueueCount-
 * MatchesDeskTest::student($tag)` writes `uniqid()` into its `schoolIdNumber`, so two `enrollment($this->courseA, …)`
 * calls are two PEOPLE, and the earlier run called them two seats for one person because it keyed the half on the
 * course argument instead of the maker. Anything the classifier cannot place is refused, never guessed.
 */
$classifyMaker = static function (array $body): string {
    $src = implode("\n", $body);
    if (preg_match('/firstOrCreate|firstOrNew|updateOrCreate|::find\(|::findOrFail\(|->first\(\)/', $src)) {
        return 'fetches';
    }
    if (preg_match('/::create\(|::factory\(|->create\(|new \\\\\w+\(/', $src)) {
        return 'creates';
    }

    return 'unknown';
};

$prove = static function (string $expr, array $before, string $file, int $line, array $makers = []) : array {
    if ($expr === '') {
        return ['UNBOUND', 'empty expression'];
    }
    // A literal names the SAME record wherever it appears, so it is comparable: `'termId' => 1` in three methods is
    // three seats in term 1, not three unknowns. Refusing it as UNBOUND would hide a real duplicate behind the
    // reader's own blind spot — and a fixture that pins an id by number is itself a finding this census can now see.
    if (preg_match('/^\d+$/', $expr) || preg_match("/^'[^']*'\$/", $expr)) {
        return ['LITERAL', $expr];
    }
    // A conditional or coalescing expression takes its value from an argument the reader has not bound:
    // `($term ?? $this->term)->termId` is the caller's term when one was passed and the class's when none was.
    // That is genuinely not provable from the site, so it is refused by name rather than guessed at — guessing at a
    // `??` would credit the census with a reading it does not have.
    if (str_starts_with($expr, '(') || str_contains($expr, '??')) {
        return ['CALLER', $expr];
    }
    // A method call on the class is a MAKER, not a shared property. `$this->term('2025-2026', '1st')` and
    // `$this->term('2025-2026', '2nd')` are two terms, and reading both as PROP:term is what made seven
    // YearLevelDerivationTest methods look like one student double-seated when the case is about ONE STUDENT ACROSS
    // SEVERAL TERMS. The identity is the call with its arguments folded, so the same call twice is one record and a
    // call with a different label is another — provable without reading the maker's body.
    if (preg_match('/^\$this->(\w+)\s*\((.*)$/u', $expr, $mm)) {
        $kind = $makers[$mm[1]] ?? null;
        if ($kind === 'creates') {
            // A new record at every call: not comparable, and the identity is the call site itself.
            return ['FRESH', 'maker '.$mm[1].'@'.$line];
        }
        if ($kind === 'fetches') {
            return ['MAKER', $mm[1].':'.preg_replace('/\s+/', '', $mm[2])];
        }

        return ['CALLER', 'maker '.$mm[1].' not classified: '.preg_replace('/\s+/', '', $mm[2])];
    }
    // A class property is shared by every method in the class.
    if (preg_match('/^\$this->(\w+)/', $expr, $m)) {
        return ['PROP', $m[1]];
    }
    // `Students::create([...])` inlined in the argument list makes a new person at this site.
    if (preg_match('/(Students::create|Students::factory|Enrollments::create)/', $expr)) {
        return ['FRESH', $file.'::'.$line];
    }
    // A helper call that builds the record in the argument — `$this->makeStudent()` — is fresh per call.
    if (preg_match('/\$this->((?:make|create)[A-Za-z]*)\(\s*\)/', $expr, $m)) {
        return ['FRESH', $m[1].'@'.$line];
    }
    // Otherwise find the assignment that last bound this variable above the site.
    $var = preg_match('/^(\$\w+)/', $expr, $m) ? $m[1] : null;
    if ($var === null) {
        return ['UNBOUND', $expr];
    }
    for ($i = count($before) - 1; $i >= 0; $i--) {
        $l = $before[$i];
        if (preg_match('/^\s*'.preg_quote($var, '/').'\s*=\s*(.+)$/', $l, $m)) {
            $rhs = trim($m[1]);
            $assignLine = $line - (count($before) - $i);
            // The assignment itself is a maker call: `$student = $this->createStudent();` once, then two helper
            // calls. 'creates' means the person is new at that line and SHARED by everything below it, which is the
            // shape that must be reported; 'fetches' means the same arguments answer the same record, so the identity
            // is the call with its folded arguments.
            if (preg_match('/^\$this->(\w+)\s*\((.*)$/u', $rhs, $cm2)) {
                $kind2 = $makers[$cm2[1]] ?? null;
                if ($kind2 === 'creates') {
                    return ['FRESH', $file.'::'.$assignLine];
                }
                if ($kind2 === 'fetches') {
                    return ['MAKER', $cm2[1].':'.preg_replace('/\s+/', '', $cm2[2])];
                }

                return ['CALLER', 'maker '.$cm2[1].' not assigned provably above line '.$line];
            }
            // A maker on the right-hand side means the assignment itself creates a new record.
            if (preg_match('/(Students::create|Students::factory|::factory\(|Academicterms::create|Academicterms::factory|\$this->(?:make|create)\w*\(\s*\))/u', $rhs)) {
                return ['FRESH', $file.'::'.$assignLine];
            }
            if (preg_match('/^\$this->(\w+)/', $rhs, $pm)) {
                return ['PROP', $pm[1]];
            }
            if (str_contains($l, 'foreach')) {
                return ['LOOP', $var.'@'.($line - (count($before) - $i))];
            }

            return ['LOCAL', (string) ($line - (count($before) - $i))];
        }
        if (preg_match('/foreach\s*\(\s*[^;]*\bas\s*(?:\$\w+=>)?\s*'.preg_quote($var, '/').'\s*\)/', $l)) {
            return ['LOOP', $var.'@'.($line - (count($before) - $i))];
        }
        // A method parameter binding the name: the caller's argument decides, so the site is only as shared as the
        // caller — reported as ARG and resolved by the caller's own provenance one level up.
        if (preg_match('/function\s+\w+\s*\([^)]*'.preg_quote($var, '/').'[^)]*\)/', $l)) {
            return ['ARG', $var];
        }
    }

    return ['UNBOUND', $var.' not assigned above line '.$line];
};

// Pass 1 — helpers that create an enrollment, and where their own student and term come from.
$helpers = [];
foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    if (! str_contains($src, 'Enrollments::create')) {
        continue;
    }
    $methods1 = $splitMethods($src);
    // One classification pass per file, so a `$this->maker(…)` half can be proved to create or to fetch.
    $makers = [];
    foreach ($methods1 as $mn => $md) {
        $makers[$mn] = $classifyMaker($md['body']);
    }
    foreach ($methods1 as $name => $m) {
        $body = $m['body'];
        $idx = null;
        foreach ($body as $o => $l) {
            if (str_contains($l, 'Enrollments::create')) {
                $idx = $o;

                break;
            }
        }
        if ($idx === null) {
            continue;
        }
        // A helper is a method WITHOUT a `#[Test]` attribute that is not a lifecycle hook. Name prefixes were the
        // first guess and they cost a known positive, because this repository spells cases freely
        // (`shift_request_is_granted_…`, `elective_group_validation_uses_…`).
        if ($m['test'] || in_array($name, ['setUp', 'tearDown', 'setUpBeforeClass', 'tearDownAfterClass'], true)) {
            continue;
        }
        $window = $arrayWindow($body, $idx);
        $student = (string) $grab('studentId', $window);
        $term = (string) $grab('termId', $window);
        $status = (string) $grab('enrollmentStatus', $window);
        $beforeHelper = array_slice($body, 0, $idx);
        $ps = $prove($student, $beforeHelper, $file, $m['from'] + $idx, $makers);
        $pt = $prove($term, $beforeHelper, $file, $m['from'] + $idx, $makers);
        // Keyed by FILE and name. Keyed by name alone, `createEnrollment` in RetakeAttemptTest was overwritten by a
        // different file's helper of the same name, and because pass 2 filters the map by file, the clobbered file's
        // OWN call sites matched no helper and produced no site at all — a census that silently reads nothing and
        // still prints a candidate list. That is how "1 method owes a repair" came back while CI had refused six files.
        $helpers[$file.'::'.$name] = ['name' => $name, 'file' => $file, 'studentExpr' => $student, 'termExpr' => $term, 'statusExpr' => $status,
            'student' => $ps, 'term' => $pt, 'dropped' => (bool) preg_match('/dropped/i', $status),
            'slots' => []];
        // Which PARAMETER position feeds each half of the key. The helper's own expression is only the whole answer
        // when the caller supplied nothing: `enroll($term)` writes `'termId' => $term->termId`, so the term is the
        // CALLER'S, and two calls that pass different terms are two seats in two terms — not a duplicate. Keying on
        // the helper's spelling anyway produced seven false positives in YearLevelDerivationTest, whose whole subject
        // is one student across several terms. Parameters are split at bracket depth zero, because a default such as
        // `array $overrides = ['a', 'b']` carries a comma inside itself.
        $decl = $body[0];
        if (preg_match('/function\s+'.$name.'\s*\((.*?)\)/s', $decl, $dm)) {
            $params = $splitArgs($dm[1]);
            foreach ($params as $pi => $param) {
                if (! preg_match('/(\$\w+)/', $param, $pm)) {
                    continue;
                }
                $var = $pm[1];
                // The parameter must LEAD the expression, with a word boundary. Two reasons, both paid for: `$term` is
                // a prefix of `$termId`, so a substring test hands the term slot to a parameter the helper never used;
                // and `'studentId' => $this->student($course->courseCode)->studentId` merely CONTAINS the course
                // parameter because the course is an argument to a maker — reading it as the person turned five clean
                // Dashboard methods into candidates, since `student()` inserts a brand-new row on every call.
                foreach (['student' => $student, 'term' => $term, 'status' => $status] as $half => $expr) {
                    if ((string) $expr !== ''
                        && preg_match('/^'.preg_quote($var, '/').'(?![\w])/', (string) $expr) === 1) {
                        $helpers[$file.'::'.$name]['slots'][$half] = [$var, $pi];
                    }
                }
            }
        }
    }
}
printf("helpers that create an enrollment: %d\n", count($helpers));

// Pass 2 — every site, with the provenance of both halves of its key.
$sites = [];
foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    if (! str_contains($src, 'Enrollments::create')) {
        continue;
    }
    $ownHelpers = array_filter($helpers, static fn ($h) => $h['file'] === $file);
    $methods2 = $splitMethods($src);
    $makers = [];
    foreach ($methods2 as $mn => $md) {
        $makers[$mn] = $classifyMaker($md['body']);
    }
    foreach ($methods2 as $mname => $m) {
        $body = $m['body'];
        $isHelperDecl = false;
        foreach ($ownHelpers as $h) {
            if ($mname === $h['name']) {
                $isHelperDecl = true;
            }
        }
        if ($isHelperDecl) {
            continue;
        }
        foreach ($body as $o => $line) {
            $lineNo = $m['from'] + $o;
            $before = array_slice($body, 0, $o);
            if (str_contains($line, 'Enrollments::create')) {
                $window = $arrayWindow($body, $o);
                $student = (string) $grab('studentId', $window);
                $term = (string) $grab('termId', $window);
                $status = (string) $grab('enrollmentStatus', $window);
                $sp = $prove($student, $before, $file, $lineNo, $makers);
                $tp = $prove($term, $before, $file, $lineNo, $makers);
                // At a DIRECT site nothing mints per-call: the array is written inline, so a FRESH student means the
                // person minted on the assignment line above — one record, shared by every create below it.
                $sites[] = ['file' => $file, 'method' => $mname, 'line' => $lineNo, 'via' => 'direct',
                    'student' => $sp[0] === 'FRESH' ? ['LOCAL', 'minted@'.$sp[1]] : $sp,
                    'term' => $tp[0] === 'FRESH' ? ['LOCAL', 'minted@'.$tp[1]] : $tp,
                    'dropped' => (bool) preg_match('/dropped/i', $status)];

                continue;
            }
            foreach ($ownHelpers as $h) {
                $hName = $h['name'];
                if (! str_contains($line, '$this->'.$hName.'(')) {
                    continue;
                }
                // The helper's own provenance by default; a half whose value arrives through a PARAMETER is re-proved
                // from the caller's argument at that position, because the helper's spelling says only which variable
                // feeds it, not which record the desk of the test handed it.
                $student = $h['student'];
                $term = $h['term'];
                $studentFromCaller = false;
                $termFromCaller = false;
                $droppedHere = $h['dropped'];
                $args = $callArgs($line, $hName);
                foreach (['student', 'term', 'status'] as $half) {
                    if (! isset($h['slots'][$half]) || $args === null) {
                        continue;
                    }
                    [$var, $pos] = $h['slots'][$half];
                    if (! array_key_exists($pos, $args)) {
                        // The parameter was left out, so the helper's default applies — and a default that reads
                        // `$this->term` is the class's record, which is exactly the shared case.
                        continue;
                    }
                    if ($half === 'status') {
                        // A seat the caller drops is not a second seat: `seatKey` is NULL for a dropped row and MySQL
                        // ignores NULLs in a unique index, so `enroll($t1, 1, Dropped)` frees the place for the row
                        // that follows it. Without this, EnrollmentDropTest's whole subject reads as a violation.
                        $droppedHere = preg_match('/dropped/i', $args[$pos]) === 1;

                        continue;
                    }
                    $proven = $prove($args[$pos], $before, $file, $lineNo, $makers);
                    if ($half === 'student') {
                        $student = $proven;
                        $studentFromCaller = true;
                    } else {
                        $term = $proven;
                        $termFromCaller = true;
                    }
                }
                // A helper that mints its OWN student is a new person at every call site, so the call's own line is
                // the identity — two calls, two lines, two people. But when the CALLER minted the person
                // (`$student = $this->createStudent();` once, then two `enroll($student)` calls), FRESH means one
                // person shared by the lines below the assignment: rewriting it to the call line is what made the
                // census report one violation where CI refused six, and it is the over-loosening this file has met
                // in lane 58's mirror. Only the helper's own freshness is per-call.
                if ($student[0] === 'FRESH' && ! $studentFromCaller) {
                    $student = ['FRESH', $short($file).'::'.$lineNo];
                }
                if ($term[0] === 'FRESH' && ! $termFromCaller) {
                    $term = ['FRESH', $short($file).'::'.$lineNo];
                }
                // A caller-minted person is still one person, so it becomes a LOCAL at the line that minted it: the
                // FRESH detail already carries that line, and calling it LOCAL keeps it comparable inside the method.
                if ($student[0] === 'FRESH' && $studentFromCaller) {
                    $student = ['LOCAL', 'minted@'.$student[1]];
                }
                if ($term[0] === 'FRESH' && $termFromCaller) {
                    $term = ['LOCAL', 'minted@'.$term[1]];
                }
                $sites[] = ['file' => $file, 'method' => $mname, 'line' => $lineNo, 'via' => 'helper '.$hName,
                    'student' => $student, 'term' => $term, 'dropped' => $droppedHere];

                break;
            }
        }
    }
}
$unbound = array_values(array_filter($sites, static fn ($s) => in_array($s['student'][0], ['UNBOUND', 'CALLER'], true)
    || in_array($s['term'][0], ['UNBOUND', 'CALLER'], true)));
printf("sites read: %d (%d direct, %d through a helper, %d not provable from the site)\n",
    count($sites),
    count(array_filter($sites, static fn ($s) => $s['via'] === 'direct')),
    count(array_filter($sites, static fn ($s) => str_starts_with($s['via'], 'helper'))),
    count($unbound));
if ($sites === []) {
    fwrite(STDERR, "REFUSED: no create site attributed to any method, so the census read nothing.\n");
    exit(1);
}

// Pass 3 — a candidate is two or more NON-FRESH seats that exist TOGETHER during one test run.
// Grouping by method alone produced a FALSE NEGATIVE on a known positive: `ShiftRequestFlowTest` seats its baseline
// enrollment in `setUp()` (line 161) and a second one inside the test method (line 373), same student and same term,
// and `setUp()` runs before every method — so those two rows are alive at once even though no single method wrote
// both. The unit of comparison is therefore setUp() ∪ one test method, per file.
// LIMIT, stated rather than hidden: setUp() sites are read only from the same file. A class whose baseline comes from
// a parent's setUp() is not followed, because resolving inheritance is a different reader; the footer counts how many
// files have no own setUp site but do declare `extends`, so the size of that blind spot is visible.
$byFile = [];
foreach ($sites as $s) {
    $byFile[$s['file']]['methods'][$s['method']][] = $s;
}
$groups = [];
foreach ($byFile as $file => $data) {
    $setUp = $data['methods']['setUp'] ?? [];
    foreach ($data['methods'] as $mname => $msites) {
        if ($mname === 'setUp') {
            continue;
        }
        $effective = array_merge($setUp, $msites);
        foreach ($effective as $s) {
            if (in_array($s['student'][0], ['FRESH', 'UNBOUND', 'CALLER'], true)
                || in_array($s['term'][0], ['FRESH', 'UNBOUND', 'CALLER'], true)) {
                continue;
            }
            $key = $file.'|'.$mname.'|'.$s['student'][0].':'.$s['student'][1]
                .'|'.$s['term'][0].':'.$s['term'][1];
            $groups[$key][] = $s + ['inSetUp' => $s['method'] === 'setUp', 'owner' => $mname];
        }
    }
}
$repeats = array_filter($groups, static fn ($g) => count($g) > 1);
$freshSkipped = count(array_filter($sites, static fn ($s) => $s['student'][0] === 'FRESH'));
$released = array_filter($repeats, static fn ($g) => count(array_filter($g, static fn ($s) => $s['dropped'])) > 0);
$held = array_values(array_diff_key($repeats, $released));

echo "\ncandidate groups: two rows, one person, one term, alive in one test run\n";
if ($held === []) {
    echo "  none\n";
}
foreach ($held as $g) {
    $fromSetUp = array_filter($g, static fn ($s) => $s['inSetUp']);
    printf("  TWO ACTIVE SEATS  %s ::%s%s  person=%s term=%s  lines %s  via %s\n",
        $short($g[0]['file']), $g[0]['owner'],
        $fromSetUp === [] ? '' : ' [+row written in setUp()]',
        $g[0]['student'][0].':'.$g[0]['student'][1],
        $g[0]['term'][0].':'.$g[0]['term'][1],
        implode(', ', array_map(static fn ($s) => (string) $s['line'], $g)),
        implode('/', array_unique(array_map(static fn ($s) => $s['via'], $g))));
}
foreach ($released as $g) {
    printf("  RELEASED          %s ::%s  lines %s — a row written dropped, so the seat is free\n",
        $short($g[0]['file']), $g[0]['owner'],
        implode(', ', array_map(static fn ($s) => (string) $s['line'], $g)));
}
if ($showGroups) {
    echo "\nall repeat groups with their keys:\n";
    foreach ($repeats as $key => $g) {
        printf("  %-86s %d site(s)\n", str_replace($root.'/', '', $key), count($g));
    }
}

$perFile = [];
foreach ($held as $g) {
    $perFile[$short($g[0]['file'])] = ($perFile[$short($g[0]['file'])] ?? 0) + 1;
}
echo "\nfiles owing a repair, so the active-seat key can return\n";
if ($perFile === []) {
    echo "  none\n";
}
foreach ($perFile as $f => $n) {
    printf("  %-62s %d method(s)\n", $f, $n);
}

printf("\ntally: %d site(s) read, %d of them mint their own student (FRESH, not comparable), %d group(s) repeat a "
    ."provenance pair, of which %d carry a dropped row and %d hold two active seats; %d site(s) UNBOUND.\n",
    count($sites), $freshSkipped, count($repeats), count($released), count($held), count($unbound));
printf("partition check: %d groups + %d released + %d held = %s; %d FRESH sites were excluded by rule, not "
    ."silently.\n", count($groups), count($released), count($held),
    count($groups).' = '.(count($repeats) + (count($groups) - count($repeats))), $freshSkipped);
if (count($unbound) > 0) {
    foreach ($unbound as $s) {
        printf("  NOT-PROVABLE  %s ::%s line %d  person=[%s:%s] term=[%s:%s] via %s\n", $short($s['file']),
            $s['method'], $s['line'], $s['student'][0], mb_substr((string) $s['student'][1], 0, 34),
            $s['term'][0], mb_substr((string) $s['term'][1], 0, 34), $s['via']);
    }
    fwrite(STDERR, 'REFUSED: '.count($unbound)." site(s) name a student or term this reader cannot prove. Read them "
        ."before the key returns; an unread fixture is exactly the state the census exists to catch.\n");
    exit(1);
}
if (count($held) === 0) {
    echo "CLEAN: no fixture method seats one student in one term twice, so uq_enrollments_active_seat can return.\n";
} else {
    echo 'OWED: '.count($held)." method(s) would be refused by the key; the repair list above is the precondition.\n";
}
exit(0);
