<?php

/**
 * Bulk demo dataset: every desk's queue holds what the owner asked for — 5,000 rows to work
 * through each — with believable student information, and every record-state rule at zero.
 *
 * Why a tool and not a dump: `tools/seed_full_demo_pipeline.php` writes a CAST (a named dozen
 * students the walkthrough narrates). A load test needs a POPULATION, and a population whose
 * status columns were edited by hand is exactly what `ems:audit:record-states` exists to refuse.
 * So each record here is given one POSITION on the workflow form and every fact that position
 * implies is written with it: the signature, the status-history step the money moved through,
 * the fee lines behind the sheet total, the receipt that clears the balance, the health
 * assessment behind a signed Clinic box. Twenty rules is the contract, and the run refuses
 * (exit 1) unless the audit answers zero AND every named desk reaches the target.
 *
 * The positions, and the queue each one feeds:
 *   pending      every box open                  → the Department Evaluation queue and the exam lab
 *   evaluated    box 1 signed, sheet with lines   → the Assessment queue
 *   assessed     boxes 1-2 signed, balance owing  → the Accounting queue, and the Registrar's
 *   paid         boxes 1-3 signed, receipts clear → the Registrar queue
 *   enrolled     boxes 1-5 signed, Clinic open    → the Clinic queue
 *   enrolled     boxes 1-6 signed + health record → the ID queue
 * plus pending applications (Admission), pending clearances (Clearance), timetable blocks
 * (Blocking, whose card counts the blocks its own page lists) and unmarked entrance papers.
 *
 * It never runs against live `ems`: a new write path is proven on a scratch schema first, and the
 * schema name has to say so. Ids are resolved by NAME (courseCode, feeName, `officeN_head`)
 * because a population keyed by position breaks the day the catalogue is re-seeded; enum words and
 * column lists are read from `information_schema`, so a column this schema has never held is a
 * refusal at the pre-flight rather than a 1054 halfway through stage 4.
 *
 * Usage:
 *   php tools/seed_bulk_demo_dataset.php --schema=ems_l268_a --per-office=5000
 *   php tools/seed_bulk_demo_dataset.php --schema=ems_l268_a --per-office=5000 --apply
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "REFUSED: this tool writes rows and runs only from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
require $root.'/vendor/autoload.php';

$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m) === 1) {
        $options[$m[1]] = $m[2] ?? true;
        continue;
    }

    fwrite(STDERR, "REFUSED: this tool does not understand [{$arg}]. Pass --schema= --per-office= and --apply.\n");
    exit(1);
}

$schema = $options['schema'] ?? null;
$perOffice = (int) ($options['per-office'] ?? 5000);
$apply = isset($options['apply']);

if (! is_string($schema) || $schema === '') {
    fwrite(STDERR, "REFUSED: --schema= is required, and it must name a scratch schema, not the live one.\n");
    exit(1);
}

if ($schema === 'ems' || $schema === 'ems_test') {
    fwrite(STDERR, "REFUSED: [{$schema}] is a database this tool must not write into. Name an ems_lNN_x scratch schema.\n");
    exit(1);
}

if (preg_match('/^ems_l\d+_[a-z]$/', $schema) !== 1) {
    fwrite(STDERR, "REFUSED: [{$schema}] does not look like a scratch schema (the pattern is ems_lNN_a).\n");
    exit(1);
}

if ($perOffice < 100) {
    fwrite(STDERR, "REFUSED: --per-office={$perOffice} is not a population, it is a fixture. Refuse rather than report a queue.\n");
    exit(1);
}

putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE='.$schema);
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = $schema;
$_SERVER['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_DATABASE'] = $schema;

$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\OfficeId;
use App\Support\DeskQueues;
use App\Support\SchoolTime;
use Illuminate\Support\Facades\DB;

$fail = static function (string $message): never {
    fwrite(STDERR, "REFUSED: {$message}\n");
    exit(1);
};

set_exception_handler(static function (Throwable $e) use ($fail): never {
    $fail('uncaught '.get_class($e).': '.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine());
});

// The connection and the database must come from the same place, or Laravel opens a SQLite file
// named after the MySQL schema and every row lands somewhere that is not a server.
$driver = DB::connection()->getDriverName();
$active = DB::connection()->getDatabaseName();
if ($driver !== 'mysql' || $active !== $schema) {
    $fail("the connection answers driver [{$driver}] database [{$active}]; the tool was told [{$schema}].");
}

$known = array_map(
    static fn (object $r): string => (string) array_values((array) $r)[0],
    DB::select('show tables')
);
$has = static fn (string $table): bool => in_array($table, $known, true);

if (! $has('enrollments') || ! $has('students') || ! $has('workflowsteps')) {
    $fail("schema [{$schema}] holds ".count($known).' table(s) and none of them is the domain core; migrate and seed before seeding a population.');
}

// ------------------------------------------------------------------ the schema, read not recalled
$tableColumns = [];
foreach (DB::select('select TABLE_NAME as t, COLUMN_NAME as c, IS_NULLABLE as n, COLUMN_DEFAULT as d from information_schema.COLUMNS where TABLE_SCHEMA = ? order by TABLE_NAME, ORDINAL_POSITION', [$schema]) as $col) {
    $tableColumns[$col->t][] = $col->c;
}

$assertRows = static function (string $table, array $records) use ($fail, $tableColumns): void {
    if ($records === []) {
        return;
    }

    $columns = $tableColumns[$table] ?? null;
    if ($columns === null) {
        $fail("table [{$table}] is not on [{$schema}], so nothing may be written into it.");
    }

    $extra = [];
    foreach ($records as $record) {
        foreach (array_keys($record) as $key) {
            if (! in_array($key, $columns, true) && ! in_array($key, $extra, true)) {
                $extra[] = $key;
            }
        }
    }

    if ($extra !== []) {
        $fail("{$table} holds no column named ".implode(', ', $extra).' — it holds: '.implode(', ', $columns));
    }
};

$enumValues = static function (string $table, string $column) use ($schema, $fail): array {
    $type = DB::selectOne('select COLUMN_TYPE as t from information_schema.COLUMNS where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?', [
        $schema, $table, $column,
    ]);

    if ($type === null || preg_match("/enum\((.*)\)/", $type->t, $m) !== 1) {
        $fail("{$table}.{$column} is not an enum column on [{$schema}], so its vocabulary cannot be read from the server.");
    }

    $values = [];
    foreach (explode("','", trim($m[1], "'")) as $value) {
        $values[] = str_replace("\\'", "'", $value);
    }

    if ($values === []) {
        $fail("{$table}.{$column} declares no values.");
    }

    return $values;
};

$picks = [
    'enrollmentStatus' => $enumValues('enrollments', 'enrollmentStatus'),
    'studentType' => $enumValues('enrollments', 'studentType'),
    'enrollmentType' => $enumValues('enrollments', 'enrollmentType'),
    'academicStanding' => $enumValues('enrollments', 'academicStanding'),
    'admissionStatus' => $enumValues('admissions', 'admissionStatus'),
    'applicantType' => $enumValues('admissions', 'applicantType'),
    'stepStatus' => $enumValues('workflowsteps', 'stepStatus'),
    'workflowStatus' => $enumValues('enrollmentworkflow', 'workflowStatus'),
    'paymentStatus' => $enumValues('payments', 'paymentStatus'),
    'paymentMode' => $enumValues('payments', 'paymentMode'),
    'idRequestStatus' => $enumValues('idrequests', 'status'),
    'clearanceOverall' => $enumValues('studentclearances', 'overallStatus'),
    'examStage' => $enumValues('examresults', 'examStage'),
    'examType' => $enumValues('examresults', 'examType'),
    'examResult' => $enumValues('examresults', 'examResult'),
    'resultStatus' => $enumValues('examresults', 'resultStatus'),
    'blockSchedule' => $enumValues('blocks', 'scheduleStatus'),
    'submissionStatus' => $enumValues('studentrequirementsubmissions', 'submissionStatus'),
    'studentStatus' => $enumValues('students', 'status'),
    'periodStatus' => $has('clearanceperiods') ? $enumValues('clearanceperiods', 'periodStatus') : [],
];

$need = static function (string $name, string $value) use ($picks, $fail): string {
    if (! in_array($value, $picks[$name], true)) {
        $fail("value [{$value}] is not declared for [{$name}] on this schema (it holds: ".implode(', ', $picks[$name]).')');
    }

    return $value;
};

/** The first of the wanted words the column actually declares, or a refusal naming the column. */
$resolve = static function (array $declared, array $wanted) use ($fail): string {
    foreach ($wanted as $candidate) {
        if (in_array($candidate, $declared, true)) {
            return $candidate;
        }
    }

    return $fail('none of ['.implode(', ', $wanted).'] is declared on this schema (it holds: '.implode(', ', $declared).') — read the column before writing a row.');
};

