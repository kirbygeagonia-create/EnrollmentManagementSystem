<?php

// Lane 53 step 3, corrected. Two things the first draft got wrong:
//
//   * `studentassessments` carries no studentId and no termId — only enrollmentId. A grant can only be compared
//     with an assessment by walking the enrollment that both point at.
//   * `scholarshiptypes` has a `coverageType` enum beside `coveragePercent`, and the coverage computation in
//     AssessmentController reads only the percent. So the enum either decides something somewhere, or it is a
//     column that gates nothing. This prints who reads it and what values the rows actually hold.
//
// Read-only.
//
//   php storage/app/scratch/lane53-audit-coverage.php

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

function exactCents(string $assessed, string $pct): int
{
    $a = (int) bcmul($assessed, '100', 2);
    $p = (int) bcmul($pct, '100', 2);

    return (int) bcdiv(bcadd(bcmul((string) $a, (string) $p), '5000', 0), '10000', 0);
}

function showCents(int $c): string
{
    return bcdiv((string) $c, '100', 2);
}

echo "=== scholarship types: what the columns actually hold\n";

foreach (DB::select('select scholarshipTypeId id, scholarshipName n, coverageType t, coveragePercent p from scholarshiptypes order by id') as $r) {
    printf("  #%-3d %-26s type=%-12s percent=%s\n", $r->id, (string) $r->n, (string) $r->t, (string) $r->p);
}

echo "\n=== who reads coverageType in the application\n";

$readers = [];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $f) {
    $path = (string) $f;

    if (! str_ends_with($path, '.php')) {
        continue;
    }

    $src = (string) file_get_contents($path);

    if (stripos($src, 'coverageType') !== false) {
        $n = preg_match_all('/coverageType/i', $src);
        $readers[] = str_replace($root.'/', '', $path).":$n";
    }
}

echo $readers === [] ? "  nothing in app/ mentions coverageType at all\n" : '  '.implode("\n  ", $readers)."\n";

echo "\n=== F  stored coverage vs the percent that produces it (joined through the enrollment)\n";

$grants = DB::select(
    'select s.studentScholarshipId id, s.studentId stu, s.termId term, s.status stat, st.coveragePercent pct,
            st.coverageType ctype, st.scholarshipName name, a.assessmentId aid, e.enrollmentId enr,
            a.totalAssessedAmount assessed, a.totalScholarshipCoverage coverage
       from studentscholarships s
       join scholarshiptypes st on st.scholarshipTypeId = s.scholarshipTypeId
       join enrollments e on e.studentId = s.studentId and e.termId = s.termId
       join studentassessments a on a.enrollmentId = e.enrollmentId
      order by s.studentScholarshipId'
);

$findings = 0;

if ($grants === []) {
    echo "  no grant joins an assessment, so the identity cannot be tested on this data at all — that is a finding\n";
    $findings++;
}

foreach ($grants as $g) {
    $expected = showCents(exactCents((string) $g->assessed, (string) $g->pct));
    $same = bccomp($expected, (string) $g->coverage, 2) === 0;

    printf(
        "  grant %-4s %-20s %-11s %6s%% of ₱%-9s = ₱%-9s | enroll %-5s assess %-4s stores ₱%-9s %s (%s)\n",
        $g->id, mb_substr((string) $g->name, 0, 20), (string) $g->ctype, (string) $g->pct, (string) $g->assessed,
        $expected, $g->enr, $g->aid, (string) $g->coverage, $same ? 'ok' : 'MISMATCH', $g->stat
    );

    if (! $same) {
        $findings++;
    }
}

echo "\n=== G  the float rounding path vs exact decimal half-up\n";

$percents = array_map(static fn ($r) => (string) $r->p, DB::select('select distinct coveragePercent p from scholarshiptypes order by p'));

if ($percents === []) {
    $percents = ['100.00'];
}

foreach ($percents as $pct) {
    $checked = 0;
    $bad = 0;
    $first = '';

    for ($cents = 100; $cents <= 2000000; $cents++) {
        $assessed = bcdiv((string) $cents, '100', 2);
        $floatCents = (int) round(round((float) $assessed * ((float) $pct / 100), 2) * 100);
        $exact = exactCents($assessed, $pct);
        $checked++;

        if ($floatCents !== $exact) {
            $bad++;

            if ($first === '') {
                $first = "₱$assessed: float ".showCents($floatCents).' vs exact '.showCents($exact);
            }
        }
    }

    if ($bad === 0) {
        printf("  %8s%% : %s amounts probed, every one lands on the same centavo\n", $pct, number_format($checked));

        continue;
    }

    printf("  %8s%% : %s of %s probed amounts differ from exact half-up — first: %s\n", $pct, number_format($bad), number_format($checked), $first);
    $findings++;
}

printf("\n%d finding(s).\n", $findings);
