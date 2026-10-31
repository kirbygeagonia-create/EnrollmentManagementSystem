<?php

/**
 * Lane 116 (G-36): prove the notice-dedupe guards can fail.
 *
 * Seven promises, one leg each. Every leg plants the pre-fix or the over-eager shape and requires the case that
 * NAMES that promise to print `1 failed` — a case merely "not passing" is not a kill, since a filter that selects
 * nothing looks identical to a mutant that was caught. A needle that matches zero times is reported BAD (the
 * harness's own age), the tree is restored between legs as well as at shutdown, and a green control is measured
 * before and after.
 *
 * What has no leg, and why: dropping the `exists()` pre-check in `NoticeDelivery::once()` is not observable — the
 * unique index refuses the second write anyway — so a leg asserting it would be decoration. The pre-check is an
 * optimization; the index is the guard, and the index is leg 5.
 *
 * Usage: php storage/app/scratch/mutate-g36-notice-dedupe-tests.php [--only=<leg>] [--list]
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
    'key-carries-the-act' => 'a_real_transition_carries_the_history_row_that_names_the_act',
    'whole-path-retry-refused' => 'a_notice_written_by_a_real_transition_refuses_a_retried_delivery',
    'retry-writes-once' => 'a_retried_listener_leaves_one_notice_for_the_desk',
    'distinct-acts-both-deliver' => 'a_different_act_with_the_same_words_is_still_delivered',
    'null-key-delivers' => 'an_act_with_no_identity_is_delivered_rather_than_dropped',
    'index-is-unique' => 'the_server_holds_a_unique_index_on_the_key_whichever_driver_is_open',
    'other-errors-throw' => 'a_failure_that_is_not_a_duplicate_still_propagates',
];

$MIG = 'database/migrations/2026_11_01_1000000000_add_dedupe_key_to_notifications_table.php';
$DEL = 'app/Support/NoticeDelivery.php';
$SM = 'app/Services/EnrollmentStateMachine.php';
$EL = 'app/Listeners/SendEnrollmentNotification.php';
$EV = 'app/Events/EnrollmentStatusChanged.php';

$legs = [
    // The state machine stops naming the act: the history row is created but its id never reaches the event, so every
    // notice is written unkeyed and a retry duplicates it again. This is G-36 as first written.
    //
    // Two cases, and the second was added after the first run: pointing this leg at `retry-writes-once` survived,
    // because that case dispatches hand-built events that bring their own history id and can therefore never see a
    // defect in the machine that supplies it. A surviving leg asks which input actually reaches the branch — here the
    // answer was that no case ran the real transition end to end, so the promise had no guard at all.
    [
        'name' => 'transition-stops-naming-the-act',
        'cases' => ['key-carries-the-act', 'whole-path-retry-refused'],
        'file' => $SM,
        'from' => "                \$changedBy,\n                \$remarks,\n                \$history->historyId\n            ));",
        'to' => "                \$changedBy,\n                \$remarks\n            ));",
    ],
    // The key built from the type and the recipient only — the shape a first draft would write, because it looks like
    // "one notice per desk" and is really "one notice per desk, ever".
    [
        'name' => 'key-drops-the-act-identity',
        'cases' => ['distinct-acts-both-deliver'],
        'file' => $EL,
        'from' => '"enrollment_status_changed:{$event->historyId}:{$userId}"',
        'to' => '"enrollment_status_changed:{$userId}"',
    ],
    // An unkeyed notice treated as already delivered. This is the failure mode a dedupe guard drifts into: it stops
    // being a filter on duplicates and becomes a way of losing bells.
    [
        'name' => 'null-key-swallowed',
        'cases' => ['null-key-delivers'],
        'file' => $DEL,
        'from' => "        if (\$key === null) {\n            return Notifications::create(\$attributes);\n        }",
        'to' => "        if (\$key === null) {\n            return null;\n        }",
    ],
    // The catch widened to every QueryException — a broken write then looks exactly like a duplicate delivery.
    [
        'name' => 'catch-swallowers-every-error',
        'cases' => ['other-errors-throw'],
        'file' => $DEL,
        'from' => "        } catch (QueryException \$e) {\n            if (self::isDuplicateKey(\$e)) {\n                return null;\n            }\n\n            throw \$e;\n        }",
        'to' => "        } catch (QueryException \$e) {\n            return null;\n        }",
    ],
    // The index declared as a plain index. `once()` still refuses the retry through its pre-check, so only the case
    // that reads the schema notices that the server stopped enforcing anything.
    [
        'name' => 'unique-declared-as-plain-index',
        'cases' => ['index-is-unique'],
        'file' => $MIG,
        'from' => "            \$table->unique(['dedupeKey'], 'uq_notifications_dedupe');",
        'to' => "            \$table->index(['dedupeKey'], 'uq_notifications_dedupe');",
    ],
    // The column added without the key at all, which is what "we'll enforce it in PHP" looks like on the server.
    [
        'name' => 'key-column-not-indexed',
        'cases' => ['index-is-unique'],
        'file' => $MIG,
        'from' => "        Schema::table('notifications', function (Blueprint \$table) {\n            \$table->unique(['dedupeKey'], 'uq_notifications_dedupe');\n        });",
        'to' => '        // no index: the application is the only thing that objects',
    ],
    // The listener writes through the model again, bypassing the guard: the pre-fix bytes.
    [
        'name' => 'listener-bypasses-the-guard',
        'cases' => ['retry-writes-once'],
        'file' => $EL,
        'from' => "            NoticeDelivery::once(\n                \$event->historyId === null ? null : \"enrollment_status_changed:{\$event->historyId}:{\$userId}\",\n                [",
        'to' => "            \\App\\Models\\Notifications::create(\n                [",
    ],
];

if (isset($flags['list'])) {
    foreach ($legs as $l) {
        echo $l['name']."\n";
    }
    exit(0);
}

if (isset($flags['only'])) {
    $legs = array_values(array_filter($legs, fn (array $l): bool => $l['name'] === $flags['only']));
    if ($legs === []) {
        fwrite(STDERR, "REFUSED: --only={$flags['only']} names no leg (a run over zero legs is a green over nothing)\n");

        exit(1);
    }
}

$copies = [];

register_shutdown_function(function () use (&$copies): void {
    foreach ($copies as [$path, $bytes]) {
        file_put_contents($path, $bytes);
    }
});

/** Restore everything already touched, so the next leg measures a clean tree rather than the previous defect. */
function restore_all(): void
{
    global $copies;
    foreach ($copies as [$path, $bytes]) {
        file_put_contents($path, $bytes);
    }
}

