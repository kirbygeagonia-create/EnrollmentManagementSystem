<?php
// Does putenv() in this parent reach a child that `exec()` spawns? The residue probe depends on it, because
// `phpunit.xml` pins DB_CONNECTION=sqlite and the MySQL leg has to defeat that pin the way CI's step env does.
// Written as a file pair because `cmd.exe` does not strip the single quotes escapeshellarg() wraps, and a `-r`
// body with nested quotes arrives mangled (this box's documented trap).
$root = dirname(__DIR__, 3);
chdir($root);

putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=zz_probe_schema');

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$out = [];
exec($php.' '.escapeshellarg($root.'/storage/app/scratch/lane227b-child-env.php'), $out, $code);

echo 'child exit '.$code.': '.implode('|', $out)."\n";
echo 'this process: DB_CONNECTION='.getenv('DB_CONNECTION').' DB_DATABASE='.getenv('DB_DATABASE')."\n";