$resolved = [
    'courses' => DB::table('courses')->select('courseId', 'courseCode', 'requiresEntranceExam')->get()->all(),
    'terms' => DB::table('academicterms')
        ->join('academicyears', 'academicyears.academicYearId', '=', 'academicterms.academicYearId')
        ->select('academicterms.termId', 'academicterms.semester', 'academicyears.yearLabel')
        ->orderBy('academicterms.termId')->get()->all(),
    'curriculums' => DB::table('curriculums')->select('curriculumId', 'courseId')->get()->all(),
    'feeTypes' => DB::table('feetypes')->select('feeTypeId', 'feeName', 'defaultAmount')->orderBy('feeTypeId')->get()->all(),
    'users' => DB::table('staffusers')->select('userId', 'officeId', 'username')->get()->all(),
    'religions' => DB::table('religions')->select('religionId')->get()->all(),
    'admissionRequirements' => $has('admissionrequirements') ? DB::table('admissionrequirements')->select('requirementId')->get()->all() : [],
    'clearanceRequirements' => DB::table('clearancerequirements')->select('clearanceRequirementId')->get()->all(),
    'periods' => $has('clearanceperiods') ? DB::table('clearanceperiods')->select('clearancePeriodId', 'termId')->get()->all() : [],
    'scholarships' => $has('scholarshiptypes') ? DB::table('scholarshiptypes')->select('scholarshipTypeId', 'coveragePercent')->get()->all() : [],
    'rooms' => $has('rooms') ? DB::table('rooms')->select('roomId')->get()->all() : [],
    'majors' => $has('majors') ? DB::table('majors')->select('majorId')->get()->all() : [],
];

foreach (['courses', 'terms', 'feeTypes', 'users', 'religions', 'periods', 'rooms'] as $pool) {
    if ($resolved[$pool] === []) {
        $fail("the {$pool} pool read empty from [{$schema}]: the reference data must be seeded before a population stands on it.");
    }
}

$curriculumByCourse = [];
foreach ($resolved['curriculums'] as $row) {
    $curriculumByCourse[(int) $row->courseId] = (int) $row->curriculumId;
}

/** Desk signers by the username the seeders use, so a re-seated office is never addressed by id. */
$signers = [];
foreach (OfficeId::cases() as $office) {
    $match = collect($resolved['users'])->first(
        static fn (object $u): bool => (int) $u->officeId === $office->value && str_starts_with((string) $u->username, 'office')
    );

    if ($match === null) {
        $match = collect($resolved['users'])->first(fn (object $u): bool => (string) $u->username === 'admin');
    }

    if ($match === null) {
        $fail('no desk account stands at office '.$office->value.' and no admin to sign for it — a completed box with no signer is one of the twenty rules.');
    }

    $signers[$office->value] = (int) $match->userId;
}

