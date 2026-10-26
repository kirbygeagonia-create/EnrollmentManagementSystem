<?php

namespace App\Console\Commands;

use App\Services\ChromiumLocator;
use App\Support\SchoolTime;
use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * One command that answers "is this server actually ready", which DEPLOYMENT.md otherwise
 * asks a human to walk through by reading it.
 *
 * Every check here exists because that document records a real incident or a real trap: the
 * private disk an upload was sitting on the public one, the browser binary a PDF 503s without,
 * the migrations a fresh server does not have, the seed that leaves every desk locked out, the
 * folder Laravel cannot write into, the application key a stranger can sign an upload with if it
 * was never generated. It writes nothing and changes nothing — a deployment check
 * that mutates the deployment is not a check.
 */
class Preflight extends Command
{
    /**
     * Folders the application must find in place. `storage/app/prints` is deliberately not
     * one of them: it is gitignored, so a fresh clone has never had it, and PrintService
     * creates it on the first download — see the separate line that reports that fact rather
     * than failing a server for a folder it will make itself.
     *
     * @var array<string, string>
     */
    private const WRITABLE = [
        'uploaded papers and face photos' => 'storage/app/private',
        'the application log' => 'storage/logs',
        'cached configuration' => 'bootstrap/cache',
    ];

    protected $signature = 'ems:preflight';

    protected $description = 'Check the server against the requirements DEPLOYMENT.md records; writes nothing';

