<?php

namespace Tests\Feature\Console;

use App\Console\Commands\Preflight;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ems:preflight turns DEPLOYMENT.md from something an operator reads into something they run.
 * Each test here is one of the traps that document records, and the assertion is on the fix
 * being named in the output, not only on the failure: an operator standing in front of a
 * server has to be told what to type.
 */
class PreflightTest extends TestCase
{
    use RefreshDatabase;

    private string $lastOutput = '';

    private function callPreflight(): int
    {
        // Artisan::output() drains the buffer, so the report is read once per run and kept.
        $code = Artisan::call('ems:preflight');
        $this->lastOutput = Artisan::output();

        return $code;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase hands over an empty schema, and an empty RBAC is one of the things
        // this command is supposed to fail on. Seed it so the passing tests mean "a server
        // that is actually ready", not "a server with nothing in it".
        $this->seed(RbacSeeder::class);
    }

    private function commandOutput(): string
    {
        return $this->lastOutput;
    }

    #[Test]
    public function a_server_that_meets_the_documented_requirements_passes(): void
    {
        $this->assertSame(0, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringNotContainsString('FAIL', $out);
        $this->assertStringContainsString('Database connection', $out);
        $this->assertStringContainsString('Migrations', $out);
        $this->assertStringContainsString('Browser for PDFs', $out);

        // The suite runs as `testing`, which is not the environment a school server should
        // report, so the honest verdict here is a warning, not a clean sheet: this command
        // cannot tell a laptop from the machine the desks will use, and says so.
        $this->assertStringContainsString('WARN  APP_ENV / APP_DEBUG', $out);
        $this->assertStringContainsString('check(s) worth a look', $out);
    }

    #[Test]
    public function a_database_that_stamps_its_own_hours_fails_and_names_the_string_to_fix(): void
    {
        // The two DATETIME columns with a MySQL default (auditlogs.createdAt,
        // enrollmentstatushistory.changedAt) get whatever string the database writes, and every other
        // timestamp gets whatever PHP writes. A DATETIME keeps no zone, so a session running in a different
        // one silently puts a record hours from its own siblings. Moving PHP's clock is how this suite can
        // create that split: the suite database answers in its own time either way.
        Carbon::setTestNow(Carbon::now()->addHours(11));

        try {
            $this->assertSame(1, $this->callPreflight());
        } finally {
            Carbon::setTestNow(null);
        }

        $out = $this->commandOutput();
        $this->assertStringContainsString('FAIL  Clocks agree', $out);
        $this->assertStringContainsString('apart in the column', $out);
        $this->assertStringContainsString('session time zone', $out);
        $this->assertStringContainsString('1 check(s) will break a desk', $out);
    }

    #[Test]
    public function the_server_that_agrees_on_one_clock_says_so_and_does_not_shout(): void
    {
        // The other direction matters as much: a rule that fails a healthy server teaches the operator to
        // ignore the report. SQLite answers datetime('now') in UTC and the app timezone is UTC, so the two
        // strings are the same instant and the line is a clean OK.
        $this->assertSame(0, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('OK  Clocks agree', $out);
        $this->assertStringContainsString('write the same stamp', $out);
    }

    #[Test]
    public function a_server_that_never_generated_its_key_fails_and_names_the_command(): void
    {
        // `.env.example` ships `APP_KEY=` blank, and DEPLOYMENT.md §3 generates it with one line
        // between `npm run build` and `migrate`. Skipping that line does not look like a storage
        // problem — it looks like a broken login — while /storage/{path}, which needs no session
        // encryption, keeps answering. Measured end to end by ServedUploadPathTest.
        config(['app.key' => '']);

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('FAIL  Application key', $out);
        $this->assertStringContainsString('php artisan key:generate', $out);
        $this->assertStringContainsString('/storage upload', $out);
    }

    #[Test]
    public function a_key_of_the_wrong_length_fails_because_the_cipher_would_refuse_the_first_login(): void
    {
        config(['app.key' => 'twenty-three-characters-raw']);

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('FAIL  Application key', $out);
        $this->assertStringContainsString('not a 32-byte key', $out);
    }

    #[Test]
    public function a_served_disk_declared_public_fails_because_that_line_switches_the_gate_off(): void
    {
        // ServeFile::hasValidSignature() returns true with no signature at all once the served
        // disk says its visibility is public. This configuration refuses the same unsigned read
        // (403, measured in audit-24c-servegate.php); with the flag set it walks through and only
        // a missing file answers 404. The served `local` disk is rooted at storage/app/private —
        // the same root as the documents disk — so that one line publishes every uploaded paper.
        config(['filesystems.disks.local.visibility' => 'public']);

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('FAIL  Served folder', $out);
        $this->assertStringContainsString('switches the signature check off', $out);
        $this->assertStringContainsString('reachable by path', $out);
    }

    #[Test]
    public function a_server_that_serves_no_folder_reports_the_uploads_as_having_one_door(): void
    {
        config(['filesystems.disks.local.serve' => false]);

        $this->assertSame(0, $this->callPreflight());

        $this->assertStringContainsString(
            'no local disk asks Laravel to serve',
            $this->commandOutput()
        );
    }

    #[Test]
    public function the_two_new_rows_do_not_shout_at_a_server_that_is_already_correct(): void
    {
        // The pair to the failures above: a rule that FAILs a healthy install teaches the
        // operator to ignore the whole report, so the OK wording is pinned too.
        $this->assertSame(0, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('OK  Application key', $out);
        $this->assertStringContainsString('answers only to a signature made with it', $out);
        $this->assertStringContainsString('OK  Served folder', $out);
        $this->assertStringContainsString('served private: every read needs a signature', $out);
    }

    #[Test]
    public function a_documents_disk_that_exposes_a_url_fails_and_says_why(): void
    {
        config(['filesystems.disks.documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'url' => 'https://ems.example.invalid/storage',
            'throw' => false,
        ]]);

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('Upload disk', $out);
        $this->assertStringContainsString('must not be reachable by path', $out);
    }

    #[Test]
    public function a_server_without_a_documents_disk_has_nowhere_private_to_put_a_paper(): void
    {
        config(['filesystems.disks.documents' => null]);

        $this->assertSame(1, $this->callPreflight());
        $this->assertStringContainsString('declares no "documents" disk', $this->commandOutput());
    }

    #[Test]
    public function a_configured_browser_path_that_does_not_exist_fails_and_names_the_variable_to_fix(): void
    {
        // locate() honours a configured chrome_path strictly and does not fall back to a guess,
        // so a typo in EMS_CHROME_PATH is exactly the case that must not pass quietly.
        config(['services.chrome_path' => 'C:\\ems-preflight-test\\definitely-not-a-browser.exe']);

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('Browser for PDFs', $out);
        $this->assertStringContainsString('every download answers 503', $out);
        $this->assertStringContainsString('EMS_CHROME_PATH', $out);
    }

    #[Test]
    public function an_unseeded_database_fails_because_every_desk_would_be_locked_out(): void
    {
        DB::table('permissions')->delete();

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('every desk is locked out', $out);
        $this->assertStringContainsString('db:seed --class=RbacSeeder', $out);
    }

    #[Test]
    public function a_migration_file_that_never_ran_is_named_so_the_operator_knows_which_table_is_missing(): void
    {
        $last = DB::table('migrations')->orderByDesc('id')->first();
        $this->assertNotNull($last, 'The test database records no migrations.');

        DB::table('migrations')->where('id', $last->id)->delete();

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('not applied', $out);
        $this->assertStringContainsString($last->migration, $out);
        $this->assertStringContainsString('php artisan migrate', $out);
    }

    #[Test]
    public function jobs_sitting_in_the_table_with_no_worker_proven_fail_the_run(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'SendNotification', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('desks will not see their notifications', $out);
    }

    #[Test]
    public function a_database_left_on_a_non_sync_queue_without_waiting_jobs_is_a_warning_not_a_fault(): void
    {
        config(['queue.default' => 'redis']);

        $this->assertSame(0, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('WARN', $out);
        $this->assertStringContainsString('a worker has to be running', $out);
        $this->assertStringNotContainsString('FAIL', $out);
    }

    #[Test]
    public function an_absent_print_folder_is_not_a_fault_because_the_print_driver_makes_it(): void
    {
        $prints = base_path('storage/app/prints');
        $moved = base_path('storage/app/prints-preflight-test');

        if (! File::isDirectory($prints)) {
            $this->markTestSkipped('This checkout has no storage/app/prints to move aside.');
        }

        File::moveDirectory($prints, $moved);

        try {
            $this->assertSame(0, $this->callPreflight());

            $out = $this->commandOutput();
            $this->assertStringContainsString('Print folder', $out);
            $this->assertStringContainsString('PrintService makes it on the first download', $out);
            $this->assertStringNotContainsString('FAIL', $out);
        } finally {
            File::moveDirectory($moved, $prints);
        }

        $this->assertTrue(File::isDirectory($prints), 'The sample folder was not put back.');
    }

    #[Test]
    public function a_green_clock_row_says_so_when_only_the_application_carries_the_zone(): void
    {
        // The third arm of the check, and the one the school's server will hit: clocks agree because
        // config/database.php forces the session, while the server itself still defaults to something else.
        // Nothing fails here, but the operator has to be told that a dump, a cron job or phpMyAdmin on that
        // machine will stamp a different hour into the same zone-less DATETIME column.
        $note = Preflight::zoneNote('+00:00', 'SYSTEM');

        $this->assertStringContainsString("'s session is +00:00", $note);
        $this->assertStringContainsString('defaults to SYSTEM', $note);
        $this->assertStringContainsString('DEPLOYMENT.md §2', $note);
        $this->assertStringContainsString('mysqldump', $note);
    }

    #[Test]
    public function a_server_that_already_agrees_gets_no_note_and_a_zone_that_cannot_be_read_stays_silent(): void
    {
        // Both of these must return '' rather than a warning: the development box after the my.ini edit
        // reports session and global identical, and a note on every healthy server is how a report learns
        // its operator to skim it.
        $this->assertSame('', Preflight::zoneNote('+00:00', '+00:00'));
        $this->assertSame('', Preflight::zoneNote('', 'SYSTEM'), 'an unreadable session must not guess');
        $this->assertSame('', Preflight::zoneNote('+00:00', ''), 'nor may an unreadable server default');
    }

    #[Test]
    public function a_host_that_keeps_the_schools_hour_is_an_ok_and_an_unreadable_host_is_a_warning_never_a_fault(): void
    {
        // The two verdicts a machine can be told about, from readings this process cannot have. Both arms
        // matter: a row that only ever says WARN teaches the operator to skim it, and a row that FAILs a
        // healthy server is a fault the preflight cannot fix — preflight cannot move a clock, and stored
        // instants are UTC whatever the wall says.
        $match = Preflight::hostZoneRow(['+08:00', 'the database\'s @@system_time_zone'], 'Asia/Manila');
        $blind = Preflight::hostZoneRow(null, 'Asia/Manila');

        $this->assertSame(['Host time zone', 'OK', 'the machine is at +08:00 (the database\'s @@system_time_zone), which is Asia/Manila\'s offset; stored instants stay UTC either way'], $match);
        $this->assertSame('WARN', $blind[1], 'a zone that cannot be read must not be reported as agreement');
        $this->assertStringContainsString('could not be read', $blind[2]);
    }

    #[Test]
    public function a_host_at_another_hour_is_a_warning_that_names_both_offsets_and_the_fix(): void
    {
        // This box: MySQL's @@system_time_zone answers the Windows name `UTC-11` while the school declares
        // Asia/Manila. Nothing stored is wrong — the session is forced and every civil day goes through
        // SchoolTime — so the row's job is to name the two hours, say how far apart they are, and point at
        // the one place an operator could still be burned: mysqldump's filename, cron, a bare script.
        [$label, $verdict, $detail] = Preflight::hostZoneRow(['-11:00', 'the database\'s @@system_time_zone'], 'Asia/Manila');

        $this->assertSame('Host time zone', $label);
        $this->assertSame('WARN', $verdict);
        $this->assertStringContainsString('this machine is at -11:00', $detail);
        $this->assertStringContainsString('Asia/Manila at +08:00', $detail);
        $this->assertStringContainsString('19 h apart on the wall clock', $detail);
        $this->assertStringContainsString('mysqldump', $detail);
        $this->assertStringContainsString('DEPLOYMENT.md §2', $detail);
    }

    #[Test]
    public function the_gap_is_measured_from_the_two_offsets_so_a_half_hour_zone_and_a_changed_declaration_both_read_true(): void
    {
        // The distance between the two clocks has to come out of the pair it is describing: a figure written
        // into the sentence would be true only of this box, and only while SCHOOL_TIMEZONE names Manila. A
        // half-hour zone is something real schools keep, and a declaration is something an office can change.
        $this->assertStringContainsString('2.5 h apart', Preflight::hostZoneRow(['+05:30', 'x'], 'Asia/Manila')[2]);
        $this->assertStringContainsString('1 h apart', Preflight::hostZoneRow(['+09:00', 'x'], 'Asia/Manila')[2]);

        // A zone PHP cannot resolve is not a zone the check may guess at.
        $unresolved = Preflight::hostZoneRow(['+08:00', 'x'], 'Not/AZone');
        $this->assertSame('WARN', $unresolved[1]);
        $this->assertStringContainsString('not a zone PHP can resolve', $unresolved[2]);
    }

    #[Test]
    public function the_offset_is_read_from_the_database_so_a_suite_on_sqlite_cannot_answer_it(): void
    {
        // The read itself, asked on the driver this run actually has. The default job is SQLite, which has no
        // @@system_time_zone at all: an array there would be a zone the database never quoted, which is how
        // the first draft of this check passed everywhere (it read date_default_timezone_get(), and Laravel
        // pins that to app.timezone, so the answer was always UTC and always agreed). The MySQL job is the
        // mirror: refusing there would hide a gap the operator can be told about.
        $read = Preflight::hostUtcOffset();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->assertNull($read, 'SQLite has no @@system_time_zone, so the honest read is nothing');

            return;
        }

        $this->assertIsArray($read, 'MySQL does report its system zone, so this driver must answer');
        $this->assertMatchesRegularExpression('/^[-+]\d{2}:\d{2}$/', $read[0]);
        $this->assertSame("the database's @@system_time_zone", $read[1]);
    }

    #[Test]
    public function the_host_zone_row_names_the_source_it_read_or_says_it_could_not_read_and_never_faults(): void
    {
        // End to end, because the pure calls above prove nothing about the wiring. What is asserted is the
        // consistency between the read and the sentence, measured in the same run rather than assumed of the
        // driver or the machine's zone: a row that cannot look has to say so, and a row that looked has to
        // name the hour and where it came from. Neither shape may be a FAIL — preflight cannot move a clock.
        $this->assertSame(0, $this->callPreflight());

        $line = collect(explode("\n", $this->commandOutput()))
            ->first(fn (string $l): bool => str_contains($l, 'Host time zone')) ?? '';

        $this->assertNotSame('', $line, 'the report printed no Host time zone row');
        $this->assertStringNotContainsString('FAIL', $line);

        if (Preflight::hostUtcOffset() === null) {
            $this->assertStringContainsString('WARN  Host time zone', $line);
            $this->assertStringContainsString('could not be read', $line);

            return;
        }

        $this->assertMatchesRegularExpression('/(WARN|OK)\s+Host time zone/', $line);
        $this->assertMatchesRegularExpression('/is at [-+]\d{2}:\d{2} \(the database\'s @@system_time_zone\)/', $line);
    }

    #[Test]
    public function the_forwarded_scheme_row_decides_the_proxy_question_instead_of_asking_the_installer_to_remember_it(): void
    {
        // G-13 is the register's one item that waits on a server nobody has built yet. The row cannot see a proxy
        // that does not exist, so it judges the states it CAN see: an APP_URL that claims HTTPS while nothing is
        // trusted (the header would silently never reach a desk), an address named — which is the answered state —
        // and the spellings that look answered and are not. Those last two are why this case drives five shapes:
        // every verdict here was measured through the real middleware first, in
        // storage/app/scratch/lane73b-probe-proxy-shapes.php, so the sentence and the effect are known to agree.
        $row = fn (string $out): string => (string) (collect(explode("\n", $out))
            ->first(fn (string $l): bool => str_contains($l, 'Forwarded scheme')));

        config()->set('trustedproxy.proxies', null);
        config()->set('app.url', 'http://localhost:8080');
        $this->assertSame(0, $this->callPreflight());
        $line = $row($this->commandOutput());
        $this->assertNotSame('', $line, 'the report printed no Forwarded scheme row');
        $this->assertStringNotContainsString('FAIL', $line);
        $this->assertStringContainsString('DEPLOYMENT.md', $line, 'the answered-as-nothing state still names the instruction');
        $this->assertStringContainsString('TRUSTED_PROXIES', $line,
            'the row must name the setting the installer has to touch, not the file they used to have to edit');

        config()->set('app.url', 'https://ems.example.edu.ph');
        $this->callPreflight();
        $line = $row($this->commandOutput());
        $this->assertStringContainsString('WARN', $line, 'an https URL with no trusted proxy is the silent-HSTS shape');
        $this->assertStringContainsString('no proxy is trusted', $line);
        $this->assertStringContainsString('config:cache', $line,
            'setting .env on a cached install does nothing until the cache is rebuilt, and the installer would otherwise blame the setting');

        config()->set('trustedproxy.proxies', ['10.0.0.5']);
        $this->callPreflight();
        $line = $row($this->commandOutput());
        $this->assertStringContainsString('OK', $line);
        $this->assertStringContainsString('1 proxy address(es) trusted', $line);

        // The shape `.env` actually produces: one string, and a comma splits it inside the middleware
        // (vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:87-88). Reading only an array
        // here used to call a correct one-line TRUSTED_PROXIES setting "nothing is trusted".
        config()->set('trustedproxy.proxies', '10.0.0.5, 10.0.0.6');
        $this->callPreflight();
        $line = $row($this->commandOutput());
        $this->assertStringContainsString('OK', $line, 'a comma-separated string is the documented way to set this');
        $this->assertStringContainsString('2 proxy address(es) trusted', $line);

        // A block is as valid as a single address — measured through the middleware in the same probe, a proxy on
        // a redundant pair of addresses is written one CIDR. This line is what makes the /33 refusal below mean
        // "that mask is out of range" rather than "blocks are not allowed".
        config()->set('trustedproxy.proxies', '10.0.0.0/29');
        $this->callPreflight();
        $line = $row($this->commandOutput());
        $this->assertStringContainsString('OK', $line, 'a CIDR block is a legitimate way to name a proxy');

        // A star written on its own is the catchall and must be refused as the forge.
        config()->set('trustedproxy.proxies', '*');
        $this->callPreflight();
        $line = $row($this->commandOutput());
        $this->assertStringContainsString('WARN', $line, 'the wildcard must never read as the answered state');
        $this->assertStringContainsString('forge', $line);

        // A star inside a list, and a hostname, are the other two ways to install nothing: Symfony is handed them
        // as patterns, validates nothing, and matches no address — so the header stays discarded while the setting
        // looks filled in. Both must refuse rather than count as an address.
        config()->set('trustedproxy.proxies', 'proxy-server');
        $this->callPreflight();
        $line = $row($this->commandOutput());
        $this->assertStringContainsString('WARN', $line, 'a name is not an address, and silently trusts nobody');
        $this->assertStringContainsString('match no client address', $line);

        // A CIDR mask outside its family's range is the same silent shape: `checkIp4` returns false for a mask over
        // 32 rather than complaining, so a typo'd block installs nothing.
        config()->set('trustedproxy.proxies', '10.0.0.5/33');
        $this->callPreflight();
        $line = $row($this->commandOutput());
        $this->assertStringContainsString('WARN', $line, 'a /33 v4 block matches no address');
        $this->assertStringContainsString('match no client address', $line);

        config()->set('trustedproxy.proxies', ['*']);
        $this->callPreflight();
        $line = $row($this->commandOutput());
        $this->assertStringContainsString('WARN', $line);
        $this->assertStringContainsString('match no client address', $line,
            'an array star is the silent shape, not the forge — measured, it matches nothing');

        config()->set('trustedproxy.proxies', 'PRIVATE_SUBNETS');
        $this->callPreflight();
        $line = $row($this->commandOutput());
        $this->assertStringContainsString('WARN', $line, 'a range keyword is not one machine');
        $this->assertStringContainsString('instead of one machine', $line);

        config()->set('trustedproxy.proxies', null);
        config()->set('app.url', 'http://localhost:8080');
    }

    #[Test]
    public function the_trusted_proxy_config_file_answers_the_env_var_and_a_blank_one_means_no_proxy(): void
    {
        // The middleware reads config('trustedproxy.proxies'), which the row above can only be handed by hand. This
        // is the other half: the shipped file has to read the NAME the installer is told to set, and a blank value
        // must mean "no proxy" rather than the empty-string pattern. Measured with
        // storage/app/scratch/lane73b-probe-proxy-shapes.php: `TRUSTED_PROXIES=` arrives as '' — not null — and
        // Symfony stores '' without complaint and matches nothing, so '' and null have to be the same answer here.
        $file = fn (): array => (array) require base_path('config/trustedproxy.php');

        putenv('TRUSTED_PROXIES');
        $this->assertNull($file()['proxies'], 'nothing set must mean nothing trusted');

        putenv('TRUSTED_PROXIES=');
        $this->assertNull($file()['proxies'], 'a blank TRUSTED_PROXIES must not become the empty-string pattern');

        putenv('TRUSTED_PROXIES=10.0.0.5');
        $this->assertSame('10.0.0.5', $file()['proxies'], 'the value the installer types is the value the middleware gets');

        // Leave the environment exactly as found, or a later test in this process runs behind a proxy.
        putenv('TRUSTED_PROXIES');
    }

    #[Test]
    public function the_application_zone_is_still_utc_so_wiring_the_env_var_changed_nothing(): void
    {
        // DEPLOYMENT.md names APP_TIMEZONE as the knob; until now config/app.php ignored it. Reading the
        // variable makes the document true, and this pins the other half: with neither .env nor
        // .env.example setting it, the value is still UTC, which is what the owner ruled on 2026-10-14.
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', Carbon::now()->getTimezone()->getName());
    }

    #[Test]
    public function the_connection_states_its_zone_and_an_empty_setting_drops_the_statement_instead_of_sending_it(): void
    {
        // Re-reading the config file is the only way to test this: the option is built once, at config
        // load, from env(), and the connection the suite is already using cannot be reconfigured mid-run.
        $options = function (): array {
            return require base_path('config/database.php');
        };

        putenv('DB_SESSION_TIME_ZONE');
        $byDefault = $options()['connections']['mysql']['options'];
        $this->assertSame(
            "SET time_zone = '+00:00'",
            $byDefault[PDO::MYSQL_ATTR_INIT_COMMAND] ?? null,
            'with nothing set, every connection must state the zone rather than inherit the server\'s');

        // The escape hatch DEPLOYMENT.md §2 offers has to be a hatch. Measured first: env() answers '' for
        // `DB_SESSION_TIME_ZONE=`, and `SET time_zone = ''` is not a no-op — MySQL rejects it, so the
        // statement would fail *every* connection in the application instead of merely losing the setting.
        putenv('DB_SESSION_TIME_ZONE=');
        $declined = $options()['connections']['mysql']['options'];
        $this->assertArrayNotHasKey(PDO::MYSQL_ATTR_INIT_COMMAND, $declined,
            'an empty DB_SESSION_TIME_ZONE must remove the statement, not send an empty zone');

        putenv('DB_SESSION_TIME_ZONE=+08:00');
        $manila = $options()['connections']['mysql']['options'];
        $this->assertSame("SET time_zone = '+08:00'", $manila[PDO::MYSQL_ATTR_INIT_COMMAND],
            'a zone that is set for real still reaches the server');

        // Leave the environment exactly as found, or a later test in this process connects in Manila.
        putenv('DB_SESSION_TIME_ZONE');
    }
}
