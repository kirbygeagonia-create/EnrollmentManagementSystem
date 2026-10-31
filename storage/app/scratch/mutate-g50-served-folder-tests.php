<?php

/**
 * Lane 115 (G-50): prove the served-folder guards can fail.
 *
 * §28.3 G-50 closed by taking `'serve' => true` off the `local` disk, which removes
 * `GET|HEAD /storage/{path}` and `PUT /storage/{path}` instead of trusting their HMAC. Three promises carry that
 * decision — the disk asks for no serving, the two route names are absent, and `ems:preflight` reports the shipped
 * shape while still refusing an operator who puts the flag back — and each is planted here in the pre-fix spelling.
 *
 * Every leg restores the file by hash in a shutdown hook AND between legs (a plant that survives into the next
 * leg's measurement is the defect this file has recorded three times). A needle that matches zero times is BAD,
 * never a silent skip, and a green control runs before and after: a harness whose control is red has measured
 * nothing about the tree it claims to certify.
 *
 * Usage: php storage/app/scratch/mutate-g50-served-folder-tests.php [--only=<leg>] [--list]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$flags = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--(only|list)$/', $a) || str_starts_with($a, '--only=')) {
        $flags[substr($a, 2, strpos($a.'=', '=') - 2)] = substr(strstr($a, '='), 1);
    } else {
        fwrite(STDERR, "REFUSED: unknown flag '$a' — this harness reads only --only= and --list.\n");

        exit(1);
    }
}

$CASES = [
    'no-serving-asked' => 'no_local_disk_asks_the_framework_to_serve_its_folder',
    'routes-absent' => 'the_two_storage_routes_are_not_registered_at_all',
    'unsigned-write-refused' => 'a_stranger_cannot_put_a_file_into_the_uploads_folder_or_read_one_out_of_it',
    'served-elsewhere-ok' => 'a_disk_served_from_somewhere_else_is_reported_without_crediting_the_uploads',
    'rooted-at-uploads-warns' => 'a_served_disk_rooted_at_the_uploads_warns_and_names_the_door_it_leaves_open',
    'public-visibility-fails' => 'a_served_disk_declared_public_fails_because_that_line_switches_the_gate_off',
    'healthy-install-quiet' => 'the_two_new_rows_do_not_shout_at_a_server_that_is_already_correct',
    'blank-key-names-the-cipher' => 'a_server_that_never_generated_its_key_fails_and_names_the_command',
];

/** Each leg: the case that must go red, and the exact bytes it is planted into. */
$legs = [
    // The pre-fix config, byte for byte. Both route promises must fall together: a disk that asks to be served
    // registers the routes, and the case that reads the config array cannot be the only thing that notices.
    [
        'name' => 'serve-flag-put-back',
        'cases' => ['no-serving-asked', 'routes-absent'],
        'file' => 'config/filesystems.php',
        'from' => "        'local' => [\n            'driver' => 'local',\n            'root' => storage_path('app/private'),\n            'throw' => false,",
        'to' => "        'local' => [\n            'driver' => 'local',\n            'root' => storage_path('app/private'),\n            'serve' => true,\n            'throw' => false,",
    ],
    // The row that used to call this OK. Re-planting the old concatenation makes the WARN branch unreachable, so
    // the install that re-opens the door reports an agreement — which is the defect the row exists to name.
    [
        'name' => 'warn-branch-removed',
        'cases' => ['rooted-at-uploads-warns'],
        'file' => 'app/Console/Commands/Preflight.php',
        'from' => "        if (\$sharesUploadRoot) {\n            return ['Served folder', 'WARN', 'disk(s) '",
        'to' => "        if (false) {\n            return ['Served folder', 'WARN', 'disk(s) '",
    ],
    // The public-visibility refusal, deleted. `visibility => public` switches ServeFile's signature check off
    // altogether, so a row that cannot say FAIL would publish every applicant paper behind a green label.
    [
        'name' => 'public-visibility-check-dropped',
        'cases' => ['public-visibility-fails'],
        'file' => 'app/Console/Commands/Preflight.php',
        'from' => "            if ((\$disk['visibility'] ?? 'private') === 'public') {",
        'to' => "            if (false && (\$disk['visibility'] ?? 'private') === 'public') {",
    ],
    // The sentence, not the code: the OK wording still promising that /storage/{path} answers to a signature.
    // A mechanism claim the build overtook is the lane-70 family, and this leg is what makes the pin real.
    [
        'name' => 'key-row-reclaims-the-storage-door',
        'cases' => ['healthy-install-quiet'],
        'file' => 'app/Console/Commands/Preflight.php',
        'from' => "'32 bytes, so the session cookie and any signed URL this application mints answer only to a signature made with it'",
        'to' => "'32 bytes, so /storage/{path} answers only to a signature made with it'",
    ],
    // The blank-key sentence, put back to naming the upload route as the reason. The reason is now the cipher and
    // the signed URL; a row that recites the retired hazard would teach an installer a threat that no longer ships.
    [
        'name' => 'blank-key-names-the-retired-door',
        'cases' => ['blank-key-names-the-cipher'],
        'file' => 'app/Console/Commands/Preflight.php',
        'from' => "'APP_KEY is blank, so nothing can be encrypted or signed — the session cookie is refused at the first login and any signed URL is signed with an empty secret",
        'to' => "'APP_KEY is blank, so anyone can sign their own /storage upload",
    ],
];

