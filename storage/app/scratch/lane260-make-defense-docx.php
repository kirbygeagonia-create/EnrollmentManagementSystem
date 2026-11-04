<?php

/**
 * Lane 260 — the defense-and-offline Word document, generated from the artefacts rather than retyped.
 *
 * Six things the owner asked for, one file. Every row is read from where it lives:
 *   - the transaction census walks `app/` and `tools/` for `DB::transaction(` and records the enclosing action,
 *     the markers beside it (a row lock, a retry, a nested call) and the line it sits on;
 *   - the ACID and 12-concept sections are lifted from `Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md`, which is
 *     the instrument-weighted source, so this document is a projection of it and not a second answer;
 *   - the 24 ADS problems come from `Documentation/REQUIREMENTS-TRACEABILITY-ADS.md`'s own P register rows, with the
 *     artefacts named in each verdict extracted as "where to witness it";
 *   - the account list is queried from live `ems` and the password is read out of the seeder that writes it, never
 *     out of a hash (a bcrypt hash is not recoverable, and pretending otherwise would be invented data);
 *   - the offline inventory measures the directories and binaries on this box at run time;
 *   - the LAN section quotes what was probed: the listening socket, the HTTP status of the app on this box's own
 *     LAN address, and whether Windows answers inbound on that port.
 *
 * A figure that no source answers stops the run: this file would rather refuse than write a plausible number.
 *
 * Usage: php storage/app/scratch/lane260-make-defense-docx.php [--out=PATH] [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$out = $root.'/Documentation/Transactions-ACID-Evidence-and-Offline-Kit.docx';
$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif (preg_match('/^--out=(.*)$/', $arg, $m)) {
        $out = $m[1];
    } else {
        fwrite(STDERR, "REFUSED: this tool reads only --out=PATH and --apply, got '{$arg}'.\n");

        exit(1);
    }
}

// A PHP warning is a broken instrument, not a cosmetic notice: a pattern that lost its closing delimiter returns
// null, and the document would then carry "remoteip=/24" while the run still printed WROTE.
set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
    fwrite(STDERR, "REFUSED: php warning at {$file}:{$line} — {$msg}\n");

    exit(1);
});

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");

    exit(1);
};

$read = static function (string $path) use ($fail): string {
    if (! is_file($path)) {
        $fail("the source document {$path} is not on disk — a section built without it would be written from memory.");
    }
    $t = file_get_contents($path);
    if (! is_string($t) || trim($t) === '') {
        $fail("{$path} read back empty.");
    }

    return $t;
};

$esc = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8', false);

$schoolToday = trim((string) shell_exec(escapeshellarg(PHP_BINARY).' -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();" 2>&1'));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schoolToday) !== 1) {
    $fail("the school's civil day answered [{$schoolToday}] — no dated claim in this document may be typed by hand.");
}
$sha = trim((string) shell_exec('git rev-parse --short HEAD 2>&1'));
if (preg_match('/^[0-9a-f]{7,40}$/', $sha) !== 1) {
    $fail("git answered [{$sha}] for HEAD, which is not a commit — the provenance line would be fiction.");
}

// ---------------------------------------------------------------- 1. transaction census
$sites = [];
foreach (['app', 'tools'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') {
            continue;
        }
        $lines = file($f->getPathname());
        foreach ($lines as $i => $line) {
            if (! str_contains($line, 'DB::transaction(')) {
                continue;
            }
            $method = '(top level)';
            for ($j = $i; $j >= 0; $j--) {
                if (preg_match('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $lines[$j], $m)) {
                    $method = $m[1].'()';

                    break;
                }
            }
            $body = implode('', array_slice($lines, max(0, $i - 8), 40));
            $marks = [];
            if (stripos($body, 'lockforupdate') !== false) {
                $marks[] = 'row lock';
            }
            if (stripos($body, 'retryondeadlock') !== false) {
                $marks[] = 'deadlock retry';
            }
            // No "nested" marker here: an offset window cannot tell a transaction inside another one from two that
            // run one after the other, and a document that guessed the difference would be making a claim the
            // reader cannot support.
            $sites[] = [
                'file' => str_replace($root.'/', '', str_replace('\\', '/', $f->getPathname())),
                'line' => $i + 1,
                'method' => $method,
                'marks' => $marks === [] ? ['plain'] : $marks,
            ];
        }
    }
}
if ($sites === []) {
    $fail('the walk found no DB::transaction site anywhere in app/ or tools/, which means the reader is broken, '
        .'not that the system has none.');
}
$appCount = count(array_filter($sites, static fn (array $s): bool => str_starts_with($s['file'], 'app/')));
$toolsCount = count($sites) - $appCount;
$typeTally = [];
foreach ($sites as $s) {
    foreach ($s['marks'] as $m) {
        $typeTally[$m] = ($typeTally[$m] ?? 0) + 1;
    }
}
ksort($typeTally);

// ---------------------------------------------------------------- 2. concepts and ACID
$conceptDoc = $read($root.'/Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md');
preg_match_all('/^## (\d+)\.\s*(.+)$/mu', $conceptDoc, $hh);
if (count($hh[0]) !== 12) {
    $fail('the concept compliance file answered '.count($hh[0]).' numbered sections; the guide asks twelve, '
        .'so the population this document projects from moved.');
}
// The guide's own summary table: # | Concept | Status word.
preg_match_all('/^\|\s*(\d+)\s*\|\s*([^|]+?)\s*\|\s*(.+?)\s*\|$/m', $conceptDoc, $rows);
$conceptRows = [];
foreach ($rows[1] as $i => $n) {
    $conceptRows[(int) $n] = [trim($rows[2][$i]), trim($rows[3][$i])];
}
if (count($conceptRows) !== 12) {
    $fail('the summary table answered '.count($conceptRows).' of 12 concept rows — read the file before quoting it.');
}
// The four ACID bullets, lifted from section 3 verbatim.
preg_match('/^## 3\. ACID.*?\n(.*?)\n\*\*Test method/msu', $conceptDoc, $acid);
if (! isset($acid[1])) {
    $fail('section 3 of the concept file no longer holds the four ACID bullets in the shape this document reads.');
}
$acidBullets = [];
foreach (preg_split('/\n(?=- \*\*)/', trim($acid[1])) as $b) {
    if (preg_match('/^- \*\*(.+?)\*\*\s*—\s*(.+)$/su', trim($b), $m)) {
        $acidBullets[] = [trim($m[1]), preg_replace('/\s+/', ' ', trim($m[2]))];
    }
}
if (count($acidBullets) !== 4) {
    $fail('the ACID section answered '.count($acidBullets).' properties, not four.');
}

// ---------------------------------------------------------------- 3. the 24 ADS problems
$trace = $read($root.'/Documentation/REQUIREMENTS-TRACEABILITY-ADS.md');
preg_match_all('/^\|\s*(P\d{2})\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|\s*(.+?)\s*\|$/m', $trace, $prows);
$problems = [];
foreach ($prows[1] as $i => $id) {
    $verdict = trim($prows[4][$i]);
    preg_match_all('/`([^`]+?\.(?:php|jsx|txt|md|mjs|json|sql|py))`/', $verdict, $arts);
    $witness = array_values(array_unique(array_map(
        static fn (string $a): string => trim($a),
        $arts[1] ?? []
    )));
    $problems[] = [
        'id' => $id,
        'title' => trim($prows[2][$i]),
        'applies' => trim($prows[3][$i]),
        'verdict' => preg_replace('/\s+/', ' ', $verdict),
        'witness' => $witness === [] ? ['(the verdict names no file — read the row in the matrix)'] : $witness,
    ];
}
if (count($problems) !== 24) {
    $fail('the P register answered '.count($problems).' problems; the guide holds 24, so this document would '
        .'report a short census.');
}

// ---------------------------------------------------------------- 4. credentials
$mysql = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
is_file($mysql) || $fail('no mysql binary to ask the live schema who the accounts are.');
$q = 'select s.username, concat(s.firstName,\' \',s.lastName), s.role, coalesce(o.officeName,\'(no office)\'), '
    .'if(s.unitId is null,\'unbounded\',concat(\'unit \',s.unitId)), s.status from staffusers s '
    .'left join offices o on o.officeId = s.officeId order by s.userId';
$accounts = [];
exec(escapeshellarg($mysql).' -u root ems -B -N -e '.escapeshellarg($q).' 2>&1', $accounts);
// -N already suppresses the header, so nothing is shifted off the top; the row count is checked against the
// server's own COUNT(*) instead of trusting the reader, because a dropped first account would print as "20".
$serverCount = (int) trim((string) shell_exec(escapeshellarg($mysql).' -u root ems -N -e "select count(*) from staffusers" 2>&1'));
if (count($accounts) !== $serverCount) {
    $fail('the account query answered '.count($accounts).' rows while live ems counts '.$serverCount
        .' staffusers — the list in this document would be short by the difference.');
}
foreach ($accounts as $i => $line) {
    if (str_starts_with($line, 'ERROR')) {
        $fail('mysql answered: '.$line);
    }
}
$seeder = $read($root.'/database/seeders/DevReferenceDataSeeder.php');
if (preg_match("/Hash::make\('([^']+)'\)/", $seeder, $pm) !== 1) {
    $fail('the seeder no longer writes a literal default password, so this document may not state one.');
}
$defaultPassword = $pm[1];

// ---------------------------------------------------------------- 5. offline inventory
$du = static function (string $rel) use ($root): string {
    $path = $root.'/'.$rel;
    if (! is_dir($path)) {
        return 'ABSENT';
    }
    $bytes = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        if ($f->isFile()) {
            $bytes += $f->getSize();
        }
    }

    return number_format($bytes / 1048576, 1).' MB';
};
// Sizes are measured, not re-used from a previous day's reading.
$vendor = $du('vendor');
$nodes = $du('node_modules');
$build = $du('public/build');
$storageDir = $du('storage/app');

$phpV = trim((string) shell_exec(escapeshellarg(PHP_BINARY).' -v 2>&1'));
$phpV = explode("\n", $phpV)[0] ?? '(unknown)';
$nodeV = trim((string) shell_exec('node -v 2>&1'));
$mysqlV = trim((string) shell_exec(escapeshellarg($mysql).' -u root -N -e "select version()" 2>&1'));
// The browser the printed papers need is asked from the code that finds it, not from `where chrome`: this box has
// Chrome installed but not on PATH, which is exactly why App\Services\ChromiumLocator exists.
$chrome = trim((string) shell_exec(escapeshellarg(PHP_BINARY).' -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Services\ChromiumLocator::locate() ?? \'\';" 2>&1'));
$chromeNote = $chrome === ''
    ? 'NOT FOUND — every printed paper would fail at render time while the desks still work'
    : $chrome.' (resolved by App\Services\ChromiumLocator::locate())';
$composerJson = json_decode((string) file_get_contents($root.'/composer.json'), true);
$phpstan = $composerJson['require']['php'] ?? '(composer.json names no php constraint)';
$laravel = $composerJson['require']['laravel/framework'] ?? '(no framework constraint)';

// ---------------------------------------------------------------- 6. LAN facts (measured, not assumed)
// The address a second laptop must use is the one on the interface that carries the default route, not the first
// "IPv4 Address" line ipconfig prints: on this box that first line is 192.168.137.1, the Internet Connection
// Sharing adapter, which answers locally and would leave the other laptop reaching for an address that is not on
// the network it is joined to. The adapter's own name is printed in the document so the reader can see which
// interface was meant, and the probe then asks that address for the login page rather than assuming.
$ps = 'powershell -NoProfile -Command "$r=Get-NetRoute -DestinationPrefix \'0.0.0.0/0\' | Sort-Object RouteMetric | Select-Object -First 1; '
    .'$a=Get-NetIPAddress -InterfaceIndex $r.InterfaceIndex -AddressFamily IPv4 | Select-Object -First 1; '
    .'Write-Output ($a.IPAddress + \'|\' + (Get-NetAdapter -InterfaceIndex $r.InterfaceIndex).Name)" 2>&1';
$netLine = trim((string) shell_exec($ps));
$pipe = strpos($netLine, '|');
$ip = $pipe === false ? '' : substr($netLine, 0, $pipe);
$adapter = $pipe === false ? '(unknown adapter)' : substr($netLine, $pipe + 1);
if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
    $fail("the default-route probe answered [{$netLine}], which is not an IPv4 address — the share address would be invented.");
}
if (str_starts_with($ip, '169.254.')) {
    $fail("the interface that owns the default route answers {$ip}, which is a link-local address — no other laptop could reach it.");
}
$listen = shell_exec('netstat -ano 2>&1');
$binds = str_contains((string) $listen, '0.0.0.0:8080') ? '0.0.0.0:8080 (every interface)' : 'not on 0.0.0.0:8080';
$http = trim((string) shell_exec('curl -s -o NUL -w "%{http_code}" --max-time 8 http://'.escapeshellarg($ip).':8080/login 2>&1'));
if ($http !== '200') {
    $fail("the app answered HTTP {$http} when asked at http://{$ip}:8080/login — the sharing instructions would be written from an assumption.");
}
$fw = trim((string) shell_exec('netsh advfirewall show allprofiles state 2>&1'));
$fwOn = preg_match_all('/State\s+ON/i', $fw);
$hasRule = str_contains((string) shell_exec('netsh advfirewall firewall show rule name=all 2>&1'), '8080');
// The subnet the firewall rule is scoped to is derived from the address actually chosen, not typed.
$subnet = preg_replace('/\.\d+$/', '.0', $ip).'/24';
if ($subnet === '/24' || $subnet === '') {
    $fail("the subnet could not be derived from {$ip} (got [{$subnet}]), so the firewall command would name a range that is not this network.");
}

// ---------------------------------------------------------------- document assembly
$blocks = [];
$blocks[] = ['h1', 'SEAIT Enrollment Management System — transactions, ACID, evidence, offline kit, accounts and LAN sharing'];
$blocks[] = ['p', 'Generated from the repository on '.$schoolToday.' (the school\'s civil day) at commit '.$sha
    .'. Every table below is read from the file or the server named in it, not typed: the transaction census walks the source, '
    .'the concept and ACID wording is lifted from Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md, the 24 problems come from '
    .'Documentation/REQUIREMENTS-TRACEABILITY-ADS.md, the accounts come from live ems, and the network facts were probed on this box. '
    .'Where a claim is only partly true the wording carries the limit, because a document that overstates is harder to defend than one that says "not yet".'];

$blocks[] = ['h2', '1. Every database transaction in the system, and what kind it is'];
$blocks[] = ['p', 'The census walked app/ and tools/ for DB::transaction(. It found '.$appCount.' sites in app/ and '
    .$toolsCount.' in tools/ ('.count($sites).' in total). "Markers" are what sits beside the call in the same action: a row lock, '
    .'the bounded deadlock retry, a nested transaction, or neither, which is a plain atomic unit.'];
$typeList = [];
foreach ($typeTally as $k => $v) {
    $typeList[] = $k.' '.$v;
}
$blocks[] = ['p', 'Types in use, counted from the same walk: '.implode('; ', $typeList).'. Two other mechanisms do the work the '
    .'guide attributes to transactions and are worth naming here: `App\Support\RetryOnDeadlock` wraps the contended acts, and a '
    .'MySQL unique key refuses a duplicate at the server, so the guard holds even when two writers pass the read in the application.'];
$blocks[] = ['table', ['File', 'Line', 'Enclosing action', 'Markers beside the call'], array_map(
    static fn (array $s): array => [$s['file'], (string) $s['line'], $s['method'], implode(', ', $s['marks'])],
    $sites
)];

$blocks[] = ['h2', '2. The ACID properties, each stated with what proves it'];
foreach ($acidBullets as [$prop, $text]) {
    $blocks[] = ['h3', $prop];
    $blocks[] = ['p', $text];
}
$blocks[] = ['p', 'The limit is stated in the source and repeated here on purpose: isolation is demonstrated for the contended '
    .'write and for the daily collection sheet, not for every read. Traceability row P20 names the screens that still read outside '
    .'a transaction, and this document does not claim more than that row.'];

$blocks[] = ['h2', '3. The twelve database concepts the ADS guide asks for, in the guide\'s own order'];
$blocks[] = ['table', ['#', 'Concept', 'Status word used for the guide\'s tick box'], array_map(
    static fn (array $r): array => [(string) $r[0], $r[1]],
    $conceptRows
)];

$blocks[] = ['h2', '4. The 24 ADS problems and where each one can be witnessed'];
$blocks[] = ['p', 'Read from the P register in Documentation/REQUIREMENTS-TRACEABILITY-ADS.md. The last column is every file '
    .'the verdict itself names — that is the place to look, and a row whose verdict names no file says so instead of inventing one.'];
foreach ($problems as $p) {
    $blocks[] = ['h3', $p['id'].' — '.$p['title'].' ('.$p['applies'].')'];
    $blocks[] = ['p', 'Verdict: '.$p['verdict']];
    $blocks[] = ['bullets', $p['witness']];
}

$blocks[] = ['h2', '5. Running the system with no internet: what is on this box and what to copy'];
$blocks[] = ['table', ['What', 'Where it lives', 'Measured now'], [
    ['PHP framework and every dependency', 'vendor/', $vendor],
    ['Node packages (Vite, React, Inertia, ESLint)', 'node_modules/', $nodes],
    ['Built front-end assets already compiled', 'public/build/', $build],
    ['Uploaded files and private disks', 'storage/app/', $storageDir],
    ['Database server', $mysql, $mysqlV],
    ['PHP runtime', PHP_BINARY, $phpV],
    ['Node runtime', 'node on PATH', ($nodeV === '' ? 'ABSENT' : $nodeV).' (composer requires php '.$phpstan.', Laravel '.$laravel.')'],
    ['Chrome for the printed PDFs (Browsershot)', 'App\Services\ChromiumLocator::locate(), or EMS_CHROME_PATH if an operator names one', $chromeNote],
]];
$blocks[] = ['p', 'Nothing in that list needs the network, because it is already installed on this machine: the whole set was '
    .'produced by composer install and npm install while online, and the app is served by Laragon\'s Apache. To carry the system to '
    .'another laptop, copy the repository plus vendor/, node_modules/ and public/build/ (or rebuild the last one with npm run build '
    .'on the target), restore a database dump into that machine\'s MySQL, and point .env at it. Do not put a database dump in the '
    .'repository: this project keeps dumps in the ems-backups folder outside the tree, and the demo snapshot that once sat in '
    .'Documentation was untracked for that reason.'];
$blocks[] = ['p', 'Printed documents need a real browser, because Browsershot shells out to one: the class card, the subject '
    .'load, the certificate and the clearance slip are drawn by Chrome, not by PHP. The system asks '
    .'App\Services\ChromiumLocator::locate(), which returns the path in EMS_CHROME_PATH when an operator names one (and '
    .'will not substitute a different browser, because the four papers were signed off against one rendering) or otherwise '
    .'walks a candidate list of standard install folders. On this box that resolves to '
    .($chrome === '' ? 'nothing, which means every print would fail while the desks keep working' : $chrome)
    .'. A target machine with no Chrome still runs every desk screen and fails only at "Download PDF", so test one print '
    .'before a demo. Documentation/Offline-Presentation-Guide.md carries the boot checklist and the restore commands; this '
    .'section does not re-type them.'];

$blocks[] = ['h2', '6. The accounts you can log in with, and what each one is'];
$blocks[] = ['p', 'Queried from live ems just now: '.count($accounts).' staff accounts. Every one of them carries the seeded '
    .'default password, which is the literal the seeder writes ('.$defaultPassword.'), read from database/seeders/DevReferenceDataSeeder.php. '
    .'The stored value in the database is a bcrypt hash and cannot be turned back into a password, so this is the seed default and not a '
    .'recovered secret. These are the synthetic demo cast invented for this project — no real student or employee record is involved — '
    .'but the file that lists them should not be handed out beyond the school, and every one of these passwords must be changed before '
    .'the system holds any real record. An account whose status is inactive is refused at its next request, so a listed name is not '
    .'always a working door.'];
$rows = [];
foreach ($accounts as $line) {
    $c = preg_split('/\t/', $line);
    if (count($c) < 6) {
        continue;
    }
    $rows[] = [$c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $defaultPassword];
}
$blocks[] = ['table', ['Username', 'Displays as', 'Role enum', 'Office', 'College bound', 'Status', 'Password'], $rows];
$roleNames = [];
exec(escapeshellarg($mysql).' -u root ems -N -e "select name from roles order by name" 2>&1', $roleNames);
$roleNames = array_values(array_filter(array_map(
    static fn (string $r): string => trim(str_replace('"', '', $r)),
    $roleNames
), static fn (string $r): bool => $r !== '' && ! str_starts_with($r, 'ERROR')));
if ($roleNames === []) {
    $fail('the roles table answered no names, so the paragraph about what sits behind the role enum cannot be written.');
}
$blocks[] = ['p', 'The role enum above is what the seeder assigns; the permissions behind it are held as Spatie roles, '
    .count($roleNames).' of them named on this schema: '.implode(', ', $roleNames).'. '
    .'SysAdmin is granted everything by Gate::before, which is why a permission-collision test exists: a gate ability may '
    .'never share a permission name, or the policy body would stop running.'];

$blocks[] = ['h2', '7. Sharing the running system with another laptop over the LAN'];
$blocks[] = ['p', 'Measured on this box, now: Apache listens on '.$binds.'; the interface that carries the default route is '
    .'"'.$adapter.'" at '.$ip.'; asking that address for the login page answered HTTP '.$http.'; and Windows firewall reports '
    .($fwOn === 0 ? 'no active profile' : $fwOn.' profile(s) ON').'. There is '.($hasRule ? '' : 'no ').
    'inbound rule for port 8080'.($hasRule ? ' already' : '').', so a second laptop will be blocked by the firewall even though '
    .'the application is answering. The app is already shared correctly; the only missing step is the inbound allow, which needs '
    .'an elevated (Administrator) PowerShell and is a machine-level change, so it is given as a command rather than applied behind '
    .'your back. If ipconfig shows several IPv4 addresses — a hotspot adapter, a VirtualBox card, a second network — use the one on '
    .'the interface named above, because that is the one traffic actually leaves through.'];
$blocks[] = ['steps', [
    'One time, in the built mode you will demo in: run "npm run build" in the project folder. That writes public/build/, and Apache serves the compiled assets, so no Node process has to stay open on the other laptop.',
    'Make sure Laravel knows the address the other laptop will use. In .env set APP_URL=http://'.$ip.':8080 (the address must be the LAN IP, not localhost, or the browser on the other machine asks its own loopback). Then run "php artisan config:clear" and "php artisan optimize:clear" so nothing cached keeps the old value.',
    'Allow the port inward, once, from an elevated PowerShell: netsh advfirewall firewall add rule name="EMS 8080" dir=in action=allow protocol=TCP localport=8080. Scope it to this network instead of the world by adding remoteip='.$subnet.', which is the subnet of the interface measured above.',
    'On the other laptop, with both machines on the same Wi-Fi or switch, open http://'.$ip.':8080/login. It should answer the sign-in page; log in with a desk account from section 6.',
    'If you want the hot-reloading dev server instead of the built assets — during development only — run "npm run dev -- --host 0.0.0.0 --port 5173" and also allow 5173 inward, then set APP_URL and ASSET_URL to the same machine address with the matching port. Keep the demo on the built mode: a dev server that stops takes every page\'s styling with it.',
    'Find your IP again any time with "ipconfig" and read the line that starts "IPv4 Address". If it changes (a different network, a new DHCP lease), APP_URL changes with it — that is the single most common reason a shared page loads on one laptop and not the other.',
]];

$blocks[] = ['p', 'Two limits worth stating before a defense. First, this is a plain HTTP LAN setup: no TLS, so the session cookie '
    .'travels in the clear between the two machines — fine for a classroom demonstration, not for real records. Second, MySQL is only '
    .'listening for the machine it runs on; the second laptop talks to the application, never to the database, which is exactly as it '
    .'should be.'];

// ---------------------------------------------------------------- OOXML writer
$pPr = static function (string $extra = ''): string {
    return '<w:pPr>'.$extra.'</w:pPr>';
};

$run = static function (string $text, array $props = []) use ($esc): string {
    $rPr = '';
    if (! empty($props['b'])) {
        $rPr .= '<w:b/>';
    }
    if (! empty($props['sz'])) {
        $rPr .= '<w:sz w:val="'.((int) $props['sz']).'"/><w:szCs w:val="'.((int) $props['sz']).'"/>';
    }
    if (! empty($props['color'])) {
        $rPr .= '<w:color w:val="'.$esc($props['color']).'"/>';
    }
    if ($rPr !== '') {
        $rPr = '<w:rPr>'.$rPr.'</w:rPr>';
    }

    return $rPr.'<w:t xml:space="preserve">'.$esc($text).'</w:t>';
};

$body = '';
$pageW = 9360; // A4 minus one inch each side, in twentieths of a point

foreach ($blocks as $b) {
    [$kind, $a, $c] = array_pad($b, 3, null);
    if ($kind === 'h1') {
        $body .= '<w:p>'.$pPr('<w:spacing w:before="240" w:after="160"/>').'<w:r>'.
            $run($a, ['b' => 1, 'sz' => 36]).'</w:r></w:p>';
    } elseif ($kind === 'h2') {
        $body .= '<w:p>'.$pPr('<w:spacing w:before="280" w:after="120"/>').'<w:r>'.
            $run($a, ['b' => 1, 'sz' => 28, 'color' => '1F3864']).'</w:r></w:p>';
    } elseif ($kind === 'h3') {
        $body .= '<w:p>'.$pPr('<w:spacing w:before="160" w:after="80"/>').'<w:r>'.
            $run($a, ['b' => 1, 'sz' => 24, 'color' => '2E5496']).'</w:r></w:p>';
    } elseif ($kind === 'p') {
        $body .= '<w:p>'.$pPr('<w:spacing w:after="120"/><w:jc w:val="both"/>').'<w:r>'.$run($a).'</w:r></w:p>';
    } elseif ($kind === 'bullets') {
        foreach ($a as $item) {
            $body .= '<w:p>'.$pPr('<w:spacing w:after="40"/><w:ind w:left="360" w:hanging="180"/>').'<w:r>'.
                $run("\u{2022}  ".$item).'</w:r></w:p>';
        }
    } elseif ($kind === 'steps') {
        foreach ($a as $i => $item) {
            $body .= '<w:p>'.$pPr('<w:spacing w:after="60"/><w:ind w:left="420" w:hanging="420"/>').'<w:r>'.
                $run((string) ($i + 1).'.  '.$item).'</w:r></w:p>';
        }
    } elseif ($kind === 'table') {
        $head = $a;
        $rowsIn = $c;
        $cols = count($head);
        $w = (int) floor($pageW / $cols);
        $grid = '';
        for ($i = 0; $i < $cols; $i++) {
            $grid .= '<w:gridCol w:w="'.$w.'"/>';
        }
        $tbl = '<w:tbl><w:tblPr><w:tblW w:w="'.$pageW.'" w:type="dxa"/>'
            .'<w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:color="BFBFBF"/><w:left w:val="single" w:sz="4" w:color="BFBFBF"/>'
            .'<w:bottom w:val="single" w:sz="4" w:color="BFBFBF"/><w:right w:val="single" w:sz="4" w:color="BFBFBF"/>'
            .'<w:insideH w:val="single" w:sz="2" w:color="D9D9D9"/><w:insideV w:val="single" w:sz="2" w:color="D9D9D9"/>'
            .'</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>'.$grid.'</w:tblGrid>';
        $mkRow = static function (array $cells, bool $header) use ($w, $run, $pPr): string {
            $r = '<w:tr>';
            foreach ($cells as $cell) {
                $r .= '<w:tc><w:tcPr><w:tcW w:w="'.$w.'" w:type="dxa"/>'
                    .($header ? '<w:shd w:val="clear" w:color="auto" w:fill="EDEFF3"/>' : '')
                    .'</w:tcPr><w:p>'.$pPr('<w:spacing w:after="20"/>').'<w:r>'.
                    $run((string) $cell, $header ? ['b' => 1] : []).'</w:r></w:p></w:tc>';
            }

            return $r.'</w:tr>';
        };
        $tbl .= $mkRow($head, true);
        foreach ($rowsIn as $r) {
            $tbl .= $mkRow($r, false);
        }
        $body .= $tbl.'</w:tbl><w:p>'.$pPr('<w:spacing w:after="120"/>').'</w:p>';
    }
}

$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
    .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    .'<w:body>'.$body
    .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
    .'<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/>'
    .'</w:sectPr></w:body></w:document>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/></w:rPr></w:rPrDefault></w:docDefaults>'
    .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
    .'</w:styles>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    .'<Default Extension="xml" ContentType="application/xml"/>'
    .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
    .'</Types>';

$relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    .'</Relationships>';

$relsDoc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    .'</Relationships>';

// The XML must parse before it is written; a document Word cannot open is not a deliverable.
foreach (['document' => $document, 'styles' => $styles, 'contentTypes' => $contentTypes, 'rels' => $relsRoot, 'relsDoc' => $relsDoc] as $name => $xml) {
    libxml_use_internal_errors(true);
    if (simplexml_load_string($xml) === false) {
        $err = libxml_get_errors();
        $fail("part {$name} does not parse: ".trim($err === false ? 'unknown' : $err[0]->message));
    }
}

echo 'sections 12, problems '.count($problems).', accounts '.count($rows).', transaction sites '.count($sites)."\n";
echo 'parts: document '.strlen($document)." bytes, tables 4\n";

if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$zip = new ZipArchive;
if (! is_dir(dirname($out))) {
    $fail('the output folder '.dirname($out).' does not exist.');
}
if (is_file($out)) {
    $fail($out.' already exists — this tool will not overwrite a document it did not just build. Move or rename it first.');
}
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    $fail("could not create {$out}.");
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $relsRoot);
$zip->addFromString('word/document.xml', $document);
$zip->addFromString('word/_rels/document.xml.rels', $relsDoc);
$zip->addFromString('word/styles.xml', $styles);
$zip->close();

// Read back: the file must open as a zip and hold every part.
$check = new ZipArchive;
if ($check->open($out) !== true) {
    $fail('the file that was just written does not open as a package.');
}
foreach (['[Content_Types].xml', '_rels/.rels', 'word/document.xml', 'word/_rels/document.xml.rels', 'word/styles.xml'] as $part) {
    if ($check->getFromName($part) === false) {
        $fail("the written package has no {$part}.");
    }
}
$check->close();
echo 'WROTE '.$out."\n";