$feeLines = [];
foreach ($resolved['feeTypes'] as $fee) {
    $amount = (float) $fee->defaultAmount;
    if ($amount > 0) {
        $feeLines[] = ['feeTypeId' => (int) $fee->feeTypeId, 'amount' => $amount];
    }
}
if ($feeLines === []) {
    $fail('no fee type carries a positive default amount, so no sheet total could be defended by its own lines.');
}

$today = SchoolTime::today();
$stamp = 'B'.date('ymd', strtotime($today)).'-';
$positions = ['pending', 'evaluated', 'assessed', 'paid', 'enrolled-clinic', 'enrolled-id'];
$positionsPerStudent = 8;
$studentCount = $perOffice * $positionsPerStudent;

printf(
    "Vocabulary from [%s]: %d enums, %d courses, %d terms, %d priced fee lines, %d offices with a desk signer.\n",
    $schema, count($picks), count($resolved['courses']), count($resolved['terms']), count($feeLines), count($signers)
);
printf(
    "Plan: %s students; %d enrollments in positions %s; %d applications, %d clearances, %d blocks, %d papers.\n",
    number_format($studentCount), $perOffice * count($positions), implode('/', $positions),
    $perOffice, $perOffice, $perOffice, $perOffice
);

if (! $apply) {
    echo "DRY RUN — nothing was written. Pass --apply to build the population.\n";
    exit(0);
}

if (DB::table('students')->where('schoolIdNumber', 'like', $stamp.'%')->count() > 0) {
    $fail("schema [{$schema}] already holds students marked {$stamp}; a second run would double every queue. Build a fresh schema.");
}

$started = microtime(true);
$rows = [];

// Ask the record-state audit BEFORE anything is written. A freshly migrated and seeded schema may
// already carry a note that belongs to the reference data, and this tool's own promise is that the
// population it adds introduces none — a verdict without a baseline cannot say which is which.
Illuminate\Support\Facades\Artisan::call('ems:audit:record-states');
$baseline = Illuminate\Support\Facades\Artisan::output();
$baselineImpossible = preg_match_all('/^\s*FAIL\s/mu', $baseline);
$baselineNotes = preg_match_all('/^\s*note\s/mu', $baseline);
printf("Baseline audit before any row: %d impossible, %d decision row(s).\n", $baselineImpossible, $baselineNotes);

$chunk = static function (string $table, array $records) use (&$rows, $assertRows, $fail): void {
    if ($records === []) {
        return;
    }

    $assertRows($table, $records);

    foreach (array_chunk($records, 400) as $part) {
        try {
            DB::table($table)->insert($part);
        } catch (Illuminate\Database\QueryException $e) {
            $fail('insert into '.$table.' refused: '.$e->getMessage());
        }
    }

    $rows[$table] = ($rows[$table] ?? 0) + count($records);
    printf("  %-32s %8d rows  %6.1fs\n", $table, count($records), microtime(true) - $GLOBALS['started']);
};

// ------------------------------------------------------------------ name pools (synthetic people)
$maleFirst = ['Juan', 'Jose', 'Mark', 'Joshua', 'Angel', 'Rafael', 'Gabriel', 'Paolo', 'Miguel', 'Carlo', 'Noel', 'Arnel', 'Dominic', 'Elijah', 'Kiel', 'Ronnie', 'Vincent', 'Lorenzo', 'Emmanuel', 'Aljon'];
$femaleFirst = ['Maria', 'Ana', 'Grace', 'Bea', 'Kyla', 'Andrea', 'Patricia', 'Joy', 'Divine', 'Christine', 'Rowena', 'Mylene', 'Jhoana', 'Angelica', 'Trisha', 'Nancy', 'Marites', 'Cristina', 'Elaine', 'Danica'];
$lastNames = ['Dela Cruz', 'Santos', 'Reyes', 'Bautista', 'Ocampo', 'Gonzales', 'Ramos', 'Mendoza', 'Aquino', 'Castillo', 'Villanueva', 'Fernandez', 'Mercado', 'Rosales', 'Tolentino', 'Navarro', 'Salazar', 'Domingo', 'Alonzo', 'Yumul', 'Balautan', 'Limbaga', 'Gumentog', 'Pelaez', 'Cabug', 'Kawam', 'Inting', 'Tablante', 'Bersabe', 'Faderao'];
$places = ['Bogo City', 'Cebu City', 'Mambajao', 'Catbalogan', 'Ormoc City', 'Tacloban City', 'Iloilo City', 'Davao City', 'Valenzuela City', 'Antipolo City'];
$provinces = ['Cebu', 'Camiguin', 'Masbate', 'Leyte', 'Iloilo', 'Davao del Sur', 'Metro Manila', 'Rizal', 'Misamis Oriental', 'Agusan del Sur'];
$barangays = ['Poblacion', 'San Isidro', 'Maligaya', 'Bagong Silang', 'Santo Nino', 'Villa Esperanza', 'Sabang', 'Guadalupe', 'Patrocinio', 'Handumanan'];
$highSchools = ['Bogo National HS', 'Cebu National HS', 'St. Joseph Academy', 'Mambajao National HS', 'Our Lady of Peace', 'Rizal Senior HS', 'Assumption HS', 'San Jose Parochial'];
$strands = ['STEM', 'ABM', 'HUMSS', 'GAS', 'ICT'];
$occupations = ['Farmer', 'Seaman', 'Teacher', 'Vendor', 'Nurse', 'Driver', 'Clerk', 'Engineer', 'Homemaker', 'Contractor'];
$at = static fn (array $pool, int $i): string => $pool[$i % count($pool)];

$courseRows = array_values($resolved['courses']);
$termRows = array_values($resolved['terms']);
$religionIds = array_map(static fn (object $r): int => (int) $r->religionId, $resolved['religions']);
$activeStudent = $resolve($picks['studentStatus'], ['active']);
$bulkPasswordHash = Illuminate\Support\Facades\Hash::make('password123');

// ------------------------------------------------------------------ stage 1: the people
$students = [];
$carried = [];

