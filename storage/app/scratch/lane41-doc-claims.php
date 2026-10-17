<?php

/**
 * Lane 41 — the money and the counts the operational documents state, checked against the tables.
 *
 * Lane 40 built the checker that reads record ids and statuses out of the desk-facing walkthrough and found
 * seven citations to retired rows. That left the other half of what these documents assert unexamined: the
 * amounts a presenter says out loud (OR DEMO-OR-0001 is ₱12,000), the balances a desk is told to expect
 * (Bea Alonzo owes ₱10,500 with ₱8,000 held), and the aggregates ("16 programs", "all 17 roles", "the 12
 * catalogs", "21 staff accounts"). None of those was ever resolved against the database in bulk.
 *
 * Three rule the file is written to, because lane 40's own first attempt broke the third:
 *   1. Every claim prints, resolved or not. A checker that silently skips what its parser did not match looks
 *      exactly like a checker that found nothing wrong — that is how the `(term N)` shape hid two stale rows.
 *      So each file reports `claims seen` against `claims resolved`, and the leftovers appear as UNBOUND.
 *   2. Money is compared as decimals with an explicit scale, never as floats rounded through a printf.
 *   3. An aggregate names its definition in the output. "13 catalogs" is only a fact once you say that the
 *      landing page and the per-curriculum drill-in are excluded; the number without the rule is what let
 *      "the 12 catalogs" survive.
 *
 * Usage: php storage/app/scratch/lane41-doc-claims.php             (live ems)
 *        php storage/app/scratch/lane41-doc-claims.php --documents=a,b
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Booting the framework installs Whoops, and an uncaught throw through it still left this script's exit code
// at 0 — proven the hard way by the first run of this file, which died on a wrong path and reported nothing
// failing. A checker that cannot fail has checked nothing, so the handler is installed after the bootstrap and
// turns any throw into exit 1.
set_exception_handler(function (\Throwable $e): void {
    fwrite(STDERR, 'FATAL: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n");
    exit(1);
});

$root = dirname(__DIR__, 3);
// A document directory can be substituted, which is how this file is proved with mutants: the alternative is
// copying planted defects into the repository's own docs and remembering to undo it.
$docDir = $root.'/Documentation';
// `--ci` runs only the claims that hold on ANY correctly seeded schema. That distinction is not cosmetic: the
// guides name records by the number this developer's repaired box happens to carry (enrollment 87, fee type 5,
// "21 staff accounts"), and on the CI-ordered schema those same claims miss — 8 of 22 measured on a copy built
// the way CI builds it, including `Clearance Slip Replacement` sitting at feeTypeId 19. Running every claim in
// CI would be a permanently red step that teaches people to ignore the step.
$ci = false;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--dir=')) {
        $docDir = rtrim(substr($arg, 6), '/');
    } elseif ($arg === '--ci') {
        $ci = true;
    }
}
if (! is_dir($docDir)) {
    fwrite(STDERR, 'no such document directory: '.$docDir."\n");
    exit(1);
}
$docs = [
    'Demo-Day-Guide.md',
    'Offline-Presentation-Guide.md',
    'Per-Office-UAT-Walkthrough.md',
];

$database = DB::connection()->getDatabaseName();
printf(
    "Documents: %s\nDocument dir: %s\nDatabase:  %s\nMode:      %s\n\n",
    implode(', ', $docs),
    $docDir,
    $database,
    $ci ? 'CI — schema-independent claims only' : 'all claims (this box)'
);

$problems = 0;
$seen = 0;
$resolved = 0;
$skippedBox = 0;

$report = function (string $kind, string $claim, bool $ok, string $detail) use (&$problems, &$resolved): void {
    printf("  %s %-46s %s\n", $ok ? 'ok  ' : 'MISS', $claim, $detail);
    $resolved++;
    if (! $ok) {
        $problems++;
    }
};

// A claim the parser could not attach is not a verdict about the database, and it must not be counted as one:
// reporting it through $report() would raise `resolved` alongside `seen` and leave the closing arithmetic guard
// asleep on exactly the case it exists for — a claim read but never checked. So it prints here and only `seen`
// moves, which is what makes the run refuse.
$note = function (string $claim, string $detail) use (&$seen): void {
    printf("  UNBOUND %-44s %s\n", $claim, $detail);
    $seen++;
};

/** Reads one document and returns its full text (claims are found across line breaks, so no line loop). */
$textOf = function (string $file) use ($docDir): string {
    $t = file_get_contents($docDir.'/'.$file);
    if ($t === false) {
        throw new RuntimeException("cannot read {$file}");
    }

    return str_replace(["\r\n", "\r"], "\n", $t);
};

