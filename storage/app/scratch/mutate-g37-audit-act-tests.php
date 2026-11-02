<?php

/**
 * Falsifier for the audit act vocabulary (prompt §9's audit families, register §28.3 G-37): one planted defect per
 * promise the mechanism makes, each of which must make the case that OWNS that promise print `1 failed` and name its
 * own assertion. A green control runs before and after, and every file is restored by hash, because a half-restored
 * observer or a map left mutated would make every later verdict a lie about a file nobody meant to change.
 *
 * Three rules this harness is built to, each learned elsewhere in this repository:
 *  - a leg must require the case to fail with its OWN assertion sentence, since a plant that does not compile also
 *    prints `1 failed` and proves nothing;
 *  - a plant is verified against the exact bytes the leg claims to have written, and a needle that matches nothing is
 *    reported BAD (the harness's own age), never as a surviving defect;
 *  - `--filter` takes the method name, not the sentence Pest prints for it, and "No tests found" is BAD.
 *
 * The leg that matters most is `middleware-not-registered`: every other case in the class would stay green if the
 * request boundary never ran, because the mechanism cases call `AuditAct::run()` directly and the map cases read
 * constants. That is the shape of a suite that proves a mechanism nobody wired up.
 *
 * Usage: php storage/app/scratch/mutate-g37-audit-act-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$act = 'app/Support/AuditAct.php';
$observer = 'app/Observers/AuditLogObserver.php';
$issuer = 'app/Services/EnrollmentIssuer.php';
$kernel = 'bootstrap/app.php';
$middleware = 'app/Http/Middleware/NameAuditAct.php';
$page = 'resources/js/Pages/Admin/UserManagement/AuditLogs.jsx';

$actTest = 'tests/Feature/Audit/AuditActNamingTest.php';
$issueTest = 'tests/Feature/Enrollment/EnrollmentRequestKeyTest.php';

$targets = [$act, $observer, $issuer, $kernel, $middleware, $page];
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

$runFile = static function (string $file, string $filter) use ($php): array {
    $out = [];
    // The process already `chdir`d to the project root at the top of this file; a `cd` inside the command would
    // only add a second way to run from the wrong directory, which is the failure this harness met before.
    exec('"'.$php.'" artisan test '.$file.' --filter '.$filter.' 2>&1', $out, $code);

    return [(int) $code, implode("\n", $out)];
};

$assertRestored = static function () use ($originals): bool {
    // `$originals` is keyed by FILENAME, so the hash table is too. Indexing it numerically reads key 0 of an
    // array that has no key 0, compares the file's md5 against null, and reports a correctly restored tree as
    // damaged — which is the harness accusing itself, and the reason this leg was rewritten rather than relaxed.
    foreach ($originals as $f => $body) {
        if (md5((string) file_get_contents($f)) !== md5($body)) {
            echo '  TREE NOT RESTORED after '.basename($f)."\n";

            return false;
        }
    }

    return true;
};

/** Plant one exact replacement, then require the named case to fail naming its own assertion. */
$leg = static function (string $name, string $file, string $from, string $to, string $testFile, string $filter, string $needle, string $why) use (&$bad, &$legs, $runFile, $restore, $assertRestored): void {
    $legs++;
    $body = (string) file_get_contents($file);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 34)."BAD   needle occurs {$hits} time(s), wanted exactly 1\n";
        $bad++;

        return;
    }
    file_put_contents($file, str_replace($from, $to, $body));
    if (! str_contains((string) file_get_contents($file), $to)) {
        echo str_pad($name, 34)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($testFile, $filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 34)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 34).'SURVIVED the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 34)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 34)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $testFile, string $filter) use (&$bad, &$legs, $runFile): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 34).'BAD   the control is not green: '.str_replace("\n", ' / ', mb_substr($out, -240))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 34)."ok    control green\n";
};

// ------------------------------------------------------------------ controls, on the untouched tree
$control('control-act-names-rows', $actTest, 'an_act_names_every_audit_row_written_inside_it');
$control('control-generic-word', $actTest, 'a_write_outside_any_act_keeps_the_event_word');
$control('control-request-names-act', $actTest, 'a_desk_request_names_the_act_on_every_row_that_request_writes');

// --- the request boundary itself
$leg(
    'middleware-not-registered', $kernel,
    "            NameAuditAct::class,\n",
    '',
    $actTest, 'a_desk_request_names_the_act_on_every_row_that_request_writes', 'account.deactivate',
    'the map and the observer both stood, but no request ever consulted them'
);

