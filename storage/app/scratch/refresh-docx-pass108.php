<?php

/**
 * Pass 108 — the college boundary, and one clause of pass 106 that overstated what the records show.
 *
 * Two edits to the paragraph that already answers "who may act":
 *
 *   1. Pass 106 wrote that "office7_head and three instructors evaluated every program from one anonymous
 *      desk". Measured against live ems that is wrong in its verb: those four accounts are credited with
 *      **no enrollment at all**, and every one of the 29 rows the audit found credits office4_head — a
 *      Guidance account — 15 of them still in progress. What office 7 had was a reach nothing bounded, not
 *      a history of doing the work. The clause is rewritten to the measured thing.
 *   2. The boundary itself, ruled the same day: each college's department is given every student and
 *      applicant of that college and none of another's, with the two decisions the ruling did not answer
 *      recorded beside it (a shift reaches either side of the move; an account with no college is bounded
 *      by nothing, and the measured reason why).
 *
 * Offsets re-derived on every run, one edit per offset, applied highest-first. Guards prove the structure
 * survived and that the numbers in the new sentences still match live ems. DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$edits = [];

$refuse = function (string $why) use (&$fails): void {
    fwrite(STDERR, "REFUSE: {$why}\n");
    $fails++;
};

$swap = function (string $from, string $to, string $label) use ($xml, &$edits, &$refuse): void {
    $n = substr_count($xml, $from);
    if ($n !== 1) {
        $refuse("{$label}: anchor appears {$n} time(s), expected 1 — 「".mb_substr($from, 0, 70).'…」');

        return;
    }
    $edits[] = [strpos($xml, $from), strlen($from), $to, $label];
};

// ─── 1. what the records actually say ─────────────────────────────────────────────────────────────────
require_once $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// The counts this pass is about to assert in prose, measured rather than remembered.
$credits = Illuminate\Support\Facades\DB::table('enrollments')
    ->join('staffusers', 'staffusers.userId', '=', 'enrollments.evaluatedBy')
    ->selectRaw('staffusers.username as who, count(*) as n')
    ->groupBy('who')->pluck('n', 'who')->all();
$inProgress = Illuminate\Support\Facades\DB::table('enrollments')
    ->join('enrollmentworkflow', 'enrollmentworkflow.enrollmentId', '=', 'enrollments.enrollmentId')
    ->where('enrollmentworkflow.workflowStatus', 'inProgress')
    ->count();
$collegesOfHeld = Illuminate\Support\Facades\DB::table('enrollments')
    ->join('courses', 'courses.courseId', '=', 'enrollments.courseId')
    ->join('staffusers', 'staffusers.userId', '=', 'enrollments.evaluatedBy')
    ->where('staffusers.username', 'office4_head')
    ->distinct()->count('courses.unitId');

printf("measured: %s credited with evaluation; %d enrollments inProgress; office4_head spans %d college(s)\n",
    json_encode($credits), $inProgress, $collegesOfHeld);

$owed = ['office4_head' => 29, 'staff8' => 1];
foreach ($owed as $who => $n) {
    if (($credits[$who] ?? 0) !== $n) {
        $refuse("the sentence would say {$who} holds {$n} enrollments; live reads ".($credits[$who] ?? 0));
    }
}
foreach (['office7_head', 'instr.mendoza', 'instr.salazar', 'instr.alvarez'] as $who) {
    if (isset($credits[$who])) {
        $refuse("the correction claims {$who} is credited with nothing; live shows ".$credits[$who]);
    }
}
if ($inProgress !== 15 || $collegesOfHeld !== 3) {
    $refuse("the clause says 15 in progress across 3 colleges; measured {$inProgress} across {$collegesOfHeld}");
}

$swap(
    'no seeded staff row carried a unitId at all, so office7_head and three instructors evaluated every '
    .'program from one anonymous desk',
    'no seeded staff row carried a unitId at all, so office7_head and the three instructors held the '
    .'evaluation rights over every program from one anonymous desk — and it was only ever a reach, not a '
    .'history: the install credits those four accounts with no enrollment at all, while 29 rows name '
    .'office4_head, a Guidance account, 15 of them still in progress in three different colleges',
    '1 the overstatement, corrected to the records'
);

// ─── 2. the boundary, appended where the paragraph ends ───────────────────────────────────────────────
$anchor = 'could not be performed, now closed by re-deriving the mapping at the end of that seeder.';
if (substr_count($xml, $anchor) !== 1) {
    $refuse('the paragraph this pass appends to no longer ends where it was written');
} else {
    $at = strpos($xml, $anchor) + strlen($anchor);
    $closing = strpos($xml, '</w:t>', $at);
    if ($closing === false || $closing - $at > 10) {
        $refuse('the anchor is not inside a run that ends right after it');
    } else {
        $ruling = ' Ruled the same day, the boundary those desks work inside: each college’s Academic '
            .'Department receives every student and applicant related to that college, and none of another’s. '
            .'One rule in one place — App\\Support\\CollegeScope — which the policies ask and every list the '
            .'desk reads answers to: the evaluation queue and the program dropdown, the applicant queue, the '
            .'directory and the quick search (a students row carries no course, so the relation is read '
            .'through that person’s enrollments and applications), and the shift docket, where the paper '
            .'reaches the college on either side of the move because the one the student leaves endorses it '
            .'and the one they arrive at takes them on. A desk that asks for another college’s examination '
            .'roster is refused rather than shown an empty list — a blank list would report that this college '
            .'has nobody when the truth is that it asked for someone else’s. An account with no college is '
            .'bounded by nothing, and that is a decision with a measured reason rather than a gap: '
            .'office4_head holds the 29 enrollments above, 15 of them in progress across three colleges, so '
            .'bounding it would strand papers no desk could then close, and office7_head with the seeded '
            .'instructors stay unbounded because §7 of the walkthrough walks two colleges and a third’s '
            .'applicant in one login. CollegeDeskScopeTest pins both halves — every row of the desk’s own '
            .'college, and no row of another’s — and six mutants proved them: the policy stop, the queue '
            .'filter, applicants counting toward a college, the shift visible from both sides, the unbounded '
            .'account keeping its reach, and the dropdown agreeing with the policy it feeds.';
        $edits[] = [$at, 0, $ruling, '2 the boundary ruling, appended in place'];
    }
}

printf("\nedits planned: %d\n", count($edits));
foreach ($edits as [$off, $len, $to, $label]) {
    printf("  %-44s byte %8d  %4d → %4d chars\n", $label, $off, $len, strlen($to));
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$previousStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $previousStart) {
        $refuse(sprintf('edits overlap at %d and %d', $e[0], $previousStart));
    }
    $previousStart = $e[0];
}
if ($fails > 0) {
    exit(1);
}

foreach ($edits as [$off, $len, $to, $label]) {
    $xml = substr_replace($xml, $to, $off, $len);
    printf("applied  %s\n", $label);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the overstatement is gone' => substr_count($xml, 'evaluated every program from one anonymous desk') === 0,
    // The clause must carry the numbers this run measured, not the numbers the sentence was written with:
    // a guard that only checks its own constant would let the prose drift by one and still call it clean.
    'the clause carries the measured counts' => str_contains($xml, "while {$credits['office4_head']} rows name office4_head")
        && str_contains($xml, "{$inProgress} of them still in progress")
        && str_contains($xml, "office4_head holds the {$credits['office4_head']} enrollments above, {$inProgress} of them in progress"),
    'the correction is present once' => substr_count($xml, 'it was only ever a reach, not a history') === 1
        && substr_count($xml, '15 of them still in progress') === 1,
    'the boundary sentence is present once' => substr_count($xml, 'Ruled the same day, the boundary those desks work inside') === 1,
    'the class the ruling names exists' => is_file($root.'/app/Support/CollegeScope.php')
        && substr_count($xml, 'App\\Support\\CollegeScope') >= 1,
    'the test the ruling names exists' => is_file($root.'/tests/Feature/Rbac/CollegeDeskScopeTest.php')
        && substr_count($xml, 'CollegeDeskScopeTest') >= 1,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

echo $fails === 0 ? "\nall edits and guards ok\n" : "\n{$fails} problem(s)\n";
if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass108.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
