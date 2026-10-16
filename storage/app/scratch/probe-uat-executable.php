<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems','database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');
$rows = DB::table('admissions')->join('courses','courses.courseId','=','admissions.courseId')
    ->select('admissions.admissionId','admissions.admissionStatus','admissions.applicantType','courses.courseCode')
    ->orderBy('courses.courseCode')->get();
foreach ($rows as $r) { printf("  adm %-4d %-9s %-11s %s\n", $r->admissionId, $r->admissionStatus, $r->applicantType, $r->courseCode); }
$boardOnly = ['BSA','BSCE','BSEE'];
$pend = $rows->filter(fn($r) => in_array($r->admissionStatus, ['pending','approved'], true));
printf("\npending|approved=%d  on a board-only program (BSA/BSCE/BSEE)=%d  transferee=%d\n",
  $pend->count(), $pend->whereIn('courseCode',$boardOnly)->count(), $pend->where('applicantType','transferee')->count());