    public function handle(): int
    {
        $failures = 0;
        $warnings = 0;

        $this->line('');
        $this->line('  <bg=blue;fg=white> EMS PREFLIGHT </>  reports only — this command writes nothing');
        $this->line('');

        foreach ($this->checks() as [$label, $verdict, $detail]) {
            if ($verdict === 'FAIL') {
                $failures++;
            } elseif ($verdict === 'WARN') {
                $warnings++;
            }

            $this->line(sprintf('  <bg=%s;fg=white>%s</>  %-46s %s', $verdict === 'FAIL' ? 'red' : ($verdict === 'WARN' ? 'yellow' : 'green'), $verdict, $label, $detail));
        }

        $this->newLine();

        if ($failures === 0 && $warnings === 0) {
            $this->info('Nothing in the way of serving desks from this server.');

            return self::SUCCESS;
        }

        if ($failures > 0) {
            $this->error($failures.' check(s) will break a desk. DEPLOYMENT.md names the fix for each.');
        }

        if ($warnings > 0) {
            $this->warn($warnings.' check(s) worth a look before an office session.');
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function checks(): array
    {
        return [
            $this->applicationKeyIsReal(),
            $this->environmentPair(),
            $this->databaseReachable(),
            $this->clocksAgree(),
            $this->hostZoneMatchesTheSchools(),
            $this->migrationsApplied(),
            $this->seededEnough(),
            $this->uploadDiskIsPrivate(),
            $this->servedFolderStaysPrivate(),
            $this->foldersWritable(),
            $this->printFolderArrives(),
            $this->browserForPdfs(),
            $this->queueConnection(),
            $this->forwardedSchemeIsDecided(),
        ];
    }

    /**
     * Whether this install can be told which scheme the desk arrived over.
     *
     * `SecurityHeaders` sends `Strict-Transport-Security` only when `$request->isSecure()`, and a TLS-terminating
     * proxy makes that question answerable only if the proxy's address is trusted — otherwise
     * `Illuminate\Http\Middleware\TrustProxies` discards `X-Forwarded-Proto` and the header never appears. This is
     * the install-time half of §28.2's G-13, which the registers could only record as "not yet deployed": a
     * preflight cannot see a proxy that has not been built yet, but it can refuse to let an `https` APP_URL sit
     * beside no trusted address, and it can refuse to call a wildcard "named".
     *
     * The wildcard is the branch that has to say something, because `trustProxies(at: '*')` is the one-line fix
     * that makes the header appear and lets any client forge its own scheme and address — and
     * `LoginRequest::throttleKey()` and `auditlogs.ipAddress` both read `$request->ip()`.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function forwardedSchemeIsDecided(): array
    {
        $configured = config('trustedproxy.proxies');
        $proxies = is_array($configured) ? array_values(array_filter($configured, static fn ($p): bool => (string) $p !== '')) : [];
        $url = (string) config('app.url');
        $expectsHttps = str_starts_with($url, 'https://');

        if (in_array('*', $proxies, true) || in_array('**', $proxies, true)) {
            return ['Forwarded scheme', 'WARN', 'every address is trusted, so any client can forge its scheme and its IP — '
                .'name the proxy instead: throttling and auditlogs.ipAddress both read $request->ip()'];
        }

        if ($proxies !== []) {
            return ['Forwarded scheme', 'OK', count($proxies).' proxy address(es) trusted, so X-Forwarded-Proto is honoured '
                .'and HSTS can reach a desk that arrived through a TLS terminator'];
        }

        if ($expectsHttps) {
            return ['Forwarded scheme', 'WARN', $url.' is https but no proxy is trusted, so behind a TLS terminator the '
                .'header never appears — name its address in bootstrap/app.php (never `*`); DEPLOYMENT.md §2, G-13'];
        }

        return ['Forwarded scheme', 'OK', 'nothing is trusted, which is right while this host speaks HTTP itself; '
            .'if a TLS terminator is ever put in front, name its address (DEPLOYMENT.md §2, G-13)'];
    }

    /**
     * The one secret standing between a stranger and an unauthenticated write into the upload
     * folder. `config/filesystems.php` gives the `local` disk `'serve' => true`, and Laravel
     * registers `GET|HEAD /storage/{path}` and `PUT /storage/{path}` for it with **no auth
     * middleware at all** — measured with `storage/app/scratch/audit-24b-storage-routes.php`,
     * driving the route through the HTTP kernel: a genuine relative signature returns 204, and
     * a signature computed with the *empty* secret returns 204 on a server whose APP_KEY was
     * never generated, which is the state `.env.example` leaves it in. The same forgery against
     * this install returns 403, and so does an unsigned request: the key is the gate.
     *
     * A blank key is not a hypothetical. It is one skipped line in DEPLOYMENT.md §3, and the
     * symptom it produces elsewhere — every login throwing on cookie encryption — reads like a
     * broken session driver, while the route that needs no encryption keeps answering.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function applicationKeyIsReal(): array
    {
        $key = (string) config('app.key');

        if ($key === '') {
            return ['Application key', 'FAIL', 'APP_KEY is blank, so anyone can sign their own /storage upload — run: php artisan key:generate, then php artisan config:clear if §3 cached the config'];
        }

        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        if (! is_string($raw) || strlen($raw) !== 32) {
            return ['Application key', 'FAIL', 'APP_KEY is not a 32-byte key ('.strlen($raw).'), and AES-256 will refuse it at the first login — run: php artisan key:generate'];
        }

        return ['Application key', 'OK', '32 bytes, so /storage/{path} answers only to a signature made with it'];
    }

    /**
     * The other door into the same folder, and the one an operator opens by accident.
     * `ServeFile::hasValidSignature()` waves the signature check through when the served disk
     * declares `'visibility' => 'public'` — measured with `audit-24c-servegate.php`: the unsigned
     * `GET /storage/{path}` that this configuration refuses with 403 walks straight past the gate
     * against that single line, and only the missing file stops it with 404. Someone adding it to
     * fix a broken image URL would be publishing every applicant paper and face photo, because the
     * served `local` disk is rooted at `storage/app/private` — the same root the `documents` disk
     * writes to. Unlike a leaked key, this is a change made on purpose, which is why it gets its
     * own row instead of a paragraph.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function servedFolderStaysPrivate(): array
    {
        $names = [];
        $sharesUploadRoot = false;

        foreach ((array) config('filesystems.disks') as $name => $disk) {
            if (! is_array($disk) || ($disk['driver'] ?? null) !== 'local' || ! ($disk['serve'] ?? false)) {
                continue;
            }

            $names[] = (string) $name;

            if (($disk['visibility'] ?? 'private') === 'public') {
                return ['Served folder', 'FAIL', 'disk "'.$name.'" is served at /storage/{path} and declared public, which switches the signature check off — papers and face photos become reachable by path'];
            }

            if (($disk['root'] ?? '') === storage_path('app/private')) {
                $sharesUploadRoot = true;
            }
        }

        if ($names === []) {
            return ['Served folder', 'OK', 'no local disk asks Laravel to serve /storage, so the uploads have only the routes that authorize them'];
        }

        return ['Served folder', 'OK', 'disk(s) '.implode(', ', $names).' served private: every read needs a signature'
            .($sharesUploadRoot ? ' — and it is rooted where the uploads live, so that signature is the door' : '')];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function environmentPair(): array
    {
        $env = config('app.env');
        $debug = (bool) config('app.debug');

        if ($env === 'production' && $debug) {
            // AppServiceProvider throws before this can run, so reaching it means the guard
            // was bypassed; reported anyway because a check that cannot fire is not a check.
            return ['APP_ENV / APP_DEBUG', 'FAIL', 'production with debug on — stack traces are being shown to desks'];
        }

        return ['APP_ENV / APP_DEBUG', $env === 'production' ? 'OK' : 'WARN', $env.($debug ? ' with debug on (fine for local)' : ' with debug off')];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function databaseReachable(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();

            return ['Database connection', 'OK', $connection->getDriverName().' / '.(string) config('database.connections.'.$connection->getName().'.database')];
        } catch (\Throwable $e) {
            return ['Database connection', 'FAIL', 'no connection: '.str($e->getMessage())->limit(90)];
        }
    }

    /**
     * The two processes that stamp a record must write the same string for the same instant.
     *
     * `auditlogs.createdAt` and `enrollmentstatushistory.changedAt` are plain DATETIME columns with a MySQL
     * `CURRENT_TIMESTAMP` default, and every other timestamp here is written by PHP. A DATETIME carries no
     * zone, so what a desk reads back is the string the writer happened to produce. Measured on the
     * development box on 2026-10-12 the two epochs agreed to the second — the clocks are fine — while
     * `SELECT NOW()` printed 2026-10-12 18:27 and PHP printed 2026-10-13 05:27, because the database session
     * runs in a zone that is not the app's. A row stamped by the database would then read eleven hours from
     * its own siblings, and no check anywhere said so. Compared as stored strings for exactly that reason.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function clocksAgree(): array
    {
        $connection = DB::connection();

        // Driver-guarded the way every raw query here must be: the SQLite suite has no NOW().
        $sql = $connection->getDriverName() === 'sqlite' ? "SELECT datetime('now') AS stamp" : 'SELECT NOW() AS stamp';

        try {
            $dbStamp = (string) $connection->selectOne($sql)->stamp;
        } catch (\Throwable $e) {
            return ['Clocks agree', 'WARN', 'the database clock could not be read: '.str($e->getMessage())->limit(70)];
        }

        $phpStamp = Carbon::now()->toDateTimeString();

        $gap = Carbon::parse($dbStamp)->diffInSeconds(Carbon::parse($phpStamp), false);

        if (abs($gap) <= 300) {
            // Agreeing is not the same as being configured. `config/database.php` now forces the session
            // zone on every connection, so this row would pass on a server whose own time zone is wrong —
            // the exact situation that would otherwise never be reported. Read the server's answer and say
            // which of the two is holding the line.
            $from = '';

            if ($connection->getDriverName() !== 'sqlite') {
                try {
                    $zones = (array) $connection->selectOne('SELECT @@session.time_zone AS s, @@global.time_zone AS g');
                    $from = self::zoneNote((string) ($zones['s'] ?? ''), (string) ($zones['g'] ?? ''));
                } catch (\Throwable $e) {
                    $from = ' (the server zone could not be read: '.str($e->getMessage())->limit(40).')';
                }
            }

            return ['Clocks agree', 'OK', 'PHP and '.($connection->getDriverName()).' write the same stamp: '.$phpStamp.$from];
        }

        return [
            'Clocks agree',
            'FAIL',
            sprintf(
                'PHP writes %s and the database writes %s for the same moment — %.1f h apart in the column. Set the database session time zone to the app\'s (config/app.php timezone is %s), or the two writers will disagree on every DATETIME.',
                $phpStamp,
                $dbStamp,
                abs($gap) / 3600,
                (string) config('app.timezone')
            ),
        ];
    }

    /**
     * Whether the machine's wall clock is the clock the school reads.
     *
     * Inside a Laravel process the operating system's zone is hidden on purpose: the framework calls
     * `date_default_timezone_set()` with `app.timezone`, so `date_default_timezone_get()` answers UTC on every
     * server and a check built on it passes everywhere — a check that cannot fail. Two sources were measured
     * before this one was kept. `php_uname('a')` on this box is `Windows NT DESKTOP-IIUKRIB 10.0 build 19045
     * (Windows 10) AMD64` and on a runner the same shape: no zone in it at all. php.ini's `date.timezone`
     * answers `UTC` here even outside the framework — that is what *PHP* was told, not what the machine keeps
     * (`localtime()` carries neither `tm_zone` nor `tm_gmtoff` on Windows). MySQL is the binary that still
     * quotes the operating system: `@@system_time_zone` is `UTC-11` on this box, which is the Windows zone
     * name, and `UTC` in a container.
     *
     * Nothing stored depends on the answer — instants are UTC, every MySQL session is forced to `+00:00`
     * since lane 31, and the civil day a desk stands in comes from `config/school.php` through
     * `App\Support\SchoolTime` alone since lane 51. What the host's hour still decides is everything outside
     * the application: the stamp on a `mysqldump` filename, the hour cron wakes the worker, and what a script
     * that never boots the framework prints. That gap is why every lane since 50 has had to prove a calendar
     * day from both ends rather than trust one reading of "today".
     *
     * The verdict is a WARN, never a FAIL: a preflight cannot move a server's clock, and a machine keeping UTC
     * is a defensible choice when its stored time is UTC. When the source answers no offset at all — SQLite
     * has no `@@system_time_zone`, and a Windows server set to "Singapore Standard Time" answers a name rather
     * than an hour — the row says it could not look, rather than guessing that the clocks agree.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function hostZoneMatchesTheSchools(): array
    {
        return self::hostZoneRow(self::hostUtcOffset(), SchoolTime::zone());
    }

    /**
     * The comparison, kept apart from the read so a zone this machine does not have can still be tested.
     *
     * @param  array{0: string, 1: string}|null  $host  the machine's UTC offset and the source it was read from
     * @return array{0: string, 1: string, 2: string}
     */
    public static function hostZoneRow(?array $host, string $school): array
    {
        $schoolOffset = self::offsetOf($school);

        if ($host === null || $schoolOffset === null) {
            return ['Host time zone', 'WARN', $schoolOffset === null
                ? $school.' is not a zone PHP can resolve, so nothing here can be compared — check the clock by hand'
                : 'this machine\'s zone could not be read — compare its clock with '.$school.' by hand (DEPLOYMENT.md §2)'];
        }

        [$hostOffset, $from] = $host;

        if ($hostOffset === $schoolOffset) {
            return ['Host time zone', 'OK', 'the machine is at '.$hostOffset.' ('.$from.'), which is '.$school.'\'s offset; stored instants stay UTC either way'];
        }

        return [
            'Host time zone',
            'WARN',
            sprintf(
                'this machine is at %s (%s) while the school keeps %s at %s — %s h apart on the wall clock. Nothing stored moves, because the application forces its session to %s and reads every civil day through SchoolTime, but mysqldump, cron and any script that never boots the framework name the other hour (set the OS zone to the school\'s per DEPLOYMENT.md §2, or keep reading days through App\Support\SchoolTime)',
                $hostOffset,
                $from,
                $school,
                $schoolOffset,
                self::offsetGapHours($hostOffset, $schoolOffset),
                (string) config('app.timezone')
            ),
        ];
    }

    /**
     * The machine's UTC offset, read from the one binary that quotes the operating system, or null.
     *
     * Public and static so a test can ask the read itself, not only the sentence it produces: the whole point
     * of this row is that the number comes from outside the framework, and the suite runs on two drivers —
     * one of which cannot answer at all.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function hostUtcOffset(): ?array
    {
        try {
            $row = (array) DB::selectOne('SELECT @@system_time_zone AS zone');
        } catch (\Throwable) {
            return null;
        }

        $offset = self::normaliseOffset((string) ($row['zone'] ?? ''));

        return $offset === null ? null : [$offset, 'the database\'s @@system_time_zone'];
    }

    /**
     * How far apart two `±HH:MM` offsets are, said in hours with the decimals a half-hour zone needs.
     *
     * Computed rather than written into the sentence, because `SCHOOL_TIMEZONE` is a declaration an office can
     * change and a hard-coded "19 hours" would outlive it and be quoted as a measurement.
     */
    private static function offsetGapHours(string $a, string $b): string
    {
        $toSeconds = fn (string $o): int => (((int) substr($o, 1, 2)) * 3600 + ((int) substr($o, 4, 2)) * 60) * (substr($o, 0, 1) === '-' ? -1 : 1);
        $hours = abs($toSeconds($a) - $toSeconds($b)) / 3600;

        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');
    }

    /**
     * `UTC-11` and `-1100` and `+00:00` all mean the same hour, and each spelling has been seen from a real
     * server. Anything that is not an offset answers null rather than a guess — `CST` on a Linux host and
     * `Singapore Standard Time` on a Windows one are both names, not hours, and a wrong "the clocks agree" is
     * worse than a row that says it could not look.
     */
    private static function normaliseOffset(string $raw): ?string
    {
        $value = strtoupper(trim($raw));

        if ($value === '' || $value === 'SYSTEM' || $value === 'UNKNOWN') {
            return null;
        }

        if (str_starts_with($value, 'UTC')) {
            $value = substr($value, 3);

            if ($value === '') {
                return '+00:00';
            }
        }

        if (preg_match('/^([+-])(\d{1,2})(?::?(\d{2}))?$/', $value, $m) === 1) {
            return sprintf('%s%02d:%02d', $m[1], (int) $m[2], (int) ($m[3] ?? 0));
        }

        return null;
    }

    /**
     * The UTC offset a named zone is holding right now, in the `+08:00` spelling the comparisons above use.
     */
    private static function offsetOf(string $zone): ?string
    {
        try {
            $seconds = (new DateTimeZone($zone))->getOffset(new DateTimeImmutable('now'));
        } catch (\Throwable) {
            return null;
        }

        $abs = abs($seconds);

        return sprintf('%s%02d:%02d', $seconds < 0 ? '-' : '+', intdiv($abs, 3600), intdiv($abs % 3600, 60));
    }

    /**
     * Say what the agreement is resting on, because agreeing is not the same as being configured.
     *
     * `config/database.php` forces the session zone on every MySQL connection the application opens, so the
     * "Clocks agree" row would pass on a server whose own zone is still the operating system's. That is the
     * situation this box was in until 2026-10-14, and it is the one the school's server will arrive with:
     * the app reads `now()` in UTC, and a `mysqldump`, a cron job, a phpMyAdmin window or the next
     * developer's `tinker` connects with no such forcing and stamps a different hour into the same
     * zone-less DATETIME column. So when the session and the server default disagree, say so on the line
     * that is otherwise green — a check that can only be red or silent is a check that gets ignored.
     *
     * Compared from what the server reports rather than from `env()`, because under `config:cache` an
     * `env()` call in a command returns null and the note would describe a setting that is not in force.
     *
     * @return string the parenthetical to append, or '' when the two already agree
     */
    public static function zoneNote(string $session, string $global): string
    {
        if ($session === '' || $global === '' || $session === $global) {
            return '';
        }

        return " (this connection's session is {$session} while the server defaults to {$global}"
            .' — anything that connects without the application (mysqldump, a cron job, phpMyAdmin, tinker)'
            .' stamps a different hour; set default-time-zone per DEPLOYMENT.md §2)';
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function migrationsApplied(): array
    {
        $files = collect(File::glob(database_path('migrations/*.php')));

        try {
            $done = DB::table('migrations')->pluck('migration');
        } catch (\Throwable $e) {
            return ['Migrations', 'FAIL', 'the migrations table cannot be read — run: php artisan migrate'];
        }

        $pending = $files->map(fn (string $path): string => basename($path, '.php'))
            ->reject(fn (string $name): bool => $done->contains($name));

        if ($pending->isEmpty()) {
            return ['Migrations', 'OK', $files->count().' files, none pending'];
        }

        return ['Migrations', 'FAIL', $pending->count().' not applied (e.g '.$pending->first().') — run: php artisan migrate'];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function seededEnough(): array
    {
        try {
            $permissions = DB::table('permissions')->count();
            $roles = DB::table('roles')->count();
        } catch (\Throwable $e) {
            return ['Roles and permissions', 'FAIL', 'the RBAC tables cannot be read — run: php artisan db:seed --class=RbacSeeder'];
        }

        // No magic expected count: rights are added as desks are built, so pinning a number
        // here would fail the next legitimate feature. Zero is the only state that is wrong.
        if ($permissions === 0 || $roles === 0) {
            return ['Roles and permissions', 'FAIL', "roles={$roles} permissions={$permissions} — every desk is locked out until: php artisan db:seed --class=RbacSeeder"];
        }

        return ['Roles and permissions', 'OK', "{$roles} roles, {$permissions} permissions"];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function uploadDiskIsPrivate(): array
    {
        $disk = config('filesystems.disks.documents');

        if (! is_array($disk)) {
            return ['Upload disk', 'FAIL', 'config/filesystems.php declares no "documents" disk — uploaded papers have nowhere private to go'];
        }

        $served = isset($disk['url']) || ($disk['driver'] ?? null) === 's3';

        if ($served) {
            return ['Upload disk', 'FAIL', 'the documents disk exposes a URL; face photos and signed forms must not be reachable by path'];
        }

        if (($disk['root'] ?? '') !== storage_path('app/private')) {
            return ['Upload disk', 'WARN', 'root is '.str((string) ($disk['root'] ?? ''))->replaceFirst(base_path(), '')->limit(70).' — DEPLOYMENT.md §2 expects storage/app/private'];
        }

        // The uploads hardcode this disk name, so the default disk cannot leak them; a
        // non-default disk is worth noting because anything written without the name follows it.
        $defaultFollows = config('filesystems.default') === 'documents';

        return ['Upload disk', $defaultFollows ? 'WARN' : 'OK', 'private, root storage/app/private'.($defaultFollows ? ' — and it is the default disk, so any upload that forgets the name lands here' : '')];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function foldersWritable(): array
    {
        foreach (self::WRITABLE as $purpose => $relative) {
            $path = base_path($relative);

            if (! File::isDirectory($path)) {
                return ['Folders writable', 'FAIL', $relative.' does not exist ('.$purpose.')'];
            }

            if (! is_writable($path)) {
                return ['Folders writable', 'FAIL', $relative.' is not writable ('.$purpose.')'];
            }
        }

        return ['Folders writable', 'OK', count(self::WRITABLE).' folders, all writable'];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function printFolderArrives(): array
    {
        $prints = base_path('storage/app/prints');
        $parent = base_path('storage/app');

        if (File::isDirectory($prints)) {
            return is_writable($prints)
                ? ['Print folder', 'OK', 'storage/app/prints exists and is writable']
                : ['Print folder', 'FAIL', 'storage/app/prints exists but cannot be written — a download will fail'];
        }

        // Absent is the normal state of a fresh clone: the folder is gitignored and
        // PrintService makes it on the first download, provided its parent takes a mkdir.
        return is_writable($parent)
            ? ['Print folder', 'OK', 'storage/app/prints not created yet — PrintService makes it on the first download']
            : ['Print folder', 'FAIL', 'storage/app/prints is missing and storage/app is not writable, so the print driver cannot create it'];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function browserForPdfs(): array
    {
        $binary = ChromiumLocator::locate();

        if ($binary === null) {
            return ['Browser for PDFs', 'FAIL', 'no Chrome or Edge found — every download answers 503 until one is installed or EMS_CHROME_PATH is set'];
        }

        return ['Browser for PDFs', 'OK', $binary];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function queueConnection(): array
    {
        $connection = (string) config('queue.default');

        try {
            $pending = DB::table('jobs')->count();
        } catch (\Throwable $e) {
            $pending = -1;
        }

        if ($connection === 'sync') {
            return ['Queue', 'OK', 'sync — notifications go out inside the request that raises them'];
        }

        if ($pending > 0) {
            return ['Queue', 'FAIL', "queue={$connection} with {$pending} job(s) waiting and no worker proven here — desks will not see their notifications"];
        }

        return ['Queue', 'WARN', "queue={$connection}: a worker has to be running or notifications will sit in the jobs table"];
    }
}
