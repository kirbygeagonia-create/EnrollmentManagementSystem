<?php

// Measure, on the live dev box, what the application connection reports versus what the server defaults to.
// Pass 116 writes a sentence about this into the authority docx, so it must be measured rather than assumed.

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$os = php_uname('n');
$zones = (object) DB::selectOne('SELECT @@session.time_zone AS s, @@global.time_zone AS g, NOW() AS n, UTC_TIMESTAMP() AS u, @@hostname AS h');

echo 'host:        ', $os, PHP_EOL;
echo 'mysql host:  ', $zones->h, PHP_EOL;
echo 'session tz:  ', $zones->s, PHP_EOL;
echo 'global tz:   ', $zones->g, PHP_EOL;
echo 'NOW():       ', $zones->n, PHP_EOL;
echo 'UTC_TIMESTAMP(): ', $zones->u, PHP_EOL;
echo 'config app.timezone: ', config('app.timezone'), PHP_EOL;
echo 'Carbon now:  ', \Carbon\Carbon::now()->toDateTimeString(), PHP_EOL;
echo 'gmdate:      ', gmdate('Y-m-d H:i:s'), PHP_EOL;
echo 'php init cmd: ', json_encode(array_map('strval', config('database.connections.mysql.options')), JSON_UNESCAPED_SLASHES), PHP_EOL;
echo 'env APP_TIMEZONE: ', var_export(env('APP_TIMEZONE'), true), PHP_EOL;
echo 'same stamp?  ', $zones->n === \Carbon\Carbon::now()->toDateTimeString() ? 'equal' : 'differ', PHP_EOL;
echo 'session==global? ', $zones->s === $zones->g ? 'YES (no note)' : 'NO (note expected)', PHP_EOL;
echo 'OS local time: ', date('Y-m-d H:i:s P'), '   zone: ', date_default_timezone_get(), PHP_EOL;

// A second connection opened without the init command, to prove the forcing comes from the app and not the
// server: the direct PDO path reads the raw session default.
$raw = new PDO('mysql:host='.config('database.connections.mysql.host').';port='.config('database.connections.mysql.port').';dbname='.config('database.connections.mysql.database'),
    config('database.connections.mysql.username'), (string) config('database.connections.mysql.password'));
$r = (object) $raw->query('SELECT @@session.time_zone AS s, NOW() AS n')->fetch(PDO::FETCH_OBJ);
echo 'plain PDO session tz: ', $r->s, '  NOW(): ', $r->n, PHP_EOL;