for ($i = 0; $i < $studentCount; $i++) {
    $male = $i % 2 === 0;
    $course = $courseRows[$i % count($courseRows)];
    $term = $termRows[intdiv($i, count($courseRows)) % count($termRows)];
    $birthYear = (int) substr($today, 0, 4) - 17 - ($i % 9);

    $students[] = [
        'schoolIdNumber' => $stamp.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
        'lastName' => $at($lastNames, $i),
        'firstName' => $at($male ? $maleFirst : $femaleFirst, intdiv($i, 2)),
        'middleName' => chr(65 + ($i % 26)).'.',
        'suffix' => $i % 23 === 0 ? 'Jr.' : 'N/A',
        'gender' => $male ? 'male' : 'female',
        'birthdate' => sprintf('%04d-%02d-%02d', $birthYear, 1 + ($i * 7 % 12), 1 + ($i * 11 % 28)),
        'birthplace' => $at($places, $i),
        'citizenship' => 'Filipino',
        'civilStatus' => $i % 17 === 0 ? 'married' : 'single',
        'religionId' => $religionIds[$i % count($religionIds)],
        'contactNumber' => sprintf('09%02d%07d', $i % 100, $i % 10000000),
        'telephoneNumber' => $i % 5 === 0 ? sprintf('(083) %03d-%04d', 200 + ($i % 40), $i % 10000) : null,
        'email' => sprintf('bulk.%d@example.edu.ph', $i),
        // `students.username` and `students.passwordHash` are NOT NULL with no server default, so a
        // person row cannot exist without them. These are synthetic people, and the credential is
        // the demo cast's own placeholder — the same hash `tools/seed_full_demo_pipeline.php` gives
        // its students — so no real account is created and nothing here is a password to publish.
        'username' => 'bulk'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
        'passwordHash' => $bulkPasswordHash,
        'status' => $activeStudent,
        'semestersCompleted' => $i % 9,
        'yearsInInstitution' => $i % 4,
        'rowVersion' => 1,
        'created_at' => $today.' 06:00:00',
        'updated_at' => $today.' 06:00:00',
    ];

    $carried[$i] = [
        'courseId' => (int) $course->courseId,
        'termId' => (int) $term->termId,
        'barangay' => $at($barangays, $i),
        'city' => $at($places, $i + 3),
        'province' => $at($provinces, $i + 1),
        'highSchool' => $at($highSchools, $i),
        'strand' => $at($strands, $i),
        'guardianName' => $at($lastNames, $i + 7).', '.$at($i % 2 ? $femaleFirst : $maleFirst, $i + 11),
        'guardianJob' => $at($occupations, $i),
        'guardianContact' => sprintf('09%02d%07d', ($i * 3) % 100, $i % 10000000),
    ];
}

DB::beginTransaction();
$chunk('students', $students);
$firstStudent = (int) DB::table('students')->where('schoolIdNumber', $stamp.'000000')->value('studentId');
$studentIds = DB::table('students')->where('schoolIdNumber', 'like', $stamp.'%')->orderBy('studentId')->pluck('studentId')->all();
DB::commit();

if (count($studentIds) !== $studentCount) {
    $fail('wrote '.count($students).' students and the table answers '.count($studentIds).' — the population the plan promised did not land.');
}

printf("  students keyed from %d to %d\n", $firstStudent, end($studentIds));

if ($has('addresses')) {
    $addressRows = [];
    foreach ($studentIds as $j => $studentId) {
        $meta = $carried[$j];
        // `addresses` requires every part of the place, so none of these is left blank: house, street,
    // sitio/purok, barangay, city, province, region and zip are all NOT NULL on this schema.
    $addressRows[] = [
        'studentId' => (int) $studentId,
        'addressType' => 'permanent',
        'houseBuildingNo' => 'No. '.(100 + $j % 900),
        'street' => $meta['barangay'].' St.',
        'sitioPurok' => 'Purok '.(1 + $j % 9),
        'barangay' => $meta['barangay'],
        'cityMunicipality' => $meta['city'],
        'province' => $meta['province'],
        'region' => 'Region VII',
        'zipCode' => (string) (6000 + $j % 400),
    ];
    }

    $addressColumns = $tableColumns['addresses'];
    $addressRows = array_map(static function (array $row) use ($addressColumns): array {
        return array_intersect_key($row, array_flip($addressColumns));
    }, $addressRows);

    $chunk('addresses', $addressRows);
}

echo 'Stage 1 done in '.round(microtime(true) - $started, 1)."s — the remaining stages are the records.\n";

// ------------------------------------------------------------------ the words this schema uses
$enrollmentStatus = static fn (string $word): string => $need('enrollmentStatus', $resolve($picks['enrollmentStatus'], [$word]));
$studentTypeWord = static function (array $words) use ($picks, $resolve): string {
    return $resolve($picks['studentType'], $words);
};
$firstYear = $studentTypeWord(['firstYear', 'new', 'freshman']);
$transferee = $studentTypeWord(['transferee']);
$continuing = $studentTypeWord(['continuing', 'shifter', 'returning']);
$stepPending = $need('stepStatus', 'pending');
$stepCompleted = $need('stepStatus', 'completed');
$wfOpen = $need('workflowStatus', 'inProgress');
$wfCompleted = $need('workflowStatus', 'completed');
$payHeld = $need('paymentStatus', 'paid');
$payPartial = $need('paymentStatus', 'partial');
$payMode = $resolve($picks['paymentMode'], ['cash', 'gcash', 'bank']);
$admissionPending = $need('admissionStatus', 'pending');
$admissionApproved = $need('admissionStatus', 'approved');
$admissionRejected = $need('admissionStatus', 'rejected');
$applicantType = $resolve($picks['applicantType'], ['firstYear', 'transferee']);
$submissionPending = $resolve($picks['submissionStatus'], ['pending', 'submitted', 'notSubmitted']);
$idPending = $need('idRequestStatus', 'pending');
// `idrequests.requestReason` is an enum on this schema, so the word is read from the column rather
// than invented — a string the declaration does not hold is a 1265 warning MySQL raises mid-insert.
$idReason = $resolve($enumValues('idrequests', 'requestReason'), ['lost', 'Lost', 'replacement', 'new', 'firstIssue', 'damaged']);
$clearancePending = $need('clearanceOverall', 'pending');
$blockStatus = $resolve($picks['blockSchedule'], ['draft', 'proposed', 'pending', 'active']);
$examStageName = $resolve($picks['examStage'], ['entrance', 'departmental', 'retention']);
$examTypeName = $resolve($picks['examType'], ['general', 'courseSpecific']);
$resultProvisional = $need('resultStatus', 'provisional');
$clinicStatusValues = $enumValues('clinicrecords', 'status');
$clinicStatusDone = $resolve($clinicStatusValues, ['completed', 'normal', 'assessed', 'regular']);
$periodOpen = $picks['periodStatus'] === [] ? null : $resolve($picks['periodStatus'], ['open', 'extended', 'active']);

