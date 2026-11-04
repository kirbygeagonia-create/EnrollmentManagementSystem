<?php

/**
 * Lane 261 — assemble the offline kit OUTSIDE the repository.
 *
 * Nothing here needs the network: every dependency is already installed on this box. What a second machine needs is
 * a copyable folder, so this stages one. Three rules the script is written around, all of them paid for elsewhere in
 * this repository:
 *
 *  - a database dump never goes in the tree, so the target is C:/Users/ADMIN/ems-offline/ and the source dump is
 *    taken fresh at run time rather than pointed at an old snapshot;
 *  - robocopy answers 0..7 for success (1 means "files were copied"), so a status check that demands 0 would call a
 *    good copy a failure and a check that ignores the number would call a failed copy a success;
 *  - `git archive HEAD` is the source, which is why the committed document and the ignored reference scans and dumps
 *    cannot ride along by accident.
 *
 * Usage: php storage/app/scratch/lane261-make-offline-bundle.php [--recreate]
 */
$homeDir = 'C:/Users/ADMIN';
$repo = $homeDir.'/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem';
// The stamp is taken by booting the framework with a relative require, so the working directory has to be the
// project before that line runs — otherwise the date comes back as a fatal and the refusal names the wrong cause.
chdir($repo);
$stamp = trim((string) shell_exec('"'.PHP_BINARY.'" -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();" 2>&1'));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stamp) !== 1) {
    fwrite(STDERR, "REFUSED: the school's civil day answered [{$stamp}], so the bundle would carry a typed date.\n");

    exit(1);
}
$target = $homeDir.'/ems-offline/ems-offline-kit-'.$stamp;
$recreate = in_array('--recreate', array_slice($GLOBALS['argv'], 1), true);
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if ($a !== '--recreate') {
        fwrite(STDERR, "REFUSED: this tool reads only --recreate, got '{$a}'.\n");

        exit(1);
    }
}
chdir($repo);

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");

    exit(1);
};

if (is_dir($target)) {
    if (! $recreate) {
        $fail("{$target} already exists. Pass --recreate to drop and rebuild it, because a half-copied kit "
            .'that is silently refreshed is worse than no kit.');
    }
    $rm = shell_exec('rmdir /S /Q '.escapeshellarg(str_replace('/', '\\', $target)).' 2>&1');
    if (is_dir($target)) {
        $fail('the old bundle could not be removed: '.trim((string) $rm));
    }
}
foreach ([$target, $target.'/source', $target.'/database', $target.'/runtime'] as $d) {
    if (! mkdir($d, 0777, true) && ! is_dir($d)) {
        $fail("could not create {$d}.");
    }
}

// 1. the source, from the commit — never from the working tree, so ignored artefacts cannot join by accident.
$zip = $target.'/source/ems-source.zip';
shell_exec('git archive --format=zip -o '.escapeshellarg($zip).' HEAD 2>&1');
if (! is_file($zip) || filesize($zip) < 100000) {
    $fail('git archive produced '.(is_file($zip) ? filesize($zip).' bytes' : 'no file').' — the source half of the kit is not there.');
}
echo 'source   : '.number_format(filesize($zip) / 1048576, 1)." MB from HEAD (git archive)\n";
$sha = trim((string) shell_exec('git rev-parse --short HEAD 2>&1'));

// 2. a fresh dump of the demo database, written outside the repository.
$mysqldump = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe';
is_file($mysqldump) || $fail('no mysqldump at the path this box documents.');
$dump = $target.'/database/ems-demo-'.date('Ymd-His').'.sql';
$out = (string) shell_exec(escapeshellarg($mysqldump).' -u root --databases ems --add-drop-database --routines --triggers '
    .'> '.escapeshellarg($dump).' 2>&1');
if (! is_file($dump) || filesize($dump) < 10000) {
    $fail("the dump came back ".(is_file($dump) ? filesize($dump) : 0)." bytes; mysqldump said: ".trim((string) $out));
}
$head = substr((string) file_get_contents($dump, false, null, 0, 4000), 0, 4000);
if (! str_contains($head, 'Current Database: `ems`')) {
    $fail('the dump header does not name ems, so it is not the snapshot this kit claims to carry.');
}
echo 'database : '.number_format(filesize($dump) / 1048576, 1)." MB dumped from ems, outside the repository\n";

