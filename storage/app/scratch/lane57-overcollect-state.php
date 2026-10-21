<?php

/**
 * Lane 57 — can the Accounting desk hand the school money it owes nothing for, in one act?
 *
 * `AccountingController::record()` validates the amount as `required|numeric|min:0.01` and nothing above it, while
 * `refund()` is documented as the desk's answer to "an over-collection". So the state is anticipated by the code and
 * created by the form. This probe asks the route, not the model: it drives POST /accounting/{assessment}/payment
 * through the HTTP kernel — middleware, policy and validation included — against a scratch schema, and prints what
 * the account looks like afterwards. It does not pretend to have acted: every mode reads back the row it aimed at
 * and refuses when the response was not the one the desk would have gotten.
 *
 * `env=testing` is set so `ValidateCsrfToken::runningUnitTests()` skips the token check (`Application.php:839-842`);
 * a 419 here would mean the probe never reached the action, and this file says so rather than reporting a clean run.
 *
 * Modes:  read    what the target account looks like now (no writes)
 *         plant   take balance + ₱1,500 through the route, commit, print the resulting state
 *         revert  remove the receipt this probe wrote and recompute the balance
 *
 *   DB_CONNECTION=mysql DB_DATABASE=ems_l56_a php storage/app/scratch/lane57-overcollect-state.php [read|plant|revert]
 */

$root = dirname(__DIR__, 3);
$mode = $argv[1] ?? 'read';

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\EnrollmentStatus;
use App\Models\Enrollments;
use App\Models\Payments;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Support\SchoolTime;
use Illuminate\Support\Facades\DB;

if ((string) config('app.env') !== 'testing') {
    fwrite(STDERR, "REFUSED: app.env is '".config('app.env')."', not testing — the CSRF token would 419 this request\n");
    exit(1);
}

$orNumber = 'L57-OVERCOLLECT';

/** The account this probe aims at: a demo enrollment that still owes, on a sheet with lines behind it. */
$target = function (): ?array {
    $sheet = DB::table('studentassessments as a')
        ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
        ->where('a.remainingBalance', '>', 0)
        ->where('a.totalAssessedAmount', '>', 0)
        ->where('e.enrollmentStatus', 'assessed')
        ->orderBy('a.enrollmentId')
        ->first();

    if ($sheet === null) {
        return null;
    }

    return [
        'enrollmentId' => (int) $sheet->enrollmentId,
        'assessmentId' => (int) $sheet->assessmentId,
        'due' => (float) $sheet->totalAssessedAmount - (float) $sheet->totalScholarshipCoverage - (float) $sheet->totalWaived,
        'balance' => (float) $sheet->remainingBalance,
        'status' => (string) $sheet->enrollmentStatus,
    ];
};

$print = function (string $label, ?array $t) use ($orNumber): void {
    if ($t === null) {
        echo "status=MISSING\n";
        echo "note={$label}: no assessed enrollment holding a balance\n";

        return;
    }
    $held = (float) Payments::held()->where('enrollmentId', $t['enrollmentId'])->sum('amount');
    $assessment = Studentassessments::find($t['assessmentId']);
    printf("label=%s\n", $label);
    printf("enrollment=%d assessment=%d enrollment_status=%s\n", $t['enrollmentId'], $t['assessmentId'], $t['status']);
    printf("due=%.2f balance=%.2f held=%.2f over=%.2f\n", $t['due'], $t['balance'], $held, max(0, $held - $t['due']));
    printf("model_balance=%.2f\n", (float) $assessment->outstandingBalance());
    printf("stored_remaining=%.2f\n", (float) $assessment->remainingBalance);
    printf("or_exists=%d\n", (int) (Payments::where('orNumber', $orNumber)->exists()));
};

if ($mode === 'read') {
    $print('read', $target());
    exit(0);
}

if ($mode === 'revert') {
    $n = DB::table('payments')->where('orNumber', $orNumber)->delete();

    // Deleting the receipt is only half of undoing the act: `record()` also walked the enrollment into `paid`
    // through the state machine. Leaving it there is exactly the residue this repository warns about — a row whose
    // trail says a desk did something the probe undid. So the balance is recomputed from what the account now
    // holds, and an enrollment the probe pushed into `paid` is pulled back through the same writer, with a remark
    // that names this file so a reconstruction is never mistaken for a desk act.
    $machine = $app->make(App\Services\EnrollmentStateMachine::class);
    $desk = Staffusers::where('username', 'office2_head')->first();

    if ($desk === null) {
        fwrite(STDERR, "REFUSED: no office2_head here, and the state machine needs a hand to record the walk-back with.\n");
        exit(1);
    }

    $walked = 0;
    foreach (Studentassessments::all() as $sheet) {
        $recomputed = round((float) $sheet->outstandingBalance(), 2);
        $sheet->update(['remainingBalance' => $recomputed]);

        $enrollment = Enrollments::find($sheet->enrollmentId);
        // `enrollmentStatus` is cast to the enum, so it compares to the case, never to the string — the first
        // version of this line matched `EnrollmentStatus::Paid->value`, never fired, and reported a clean revert
        // over an enrollment the probe had left `paid` while its account owed the full fee.
        if ($enrollment !== null && $recomputed > 0 && $enrollment->enrollmentStatus === EnrollmentStatus::Paid) {
            $machine->transition(
                $enrollment,
                EnrollmentStatus::Assessed,
                $desk,
                'Probe residue reverted by storage/app/scratch/lane57-overcollect-state.php — the receipt that funded this step was removed.',
            );
            $walked++;
        }
    }

    printf("deleted=%d walked_back=%d\n", $n, $walked);
    $print('after-revert', $target());
    exit(0);
}

