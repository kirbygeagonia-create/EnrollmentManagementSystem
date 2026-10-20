<?php

/**
 * Lane 54d — prove the demo pipeline's own ledger report can see what the record-state audit refuses.
 *
 * Lane 53 taught `ems:audit:record-states` to refuse two states: a fee sheet quoting a total with no fee line
 * under it, and money sitting on an account that never entered `paid`. The tool that *writes* the demo dataset
 * had no line for either — it repaired them in a loop that reported success either way, so a green pipeline
 * run said nothing about whether its own output had needed january work. Four `Ledger —` lines were added to
 * that report. A report line that can only ever print 0 is decoration, so each one is handed a defect here and
 * required to read non-zero, and the run is required to withhold its success banner (exit 1, measured at
 * tools/seed_full_demo_pipeline.php's own `if ($findings !== [])`).
 *
 *   returning claim with no year behind it        the year-floor widening switched off in a copy of the tool
 *   fee sheet with a total and no lines           one unpriceable sheet inserted after a clean build
 *   paid step this run had to reconstruct         one `assessed -> paid` history row deleted
 *   receipts exceeding the assessment             one receipt inflated (lane 38 planted this; re-planted here)
 *
 * Each case rebuilds its own schema, so exactly one defect is ever in front of a run — a harness that piled
 * four findings onto one database could not say which line each finding moved. The first case is the control:
 * the same build recipe with the real tool must print 0 on all four lines and exit 0, otherwise a non-zero
 * mutant proves nothing about the mutation.
 *
 * These probes migrate whole schemas, so the harness refuses unless DB_DATABASE names a schema it owns.
 * Nothing here touches live `ems`.
 *
 * Usage: DB_DATABASE=ems_l54_a php storage/app/scratch/mutate-lane54-report.php [--only=floor] [--keep]
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\SchoolTime;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$tool = 'tools/seed_full_demo_pipeline.php';
$variant = 'storage/app/scratch/lane54-pipeline-no-year-widening.php';
$demoTerm = (int) (getenv('EMS_L54_TERM') ?: 11);
$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, strlen('--only='));
    }
}

$schema = (string) DB::connection()->getDatabaseName();
$siblings = ['ems_l54_a' => 'ems_l54_b', 'ems_l54_b' => 'ems_l54_a'];

if (! isset($siblings[$schema])) {
    fwrite(STDERR, 'REFUSE: this harness runs `migrate:fresh`. DB_DATABASE must name ems_l54_a or ems_l54_b,'
        .' not \''.$schema.'\'.'."\n");
    exit(1);
}

// The floor case needs a schema the other cases have not been mutated, and the real tool is compared against a
// copy of itself, so both names are held here rather than guessed at each call site.
$buildSchema = $siblings[$schema];
$failures = 0;

/** `artisan <command> --force` against a named schema, from the project root, in cmd's own env. */
$artisan = function (string $command, string $database) use ($php, $root): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && set "DB_DATABASE='.$database.'" && set "DB_CONNECTION=mysql"'
        .' && set "DB_HOST=127.0.0.1" && set "DB_PORT=3306" && set "DB_USERNAME=root" && set "DB_PASSWORD="'
        .' && '.escapeshellarg($php).' artisan '.$command.' --force 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

/** Runs the pipeline (or a copy of it) in its own process so one case cannot inherit another's state. */
$pipeline = function (string $script, string $database) use ($php, $root): array {
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open([$php, $script], $desc, $pipes, $root,
        array_merge((array) getenv(), ['DB_DATABASE' => $database]));
    if (! is_resource($proc)) {
        return [255, 'could not start '.$script];
    }
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($proc), preg_replace('/\e\[[0-9;]*m/', '', $out)];
};

/**
 * The number a report line printed, or null when the line is not on the page at all.
 *
 * Null is a refusal, not a 0: a renamed label would otherwise read as a clean sheet, which is how a checker
 * that matched nothing cleared the tree in lane 46.
 */
$lineCount = function (string $out, string $label): ?int {
    foreach (explode("\n", $out) as $line) {
        if (str_starts_with(trim($line), $label)) {
            return preg_match('/(\d+)\s*$/', $line, $m) === 1 ? (int) $m[1] : null;
        }
    }

    return null;
};

$labels = [
    'floor' => 'Ledger — returning claim with no year behind it',
    'bare' => 'Ledger — fee sheet with a total and no lines',
    'trail' => 'Ledger — paid step this run had to reconstruct',
    'overpaid' => 'Ledger — receipts exceeding the assessment',
];

/** Builds the schema the CI seed step builds, in the same order. */
$build = function (string $database) use ($artisan): bool {
    foreach (['migrate:fresh', 'db:seed', 'db:seed --class=DevReferenceDataSeeder'] as $command) {
        [$code, $out] = $artisan($command, $database);
        if ($code !== 0) {
            printf("  BAD  building %s: `artisan %s` exited %d\n%s\n", $database, $command, $code, substr($out, 0, 500));

            return false;
        }
    }

    return true;
};