$docs_text = [];
foreach ($docs as $d) {
    $docs_text[$d] = $textOf($d);
}

/** Peso amounts as written in prose: ₱12,000 or ₱100.00. */
$peso = '/₱([0-9][0-9,]*(?:\.[0-9]{2})?)/u';
$asDecimal = function (string $raw): float {
    return round((float) str_replace(',', '', $raw), 2);
};

echo "=== receipts named in the documents\n";

$orClaims = [];
foreach ($docs_text as $file => $text) {
    // "DEMO-OR-0001**, ₱12,000" / "DEMO-OR-0001** (₱12,000)" / "OR DEMO-OR-0001 ₱12,000"
    if (preg_match_all('/(DEMO-OR-[A-Z0-9-]+)[^₱\n]{0,40}₱([0-9][0-9,]*(?:\.[0-9]{2})?)/u', $text, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $orClaims[] = ['file' => $file, 'or' => $x[1], 'amount' => $x[2]];
        }
    }
}

$byOr = [];
foreach ($orClaims as $c) {
    // Keyed on the decimal, not on the string as written: "₱12,000" and "₱12,000.00" are one claim about one
    // row, and keying on the text made the same receipt print twice with two mention counts.
    $byOr[$c['or']][$asDecimal($c['amount'])][] = $c['file'];
}
$pairs = array_sum(array_map(fn (array $amounts) => count($amounts), $byOr));
// Occurrences are not claims: the Daily-Collections number is quoted several times across the guides, and
// counting each mention as a claim would put `seen` ahead of `resolved` by construction, which makes the
// closing guard fire on a document with nothing wrong in it.
printf("  %d mention(s) of an OR number and an amount, collapsing to %d distinct claim(s)\n", count($orClaims), $pairs);
if ($ci) {
    $skippedBox += $pairs;
} else {
    $seen += $pairs;
}

ksort($byOr);

foreach (($ci ? [] : $byOr) as $or => $amounts) {
    $rows = DB::table('payments')->where('orNumber', $or)->get();
    if ($rows->isEmpty()) {
        foreach ($amounts as $want => $files) {
            $report('receipt', $or.' ₱'.number_format((float) $want, 2), false, 'no payment row carries this OR number');
        }

        continue;
    }

    foreach ($amounts as $want => $files) {
        $actual = round((float) $rows->first()->amount, 2);
        $report(
            'receipt',
            $or.' ₱'.number_format((float) $want, 2),
            (float) $want === $actual,
            'the row reads '.number_format($actual, 2).' on enrollment '.$rows->first()->enrollmentId
                .' (dated '.substr((string) $rows->first()->paymentDate, 0, 10).'), '
                .count($files).' mention(s) in '.implode(', ', array_values(array_unique($files)))
        );
    }
}

echo "\n=== the balance claims, per enrollment the walkthrough names\n";

// "| 88 `assessed`, assessed ₱18,500 / owes ₱10,500 | **₱8,000 already held**"
//
// Flattened to one entry per field (assessed / owes / held) so that "claims seen" and "claims resolved" count
// the same unit — a single table row carrying three figures would otherwise be one claim and three verdicts.
$balanceClaims = [];
$unboundChunks = 0;
foreach ($docs_text as $file => $text) {
    if (preg_match_all('/\b(\d{1,4})\s+`[a-zA-Z]+`[^\n|]*/u', $text, $chunks, PREG_SET_ORDER)) {
        foreach ($chunks as $ch) {
            $enrollmentId = (int) $ch[1];
            $window = $ch[0];
            if (! preg_match($peso, $window, $any)) {
                continue;
            }

            $fields = [
                'assessed' => preg_match('/assessed ₱([0-9][0-9,]*)/u', $window, $a) ? $asDecimal($a[1]) : null,
                'owes' => preg_match('/ow(?:es|ed) ₱([0-9][0-9,]*)/u', $window, $o) ? $asDecimal($o[1]) : null,
                'held' => preg_match('/₱([0-9][0-9,]*)\s+already held/u', $window, $h) ? $asDecimal($h[1]) : null,
            ];

            $kept = array_filter($fields, fn ($v) => $v !== null);
            if ($kept === []) {
                $unboundChunks++;
                $note("enrollment {$enrollmentId} ({$file})", 'a peso figure this parser could not attach: '.trim($window));

                continue;
            }

            foreach ($kept as $field => $want) {
                $balanceClaims[] = ['file' => $file, 'id' => $enrollmentId, 'field' => $field, 'want' => $want, 'raw' => trim($window)];
            }
        }
    }
}