$boxes = App\Services\WorkflowService::boxOrder();
$feeCents = static fn (float $amount): float => round($amount, 2);

// ------------------------------------------------------------------ stage 2: applications
$admissions = [];
$submissions = [];
$admissionByStudent = [];

foreach ($studentIds as $j => $studentId) {
    $studentId = (int) $studentId;
    $meta = $carried[$j];
    $position = $positions[intdiv($j, $perOffice) % count($positions)];

    // A record waiting at Admission itself is an application the desk has not decided yet; every
    // other position was issued from an approved application, so it carries one. A fifth of the
    // waiting ones are rejected, because a rejection with a reason is a state the desk produces
    // and the screen has to render.
    if ($position === 'pending') {
        $status = $j % 5 === 4 ? $admissionRejected : $admissionPending;
    } else {
        $status = $admissionApproved;
    }

    $admissions[] = [
        'studentId' => $studentId,
        'termId' => $meta['termId'],
        'courseId' => $meta['courseId'],
        'applicantType' => $applicantType,
        'admissionStatus' => $status,
        'rejectionReason' => $status === $admissionRejected ? 'The registrar found the reported senior-high strand outside the program’s admitted tracks.' : null,
        'evaluatedBy' => $status === $admissionPending ? null : $signers[OfficeId::Admission->value],
        'evaluatedDate' => $status === $admissionPending ? null : date('Y-m-d', strtotime($today.' -'.($j % 60).' days')),
        'created_at' => $today.' 06:00:00',
        'updated_at' => $today.' 06:00:00',
    ];

    if ($status !== $admissionPending) {
        $admissionByStudent[$studentId] = true;
    }
}

$admissionIds = [];
$chunk('admissions', $admissions);
$admissionIdByStudent = DB::table('admissions')->whereIn('studentId', $studentIds)->pluck('admissionId', 'studentId')->all();

if ($resolved['admissionRequirements'] !== []) {
    $catalogueIds = array_map(static fn (object $r): int => (int) $r->requirementId, $resolved['admissionRequirements']);
    $submissions = [];
    foreach ($admissions as $k => $admission) {
        $admissionId = (int) ($admissionIdByStudent[$admission['studentId']] ?? 0);
        if ($admissionId === 0) {
            $fail('an application row came back with no admissionId, so its requirement submissions would be orphaned.');
        }
        foreach ($catalogueIds as $c => $requirementId) {
            $submissions[] = [
                'admissionId' => $admissionId,
                'requirementId' => $requirementId,
                'submissionStatus' => $submissionPending,
                'submittedDate' => date('Y-m-d', strtotime($today.' -'.(($k + $c) % 90).' days')),
            ];
        }

        if (count($submissions) >= 40000) {
            $chunk('studentrequirementsubmissions', $submissions);
            $submissions = [];
        }
    }
    $chunk('studentrequirementsubmissions', $submissions);
}

// ------------------------------------------------------------------ stage 3: the records, by position
$enrollments = [];
$metaByPosition = [];

foreach ($studentIds as $j => $studentId) {
    $studentId = (int) $studentId;
    $meta = $carried[$j];
    $position = $positions[intdiv($j, $perOffice) % count($positions)];
    $signed = match ($position) {
        'pending' => 0,
        'evaluated' => 1,
        'assessed' => 2,
        'paid' => 3,
        'enrolled-clinic' => 5,
        default => 6,
    };

    $status = match ($position) {
        'pending' => $enrollmentStatus('pending'),
        'evaluated' => $enrollmentStatus('evaluated'),
        'assessed' => $enrollmentStatus('assessed'),
        'paid' => $enrollmentStatus('paid'),
        default => $enrollmentStatus('enrolled'),
    };

    $type = in_array($position, ['assessed', 'paid'], true) ? $firstYear : ($j % 3 === 0 ? $continuing : $transferee);
    $ageDays = 30 + ($j % 200);

    $enrollments[] = [
        'studentId' => $studentId,
        'courseId' => $meta['courseId'],
        'termId' => $meta['termId'],
        'yearLevel' => 1 + ($j % 4),
        'studentType' => $type,
        'enrollmentType' => $resolve($picks['enrollmentType'], ['new', 'transferee', 'shifter', 'returning']),
        'academicStanding' => $resolve($picks['academicStanding'], ['regular', 'goodStanding']),
        'enrollmentStatus' => $status,
        // `enrollments.evaluatedBy` is NOT NULL with no default, so even a record still waiting at
        // Department Evaluation names the academic account the intake endorsed; the BOX for that
        // desk stays pending, which is what puts the record in the evaluation queue.
        'evaluatedBy' => $signed >= 1 ? $signers[OfficeId::Academic->value] : $signers[OfficeId::Guidance->value],
        'registrarProcessedBy' => $signed >= 4 ? $signers[OfficeId::Registrar->value] : null,
        'enrolledDate' => date('Y-m-d', strtotime($today.' -'.$ageDays.' days')),
        'formIssuedDate' => date('Y-m-d', strtotime($today.' -'.($ageDays + 10).' days')),
        'formSignedDate' => $signed > 0 ? date('Y-m-d', strtotime($today.' -'.($ageDays - 5).' days')) : null,
        'created_at' => $today.' 06:00:00',
        'updated_at' => $today.' 06:00:00',
    ];

    $metaByPosition[count($enrollments) - 1] = ['position' => $position, 'signed' => $signed, 'ageDays' => $ageDays, 'studentId' => $studentId];
}

