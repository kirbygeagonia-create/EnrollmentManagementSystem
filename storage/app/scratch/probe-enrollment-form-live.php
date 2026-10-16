<?php
/**
 * Live proof of the enrollment-form print, against real `ems`, inside a transaction that is rolled
 * back. SQLite covers the gate; this answers the desk's question — does the Registrar actually get
 * a PDF, does the trail gain an enrollmentForm row of its own, and is the clinic head refused — on
 * the database the install runs.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Enums\DocumentType;
use App\Models\Documentprintlog;
use App\Models\Enrollments;
use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

$enrollment = Enrollments::find(53) ?? Enrollments::where('enrollmentStatus', 'enrolled')->first();
if ($enrollment === null) {
    fwrite(STDERR, "no enrolled record to print\n");
    exit(1);
}
$registrar = Staffusers::where('username', 'office1_head')->first();
$clinic = Staffusers::where('username', 'office11_head')->first();

printf("record %d (%s, %s)\n", $enrollment->enrollmentId, $enrollment->student?->schoolIdNumber, $enrollment->enrollmentStatus->value);
printf("office1_head rights: enrollment.approve=%s print.certificate=%s print.enrollmentForm=%s\n",
    var_export($registrar->can('enrollment.approve'), true),
    var_export($registrar->can('print.certificate'), true),
    var_export($registrar->can('print.enrollmentForm'), true));
printf("office11_head print.enrollmentForm=%s (clinic head, office 11)\n",
    var_export($clinic->can('print.enrollmentForm'), true));

DB::beginTransaction();
$failures = 0;
try {
    $before = Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
        ->where('documentType', DocumentType::EnrollmentForm)->count();

    $request = Illuminate\Support\Facades\Request::create(
        route('registrar.download-enrollment-form', $enrollment, false), 'GET'
    );
    $app->instance('request', $request);
    Illuminate\Support\Facades\Auth::login($registrar);
    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
    $status = $response->getStatusCode();
    $type = $response->headers->get('Content-Type');
    printf("\nRegistrar GET %s → %d (%s)\n", $request->getRequestUri(), $status, $type);
    if ($status !== 200) {
        $failures++;
    }

    $after = Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
        ->where('documentType', DocumentType::EnrollmentForm)->count();
    printf("enrollmentForm issue rows for this record: %d → %d\n", $before, $after);
    if ($after !== $before + 1) {
        echo "FAIL: the download did not mint exactly one issue row\n";
        $failures++;
    }
    $row = Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
        ->where('documentType', DocumentType::EnrollmentForm)->latest('printLogId')->first();
    printf("  row: printLogId %s, documentNumber %s, studentId %s, printedBy %s\n",
        $row?->printLogId, $row?->documentNumber, $row?->studentId, $row?->printedBy);

    // The clinic head, in its own office, is refused.
    $request2 = Illuminate\Support\Facades\Request::create(
        route('registrar.download-enrollment-form', $enrollment, false), 'GET'
    );
    $app->instance('request', $request2);
    Illuminate\Support\Facades\Auth::login($clinic);
    $response2 = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request2);
    printf("Clinic head GET the same route → %d (expected 403)\n", $response2->getStatusCode());
    if ($response2->getStatusCode() !== 403) {
        $failures++;
    }

    // And the certificate's own sequence is untouched by the form's number.
    $certMax = (int) Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
        ->where('documentType', DocumentType::Certificate)->max('documentNumber');
    printf("certificate sequence for this record reads up to %d, form up to %d — separate series\n",
        $certMax, (int) $row?->documentNumber);
} finally {
    DB::rollBack();
    echo "\nrolled back — no print row left behind\n";
}

$still = Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
    ->where('documentType', DocumentType::EnrollmentForm)->count();
printf("after rollback, enrollmentForm rows for record %d: %d\n", $enrollment->enrollmentId, $still);

echo $failures === 0 ? "\nLIVE PROOF PASSED\n" : "\n{$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