function run_case(string $php, string $case): array
{
    global $root;
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($php.' artisan test --filter='.$case.' 2>&1'));

    return [
        'failed' => preg_match('/Tests:\s+1 failed/', $out) === 1,
        'passed' => preg_match('/Tests:\s+1 passed/', $out) === 1,
        'ran' => preg_match('/Tests:/', $out) === 1,
        'out' => $out,
    ];
}

$bad = 0;
$planted = 0;

foreach ($legs as $leg) {
    $path = $root.'/'.$leg['file'];
    $original = (string) file_get_contents($path);
    $seen = false;
    foreach ($copies as &$entry) {
        if ($entry[0] === $path) {
            $seen = true;
        }
    }
    unset($entry);
    if (! $seen) {
        $copies[] = [$path, $original];
    }

    $hits = substr_count($original, $leg['from']);
    if ($hits !== 1) {
        echo "BAD  {$leg['name']} — needle matched {$hits} time(s), want exactly 1 (the file has moved since this leg was cut)\n";
        $bad++;

        continue;
    }

    foreach ($leg['cases'] as $key) {
        restore_all();
        $control = run_case($php, $CASES[$key]);
        if (! $control['ran'] || ! $control['passed']) {
            echo 'BAD  '.$leg['name'].' — control for '.$key.' was not green (ran='
                .var_export($control['ran'], true).'), so a red mutant would prove nothing'."\n";
            $bad++;

            continue;
        }

        file_put_contents($path, str_replace($leg['from'], $leg['to'], $original));
        if ((string) file_get_contents($path) === $original) {
            echo "BAD  {$leg['name']} — the plant left the file byte-identical\n";
            $bad++;
            restore_all();

            continue;
        }
        $planted++;

        $r = run_case($php, $CASES[$key]);
        if (! $r['ran']) {
            echo "BAD  {$leg['name']} — {$CASES[$key]} printed no Tests: line, so the filter selected nothing\n";
            $bad++;
        } elseif ($r['failed']) {
            echo "  killed {$leg['name']} / {$key}\n";
        } else {
            echo "BAD  {$leg['name']} / {$key} SURVIVED the plant\n";
            $bad++;
        }
        restore_all();
    }
}

echo "\n";
$final = run_case($php, $CASES['retry-writes-once']);
if (! $final['ran'] || ! $final['passed']) {
    echo "BAD final control — {$CASES['retry-writes-once']} is not green after the restores\n";
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

printf('%s: %d leg(s), %d plant(s), %d bad%s', $bad === 0 ? 'ALL KILLED' : 'FAILURES', count($legs), $planted, $bad, PHP_EOL);

exit($bad === 0 ? 0 : 1);
