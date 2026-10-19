<?php

// Lane 53 step 2: does the money add up?
//
// Every amount column is DECIMAL(10,2), but the arithmetic that produces them is PHP float: `(float)` casts and
// `round($assessed * $percent / 100, 2)` in AssessmentController, sums in AccountingController. A centavo is the
// smallest thing this system can name, so the test is exact: multiply to integer cents with a decimal-string
// library, never with a float, and compare.
//
// Identities asked of every account:
//   A  sum(charges.amount)              == totalAssessedAmount
//   B  sum(charges.waivedAmount)        == totalWaived
//   C  remainingBalance                 == max(0, assessed - coverage - waived - paid)
//   D  paid                             <= assessed - coverage - waived        (no overpayment)
//   E  enrollment status `paid`         <=>  remainingBalance == 0
//   F  a scholarship grant's amount     == round(assessed * coveragePercent/100, 2) of the row that granted it
//
// Nothing is written. Read-only.
//
//   php storage/app/scratch/lane53-audit-money.php

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

/** exact decimal comparison in cents; bcmath so no float ever touches a peso */
function cents(string $v): string
{
    $s = bcmul(trim($v) === '' ? '0' : trim($v), '100', 4);

    return bcadd($s, '0', 4);
}

function diff(string $a, string $b): string
{
    return bcsub(cents($a), cents($b), 4);
}

/** exact decimal half-up, in cents: assessed x percent, never through a float */
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

$findings = 0;
$report = function (string $kind, string $detail) use (&$findings): void {
    printf("  %-14s %s\n", $kind, $detail);
    $findings++;
};

$rows = DB::select(
    'select a.assessmentId id, a.enrollmentId enr, a.totalAssessedAmount assessed, a.totalScholarshipCoverage coverage,
            a.totalWaived waived, a.remainingBalance balance, e.enrollmentStatus estatus,
            (select coalesce(sum(c.amount),0) from charges c where c.assessmentId = a.assessmentId) lineSum,
            (select coalesce(sum(c.waivedAmount),0) from charges c where c.assessmentId = a.assessmentId) lineWaived,
            (select coalesce(sum(p.amount),0) from payments p where p.enrollmentId = a.enrollmentId
               and p.paymentStatus in ("paid","partial")) paid,
            (select count(*) from enrollmentstatushistory h where h.enrollmentId = a.enrollmentId
               and h.toStatus = "paid") paidStep
       from studentassessments a
       join enrollments e on e.enrollmentId = a.enrollmentId
       order by a.assessmentId'
);

printf("%d accounts measured.\n\n", count($rows));

foreach ($rows as $r) {
    $owed = bcsub(bcsub(bcsub((string) $r->assessed, (string) $r->coverage, 2), (string) $r->waived, 2), (string) $r->paid, 2);
    $expected = bccomp($owed, '0', 2) < 0 ? '0.00' : $owed;

    if (bccomp(diff((string) $r->lineSum, (string) $r->assessed), '0', 4) !== 0) {
        $report('A LINES≠TOTAL', "assessment {$r->id}: charges sum {$r->lineSum} vs totalAssessedAmount {$r->assessed} (diff ".diff((string) $r->lineSum, (string) $r->assessed).' cents)');
    }

    if (bccomp(diff((string) $r->lineWaived, (string) $r->waived), '0', 4) !== 0) {
        $report('B WAIVED≠LINES', "assessment {$r->id}: charges.waivedAmount sum {$r->lineWaived} vs totalWaived {$r->waived}");
    }

    if (bccomp(diff((string) $r->balance, $expected), '0', 4) !== 0) {
        $report('C BALANCE≠MATH', "assessment {$r->id}: stored remainingBalance {$r->balance}, computed $expected (assessed {$r->assessed} − coverage {$r->coverage} − waived {$r->waived} − paid {$r->paid})");
    }

    if (bccomp((string) $r->paid, $owed === '0.00' ? (string) $r->paid : bcsub(bcsub((string) $r->assessed, (string) $r->coverage, 2), (string) $r->waived, 2), 2) > 0) {
        $report('D OVERPAID', "assessment {$r->id}: paid {$r->paid} exceeds the account's due amount");
    }

    $zero = bccomp((string) $r->balance, '0', 2) === 0;

    if ($r->estatus === 'paid' && ! $zero) {
        $report('E STATUS≠MATH', "enrollment {$r->enr} is `paid` while remainingBalance is {$r->balance}");
    }

    // The first draft asked "status is not `paid` while the balance is zero", which is not an impossible
    // state at all: `paid -> enrolled` is a normal step, so a record the Registrar has approved owes nothing.
    // What is decidable is the trail — only AccountingController::record() takes money, and only
    // EnrollmentStateMachine::transition() writes the step that says it did.
    if (bccomp((string) $r->paid, '0', 2) > 0 && $zero && (int) $r->paidStep === 0) {
        $report('E NO PAID STEP', "enrollment {$r->enr} ({$r->estatus}) holds ₱{$r->paid} on a settled account with no `-> paid` step in enrollmentstatushistory");
    }
}

echo "\n=== scholarship grants vs the percent they produce\n";

// `studentscholarships` keys the grant as `studentScholarshipId`, carries no enrollmentId and no amount of its
// own — the money it covers lands on the assessment. So the join walks student + term to the enrollment.
$grants = DB::select(
    'select s.studentScholarshipId id, s.studentId stu, s.termId term, s.status stat, st.coveragePercent pct,
            a.assessmentId aid, e.enrollmentId enr, a.totalAssessedAmount assessed, a.totalScholarshipCoverage coverage
       from studentscholarships s
       join scholarshiptypes st on st.scholarshipTypeId = s.scholarshipTypeId
       join enrollments e on e.studentId = s.studentId and e.termId = s.termId
       join studentassessments a on a.enrollmentId = e.enrollmentId
      order by s.studentScholarshipId'
);

foreach ($grants as $g) {
    $expected = showCents(exactCents((string) $g->assessed, (string) $g->pct));

    if (bccomp((string) $g->coverage, $expected, 2) !== 0) {
        $report('F GRANT≠PCT', "grant {$g->id}: the assessment stores ₱{$g->coverage} covered, but {$g->pct}% of ₱{$g->assessed} is ₱$expected");
    }
}

printf("\n%d finding(s) across %d accounts and %d grants.\n", $findings, count($rows), count($grants));
