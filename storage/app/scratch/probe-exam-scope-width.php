<?php
/**
 * How wide would narrowing the exam blockers actually be?
 *
 * #48 is the question of whether an examination result answers for the program and term printed on
 * it, or for the student who earned it anywhere. The blockers read it the second way while
 * examresults stores the first, so the honest answer needs a count: how many students on this
 * install hold a result for more than one program, and how many of them are standing in front of a
 * desk right now.
 *
 * Read-only. Nothing is written.
 */
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admissions;
use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

$rows = DB::table('examresults')
    ->select('studentId', 'courseId', 'termId', 'examType', 'examResult')
    ->get();

$byStudent = $rows->groupBy('studentId');
$multi = $byStudent->filter(fn ($g) => $g->pluck('courseId')->unique()->count() > 1);

printf("examresults rows: %d\n", $rows->count());
printf("students with an exam result: %d\n", $byStudent->count());
printf("students holding results for MORE THAN ONE course: %d\n", $multi->count());
foreach ($multi as $sid => $g) {
    printf("  student %-6s %s\n", $sid, $g->map(
        fn ($r) => $r->courseId.'/t'.$r->termId.' '.$r->examType.':'.$r->examResult
    )->implode('  |  '));
}

$live = Admissions::whereIn('admissionStatus', ['pending', 'approved'])->get();
printf("\nadmissions pending|approved: %d\n", $live->count());
foreach ($live as $a) {
    $results = $rows->where('studentId', $a->studentId);
    $elsewhere = $results->where(fn ($r) => $r->courseId != $a->courseId || $r->termId != $a->termId);
    printf("  admission %-4s student %-5s course %-3d term %-3d %s : results=%d of which NOT for this course/term: %d\n",
        $a->admissionId, $a->studentId, $a->courseId, $a->termId, $a->admissionStatus->value ?? $a->admissionStatus,
        $results->count(), $elsewhere->count());
}