// 3. the two dependency trees and the compiled front end.
$copy = static function (string $from, string $to, string $label) use ($fail): void {
    $src = str_replace('/', '\\', rtrim($from, '/'));
    $dst = str_replace('/', '\\', rtrim($to, '/'));
    $lines = [];
    $code = 0;
    // Once, not twice: a first attempt that shells out for the text and a second for the status would copy the
    // largest folder twice and double the time for no extra evidence.
    exec('robocopy '.escapeshellarg($src).' '.escapeshellarg($dst).' /E /NFL /NDL /NJH /NJS /MT:16 /R:1 /W:1 2>&1', $lines, $code);
    // 0..7 is success for robocopy: 1 means files were copied, 2/3 extra files, 5/7 hybrid dir states. 8 and above
    // are real failure. Reading only "exit 0" would report a good copy as broken, and reading nothing reports a
    // broken copy as done.
    if ($code >= 8) {
        $fail("{$label} failed with robocopy status {$code}: ".trim(implode(' | ', array_slice($lines, 0, 4))));
    }
    $count = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($to, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $count++;
        }
    }
    if ($count === 0) {
        $fail("{$label} reported robocopy status {$code} but the destination holds no file — the copy did not land.");
    }
    echo str_pad($label, 12)."copied {$count} file(s) (robocopy status {$code})\n";
};
$copy($repo.'/vendor', $target.'/runtime/vendor', 'vendor');
$copy($repo.'/node_modules', $target.'/runtime/node_modules', 'node_modules');
$copy($repo.'/public/build', $target.'/runtime/public-build', 'public/build');

// 4. the instructions, with the numbers this run measured.
$size = static function (string $path): float {
    $bytes = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $bytes += $f->getSize();
        }
    }

    return round($bytes / 1048576, 1);
};
$readme = "# SEAIT EMS offline kit — built {$stamp} at commit {$sha}\n\n"
    ."Total size: ".($size($target))." MB. Nothing in here needs internet.\n\n"
    ."## Folders\n"
    ."  source\\ems-source.zip     the committed source tree (unzip it as the project folder)\n"
    ."  database\\*.sql            a fresh dump of the demo schema `ems` (synthetic demo cast, no real record)\n"
    ."  runtime\\vendor            Composer dependencies, already installed\n"
    ."  runtime\\node_modules      npm dependencies, already installed\n"
    ."  runtime\\public-build      the compiled front end, so no build step is needed on the target\n\n"
    ."## On the machine that will run it\n"
    ."  1. Install Laragon (Apache + MySQL 8) and PHP 8.3, or point this kit at an existing install of the same versions.\n"
    ."  2. Unzip source\\ems-source.zip to C:\\laragon\\www\\EnrollmentManagementSystem (any folder name works).\n"
    ."  3. Copy runtime\\vendor to that folder as vendor\\, runtime\\node_modules as node_modules\\, and runtime\\public-build as public\\build.\n"
    ."  4. Copy the .env from the machine this kit was built on (it is NOT in the zip on purpose — it holds the app key).\n"
    ."  5. Import the dump:  mysql -u root < database\\ems-demo-*.sql\n"
    ."  6. php artisan config:clear  then  php artisan migrate --force  (the dump already carries the schema; this proves it)\n"
    ."  7. Start MySQL and Apache, open http://localhost:8080/login, sign in with any account in the Word document's section 6.\n"
    ."  8. Prints need Chrome. If it is missing, install it or set EMS_CHROME_PATH in .env to an existing binary; every desk screen still works without it.\n\n"
    ."## Share it on the local network\n"
    ."  npm run build                     once, in the project folder (already done if you copied public-build)\n"
    ."  .env: APP_URL=http://<this machine's LAN IP>:8080, then php artisan config:clear\n"
    ."  From an Administrator PowerShell, once:\n"
    ."    netsh advfirewall firewall add rule name=\"EMS 8080\" dir=in action=allow protocol=TCP localport=8080 remoteip=192.168.100.0/24\n"
    ."  On the other laptop: http://<that IP>:8080/login\n"
    ."  Use the IP of the interface that carries the default route (ipconfig, or Get-NetRoute -DestinationPrefix '0.0.0.0/0'),\n"
    ."  not a hotspot or virtual adapter address.\n\n"
    ."## Do not\n"
    ."  - commit this folder, or the dump inside it, into the repository;\n"
    ."  - hand the Word document listing the demo passwords beyond the school, and change every one of those passwords\n"
    ."    before the system holds a real record — they are all the seed default.\n";
if (file_put_contents($target.'/README.txt', $readme) === false) {
    $fail('the README could not be written.');
}

printf("readme     : written\n");
echo 'BUNDLE     : '.$target.' — '.($size($target))." MB total\n";
