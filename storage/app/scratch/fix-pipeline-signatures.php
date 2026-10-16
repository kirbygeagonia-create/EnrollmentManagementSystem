<?php

/**
 * Fix the demo pipeline's workflow signatures: they were stamped with hard-coded user ids.
 *
 * Measured on live `ems` before this change: 2 of 93 completed boxes name a signer from ANOTHER office, so
 * the demo data asserts signatures the running application could never produce. The cause is not a desk and
 * not a policy — `WorkflowService::signStep()` enforces BR14 (office scope), BR13 (step order) and
 * one-signing-per-box on every HTTP path, and none of these writes goes through it. Three sites stamp a
 * literal, and each stamps EVERY completed box of that workflow with the one id:
 *
 *   'signedBy' => $order === 1 ? 5 : null   //  userId 5 on THIS machine = office4_head
 *   'signedBy' => $done ? 3 : null          //  offices 4,3,2 all get office2_head
 *   'signedBy' => $done ? 2 : null          //  offices 4,3,2,1 all get office1_head
 *
 * Each loop already holds `$off`, the office of the box being written, so the literal is not merely unsafe,
 * it is unnecessary. Two further sites read `$deskSigners[$officeId] ?? 1`: when a desk is not seated they
 * sign as userId 1, which here is `staff8`, an Admin — the account the Item-3 write-boundary comment in
 * WorkflowService excludes from every mutation. And four sites credit the Registrar with `2`.
 *
 * So: one resolver by username, a plain function (a closure would hit the `Undefined variable` trap this
 * file has hit before), refusing rather than defaulting; every call site passes the office of the box it is
 * writing; a self-check joins the run's own warnings; and the guards below assert the CALL TEXT, because the
 * first version of this script let PHP interpolate `$off` inside the replacement strings and would have
 * written `desk_signer_for()` — mangled code that its own guards happily passed. Nothing here runs the
 * pipeline. DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$target = $root.'/tools/seed_full_demo_pipeline.php';
$apply = in_array('--apply', $argv, true);

$baseline = file_get_contents($target);
$fails = 0;
$edits = [];

$swap = function (string $from, string $to, string $label, int $expect = 1) use ($baseline, &$edits, &$fails): void {
    $n = substr_count($baseline, $from);
    if ($n !== $expect) {
        fwrite(STDERR, "REFUSE {$label}: anchor occurs {$n} time(s), expected {$expect}\n");
        $fails++;

        return;
    }
    $at = 0;
    while (($at = strpos($baseline, $from, $at)) !== false) {
        $edits[] = [$at, strlen($from), $to, $label];
        $at += strlen($from);
    }
};

$resolver = <<<'PHP'

/**
 * The account that owns one office's box, looked up by username, refusing when it is not seated.
 *
 * Three call sites used to stamp a literal user id instead, which recorded whichever number this machine's
 * AUTO_INCREMENT happened to hand out (userId 5 here is office4_head; on a fresh install it need not be),
 * and two more used `?? 1`, defaulting to an Admin the write-boundary rule in WorkflowService excludes from
 * every mutation. A signature is the strongest claim a workflow row makes, so the tool stops rather than
 * guesses a signer: a box attributed to an officer who never touched it reads as signed to every desk that
 * opens it, and nothing downstream can tell.
 *
 * A plain function, not a closure: the call sites sit in blocks that capture only the variables they list.
 */
function desk_signer_for(int $officeId): int
{
    static $cache = [];

    if (! isset($cache[$officeId])) {
        $username = 'office'.$officeId.'_head';
        $id = DB::table('staffusers')->where('username', $username)->value('userId');

        if ($id === null) {
            fwrite(STDERR, "REFUSE: no account {$username} is seated, so office {$officeId}'s box cannot be signed by its own desk.\n");
            exit(1);
        }

        $cache[$officeId] = (int) $id;
    }

    return $cache[$officeId];
}
PHP;

$mapLine = "    \$deskSigners[\$deskOffice->value] = \$namedId('staffusers', 'userId', 'username', 'office'.\$deskOffice->value.'_head');\n}";

$swap($mapLine, $mapLine."\n".$resolver, 'the resolver');

// The three literal stamps — each replaced by the office the loop is already holding.
$swap("'signedBy' => \$order === 1 ? 5 : null,",
    "'signedBy' => \$order === 1 ? desk_signer_for(\$off) : null,",
    'site: Grace Tan (office 4 box, literal 5)');

$swap("'signedBy' => \$done ? 3 : null,",
    "'signedBy' => \$done ? desk_signer_for(\$off) : null,",
    'site: Patricia Diaz (three boxes, literal 3)');

$swap("'signedBy' => \$done ? 2 : null,",
    "'signedBy' => \$done ? desk_signer_for(\$off) : null,",
    'site: Gabriel Ramos (four boxes, literal 2)');