$case = function (string $name, callable $body) use ($only, &$failures): void {
    if ($only !== null && ! str_contains($name, $only)) {
        return;
    }
    echo "\nCase {$name}\n";

    // A case that dies is not a case that passed: the first run of this harness threw inside `floor` (its
    // project root was one dirname short) and still left the shell reporting success.
    try {
        $ok = $body();
    } catch (\Throwable $e) {
        printf("  BAD  threw before reaching a verdict: %s\n", $e->getMessage());
        $failures++;

        return;
    }

    if ($ok !== true) {
        $failures++;
    }
};

echo "Schema this harness drives: {$schema} (it rebuilds {$buildSchema} for the control comparison)\n";

$case('control', function () use ($build, $pipeline, $tool, $schema, $buildSchema, $labels, $lineCount, &$failures): bool {
    if (! $build($buildSchema)) {
        return false;
    }

    [$code, $out] = $pipeline($tool, $buildSchema);

    if ($code !== 0) {
        printf("  BAD  the real tool did not pass on a clean schema (exit %d)\n%s\n", $code, substr($out, -2500));
        $failures++;

        return false;
    }

    $wrong = [];
    foreach ($labels as $key => $label) {
        $n = $lineCount($out, $label);
        if ($n !== 0) {
            $wrong[] = $key.'='.var_export($n, true);
        }
    }

    if ($wrong !== []) {
        printf("  BAD  the clean control already lists findings: %s — no mutant below would be attributable\n",
            implode(', ', $wrong));
        $failures++;

        return false;
    }

    printf("  ok   the real tool passes clean on %s: the four Ledger lines this harness owns read 0, exit 0\n", $buildSchema);

    return true;
});

