<?php

// Lane 53 step 2, second read. The money audit's E row asked "status is not `paid` while the balance is zero",
// which is too strong: `paid -> enrolled` is a normal step, so an enrolled record that owes nothing is not by
// itself impossible. The question the register has to answer is narrower — did this account ever *enter* the
// state that a receipt produces? Only EnrollmentStateMachine::transition() writes enrollmentstatushistory, so
// the history table is the record of who moved the row, and a settled account with no `-> paid` step was never
// walked through Accounting by any code path.
//
// Classifies every fee sheet in the demo term. Nothing is written. Read-only.
//
//   php storage/app/scratch/lane53-probe-settled-no-history.php

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::select(
    'select a.assessmentId aid, a.enrollmentId enr, s.schoolIdNumber sid, e.enrollmentStatus stat,
            a.totalAssessedAmount assessed, a.totalScholarshipCoverage cov, a.totalWaived waived,
            a.remainingBalance bal,
            (select count(*) from charges c where c.assessmentId = a.assessmentId) lineCount,
            (select coalesce(sum(p.amount),0) from payments p where p.enrollmentId = a.enrollmentId
               and p.paymentStatus in ("paid","partial")) paid,
            (select count(*) from enrollmentstatushistory h where h.enrollmentId = a.enrollmentId) hist,
            (select count(*) from enrollmentstatushistory h where h.enrollmentId = a.enrollmentId
               and h.toStatus = "paid") enteredPaid
       from studentassessments a
       join enrollments e on e.enrollmentId = a.enrollmentId
       join students s on s.studentId = e.studentId
      order by s.schoolIdNumber, a.assessmentId'
);

$classes = [];

printf("%-14s %-6s %-6s %-22s %10s %10s %6s %6s %6s\n", 'student', 'enr', 'ass', 'status', 'due', 'paid', 'bal', 'lines', 'hist');
echo str_repeat('-', 96)."\n";

foreach ($rows as $r) {
    $due = (float) $r->assessed - (float) $r->cov - (float) $r->waived;
    $settled = $due > 0 && (float) $r->bal === 0.0;

    if ((float) $r->paid > 0 && $r->enteredPaid == 0) {
        $k = $r->hist > 0 ? 'RECEIPT, NO `-> paid` STEP (has other history)' : 'RECEIPT, NO HISTORY AT ALL';
    } elseif ((float) $r->paid > 0 && ! $settled) {
        $k = 'receipt on an unsettled account (normal)';
    } elseif ($r->lineCount == 0 && (float) $r->assessed > 0) {
        $k = 'SHEET WITH NO FEE LINES';
    } else {
        $k = 'ok';
    }

    $classes[$k][] = $r;
    printf(
        "%-14s %-6d %-6d %-22s %10.2f %10.2f %6.2f %6d %6d  %s\n",
        $r->sid, $r->enr, $r->aid, $r->stat, $due, $r->paid, $r->bal, $r->lineCount, $r->hist,
        $k === 'ok' ? '' : '<<'
    );
}

echo "\n=== classification\n";

foreach ($classes as $k => $list) {
    printf("  %-46s %d\n", $k, count($list));

    if ($k === 'ok') {
        continue;
    }

    foreach ($list as $r) {
        printf("      enroll %-5d ass %-5d %-14s %-22s paid %.2f bal %.2f lines %d hist %d\n",
            $r->enr, $r->aid, $r->sid, $r->stat, $r->paid, $r->bal, $r->lineCount, $r->hist);
    }
}

echo "\n=== every history step in the database\n";

foreach (DB::select('select enrollmentId enr, fromStatus f, toStatus t, changedBy who, remarks, changedAt at
                       from enrollmentstatushistory order by enrollmentId, historyId') as $h) {
    printf("  enroll %-5d %-24s -> %-24s by %-5s %s  [%s]\n", $h->enr, $h->f, $h->t, (string) $h->by, $h->at, mb_substr((string) $h->remarks, 0, 60));
}

printf("\n%d sheet(s) classified.\n", count($rows));