$leg(
    'middleware-ignores-the-map', $middleware,
    '$act = AuditAct::forRoute($request->route()?->getName());',
    '$act = null;',
    $actTest, 'a_desk_request_names_the_act_on_every_row_that_request_writes', 'account.deactivate',
    'the middleware ran and named nothing, which is a mechanism wearing the costume of one'
);

// --- the map, read against the route table and the list
$leg(
    'map-names-a-route-that-is-gone', $act,
    "'registrar.drop' => self::DROP,",
    "'registrar.delete' => self::DROP,",
    $actTest, 'every_route_the_map_names_exists_and_every_value_is_a_listed_act', 'which is not a route this application has',
    'a renamed route would leave its act unnamed and every other case green'
);

$leg(
    'map-names-a-word-outside-the-list', $act,
    "'clearance.approve' => self::CLEARANCE_DECIDE,",
    "'clearance.approve' => 'clearance.approved',",
    $actTest, 'every_route_the_map_names_exists_and_every_value_is_a_listed_act', 'which is not one of the named acts',
    'the map invented a spelling, which is the defect the single list exists to refuse'
);

$leg(
    'act-word-reaches-nothing', $act,
    "'id.validate' => self::ID_VALIDATE,",
    "'id.validate' => self::STEP_SIGN,",
    $actTest, 'every_named_act_is_reachable_from_a_route_or_a_call_site', 'nothing names them',
    'the list promised an act the log can never answer a query for'
);

// --- the vocabulary the desk is offered
$leg(
    'vocabulary-drops-the-acts', $act,
    'return array_merge(self::EVENTS, self::LIST);',
    'return self::EVENTS;',
    $actTest, 'the_audit_desk_is_offered_only_the_words_the_log_can_hold', 'the event words then the named acts',
    'the filter list stopped carrying the acts it exists to offer'
);

$leg(
    'page-types-its-own-words', $page,
    "        { value: '', label: 'All Actions' },",
    "        { value: '', label: 'All Actions' },\n        { value: 'unassigned', label: 'Unassigned' },",
    $actTest, 'the_audit_desk_is_offered_only_the_words_the_log_can_hold', 'the page is typing its own action words',
    'the screen went back to a hand-kept list, which is how nine dead options appeared the first time'
);

// --- the mechanism and the list
$leg(
    'observer-writes-generic-word', $observer,
    "'action' => AuditAct::labelFor(\$action),",
    "'action' => \$action,",
    $actTest, 'an_act_names_every_audit_row_written_inside_it', 'payment.void',
    'the observer stopped consulting the act, so the prompt is unanswerable as a query again'
);

$leg(
    'label-not-restored', $act,
    "        } finally {\n            self::\$current = \$previous;\n        }",
    "        } finally {\n        }",
    $actTest, 'the_label_is_restored_when_the_act_ends', 'role.change',
    'a second act in one request would inherit the first one name'
);

$leg(
    'unknown-word-accepted', $act,
    '        if (! in_array($name, self::LIST, true)) {',
    '        if (false) {',
    $actTest, 'a_word_outside_the_named_acts_is_refused_by_name', 'the unknown act was accepted',
    'any string could enter the audit table, which is how two spellings of one act appear'
);

$leg(
    'duplicate-in-list', $act,
    '        self::ISSUE, self::DROP, self::RETURN, self::REQUIREMENT_VERIFY, self::CLEARANCE_DECIDE,',
    '        self::ISSUE, self::ISSUE, self::DROP, self::RETURN, self::REQUIREMENT_VERIFY, self::CLEARANCE_DECIDE,',
    $actTest, 'the_vocabulary_is_one_list_and_covers_every_family_the_prompt_names', 'one act must not have two spellings',
    'the list gained a second spelling of an act that already has one'
);

$leg(
    'constant-outside-list', $act,
    "    public const ROLE_CHANGE = 'role.change';",
    "    public const ROLE_CHANGE = 'role.change';\n\n    public const STRAY = 'enrollment.stray';",
    $actTest, 'every_const_vocabulary_entry_is_in_the_list', 'two answers to one question',
    'a vocabulary entered in two places, one of them unread by the list'
);

$leg(
    'issuing-act-unwired', $issuer,
    'return AuditAct::run(AuditAct::ISSUE, fn () => $this->create($attributes, $issuer, $requestKey));',
    'return $this->create($attributes, $issuer, $requestKey);',
    $issueTest, 'the_issuing_act_names_itself_in_the_audit_trail_and_a_replay_adds_no_second_entry', 'enrollment.issue',
    'the mechanism stood but no act used it, which is the state that reads as done'
);

$control('control-generic-word-after-all-plants', $actTest, 'a_write_outside_any_act_keeps_the_event_word');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