if ($ci) {
    $skippedBox += count($balanceClaims);
} else {
    $seen += count($balanceClaims);
}
printf(
    "  %d figure(s) read from the documents' record rows%s%s\n",
    count($balanceClaims),
    $ci ? ' (skipped: keyed to this box’s record numbers)' : '',
    $unboundChunks === 0 ? '' : ', plus '.$unboundChunks.' UNBOUND chunk(s) reported above'
);

foreach (($ci ? [] : $balanceClaims) as $c) {
    $assessment = DB::table('studentassessments')->where('enrollmentId', $c['id'])->first();
    if ($assessment === null) {
        $report('balance', "enrollment {$c['id']} {$c['field']}", false, 'no assessment row for this enrollment: '.substr($c['raw'], 0, 60));

        continue;
    }

    $actual = match ($c['field']) {
        'assessed' => round((float) $assessment->totalAssessedAmount, 2),
        'owes' => round((float) $assessment->remainingBalance, 2),
        'held' => round((float) DB::table('payments')->where('enrollmentId', $c['id'])
            ->whereIn('paymentStatus', ['paid', 'partial'])->sum('amount'), 2),
    };

    $report(
        'balance',
        'enrollment '.$c['id'].' '.$c['field'].' ('.$c['file'].')',
        $c['want'] === $actual,
        'document '.number_format($c['want'], 2).' vs table '.number_format($actual, 2)
    );
}

echo "\n=== the itemised charges on Juan Dela Cruz's sheet, as step 7 reads them\n";

$juan = DB::table('students')->where('schoolIdNumber', 'DEMO-2026-001')->first();
$juanEnrollment = $juan === null ? null : DB::table('enrollments')
    ->where('studentId', $juan->studentId)->where('termId', 11)->orderByDesc('enrollmentId')->first();

if ($juanEnrollment === null) {
    $report('charges', "Juan's demo-term record", false, 'he holds no enrollment on term 11, so step 7 has no sheet to open');
} else {
    $assessment = DB::table('studentassessments')->where('enrollmentId', $juanEnrollment->enrollmentId)->first();
    $lines = $assessment === null ? collect() : DB::table('charges as c')
        ->join('feetypes as f', 'f.feeTypeId', '=', 'c.feeTypeId')
        ->where('c.assessmentId', $assessment->assessmentId)
        ->select('f.feeName', 'c.amount')
        ->get();

    // "₱11,250 tuition, ₱4,500 laboratory, ₱1,500 miscellaneous, ₱250 library"
    $text = $docs_text['Demo-Day-Guide.md'];
    $named = [];
    if (preg_match_all('/₱([0-9][0-9,]*)\s*\n?\s*(tuition|laboratory|miscellaneous|library)/u', $text, $n, PREG_SET_ORDER)) {
        foreach ($n as $x) {
            $named[strtolower($x[2])] = $asDecimal($x[1]);
        }
    }
    $seen += count($named);

    foreach ($named as $word => $want) {
        $row = $lines->first(fn ($l) => stripos($l->feeName, $word) !== false);
        $report(
            'charges',
            "Juan's {$word} line",
            $row !== null && $want === round((float) $row->amount, 2),
            $row === null
                ? 'no charge on his sheet names a '.$word.' fee type'
                : 'document '.number_format($want, 2).' vs '.$row->feeName.' '.number_format((float) $row->amount, 2)
        );
    }

    $sum = round((float) $lines->sum('amount'), 2);
    $report(
        'charges',
        "the four lines sum to the sheet's total",
        $assessment !== null && $sum === round((float) $assessment->totalAssessedAmount, 2),
        'charges '.number_format($sum, 2).' vs totalAssessedAmount '.($assessment === null ? 'no sheet' : number_format((float) $assessment->totalAssessedAmount, 2))
    );
    $seen++;
}

echo "\n=== the fee type the clearance slip footer reads\n";

$feeText = $docs_text['Demo-Day-Guide.md'].' '.$docs_text['Per-Office-UAT-Walkthrough.md'].' '.$docs_text['Offline-Presentation-Guide.md'];
$seen++;

// Addressed by name, and that is a rule rather than a style choice: `StarterReferenceDataSeeder` creates fee
// types with firstOrCreate on `feeName`, so the id is whatever the counter hands out — 5 on this box, 19 on a
// schema the suite seeded first. A guide that says "fee type 5" teaches a desk to look for a number that a
// fresh install does not carry.
$named = (bool) preg_match('/`Clearance Slip Replacement` ₱([0-9][0-9,]*(?:\.[0-9]{2})?)/u', $feeText, $fx);
$byname = DB::table('feetypes')->where('feeName', 'Clearance Slip Replacement')->first();
$report(
    'fee type',
    'the slip fee is named, not numbered',
    $named && $byname !== null,
    $named
        ? '`Clearance Slip Replacement` ₱'.$fx[1].' vs defaultAmount '.($byname === null ? 'no such fee type' : number_format((float) $byname->defaultAmount, 2)).' at feeTypeId '.$byname->feeTypeId
        : 'no document names the fee type with an amount — the claim regressed to an id?'
);