// The silent Admin fallbacks.
$swap("'signedBy' => \$ahead ? (\$deskSigners[\$step->officeId] ?? 1) : null,",
    "'signedBy' => \$ahead ? desk_signer_for(\$step->officeId) : null,",
    'site: advance-to-step fallback ?? 1');

$swap("'signedBy' => \$deskSigners[\$step->officeId] ?? 1,",
    "'signedBy' => desk_signer_for(\$step->officeId),",
    'site: fully-worked fallback ?? 1');

$swap("'signedBy' => \$deskSigners[OfficeId::Blocking->value],",
    "'signedBy' => desk_signer_for(OfficeId::Blocking->value),",
    'site: blocking box named through the map');

// Four sites credit the Registrar by number.
$swap("'registrarProcessedBy' => 2,",
    "'registrarProcessedBy' => desk_signer_for(OfficeId::Registrar->value),",
    'site: registrarProcessedBy literal 2', 4);

// The self-check, printed with the run's other integrity warnings.
$selfCheck = <<<'PHP'
// A box signed by an officer from another office is not a cosmetic mismatch: it is a signature the
// application cannot produce, because signStep() refuses it (BR14) on every HTTP path. Printing it here is
// what keeps a future edit of this file from reintroducing one quietly — nothing in tests/ executes
// tools/, PHPStan analyses only app/, and Pint excludes this directory.
$foreignSigners = DB::table('workflowsteps as ws')
    ->join('staffusers as s', 's.userId', '=', 'ws.signedBy')
    ->where('ws.stepStatus', WorkflowStepStatus::Completed->value)
    ->whereRaw('s.officeId <> ws.officeId')
    ->get(['ws.workflowStepId', 'ws.officeId', 'ws.signedBy', 's.username', 's.officeId as signerOffice']);

if ($foreignSigners->isNotEmpty()) {
    $who = $foreignSigners->pluck('username')->implode(', ');

    $deskIntegrity[] = 'Signatures: '.$foreignSigners->count().' completed box(es) name an officer from '
        .'another office ('.$who.') — no desk could have signed them, so the demo data claims a cross-office '
        .'signature the application refuses to write';
}

PHP;

$tailAnchor = "echo \"=======================================================\\n\";\necho \" WHAT EACH DESK WILL SHOW\\n\";";
$swap($tailAnchor, $selfCheck.$tailAnchor, 'the self-check');

printf("edits planned: %d\n", count($edits));
foreach ($edits as [, , $to, $label]) {
    printf("  %-44s %s\n", $label, strlen($to) > 58 ? substr(str_replace("\n", ' ', $to), 0, 56).'…' : str_replace("\n", ' ', $to));
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$prev = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $prev) {
        fwrite(STDERR, "REFUSE: edits overlap\n");
        exit(1);
    }
    $prev = $e[0];
}

$out = $baseline;
foreach ($edits as [$off, $len, $to]) {
    $out = substr_replace($out, $to, $off, $len);
}

$guards = [
    'no literal signedBy stamp remains' => ! preg_match("/'signedBy' => \\\$(?:order === 1 \? \d|done \? \d)/", $out)
        // Two `?? 1` fallbacks were deliberately removed, so this can only ever go down, never up.
        && substr_count($out, '?? 1') <= substr_count($baseline, '?? 1')
        && ! str_contains($out, '$deskSigners[$step->officeId] ?? 1'),
    'no registrarProcessedBy literal remains' => substr_count($out, "'registrarProcessedBy' => 2,") === 0,
    'the resolver is declared once' => substr_count($out, 'function desk_signer_for(int $officeId): int') === 1,
    'and the call text survived un-mangled' => substr_count($out, 'desk_signer_for($off)') === 3
        && substr_count($out, 'desk_signer_for($step->officeId)') === 2
        && substr_count($out, 'desk_signer_for(OfficeId::Blocking->value)') === 1
        && substr_count($out, 'desk_signer_for(OfficeId::Registrar->value)') === 4,
    // Counted from the plan, not guessed: 3 + 2 + 1 + 4 call sites plus the one declaration.
    'every planned use is present' => preg_match_all('/desk_signer_for\(/', $out)
        === preg_match_all('/desk_signer_for\(/', $resolver) + 10,
    'the self-check landed once' => substr_count($out, '$foreignSigners') === 4
        && substr_count($out, "A box signed by an officer from another office") === 1,
    'no mangled empty call was written' => substr_count($out, 'desk_signer_for()') === 0
        && substr_count($out, 'desk_signer_for( === 1') === 0,
    'the untouched map still builds' => substr_count($out, '$deskSigners = [];') === 1,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($target, $root.'/storage/app/scratch/seed_full_demo_pipeline.pre-lane30.php');
file_put_contents($target, $out);
printf("WROTE %s (%+d bytes)\n", $target, strlen($out) - strlen($baseline));
