<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems','database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');
printf("waiver ON count=%d  codes=[%s]\n",
    DB::table('courses')->where('entranceExamExemptsTransferee', true)->count(),
    DB::table('courses')->where('entranceExamExemptsTransferee', true)->pluck('courseCode')->implode(','));
printf("BSCrim row: ee=%d cse=%d exempt=%d ret=%d\n", ...array_values(
    (array) DB::table('courses')->where('courseCode','BSCrim')->first(['requiresEntranceExam','requiresCourseSpecificExam','entranceExamExemptsTransferee','requiresRetentionExam'])));
$cols = array_column(DB::select("SHOW COLUMNS FROM auditlogs"), 'Field');
printf("auditlogs PK-ish columns: %s\n", implode(',', array_slice($cols, 0, 4)));
$latest = DB::table('auditlogs')->orderByDesc($cols[0])->first();
printf("last audit: %s\n", json_encode($latest, JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR) ?: 'none');
