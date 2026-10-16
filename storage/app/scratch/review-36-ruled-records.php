<?php

/**
 * Lane 35: review the 36 records `ems:audit:record-states` reports as `note` — the ones ruled to keep.
 *
 * Read-only. It exists because a ruling is a claim about data, and data moves: the 2026-10-12 decision was
 * taken against rows that were then seven weeks old, and since then the demo pipeline was rewired (lane 30),
 * two boxes were repaired, and the CLI's own predicates changed. This re-asks, per record, whether the REASON
 * recorded in the audit is still the reason, and separates three things the count currently blurs:
 *
 *   - a row that is explained by the ruling and still matches it;
 *   - a row whose stated explanation has since become false (e.g. "the demo pipeline wrote it" when the
 *     pipeline no longer can, or "past term" when the row is in the current term);
 *   - a row that is actually actionable now — a pending request that a desk could validate today, or an
 *     assessment that exists for the same student under a different enrollment.
 *
 * Nothing is written, nothing is deleted, and no row is created. Output is a table the owner can decide on.
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\OfficeId;
use Illuminate\Support\Facades\DB;

$term = \App\Models\Academicterms::covering(now())?->termId; // the app's own rule, not a guessed column
echo 'the app rule (Academicterms::covering) names term '.var_export($term, true)." as current\n";
echo 'enrollments by term: '.DB::table('enrollments')->selectRaw('termId, COUNT(*) AS n')->groupBy('termId')
    ->pluck('n', 'termId')->map(fn ($n, $t) => $t.'='.$n)->implode(', ')."\n\n";

echo "=== GROUP 1 — documentprintlog rows with no enrollmentId, studentId or blockId ===\n";
$rows = DB::table('documentprintlog')
    ->whereNull('enrollmentId')->whereNull('studentId')->whereNull('blockId')
    ->orderBy('documentType')->get();
printf("%-22s %6s %-20s %-10s %s\n", 'document type', 'rows', 'printed range', 'printedBy', 'sequence values');
foreach ($rows->groupBy('documentType') as $type => $g) {
    printf("%-22s %6d %-20s %-10s %s\n", $type, $g->count(),
        $g->min('printedDate').' -> '.$g->max('printedDate'),
        $g->pluck('printedBy')->filter()->unique()->implode('/') ?: 'nobody',
        $g->pluck('documentNumber')->implode(','));
}
echo 'total: '.$rows->count()."\n";
$attributable = DB::table('documentprintlog')
    ->whereNull('enrollmentId')->whereNull('studentId')->whereNull('blockId')->count('printLogId');
echo "rows whose printedBy is NULL too: "
    .DB::table('documentprintlog')->whereNull('enrollmentId')->whereNull('studentId')->whereNull('blockId')
        ->whereNull('printedBy')->count()."\n";

echo "\n=== GROUP 2 — Clinic box completed, no clinicrecords row for that enrollment ===\n";
$clinic = DB::table('enrollments as e')
    ->join('enrollmentworkflow as ew', 'ew.enrollmentId', '=', 'e.enrollmentId')
    ->join('workflowsteps as ws', 'ws.workflowId', '=', 'ew.workflowId')
    ->leftJoin('students as s', 's.studentId', '=', 'e.studentId')
    ->leftJoin('staffusers as st', 'st.userId', '=', 'ws.signedBy')
    ->where('ws.officeId', OfficeId::Clinic->value)
    ->where('ws.stepStatus', 'completed')
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('clinicrecords as cr')->whereColumn('cr.enrollmentId', '=', 'e.enrollmentId'))
    ->get(['e.enrollmentId', 'e.termId', 'e.enrollmentStatus', 's.schoolIdNumber', 'ws.signedDate', 'ws.signedBy', 'st.username', 'st.officeId as signerOffice']);
printf("%-8s %-6s %-18s %-14s %-20s %-10s %s\n", 'enroll', 'term', 'student', 'status', 'signedDate', 'signer', 'signer office');
foreach ($clinic as $r) {
    printf("%-8s %-6s %-18s %-14s %-20s %-10s %s\n", $r->enrollmentId,
        $r->termId.($r->termId == $term ? ' *CURRENT*' : ''), $r->schoolIdNumber ?: '(no student row)',
        $r->enrollmentStatus, (string) $r->signedDate, $r->username ?: 'user#'.$r->signedBy, (string) $r->signerOffice);
}
echo 'count: '.$clinic->count()."\n";
echo 'same students with a clinic record under ANOTHER enrollment: ';
$ids = $clinic->pluck('studentId')->filter();
$orphans = DB::table('clinicrecords as cr')->join('enrollments as e', 'e.enrollmentId', '=', 'cr.enrollmentId')
    ->whereIn('e.studentId', $clinic->pluck('schoolIdNumber')->all() ?: ['__none__'])->pluck('e.enrollmentId')->unique();
echo $orphans->isEmpty() ? 'none — no clinic record exists for these students at all'
    : 'enrollments '.$orphans->implode(', ').' of the same students DO hold a record'."\n";

echo "\n=== GROUP 3 — ID box completed, no VALIDATED idrequests row for that enrollment ===\n";
$idbox = DB::table('enrollments as e')
    ->join('enrollmentworkflow as ew', 'ew.enrollmentId', '=', 'e.enrollmentId')
    ->join('workflowsteps as ws', 'ws.workflowId', '=', 'ew.workflowId')
    ->leftJoin('students as s', 's.studentId', '=', 'e.studentId')
    ->leftJoin('staffusers as st', 'st.userId', '=', 'ws.signedBy')
    ->where('ws.officeId', OfficeId::IdOffice->value)
    ->where('ws.stepStatus', 'completed')
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('idrequests as ir')
        ->whereColumn('ir.enrollmentId', '=', 'e.enrollmentId')->where('ir.status', 'validated'))
    ->get(['e.enrollmentId', 'e.termId', 's.schoolIdNumber', 'ws.signedDate', 'st.username', 'ws.signedBy']);
printf("%-8s %-6s %-18s %-20s %-12s %s\n", 'enroll', 'term', 'student', 'signedDate', 'signer', 'any idrequests row?');
foreach ($idbox as $r) {
    $any = DB::table('idrequests')->where('enrollmentId', $r->enrollmentId)
        ->get(['status', 'cardPhotoPath', 'validatedBy', 'validatedDate'])
        ->map(fn ($x) => $x->status.' photo='.($x->cardPhotoPath ? 'y' : 'n'))
        ->implode(', ') ?: 'NONE AT ALL';
    printf("%-8s %-6s %-18s %-20s %-12s %s\n", $r->enrollmentId,
        $r->termId.($r->termId == $term ? ' *CURRENT*' : ''), $r->schoolIdNumber ?: '(none)',
        (string) $r->signedDate, $r->username ?: 'user#'.$r->signedBy, $any);
}
echo 'count: '.$idbox->count()."\n";
echo 'overlap with group 2 (same enrollment in both): ';
$both = array_intersect($clinic->pluck('enrollmentId')->all(), $idbox->pluck('enrollmentId')->all());
echo $both === [] ? "none\n" : implode(', ', $both)."\n";

echo "\n=== how old is each group, against the ruling dates ===\n";
echo 'clinic signedDate range: '.DB::table('workflowsteps')->where('officeId', OfficeId::Clinic->value)
    ->where('stepStatus', 'completed')->min('signedDate').' -> '
    .DB::table('workflowsteps')->where('officeId', OfficeId::Clinic->value)->where('stepStatus', 'completed')->max('signedDate')."\n";
