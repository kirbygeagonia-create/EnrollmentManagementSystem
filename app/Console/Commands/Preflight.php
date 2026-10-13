<?php

namespace App\Console\Commands;

use App\Services\ChromiumLocator;
use Carbon\Carbon;
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
 * folder Laravel cannot write into. It writes nothing and changes nothing — a deployment check
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
            $this->environmentPair(),
            $this->databaseReachable(),
            $this->clocksAgree(),
            $this->migrationsApplied(),
            $this->seededEnough(),
            $this->uploadDiskIsPrivate(),
            $this->foldersWritable(),
            $this->printFolderArrives(),
            $this->browserForPdfs(),
            $this->queueConnection(),
        ];
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
            return ['Clocks agree', 'OK', 'PHP and '.($connection->getDriverName()).' write the same stamp: '.$phpStamp];
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