// A leg that cannot land is the harness's age, not a rule that does not fire — so one deliberately impossible
// needle is run through the same guard and must be reported BAD rather than skipped.
$impossibleLeg = [
    'name' => 'needle-cannot-land-is-bad',
    'cases' => ['routes-absent'],
    'file' => 'config/filesystems.php',
    'from' => "        'this_block_does_not_exist' => [\n            'driver' => 'local',",
    'to' => "        'this_block_does_not_exist' => [\n            'serve' => true,",
];

if (isset($flags['list'])) {
    foreach ($legs as $l) {
        echo $l['name']."\n";
    }
    echo $impossibleLeg['name']."\n";
    exit(0);
}

if (isset($flags['only'])) {
    $legs = array_values(array_filter($legs, fn (array $l): bool => $l['name'] === $flags['only']));
    if ($legs === []) {
        fwrite(STDERR, "REFUSED: --only={$flags['only']} names no leg. (a run over zero legs is a green over nothing)\n");

        exit(1);
    }
}

$copies = [];

register_shutdown_function(function () use (&$copies): void {
    foreach ($copies as [$path, $bytes]) {
        file_put_contents($path, $bytes);
    }
});

function snapshot(string $rel): string
{
    global $root, $copies;
    $path = $root.'/'.$rel;
    $bytes = (string) file_get_contents($path);
    foreach ($copies as &$entry) {
        if ($entry[0] === $path) {
            return $bytes;
        }
    }
    unset($entry);
    $copies[] = [$path, $bytes];

    return $bytes;
}

/** Run one case and classify: failed / passed / did-not-run. ANSI codes break the tally regex on some callers. */
function run_case(string $php, string $case): array
{
    global $root;
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($php.' artisan test --filter='.$case.' 2>&1'));
    $failed = preg_match('/Tests:\s+1 failed/', $out) === 1;
    $passed = preg_match('/Tests:\s+1 passed/', $out) === 1;
    $ran = preg_match('/Tests:/', $out) === 1;

    return ['failed' => $failed, 'passed' => $passed, 'ran' => $ran, 'out' => $out];
}

$bad = 0;
foreach ($legs as $leg) {
    $original = snapshot($leg['file']);
    $hits = substr_count($original, $leg['from']);
    if ($hits !== 1) {
        echo "BAD  {$leg['name']} — needle matched {$hits} time(s), want exactly 1 (the file has moved since this leg was cut)\n";
        $bad++;

        continue;
    }

    foreach ($leg['cases'] as $key) {
        $control = run_case($php, $CASES[$key]);
        if (! $control['ran'] || ! $control['passed']) {
            echo "BAD  {$leg['name']} — control for {$key} was not green (ran=".var_export($control['ran'], true).
                '), so a red mutant would prove nothing\n';
            $bad++;

            continue;
        }
        echo "  ok   {$leg['name']} — control green for {$key}\n";

        file_put_contents($root.'/'.$leg['file'], str_replace($leg['from'], $leg['to'], $original));
        $planted = (string) file_get_contents($root.'/'.$leg['file']);
        if ($planted === $original) {
            echo "BAD  {$leg['name']} — the plant left the file byte-identical\n";
            $bad++;
            file_put_contents($root.'/'.$leg['file'], $original);

            continue;
        }

        $r = run_case($php, $CASES[$key]);
        if (! $r['ran']) {
            // A filter that selects nothing is the lane-44 mutant that sleeps, and it looks like a pass.
            echo "BAD  {$leg['name']} — {$CASES[$key]} printed no Tests: line, so the filter selected nothing\n";
            $bad++;
        } elseif ($r['failed']) {
            echo "  killed {$leg['name']} / {$key}\n";
        } else {
            echo "BAD  {$leg['name']} / {$key} SURVIVED the plant\n";
            $bad++;
        }

        file_put_contents($root.'/'.$leg['file'], $original);
    }
}

// The impossible needle, through the same guard: it must be reported BAD, and the run must say so out loud.
$orig = snapshot($impossibleLeg['file']);
$hits = substr_count($orig, $impossibleLeg['from']);
if ($hits === 0) {
    echo "  ok   needle-cannot-land-is-bad — the guard sees 0 matches and refuses the leg\n";
} else {
    echo "BAD  needle-cannot-land-is-bad — the impossible needle matched {$hits} time(s); the leg could not fail\n";
    $bad++;
}

echo "\n";
$final = run_case($php, $CASES['routes-absent']);
if (! $final['ran'] || ! $final['passed']) {
    echo "BAD final control — {$CASES['routes-absent']} is not green after the restores\n";
    $bad++;
} else {
    echo "  ok   final control green\n";
}

foreach ($copies as [$path, $bytes]) {
    if ((string) file_get_contents($path) !== $bytes) {
        echo "BAD  {$path} was not restored byte-for-byte\n";
        $bad++;
    }
}

printf("%s: %d leg(s) planted, %d bad%s", $bad === 0 ? 'ALL KILLED' : 'FAILURES', count($legs), $bad, PHP_EOL);

exit($bad === 0 ? 0 : 1);