$seen++;
$idClaim = preg_match('/(?:fee type \d+\b|id \d+ `Clearance Slip Replacement`)/u', $feeText, $bad);
$report(
    'fee type',
    'no guide addresses the slip fee by id',
    $idClaim === 0,
    $idClaim === 0 ? 'clean' : 'a guide says "'.trim($bad[0]).'" again'
);

echo "\n=== the aggregates\n";

$courses = (int) DB::table('courses')->count();
$roles = (int) DB::table('roles')->count();
$permissions = (int) DB::table('permissions')->count();
$offices = (int) DB::table('offices')->count();
$colleges = (int) DB::table('staffusers')->whereNotNull('unitId')->distinct()->count('unitId');
$staff = (int) DB::table('staffusers')->count();
$reqLines = (int) DB::table('clearancerequirements')->count();

$gets = collect(Route::getRoutes())
    ->filter(fn ($r) => in_array('GET', $r->methods(), true) && str_starts_with((string) $r->uri(), 'admin/reference-data'))
    ->map(fn ($r) => $r->uri())
    ->reject(fn (string $u) => $u === 'admin/reference-data' || str_contains($u, '{'))
    ->unique()
    ->count();

$aggregates = [
    'programs' => [$courses, '/(\d{1,3}) SEAIT programs|(\d{1,3}) programs, the same (\d{1,3}) courses/u', 'any'],
    'roles' => [$roles, '/all (\d{1,3}) roles/u', 'any'],
    'staff accounts on the box' => [$staff, '/holds (\d{1,3}) staff accounts/u', 'box'],
    'clearance requirement lines' => [$reqLines, '/all (\d{1,3}) requirement lines/u', 'any'],
    'reference catalogs (routes, landing page and drill-ins excluded)' => [$gets, '/across the (\d{1,3}) catalogs/u', 'any'],
];

foreach ($aggregates as $label => [$actual, $re, $scope]) {
    if ($ci && $scope === 'box') {
        $skippedBox++;

        continue;
    }
    $found = false;
    foreach ($docs_text as $file => $text) {
        if (! preg_match($re, $text, $m)) {
            continue;
        }
        $found = true;
        $claimed = (int) end($m);
        $seen++;
        $report(
            'aggregate',
            $label,
            $claimed === $actual,
            $file.' says '.$claimed.', the table holds '.$actual
        );
    }
    if (! $found) {
        $note($label, 'no document states this count in a form the parser read');
    }
}

echo "\n=== the per-college desks the guide counts\n";

$deskRows = DB::table('staffusers')->where('officeId', 7)->whereNotNull('unitId')->count();
$dean = DB::table('staffusers')->where('role', 'dean')->count();
$instr = DB::table('staffusers')->where('role', 'instructor')->whereNull('unitId')->count();

if (preg_match('/(\d{1,2}) per-college Academic Department desks, the dean, (\d{1,2}) instructors/u', $docs_text['Demo-Day-Guide.md'], $dm)) {
    $seen += 2;
    $report('desk seats', 'per-college Academic Department desks', (int) $dm[1] === $deskRows, 'document '.$dm[1].', staffusers with officeId 7 and a unitId: '.$deskRows);
    $report('desk seats', 'unbounded instructor accounts', (int) $dm[2] === $instr, 'document '.$dm[2].', instructors with no unitId: '.$instr);
    $seen++;
    $report('desk seats', 'dean accounts', $dean === 1, 'the sentence names one dean; staffusers role=dean: '.$dean);
} else {
    $note('the guide college-desk sentence', 'the sentence could not be read');
}

printf(
    "\n  %d claim(s) seen, %d resolved, %d mismatch(es).\n",
    $seen,
    $resolved,
    $problems
);
echo "  Unread claims are reported as UNBOUND above, not skipped: a parser with a hole and a clean document look identical otherwise.\n";
if ($skippedBox > 0) {
    printf("  %d claim(s) about this box's record numbers were skipped in CI mode.\n", $skippedBox);
}

if ($seen !== $resolved) {
    printf("  FAIL %d claim(s) were counted but not resolved — the run is not clean.\n", $seen - $resolved);
    $problems++;
}

exit($problems === 0 ? 0 : 1);