$chunk('enrollments', $enrollments);
$enrollmentIds = DB::table('enrollments')->whereIn('studentId', $studentIds)->orderBy('enrollmentId')->pluck('enrollmentId')->all();

if (count($enrollmentIds) !== count($enrollments)) {
    $fail('wrote '.count($enrollments).' enrollments and the table answers '.count($enrollmentIds).' — the population would be short at some desk.');
}

// The workflow form and its boxes, signed exactly as far as each position has got. `seatKey` is a
// generated column and `requestKey` stays null: the key guards a replayed HTTP act, and a bulk
// write is not one.
$workflows = [];
$steps = [];
$history = [];
$trail = [
    $enrollmentStatus('pending') => null,
    $enrollmentStatus('evaluated') => $enrollmentStatus('pending'),
    $enrollmentStatus('assessed') => $enrollmentStatus('evaluated'),
    $enrollmentStatus('paid') => $enrollmentStatus('assessed'),
    $enrollmentStatus('enrolled') => $enrollmentStatus('paid'),
];

// The boxes each position has already been signed in, by OFFICE rather than by position number:
// a continuing student's form holds six boxes, so counting orders would sign the wrong desk and
// land the record in the wrong queue.
$signedBoxes = [
    'pending' => [],
    'evaluated' => [OfficeId::Guidance->value],
    'assessed' => [OfficeId::Guidance->value, OfficeId::Scholarship->value],
    'paid' => [OfficeId::Guidance->value, OfficeId::Scholarship->value, OfficeId::Accounting->value],
    'enrolled-clinic' => [OfficeId::Guidance->value, OfficeId::Scholarship->value, OfficeId::Accounting->value, OfficeId::Registrar->value, OfficeId::Blocking->value],
    'enrolled-id' => [OfficeId::Guidance->value, OfficeId::Scholarship->value, OfficeId::Accounting->value, OfficeId::Registrar->value, OfficeId::Blocking->value, OfficeId::Clinic->value],
];

foreach ($enrollments as $k => $row) {
    $enrollmentId = (int) $enrollmentIds[$k];
    $info = $metaByPosition[$k];
    $position = $info['position'];

    // Assessment is not a box a continuing or shifter student walks, so its order must not appear.
    $ordered = [];
    foreach ($boxes as $box) {
        if ($box['officeId'] === OfficeId::Scholarship->value && $row['studentType'] === $continuing) {
            continue;
        }
        $ordered[] = $box;
    }

    $signedOrders = [];
    $previous = null;
    $stepsForOne = [];

    foreach ($ordered as $index => $box) {
        $order = $index + 1;
        $isSigned = in_array($box['officeId'], $signedBoxes[$position], true);

        if ($isSigned) {
            $signedOrders[] = $order;
        }

        $stepsForOne[] = [
            'workflowId' => null,
            'officeId' => $box['officeId'],
            'stepOrder' => $order,
            'stepStatus' => $isSigned ? $stepCompleted : $stepPending,
            'signedBy' => $isSigned ? $signers[$box['officeId']] : null,
            'signedDate' => $isSigned ? date('Y-m-d H:i:s', strtotime($today.' -'.max(1, $info['ageDays'] - $order * 4).' days')) : null,
        ];

        // The status step is the signature's own evidence: money that arrives with no desk act
        // behind it is one of the twenty rules, so a record past a stage carries the history row
        // that moved it there.
        $toStatus = $isSigned ? match ($box['officeId']) {
            OfficeId::Guidance->value => $enrollmentStatus('evaluated'),
            OfficeId::Scholarship->value => $enrollmentStatus('assessed'),
            OfficeId::Accounting->value => $enrollmentStatus('paid'),
            OfficeId::Registrar->value => $enrollmentStatus('enrolled'),
            default => null,
        } : null;

        if ($toStatus !== null && $toStatus !== $previous) {
            $history[] = [
                'enrollmentId' => $enrollmentId,
                'fromStatus' => $previous ?? $trail[$toStatus],
                'toStatus' => $toStatus,
                'changedBy' => $signers[$box['officeId']],
                'remarks' => 'bulk-demo writer: the '.$box['label'].' box was signed by the tool that built this population, not by a desk.',
                'changedAt' => date('Y-m-d H:i:s', strtotime($today.' -'.max(1, $info['ageDays'] - $order * 4).' days')),
            ];
            $previous = $toStatus;
        }
    }

    // A record still waiting at Department Evaluation has no signed box and therefore no trail at
    // all — that is its state, not a disagreement. The guard is only about a record whose
    // signatures DID move it: the last status they name has to be the status on the row.
    if ($previous !== null && $previous !== $row['enrollmentStatus']) {
        $fail("enrollment {$enrollmentId} carries status [{$row['enrollmentStatus']}] but its signatures say [{$previous}] — the trail and the status would disagree.");
    }

    $workflows[] = [
        'enrollmentId' => $enrollmentId,
        'currentStep' => $signedOrders === [] ? 1 : max($signedOrders),
        'workflowStatus' => count($signedOrders) >= count($ordered) ? $wfCompleted : $wfOpen,
    ];

    $steps[] = $stepsForOne;
}

$chunk('enrollmentworkflow', array_map(static fn (array $r): array => $r, $workflows));
$workflowIds = DB::table('enrollmentworkflow')->whereIn('enrollmentId', $enrollmentIds)->pluck('workflowId', 'enrollmentId')->all();

