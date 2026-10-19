<?php

// Lane 53, the desk's own question: what does the fee sheet print?
//
// The audit counts rows; this asks the route. AssessmentController::show() serializes the assessment with
// `charges.feeType`, and the sheet renders the lines under a header that quotes totalAssessedAmount — which
// is exactly the pair that disagreed on DEMO-2026-014 (₱17,500 over an empty table). Goes through the HTTP
// kernel, so the policy and the middleware run, inside a transaction that is rolled back.
//
//   php storage/app/scratch/lane53-probe-feesheet-live.php

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use Illuminate\Support\Facades\DB;

$desk = Staffusers::where('username', 'office2_head')->first();

if ($desk === null) {
    fwrite(STDERR, "no office2_head account on this database — the desk that reads the sheet\n");

    exit(1);
}

$sheetFor = function (string $schoolId) use ($app, $desk): array {
    $enrollment = Enrollments::whereHas('student', fn ($q) => $q->where('schoolIdNumber', $schoolId))
        ->orderByDesc('enrollmentId')
        ->first();

    if ($enrollment === null) {
        return [$schoolId, null, "no enrollment"];
    }

    $assessment = Studentassessments::where('enrollmentId', $enrollment->enrollmentId)->first();

    if ($assessment === null) {
        return [$schoolId, null, "no fee sheet on enrollment {$enrollment->enrollmentId}"];
    }

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    $request = Illuminate\Support\Facades\Request::create(route('assessment.show', $assessment, false), 'GET');
    $app->instance('request', $request);
    Illuminate\Support\Facades\Auth::login($desk);

    $response = $kernel->handle($request);
    $status = $response->getStatusCode();

    if ($status !== 200) {
        return [$schoolId, $assessment, "HTTP {$status}"];
    }

    // The payload the browser is handed, read out of the page rather than re-requested: Blade renders it into
    // `data-page` with its quotes escaped, and a second GET carrying X-Inertia without the server's own
    // version string is answered 409 (measured), which carries no props at all.
    $html = $response->getContent();

    if (preg_match('/data-page="([^"]*)"/', $html, $m) !== 1) {
        return [$schoolId, $assessment, 'the page carried no data-page payload — it is not the Inertia screen this probe assumes'];
    }

    $payload = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    $props = is_array($payload) ? ($payload['props'] ?? []) : [];
    $charges = $props['assessment']['charges'] ?? null;

    if ($charges === null) {
        return [$schoolId, $assessment, 'the sheet answered with no charges key at all ('.implode(', ', array_keys($props)).')'];
    }

    return [$schoolId, $assessment, $charges];
};

DB::beginTransaction();
$failures = 0;

// `--plant=<assessmentId>` unprices one sheet inside the transaction the whole probe runs in, to prove this
// reader can see the defect it is named for. It refuses when the delete matches nothing, because an
// unmatched needle reports the clean output as a failure to complain.
$plant = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--plant=')) {
        $plant = (int) substr($arg, strlen('--plant='));
    }
}

DB::beginTransaction();

try {
    if ($plant !== null) {
        $gone = DB::table('charges')->where('assessmentId', $plant)->delete();

        if ($gone === 0) {
            fwrite(STDERR, "REFUSE: assessment {$plant} holds no fee lines to remove — this plant cannot show anything.\n");

            exit(1);
        }

        echo "PLANTED: {$gone} fee line(s) removed from assessment {$plant}, inside a transaction that is rolled back\n\n";
    }

    foreach (['DEMO-2026-014', 'DEMO-2026-012', 'DEMO-2026-013'] as $schoolId) {
        [$id, $assessment, $lines] = $sheetFor($schoolId);

        if (! is_array($lines)) {
            echo "{$id}: {$lines}\n";
            $failures++;

            continue;
        }

        $sum = 0.0;

        printf("%s — assessment %s, header says ₱%s\n", $id, $assessment->assessmentId, number_format((float) $assessment->totalAssessedAmount, 2));

        if ($lines === []) {
            echo "  NO LINES ON THE SHEET — the desk reads a total over an empty table\n";
            $failures++;

            continue;
        }

        foreach ($lines as $line) {
            $sum += (float) $line['amount'];
            printf("  %-30s ₱%s\n", $line['fee_type']['feeName'] ?? ($line['feeType']['feeName'] ?? '?'), number_format((float) $line['amount'], 2));
        }

        $agrees = abs($sum - (float) $assessment->totalAssessedAmount) < 0.005;
        printf("  the lines add up to ₱%s — %s\n\n", number_format($sum, 2), $agrees ? 'the header agrees' : 'THE HEADER DISAGREES');

        if (! $agrees) {
            $failures++;
        }
    }
} finally {
    DB::rollBack();
}

printf("%d problem(s).\n", $failures);

exit($failures === 0 ? 0 : 1);
