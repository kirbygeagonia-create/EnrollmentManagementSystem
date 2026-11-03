<?php

/**
 * Read-only diagnostic: repeat the one request `lane223-probe-term-order-live.php` could not answer, and print the
 * exception the desk met instead of a list. Kept separate because a probe that refuses should not also be the tool
 * that explains itself.
 *
 * Usage: php storage/app/scratch/lane223-probe-the-500.php --allow-live
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

if (! in_array('--allow-live', array_slice($GLOBALS['argv'], 1), true)) {
    fwrite(STDERR, "REFUSED: this reads live `ems` and takes only --allow-live.\n");
    exit(1);
}

$staff = Staffusers::first();
Auth::login($staff);

foreach ([
    ['sort' => 'nothing', 'direction' => 'desc'],
    ['search' => '2026', 'sort' => 'nothing', 'direction' => 'desc'],
    ['status' => 'active', 'sort' => 'nothing', 'direction' => 'desc'],
    ['search' => '2026', 'status' => 'active', 'sort' => 'nothing', 'direction' => 'desc'],
    ['search' => '2026', 'status' => 'active', 'semester' => '1st', 'sort' => 'nothing', 'direction' => 'desc'],
] as $query) {
    $request = Request::create(route('admin.reference-data.terms', $query), 'GET');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    try {
        $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
        printf("%-72s status %d\n", json_encode($query), $response->getStatusCode());
    } catch (Throwable $e) {
        printf("%-72s THREW %s\n%s\n", json_encode($query), get_class($e), mb_substr($e->getMessage(), 0, 400));
    }
}