$flat = [];
foreach ($steps as $index => $rowsForWorkflow) {
    $enrollmentId = (int) $enrollmentIds[$index];
    $workflowId = (int) ($workflowIds[$enrollmentId] ?? 0);
    if ($workflowId === 0) {
        $fail('enrollment '.$enrollmentId.' has no workflow form, so its boxes would be orphaned.');
    }
    foreach ($rowsForWorkflow as $step) {
        $step['workflowId'] = $workflowId;
        $flat[] = $step;
    }
}
$chunk('workflowsteps', $flat);
$chunk('enrollmentstatushistory', $history);

// ------------------------------------------------------------------ stage 4: the money
$assessments = [];
$charges = [];
$payments = [];
$orSerial = 900000;
$paidCount = 0;
$partialCount = 0;

foreach ($enrollments as $k => $row) {
    $info = $metaByPosition[$k];
    if ($info['position'] === 'pending') {
        continue;
    }

    $enrollmentId = (int) $enrollmentIds[$k];
    $assessed = $feeCents(array_sum(array_column($feeLines, 'amount')));
    $coverage = in_array($info['position'], ['paid', 'enrolled-clinic', 'enrolled-id'], true) && $k % 7 === 0 ? $feeCents($assessed * 0.25) : 0.0;
    $waived = 0.0;
    $owing = $feeCents(max(0, $assessed - $coverage - $waived));
    $paidAmount = 0.0;

    if ($info['position'] === 'assessed') {
        // The cashier has taken money but the account is not cleared: part payment, no `paid` step.
        $paidAmount = $k % 3 === 0 ? $feeCents($owing * 0.4) : 0.0;
    } elseif (in_array($info['position'], ['paid', 'enrolled-clinic', 'enrolled-id'], true)) {
        // Only a record the Accounting box has signed holds settled money — a receipt on an
        // account that never entered `paid` is one of the twenty rules, and it caught exactly this
        // mistake in the first rehearsal.
        $paidAmount = $owing;
    }

    $remaining = $feeCents($owing - $paidAmount);

    $assessments[] = [
        'enrollmentId' => $enrollmentId,
        'totalAssessedAmount' => $assessed,
        'totalScholarshipCoverage' => $coverage,
        'totalWaived' => $waived,
        'remainingBalance' => $remaining,
        'assessmentDate' => date('Y-m-d', strtotime($today.' -'.max(1, $info['ageDays'] - 6).' days')),
        'created_at' => $today.' 06:00:00',
        'updated_at' => $today.' 06:00:00',
    ];

    $lines = $feeLines;
    $lastIndex = count($lines) - 1;
    foreach ($lines as $l => $line) {
        $amount = $l === $lastIndex
            ? $feeCents($assessed - array_sum(array_column(array_slice($lines, 0, $lastIndex), 'amount')))
            : $line['amount'];

        $charges[] = [
            'assessmentId' => null,
            'feeTypeId' => $line['feeTypeId'],
            'amount' => $amount > 0 ? $amount : $line['amount'],
            'waivedAmount' => 0.0,
        ];
    }

    // One receipt per settlement, and the word `paid` only on the money that clears the account:
    // Accounting reserves it, and a `paid` slip over an owing balance is a settlement the same desk
    // refused to record.
    if ($paidAmount > 0) {
        $settled = $remaining <= 0.0;
        $payments[] = [
            'enrollmentId' => $enrollmentId,
            'orNumber' => 'B26-'.$schema.'-'.(++$orSerial),
            'amount' => $paidAmount,
            'paymentDate' => date('Y-m-d H:i:s', strtotime($today.' -'.max(1, $info['ageDays'] - 8).' days')),
            'paymentMode' => $payMode,
            'processedBy' => $signers[OfficeId::Accounting->value],
            'paymentStatus' => $settled ? $payHeld : $payPartial,
            'created_at' => $today.' 06:00:00',
            'updated_at' => $today.' 06:00:00',
        ];

        $settled ? $paidCount++ : $partialCount++;
    }
}

$chunk('studentassessments', $assessments);
$assessmentIds = DB::table('studentassessments')->whereIn('enrollmentId', $enrollmentIds)->orderBy('assessmentId')->pluck('assessmentId')->all();

$chargeFlat = [];
foreach ($charges as $index => $charge) {
    $charge['assessmentId'] = (int) $assessmentIds[intdiv($index, count($feeLines))];
    $chargeFlat[] = $charge;
}
$chunk('charges', $chargeFlat);
$chunk('payments', $payments);

$saidPaidAndOwes = DB::table('studentassessments as a')
    ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
    ->where('e.enrollmentStatus', $enrollmentStatus('paid'))
    ->where('a.remainingBalance', '>', 0)
    ->count();
if ($saidPaidAndOwes > 0) {
    $fail("{$saidPaidAndOwes} enrollment(s) say paid while the sheet still owes — the money stage disagrees with the status stage.");
}

// ------------------------------------------------------------------ stage 5: the desk records
$clinic = [];
$idRequests = [];

foreach ($enrollments as $k => $row) {
    $info = $metaByPosition[$k];
    $enrollmentId = (int) $enrollmentIds[$k];

    // A signed Clinic box owes a health assessment; a pending ID box is exactly the work the ID
    // desk lists, so its request stays pending and its photo stays unwritten.
    if ($info['position'] === 'enrolled-id') {
        $clinic[] = [
            'enrollmentId' => $enrollmentId,
            'heightCm' => 145 + ($k % 40),
            'weightKg' => 42 + ($k % 50),
            'bloodPressure' => sprintf('%d/%d', 105 + ($k % 15), 65 + ($k % 12)),
            'philhealthNumber' => $k % 4 === 0 ? sprintf('99-%010d-%d', $k % 10000000000, $k % 10) : null,
            'philhealthRegistered' => $k % 4 === 0 ? 1 : 0,
            'assessmentNotes' => 'Bulk population assessment: no restriction on record.',
            'findings' => $k % 6 === 0 ? 'Refractive error noted; corrected vision within limits.' : 'No abnormal finding.',
            'clinicStaffId' => $signers[OfficeId::Clinic->value],
            'assessmentDate' => date('Y-m-d', strtotime($today.' -'.max(1, $info['ageDays'] - 12).' days')),
            'status' => $clinicStatusDone,
            'created_at' => $today.' 06:00:00',
            'updated_at' => $today.' 06:00:00',
        ];
    }

    if (in_array($info['position'], ['enrolled-id', 'enrolled-clinic'], true)) {
        $idRequests[] = [
            'enrollmentId' => $enrollmentId,
            'requestReason' => $idReason,
            'emergencyContactName' => $carried[$k]['guardianName'],
            'emergencyContactNumber' => $carried[$k]['guardianContact'],
            'bloodType' => $at(['O', 'A', 'B', 'AB'], $k),
            'requestDate' => date('Y-m-d', strtotime($today.' -'.max(1, $info['ageDays'] - 14).' days')),
            'status' => $idPending,
            'created_at' => $today.' 06:00:00',
            'updated_at' => $today.' 06:00:00',
        ];
    }
}

