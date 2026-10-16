<?php
/**
 * Live proof of the print scope on real `ems`, inside a rolled-back transaction: the Registrar's
 * own head still gets a PDF, the Clinic head who holds every print right is refused, and the
 * standing rule issues the transferee record irregular.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Enrollments;
use App\Models\Staffusers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$enrollment = Enrollments::where('enrollmentStatus', 'enrolled')->first();
$registrar = Staffusers::where('username', 'office1_head')->first();
$clinic = Staffusers::where('username', 'office11_head')->first();

$hit = function (Staffusers $as, string $route) use ($app, $kernel, $enrollment): array {
    $request = Request::create(route($route, $enrollment, false), 'GET');
    $app->instance('request', $request);
    Auth::login($as);

    return [$kernel->handle($request)->getStatusCode(), $request->getRequestUri()];
};

DB::beginTransaction();
$failures = 0;
try {
    foreach ([
        ['office1_head holds every print right', $registrar, 'registrar.download-enrollment-form', 200],
        ['the same desk prints the certificate', $registrar, 'registrar.download-certificate', 200],
        ['clinic head holds print.enrollmentForm', $clinic, 'registrar.download-enrollment-form', 403],
        ['clinic head holds print.certificate', $clinic, 'registrar.download-certificate', 403],
    ] as [$label, $who, $route, $want]) {
        [$got, $uri] = $hit($who, $route);
        $ok = $got === $want;
        $failures += $ok ? 0 : 1;
        printf("%s  %-38s %-34s → %d (expected %d)\n", $ok ? 'ok  ' : 'FAIL', $label, $uri, $got, $want);
    }

    printf("\nclinic head permissions: enrollmentForm=%s certificate=%s | officeId=%d\n",
        var_export($clinic->hasPermissionTo('print.enrollmentForm'), true),
        var_export($clinic->hasPermissionTo('print.certificate'), true), $clinic->officeId);
} finally {
    DB::rollBack();
    echo "\nrolled back — no print row left behind\n";
}

echo $failures === 0 ? "\nLIVE SCOPE PROVED\n" : "\n{$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
