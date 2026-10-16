<?php
// Prove the queue probe discriminates: run its exact loop against an ability that only some roles hold.
// If the probe still reports "opens for 21 of 21", it is not reading anything and its clean verdict is
// worthless. 'evaluation.sign' is held by DeptEvaluator only; 'block.view' by several desks.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;

foreach (['evaluation.view', 'evaluation.sign', 'block.view', 'students.view'] as $ability) {
    $yes = [];
    $no = [];
    foreach (Staffusers::orderBy('userId')->get() as $a) {
        if ($a->hasPermissionTo($ability)) { $yes[] = $a->username; } else { $no[] = $a->username; }
    }
    printf("%-16s opens %2d of %d | refused: %s\n", $ability, count($yes), count($yes) + count($no),
        $no === [] ? 'none' : implode(',', array_slice($no, 0, 6)).(count($no) > 6 ? ',…' : ''));
}