$chunk('clinicrecords', $clinic);
$chunk('idrequests', $idRequests);

// ------------------------------------------------------------------ stage 6: clearance, blocks, papers
$clearances = [];
if ($perOffice > 0) {
    $period = (object) $resolved['periods'][0];
    foreach (array_slice($studentIds, 0, $perOffice) as $j => $studentId) {
        $clearances[] = [
            'studentId' => (int) $studentId,
            'clearancePeriodId' => (int) $period->clearancePeriodId,
            'overallStatus' => $clearancePending,
            'created_at' => $today.' 06:00:00',
            'updated_at' => $today.' 06:00:00',
        ];
    }
}
$chunk('studentclearances', $clearances);

$blocks = [];
foreach ($courseRows as $c => $course) {
    for ($n = 0; $n < intdiv($perOffice, max(1, count($courseRows))) + 1 && count($blocks) < $perOffice; $n++) {
        foreach ($termRows as $t => $term) {
            if (count($blocks) >= $perOffice) {
                break;
            }

            $blocks[] = [
                'courseId' => (int) $course->courseId,
                'termId' => (int) $term->termId,
                'yearLevel' => 1 + (count($blocks) % 4),
                'blockName' => 'BLK-'.str_pad((string) (count($blocks) + 1), 5, '0', STR_PAD_LEFT),
                'maxStudents' => 40,
                'scheduleStatus' => $blockStatus,
            ];
        }
    }
}
$chunk('blocks', $blocks);

// Papers the marking desk has not scored: `examResult` stays null, which is neither a passer nor a
// failure, and the result is provisional until the Registrar adopts it.
$papers = [];
foreach (array_slice($studentIds, 0, $perOffice) as $j => $studentId) {
    $papers[] = [
        'studentId' => (int) $studentId,
        'courseId' => $carried[$j]['courseId'],
        'termId' => $carried[$j]['termId'],
        'examStage' => $examStageName,
        'examType' => $examTypeName,
        'examResult' => null,
        'examDate' => date('Y-m-d', strtotime($today.' -'.($j % 45).' days')),
        'resultStatus' => $resultProvisional,
    ];
}
$chunk('examresults', $papers);

// ------------------------------------------------------------------ stage 7: the verdict
// The baseline was asked BEFORE the first row was written (above), so what this tool answers for is
// the delta: a note the reference data left behind is not this population's defect, but a note the
// population added is, and an impossible state is a refusal either way.
echo "\nRecord-state audit against [{$schema}] after the write:\n";
$auditExit = Illuminate\Support\Facades\Artisan::call('ems:audit:record-states');
$auditOutput = Illuminate\Support\Facades\Artisan::output();
echo $auditOutput."\n";

$impossible = preg_match_all('/^\s*FAIL\s/m', $auditOutput);
$notes = preg_match_all('/^\s*note\s/m', $auditOutput);

$queues = [
    'admission' => DeskQueues::admission(null)->count(),
    'evaluation' => DeskQueues::evaluation(null)->count(),
    'assessment' => DeskQueues::assessment()->count(),
    'accounting' => DeskQueues::accounting()->count(),
    'registrar' => DeskQueues::registrar()->count(),
    'clinic' => DeskQueues::clinic()->count(),
    'id' => DeskQueues::id()->count(),
    'blocking' => DeskQueues::blocking()->count(),
    'clearance' => DeskQueues::clearance()->count(),
];

echo "\nQueue populations (each desk's own predicate, not a copy of it):\n";
$short = [];
foreach ($queues as $name => $count) {
    $mark = $count >= $perOffice ? 'ok  ' : 'LOW ';
    printf("  %s %-12s %8d of %d\n", $mark, $name, $count, $perOffice);
    if ($count < $perOffice) {
        $short[] = $name;
    }
}

printf(
    "\nRows written: %s\nAudit: %d impossible, %d decision rows (baseline %d impossible, %d decision). Receipts settled %d, partial %d. Runtime %.1fs\n",
    json_encode($rows), $impossible, $notes, $baselineImpossible, $baselineNotes, $paidCount, $partialCount, microtime(true) - $started
);

if ($impossible > 0 || $notes > $baselineNotes) {
    fwrite(STDERR, "REFUSED: the population adds record states a desk could not produce (impossible {$impossible}, new decision rows ".max(0, $notes - $baselineNotes).") — fix the writer, not the data.\n");
    exit(1);
}

if ($baselineImpossible > 0) {
    fwrite(STDERR, "REFUSED: the schema was not clean before this tool wrote anything ({$baselineImpossible} impossible row(s) at baseline), so no verdict about the population can be read from it.\n");
    exit(1);
}

if ($short !== []) {
    fwrite(STDERR, 'REFUSED: these queues came up short of the target: '.implode(', ', $short)."\n");
    exit(1);
}

echo "DELIVERED: every desk holds at least {$perOffice} rows and every rule reads zero.\n";
exit(0);

