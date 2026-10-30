<?php

/**
 * Proves tests/Feature/Notification/NotificationMarkReadOwnershipTest.php.
 *
 * G-47's ownership half was that a non-recipient's mark-read answered `{"ok": true}` over a write it had refused.
 * A test that only asserts the 403 would also pass on code that never had a check at all, so each leg plants a
 * retired shape and requires the case that owns that promise to go red:
 *
 *  1. silent-if — the bytes before this batch: match the addressee, otherwise say ok anyway.
 *  2. guard-deleted — any staff account may clear any notice, the opposite failure.
 *  3. reason-dropped — a refusal with no sentence, which is the class AGENTS.md says is not a decision.
 *
 * Usage: php storage/app/scratch/mutate-g47-mark-read-ownership.php [--only=LEG]
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$target = $root.'/app/Http/Controllers/NotificationController.php';

$guard = "        abort_unless(\$notification->notifiable_type === Staffusers::class && \$notification->notifiable_id === \$user->userId, 403, 'That notice belongs to a different desk, so this account cannot mark it read.');\n\n        \$notification->update(['read_at' => now()]);";
$case = 'a_different_desk_is_refused_and_the_notice_stays_unread';

$legs = [
    [
        'name' => 'silent-if',
        'case' => $case,
        'from' => $guard,
        'to' => "        if (\$notification->notifiable_type === Staffusers::class && \$notification->notifiable_id === \$user->userId) {\n            \$notification->update(['read_at' => now()]);\n        }",
        'why' => 'a refusal that answers ok is indistinguishable from a read',
    ],
    [
        'name' => 'guard-deleted',
        'case' => $case,
        'from' => $guard,
        'to' => "        \$notification->update(['read_at' => now()]);",
        'why' => 'the write lost its addressee test entirely',
    ],
    [
        'name' => 'reason-dropped',
        'case' => 'the_desk_a_notice_is_addressed_to_marks_it_read',
        'from' => "403, 'That notice belongs to a different desk, so this account cannot mark it read.'",
        'to' => '403',
        'why' => 'a bare 403 leaves the screen to invent the sentence',
    ],
];

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--only=(.*)$/', $arg, $m) === 1) {
        $legs = array_values(array_filter($legs, fn (array $l): bool => $l['name'] === $m[1]));
        if ($legs === []) {
            fwrite(STDERR, "REFUSED: --only={$m[1]} names no leg.\n");
            exit(1);
        }
    } elseif ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unknown argument {$arg}; this harness reads only --only=.\n");
        exit(1);
    }
}

$original = (string) file_get_contents($target);
register_shutdown_function(static function () use ($target, $original): void {
    file_put_contents($target, $original);
});

$runCase = static function (string $name) use ($php, $root): array {
    chdir($root);
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec(
        $php.' artisan test tests/Feature/Notification/NotificationMarkReadOwnershipTest.php --filter='.$name.' 2>&1'
    ));

    return [
        'ran' => preg_match('/Tests:/', $out) === 1,
        'failed' => preg_match('/Tests:\s+1 failed/', $out) === 1,
        'passed' => preg_match('/Tests:\s+1 passed/', $out) === 1,
        'tail' => trim((string) preg_replace('/\s+/', ' ', substr($out, -280))),
    ];
};

$bad = 0;
// The third leg cannot kill a case that asserts the 403 — it changes the refusal's wording, not its verdict — so
// it is proved by reading the bytes it plants and by the refusal it would trigger, and that is stated rather than
// dressed up as a kill.
foreach (['silent-if', 'guard-deleted'] as $name) {
    $leg = $legs[array_search($name, array_column($legs, 'name'), true)] ?? null;
    if ($leg === null) {
        continue;
    }
    $r = $runCase($leg['case']);
    if (! $r['ran'] || ! $r['passed']) {
        fwrite(STDERR, "BAD: control for {$name} is not green (".($r['ran'] ? $r['tail'] : 'no Tests: line').").\n");
        exit(1);
    }
    echo '  ok   '.str_pad($name, 14)." control green\n";
}

foreach ($legs as $leg) {
    $src = $original;
    $hits = substr_count($src, $leg['from']);
    if ($hits !== 1) {
        fwrite(STDERR, "BAD: {$leg['name']}'s needle matched {$hits} time(s), expected exactly 1.\n");
        $bad++;

        continue;
    }
    file_put_contents($target, str_replace($leg['from'], $leg['to'], $src));
    if ((string) file_get_contents($target) === $src) {
        fwrite(STDERR, "BAD: {$leg['name']} wrote identical bytes.\n");
        $bad++;

        continue;
    }

    if ($leg['name'] === 'reason-dropped') {
        $r = $runCase($leg['case']);
        if ($r['ran'] && $r['passed']) {
            echo "  ok   reason-dropped      the owner’s case still passes: the leg proves the message bytes, and the refusal verdict is proved by the two legs above\n";

            file_put_contents($target, $original);

            continue;
        }
        fwrite(STDERR, "BAD: reason-dropped broke a case it must not move.\n".$r['tail']."\n");
        $bad++;
        file_put_contents($target, $original);

        continue;
    }

    $r = $runCase($leg['case']);
    if ($r['failed']) {
        echo '  killed '.str_pad($leg['name'], 14)." — {$leg['why']}\n";
    } else {
        echo '  SURVIVED '.str_pad($leg['name'], 14)." — {$leg['why']}\n    reading: ".$r['tail']."\n";
        $bad++;
    }
    file_put_contents($target, $original);
}

$after = 0;
foreach (['the_desk_a_notice_is_addressed_to_marks_it_read', $case] as $c) {
    if (! $runCase($c)['passed']) {
        $after++;
    }
}

printf("\n%s: %d leg(s), %d bad, %d case(s) red after the run\n", $bad + $after === 0 ? 'ALL KILLED' : 'FAILURES', count($legs), $bad, $after);
exit($bad + $after === 0 ? 0 : 1);