$case('floor', function () use ($build, $pipeline, $tool, $variant, $schema, $buildSchema, $labels, $lineCount, $root, &$failures): bool {
    $src = (string) file_get_contents($root.'/'.$tool);
    $eol = str_contains($src, "\r\n") ? "\r\n" : "\n";
    $from = <<<'PHP'
        ->orWhere(fn ($returning) => $returning
            ->where('termId', $termId)
PHP;
    $to = <<<'PHP'
        ->orWhere(fn ($returning) => $returning
            ->whereRaw('1 = 0')
            ->where('termId', $termId)
PHP;
    $from = str_replace("\n", $eol, $from);
    $to = str_replace("\n", $eol, $to);

    if (substr_count($src, $from) !== 1) {
        printf("  BAD  the widening anchor matched %d times, expected 1 — this mutant would sleep\n",
            substr_count($src, $from));

        return false;
    }

    $mutant = str_replace($from, $to, $src);
    if ($mutant === $src) {
        echo "  BAD  the planted copy is byte-identical to the real tool\n";

        return false;
    }
    file_put_contents($root.'/'.$variant, $mutant);

    if (! $build($schema)) {
        return false;
    }

    [$code, $out] = $pipeline($variant, $schema);
    $n = $lineCount($out, $labels['floor']);

    if ($n === null) {
        printf("  BAD  the report line was not printed at all (exit %d)\n%s\n", $code, substr($out, -1500));

        return false;
    }
    if ($n < 1 || $code === 0) {
        printf("  FAIL the switched-off widening left the line at %d and exit %d — G-11 is not what this case thinks it is\n", $n, $code);
        echo substr($out, -1500), "\n";

        return false;
    }

    printf("  ok   without the widening the report names %d returning claim(s) with no year behind it and refuses (exit %d)\n", $n, $code);

    return true;
});

$case('bare', function () use ($build, $pipeline, $tool, $schema, $labels, $lineCount, $demoTerm, &$failures): bool {
    if (! $build($schema)) {
        return false;
    }
    [$code, $out] = $pipeline($tool, $schema);
    if ($code !== 0) {
        printf("  BAD  the tool did not pass before this case planted anything (exit %d)\n%s\n", $code, substr($out, -1500));

        return false;
    }

    $settled = DB::table('studentassessments as a')
        ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
        ->where('e.termId', $demoTerm)
        ->where('a.totalAssessedAmount', '>', 0)
        ->orderBy('a.assessmentId')
        ->first();

    if ($settled === null) {
        echo "  BAD  no demo-term fee sheet to hang a second one on — the run above built nothing\n";

        return false;
    }

    // A total the demo's own four lines cannot add up to, so the pass must report it rather than invent a set
    // of charges to quiet the number — which is the half of this that is a rule, not a count.
    DB::table('studentassessments')->insert([
        'enrollmentId' => (int) $settled->enrollmentId,
        'assessmentDate' => SchoolTime::today(),
        'totalAssessedAmount' => '12345.67',
        'totalScholarshipCoverage' => '0.00',
        'totalWaived' => '0.00',
        'remainingBalance' => '12345.67',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    [$code, $out] = $pipeline($tool, $schema);
    $n = $lineCount($out, $labels['bare']);

    if ($n === null) {
        printf("  BAD  the report line was not printed at all (exit %d)\n", $code);

        return false;
    }
    if ($n < 1 || $code === 0) {
        printf("  FAIL the unpriceable sheet left the line at %d and exit %d\n", $n, $code);
        echo substr($out, -1500), "\n";

        return false;
    }

    printf("  ok   a sheet no demo lines add up to is named (%d) and left unpriced, and the run refuses (exit %d)\n", $n, $code);

    return true;
});

$case('trail', function () use ($build, $pipeline, $tool, $schema, $labels, $lineCount, $demoTerm, &$failures): bool {
    if (! $build($schema)) {
        return false;
    }
    [$code, $out] = $pipeline($tool, $schema);
    if ($code !== 0) {
        printf("  BAD  the tool did not pass before this case removed a step (exit %d)\n", $code);

        return false;
    }

    $step = DB::table('enrollmentstatushistory as h')
        ->join('enrollments as e', 'e.enrollmentId', '=', 'h.enrollmentId')
        ->where('e.termId', $demoTerm)
        ->where('h.toStatus', 'paid')
        ->orderBy('h.historyId')
        ->first();

    if ($step === null) {
        echo "  BAD  no `paid` step on the demo term — the generator wrote none, which is itself a finding\n";

        return false;
    }

    DB::table('enrollmentstatushistory')->where('historyId', $step->historyId)->delete();

    [$code, $out] = $pipeline($tool, $schema);
    $n = $lineCount($out, $labels['trail']);

    if ($n === null) {
        printf("  BAD  the report line was not printed at all (exit %d)\n", $code);

        return false;
    }
    if ($n < 1 || $code === 0) {
        printf("  FAIL the deleted step left the line at %d and exit %d\n", $n, $code);
        echo substr($out, -1500), "\n";

        return false;
    }

    printf("  ok   a receipt with its `assessed -> paid` step deleted is reconstructed (%d) and reported (exit %d)\n", $n, $code);

    return true;
});

$case('overpaid', function () use ($build, $pipeline, $tool, $schema, $labels, $lineCount, $demoTerm, &$failures): bool {
    if (! $build($schema)) {
        return false;
    }
    [$code] = $pipeline($tool, $schema);
    if ($code !== 0) {
        printf("  BAD  the tool did not pass before this case overpaid a sheet (exit %d)\n", $code);

        return false;
    }

    // An extra receipt, not a perturbed one, and on an account the tool cannot rebalance. This case used to
    // multiply the first demo receipt it found, and lane 56 made that receipt one the tool writes by OR number —
    // so the second run restated the amount, the line stayed at 0, and the harness called a working report broken.
    // The first receipt in the term belongs to the account whose collection the split pass rebalances, so a plant
    // there is swallowed too. A defect the thing under test can heal before measuring is not a planted defect:
    // plant the state, somewhere nothing reconciles.
    $rebalanced = (int) DB::table('payments')->where('orNumber', 'DEMO-OR-TODAY-01')->value('enrollmentId');
    $sheet = DB::table('studentassessments as a')
        ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
        ->where('e.termId', $demoTerm)
        ->where('a.totalAssessedAmount', '>', 0)
        ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payments as p')
            ->whereColumn('p.enrollmentId', 'a.enrollmentId')
            ->whereIn('p.paymentStatus', ['paid', 'partial']))
        ->when($rebalanced > 0, fn ($q) => $q->where('a.enrollmentId', '!=', $rebalanced))
        ->orderBy('a.assessmentId')
        ->first();

    if ($sheet === null) {
        echo "  BAD  no collected fee sheet outside the rebalanced account to overpay\n";

        return false;
    }

    $due = (float) $sheet->totalAssessedAmount - (float) $sheet->totalScholarshipCoverage - (float) $sheet->totalWaived;

    DB::table('payments')->insert([
        'enrollmentId' => $sheet->enrollmentId,
        'orNumber' => 'L54-OVERPAID-'.$sheet->enrollmentId,
        'amount' => $due + 100.00,
        'paymentDate' => App\Support\SchoolTime::today(),
        'paymentMode' => 'cash',
        // Signed by whoever signed this account's real receipt; a sheet with none is the Accounting desk's own,
        // and the column is nullable — the plant differs from the honest dataset in one column, the money.
        'processedBy' => DB::table('payments')->where('enrollmentId', $sheet->enrollmentId)->value('processedBy')
            ?? DB::table('staffusers')->where('officeId', 2)->value('userId'),
        'paymentStatus' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    [$code, $out] = $pipeline($tool, $schema);
    $n = $lineCount($out, $labels['overpaid']);

    if ($n === null) {
        printf("  BAD  the report line was not printed at all (exit %d)\n", $code);

        return false;
    }
    if ($n < 1 || $code === 0) {
        printf("  FAIL the extra receipt left the line at %d and exit %d\n", $n, $code);
        echo substr($out, -1500), "\n";

        return false;
    }

    printf("  ok   money exceeding its sheet is named (%d), not repaired, and the run refuses (exit %d)\n", $n, $code);

    return true;
});

echo "\n", $failures === 0
    ? "Every Ledger line this batch added can be non-zero, and each one was moved by the defect it names.\n"
    : "{$failures} case(s) did not behave — do not report the pipeline's report as proved.\n";

exit($failures === 0 ? 0 : 1);
