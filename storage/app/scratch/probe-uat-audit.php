<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');
echo "=== every audit event on the two ID requests in doubt (639 Nena, 82 Kevin) ===\n";
foreach (DB::select("SELECT auditId, userId, action, entityId, ipAddress, createdAt,
                            LEFT(oldValues,220) oldV, LEFT(newValues,220) newV
                       FROM auditlogs WHERE entityTable = 'idrequests' AND entityId IN (639,82)
                       ORDER BY createdAt DESC LIMIT 20") as $r) {
    $p=[]; foreach ((array)$r as $k=>$v) { $p[]="$k=".($v===null?'NULL':$v); }
    echo "  ".implode(' | ', $p)."\n";
}
echo "\n=== the last 12 audit writes of any kind, with who they name ===\n";
foreach (DB::select("SELECT a.auditId, a.entityTable, a.action, a.entityId, a.ipAddress, a.createdAt,
                            CONCAT(s.firstName,' ',s.lastName) staff, a.userId
                       FROM auditlogs a LEFT JOIN staffusers s ON s.userId = a.userId
                       ORDER BY a.auditId DESC LIMIT 12") as $r) {
    $p=[]; foreach ((array)$r as $k=>$v) { $p[]="$k=".($v===null?'NULL':$v); }
    echo "  ".implode(' | ', $p)."\n";
}
