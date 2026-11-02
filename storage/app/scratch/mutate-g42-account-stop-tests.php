<?php

/**
 * Falsifier for §28.3 G-42's second half: a deactivated account stops at its NEXT request.
 *
 * One planted defect per promise `RejectDeactivatedAccount` makes, each requiring the case that OWNS that promise to
 * print `1 failed` and name its own assertion. A green control runs before and after and both files are restored by
 * hash, because a bootstrap file left half-edited changes which middleware every later verdict runs under.
 *
 * The leg that matters most is `guard-never-runs`, and it is deliberately not the same as deleting the class: the
 * product of this batch is a refusal, and a refusal whose authorisation layer was never wired passes every case that
 * reads the class file. So the plant removes the registration line, and the case that must go red is the one that
 * performs a real request on both sides of the flip.
 *
 * Usage: php storage/app/scratch/mutate-g42-account-stop-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$kernel = 'bootstrap/app.php';
$guard = 'app/Http/Middleware/RejectDeactivatedAccount.php';
$loginFile = 'app/Http/Requests/Auth/LoginRequest.php';
$test = 'tests/Feature/Auth/DeactivatedAccountStopsMidSessionTest.php';

$targets = [$kernel, $guard, $loginFile];
$originals = [];
foreach ($targets as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is not in the tree.\n");
        exit(1);
    }
    $originals[$f] = (string) file_get_contents($f);
}

$restore = static function () use ($originals): void {
    foreach ($originals as $f => $body) {
        file_put_contents($f, $body);
    }
};
register_shutdown_function($restore);

$bad = 0;
$legs = 0;

$runCase = static function (string $filter) use ($php, $test): array {
    $out = [];
    // The process chdir'd to the project root at the top of this file; a `cd` inside the command would only add a
    // second way to run from the wrong directory, which is the failure this repository already recorded.
    exec('"'.$php.'" artisan test '.$test.' --filter '.$filter.' 2>&1', $out, $code);

    return [(int) $code, implode("\n", $out)];
};

$assertRestored = static function () use ($originals): bool {
    foreach ($originals as $f => $body) {
        if (md5((string) file_get_contents($f)) !== md5($body)) {
            echo '  TREE NOT RESTORED after '.basename($f)."\n";

            return false;
        }
    }

    return true;
};

$leg = static function (string $name, string $file, string $from, string $to, string $filter, string $needle, string $why) use (&$bad, &$legs, $runCase, $restore, $assertRestored): void {
    $legs++;
    $body = (string) file_get_contents($file);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 32)."BAD   needle occurs {$hits} time(s), wanted exactly 1\n";
        $bad++;

        return;
    }
    file_put_contents($file, str_replace($from, $to, $body));
    if (! str_contains((string) file_get_contents($file), $to)) {
        echo str_pad($name, 32)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runCase($filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 32)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 32).'SURVIVED the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 32)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 32)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runCase): void {
    $legs++;
    [$code, $out] = $runCase($filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 32).'BAD   the control is not green: '.str_replace("\n", ' / ', mb_substr($out, -240))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 32)."ok    control green\n";
};

$control('control-refusal-fires', 'an_account_signed_in_while_active_is_refused_at_its_next_request_once_it_is_deactivated');
$control('control-active-passes', 'an_active_account_with_a_limited_role_still_reaches_its_own_desk');

$leg(
    'guard-never-registered', $kernel,
    "            RejectDeactivatedAccount::class,\n",
    '',
    'an_account_signed_in_while_active_is_refused_at_its_next_request_once_it_is_deactivated',
    '$response->assertRedirect(route(\'login\'));',
    'the class exists and reads correctly but no request runs it — the state that reads as built'
);

$leg(
    'status-never-read', $guard,
    'if (! $user instanceof Staffusers || $user->status === StaffStatus::Active) {',
    'if (! $user instanceof Staffusers || true) {',
    'an_account_signed_in_while_active_is_refused_at_its_next_request_once_it_is_deactivated',
    '$response->assertRedirect(route(\'login\'));',
    'the guard ran and answered "active" for every account, which is a refusal wearing a class name'
);

$leg(
    'refusal-inverted', $guard,
    '$user->status === StaffStatus::Active',
    '$user->status !== StaffStatus::Active',
    'an_active_account_with_a_limited_role_still_reaches_its_own_desk',
    'assertOk',
    'the rule pointed at the wrong half of its own domain — the movement case is what makes the direction visible'
);

// WITHDRAWN, and the withdrawal is the finding: a leg that plants "do not invalidate the session" cannot fail,
// because the refusal already called `logout()` on the guard, so `assertGuest` passes either way. Session
// invalidation is hygiene here, not the load-bearing half: the next request from the same cookie re-reads the
// account and is refused again by this same middleware. A leg that cannot fail is decoration, so this one is
// named rather than counted.

$leg(
    'json-surface-dropped', $guard,
    "        if (\$request->expectsJson()) {\n            return response()->json(['message' => \$reason], 403);\n        }\n\n",
    '',
    'the_refusal_names_the_rule_and_the_remedy_rather_than_the_mechanism',
    'assertStatus(403)',
    'a caller that asked for JSON was handed an HTML redirect and read it as a successful sign-out'
);

$leg(
    'reason-emptied', $guard,
    "return response()->json(['message' => \$reason], 403);",
    "return response()->json(['message' => ''], 403);",
    'the_refusal_names_the_rule_and_the_remedy_rather_than_the_mechanism',
    'currently deactivated',
    'a refusal that names no reason is not a decision, and the screen cannot supply one honestly'
);

$leg(
    'login-spells-it-again', $loginFile,
    "'username' => DenialReason::deactivated(),",
    "'username' => 'Your staff account is currently deactivated. Please contact the administrator.'",
    'one_sentence_answers_a_closed_account_at_both_doors',
    'the login screen spells the refusal again',
    'two explanations of one state, which is how a drift between the doors becomes invisible'
);

$control('control-refusal-after-all-plants', 'an_account_signed_in_while_active_is_refused_at_its_next_request_once_it_is_deactivated');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