if ($mode !== 'plant') {
    fwrite(STDERR, "REFUSED: unknown mode '{$mode}'\n");
    exit(1);
}

$t = $target();
if ($t === null) {
    fwrite(STDERR, "REFUSED: this schema holds no assessed enrollment with a balance, so the desk act cannot be staged.\n");
    exit(1);
}

$desk = Staffusers::where('username', 'office2_head')->first();
if ($desk === null) {
    fwrite(STDERR, "REFUSED: no office2_head account here — that is the desk that takes the money.\n");
    exit(1);
}

$assessment = Studentassessments::find($t['assessmentId']);
$overBy = 1500.00;
$amount = round($t['balance'] + $overBy, 2);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
// Request::create(uri, method, parameters, cookies, FILES, server). The last array here is the *server* bag: putting
// HTTP_REFERER into the FILES position makes Symfony's FileBag throw "An uploaded file must be an array or an
// instance of UploadedFile" before the request reaches the action, and the harness reads that as a refusal.
$request = Illuminate\Support\Facades\Request::create(
    route('accounting.payment.record', $assessment, false), 'POST', [
        'orNumber' => $orNumber,
        'amount' => $amount,
        'paymentMode' => 'cash',
        'paymentDate' => SchoolTime::today(),
    ], [], [], [
        // The desk arrives at this form from the account it is looking at, and the refusal rides on `back()`.
        // Without a Referer the probe would follow a different redirect than the screen does.
        'HTTP_REFERER' => route('accounting.show', $assessment, false),
        'HTTP_X-Requested-With' => 'XMLHttpRequest',
    ]);
$app->instance('request', $request);
Illuminate\Support\Facades\Auth::login($desk);

$response = $kernel->handle($request);
$status = $response->getStatusCode();
printf("http=%d took=%.2f owed_before=%.2f desk=%s\n", $status, $amount, $t['balance'], $desk->username);

if ($status === 419) {
    fwrite(STDERR, "REFUSED: the CSRF token stopped the request, so nothing reached record() and this run measures nothing.\n");
    exit(1);
}

$row = Payments::where('orNumber', $orNumber)->first();
printf("accepted=%d\n", (int) ($row !== null));
if ($row !== null) {
    printf("row_status=%s row_amount=%.2f\n", $row->paymentStatus->value, (float) $row->amount);
} else {
    // The desk did not get a receipt. Say why, from the session the *request* carried — after the kernel has
    // finished, the container's session instance is a different one and answers nothing. The bag is nested
    // (ViewErrorBag → default bag → field → list of messages), so it is walked rather than imploded once: the first
    // version threw "Array to string conversion" here after already printing `accepted=0`, which is an instrument
    // reporting success on the line above its own crash.
    $bag = $request->getSession()->get('errors') ?? $app->make('session')->get('errors');
    $messages = is_array($bag) ? $bag : ($bag === null ? [] : $bag->all());
    $flat = [];
    $walk = function (mixed $value, string $field) use (&$walk, &$flat): void {
        if (is_array($value)) {
            foreach ($value as $key => $inner) {
                $walk($inner, is_string($key) ? $key : $field);
            }

            return;
        }
        $flat[] = ($field !== '' ? $field.': ' : '').(string) $value;
    };
    $walk($messages, '');
    // The bag carries its own translation scaffolding (`format` → `:message`); the desk's sentence is the payload.
    $flat = array_values(array_filter($flat,
        fn (string $line): bool => ! str_contains($line, ':message') && ! str_starts_with($line, 'format:')));
    if ($flat === []) {
        fwrite(STDERR, "REFUSED: the receipt was not created and no reason came back — the run cannot tell a guard from a break.\n");
        exit(1);
    }
    printf("refused_reason=%s\n", preg_replace('/\s+/', ' ', implode(' | ', $flat)));
}

$print('after-plant', $t);
exit(0);
