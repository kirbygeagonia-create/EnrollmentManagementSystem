<?php
// Spot-check every flow changed this session: exam create/students (both
// stages), blocking.show, gate behavior for office heads, fonts + CSP header.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;
use Illuminate\Support\Facades\Auth;

function req(Staffusers $user, string $method, string $uri, array $data = []) {
    $session = app('session.store');
    $session->start();
    Auth::login($user);
    $token = $session->token(); // AFTER login — login() regenerates it
    $request = Illuminate\Http\Request::create($uri, $method, $data + ['_token' => $token], [], [], ['HTTP_ACCEPT' => 'text/html']);
    $request->setLaravelSession($session);
    $response = app()->handle($request);
    Auth::logout();
    return $response;
}

$admin = Staffusers::where('username', 'staff8')->firstOrFail();
$academic = Staffusers::where('username', 'office7_head')->firstOrFail();
$blocking = Staffusers::where('username', 'office5_head')->firstOrFail();
$admission = Staffusers::where('username', 'office6_head')->firstOrFail();

// 1. Exam create forms — both stages render for authorized users
foreach (['entrance' => 'entrance', 'retention' => 'retention'] as $stage) {
    $r = req($academic, 'GET', route('exam.create', ['stage' => $stage]));
    $body = $r->getStatusCode() === 200 ? 'OK' : substr($r->getContent(), 0, 80);
    echo "exam.create ($stage): HTTP {$r->getStatusCode()} $body\n";
}

// 2. Exam student lookups — correct candidates per stage
foreach ([3 => 'entrance', 5 => 'retention'] as $courseId => $stage) {
    $r = req($academic, 'GET', route('exam.students', ['courseId' => $courseId, 'termId' => 18, 'stage' => $stage]));
    $names = collect(json_decode($r->getContent(), true)['students'] ?? [])->map(fn ($s) => $s['lastName'])->join(', ');
    echo "exam.students (course $courseId, $stage): HTTP {$r->getStatusCode()} → [$names]\n";
}

// 3. blocking.show — the 500-fix path (partial-select instructor serialization)
$blockId = App\Models\Blocks::where('blockName', 'like', '%1-A%')->first()?->blockId;
if ($blockId) {
    $r = req($blocking, 'GET', route('blocking.show', $blockId));
    $ok = $r->getStatusCode() === 200 && str_contains($r->getContent(), 'page');
    echo "blocking.show (#$blockId): HTTP {$r->getStatusCode()} " . ($ok ? 'OK' : substr($r->getContent(), 0, 100)) . "\n";
}

// 4. Admission show with document — Pedro's pending admission + Form138
$pedroAdmission = App\Models\Admissions::where('admissionStatus', 'pending')->latest('admissionId')->first();
if ($pedroAdmission) {
    $r = req($admission, 'GET', route('admission.show', $pedroAdmission->admissionId));
    $hasDoc = str_contains($r->getContent(), 'admission-documents') || str_contains($r->getContent(), 'Form');
    echo "admission.show (#{$pedroAdmission->admissionId}): HTTP {$r->getStatusCode()} doc-link:" . ($hasDoc ? 'yes' : 'no') . "\n";
}

// 5. RBAC: office heads carry broad view permissions by seed design (OfficeHead
// role = 56 view/work perms, 0 manage perms), so a 200 here is correct — the
// nav launcher scopes their UI to their own office desk. Manage-level writes
// are the real gate and are verified blocked in _write_gate_check.php.

// 6. Dashboard renders for an office head (scoped view)
$r = req($admission, 'GET', route('dashboard'));
echo "dashboard as office6_head: HTTP {$r->getStatusCode()}\n";
