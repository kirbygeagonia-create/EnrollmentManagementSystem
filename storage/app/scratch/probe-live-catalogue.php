<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');
$rows = DB::table('permissions')->orderBy('module')->orderBy('name')->get(['module','name']);
$byModule = [];
foreach ($rows as $r) { $byModule[$r->module][] = $r->name; }
echo "module | count | names\n";
foreach ($byModule as $m => $names) { printf("%s|%d|%s\n", $m, count($names), implode(', ', $names)); }
printf("TOTAL=%d across %d modules\n", array_sum(array_map('count',$byModule)), count($byModule));
$examOnly = DB::table('permissions')->whereIn('name', ['exam.verify.general','dashboard.view'])->pluck('name')->all();
echo 'retired-rights still present: '.($examOnly === [] ? '(none)' : implode(',', $examOnly))."\n";
