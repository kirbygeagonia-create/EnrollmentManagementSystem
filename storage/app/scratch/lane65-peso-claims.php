<?php

/**
 * Lane 65 — the peso figures the authority docx states, bound to what the money tables answer.
 *
 * Every other numeric family in this repository has an instrument: lane 58 binds suite totals, lane 59
 * binds a count to the tool named beside it, lane 60 binds the schema census and ratios over rows,
 * lane 61 reads the status word, lane 62 the artefact, lane 64 the set. Nothing bound a "₱17,500".
 * This binds the ones that can be bound soundly and PRINTS the rest as blind spots rather than
 * counting them clean — a checker that quietly skips what it cannot read is worse than none.
 *
 *   php storage/app/scratch/lane65-peso-claims.php            report
 *   php storage/app/scratch/lane65-peso-claims.php --verify   exit 1 unless every bound claim holds
 *   php … --verify --dump=<file>                              judge a copy (for the mutant harness)
 */

// The imports sit ABOVE the bootstrap on purpose: Pint's fully_qualified_strict_types shortens
// `Illuminate\Contracts\Console\Kernel::class` to `Kernel::class` and files its `use` block after the
// call, which parses, passes `pint --test`, and then fatals at runtime. Run the script after any Pint pass.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$argvv = $_SERVER['argv'];
$verify = in_array('--verify', $argvv, true);
$dump = dirname(__DIR__, 3).'/storage/app/scratch/docx-flat.txt';
foreach ($argvv as $a) {
    if (str_starts_with($a, '--dump=')) {
        $dump = substr($a, 7);
        if (! $verify) {
            fwrite(STDERR, "REFUSE: --dump= is only meaningful with --verify.\n");
            exit(2);
        }
    }
}
if (! is_file($dump)) {
    fwrite(STDERR, "REFUSE: the dump to judge does not exist: {$dump}\n");
    exit(2);
}
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
if (! str_contains($dump, 'mutant') && is_file($docx) && filemtime($dump) < filemtime($docx)) {
    fwrite(STDERR, "REFUSE: {$dump} is older than the docx — re-run dump-docx-flat.php first.\n");
    exit(2);
}

$schema = (string) DB::connection()->getDatabaseName();
$fees = [];
foreach (DB::select('SELECT feeName, defaultAmount, unitBasis FROM feetypes') as $f) {
    // The catalogue row is named "Tuition Fee (per unit)"; the document writes "Tuition ₱1,250/unit".
    // Both rules below compare against the bare word, or the row that proves the claim reads as a blind spot.
    $short = trim((string) preg_replace('/\s*\(.*\)$/u', '', (string) str_replace(' Fee', '', $f->feeName)));
    $fees[$f->feeName] = ['amount' => round((float) $f->defaultAmount, 2), 'basis' => $f->unitBasis, 'short' => $short];
}
$assessed = array_map(fn (stdClass $r): float => round((float) $r->a, 2),
    DB::select('SELECT DISTINCT totalAssessedAmount a FROM studentassessments'));
$balances = array_map(fn (stdClass $r): float => round((float) $r->b, 2),
    DB::select('SELECT DISTINCT remainingBalance b FROM studentassessments'));
$held = array_map(fn (stdClass $r): float => round((float) $r->a, 2),
    DB::select("SELECT DISTINCT amount a FROM payments WHERE paymentStatus IN ('paid','partial')"));
if ($fees === [] || $assessed === []) {
    fwrite(STDERR, "REFUSE: schema '{$schema}' answers no fee types or no assessments — that is not this document's database.\n");
    exit(2);
}

/** An account as the server holds it: sheet total, money held, balance stored. */
$accounts = [];
foreach (DB::select('SELECT s.totalAssessedAmount a, s.remainingBalance b,
        COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.enrollmentId = s.enrollmentId AND p.paymentStatus IN (\'paid\',\'partial\')),0) h
        FROM studentassessments s') as $r) {
    $accounts[] = ['assessed' => round((float) $r->a, 2), 'held' => round((float) $r->h, 2), 'balance' => round((float) $r->b, 2)];
}

$HISTORY = '/\b(20\d\d-\d\d-\d\d|was|were|had been|used to|once|earlier|previously|stale|measured|probed|found|planted|re-read)\b/iu';
$ILLUSTRATIVE = '/(a school that charges|from ₱|to ₱|every amount|each coverage|if the school|would be|says ₱|cannot be ₱)/iu';

$flat = explode("\n", (string) file_get_contents($dump));
$bad = [];
$seenTriples = [];
$blind = [];
$bound = 0;
$named = 0;
$illus = 0;
$seen = 0;

foreach ($flat as $i => $line) {
    // preg_match_all answers the COUNT, so `!== 1` would silently skip every row carrying more than one
    // figure — which is every fee table row. The bound-zero guard below caught this on its first run.
    if (preg_match_all('/₱\s?([0-9][0-9,]*(?:\.\d\d)?)/u', $line, $m) < 1) {
        continue;
    }
    $ln = $i + 1;
    // Sentences, not windows: a figure is excused by the tense of ITS OWN sentence, never by a date that
    // happens to sit in the neighbouring one (lane 58's rule for suite claims, applied to money).
    $sentences = preg_split('/(?<=[.!?]) /u', $line) ?: [];
    foreach (array_unique($m[1]) as $amt) {
        $seen++;
        $ctx = '';
        foreach ($sentences as $s) {
            if (str_contains($s, '₱'.$amt)) {
                $ctx = $s;
                break;
            }
        }
        if ($ctx === '') {
            $ctx = mb_substr($line, max(0, (int) strpos($line, '₱'.$amt) - 120), 260, 'UTF-8');
        }
        $ctx = preg_replace('/\s+/u', ' ', $ctx);
        $value = (float) str_replace(',', '', $amt);
        if (preg_match($ILLUSTRATIVE, $ctx) === 1) {
            $illus++;

            continue;
        }
        if (preg_match($HISTORY, $ctx) === 1) {
            $named++;

            continue;
        }
        // The arithmetic sentences: "₱8,000 taken against a ₱18,500 sheet that still owes ₱10,500" and
        // "₱8,000.00 held against a ₱18,500.00 sheet leaves ₱10,500.00". Checked as a triple — paid plus
        // owing must equal the sheet, and that account must exist — because each figure on its own would
        // still match some row on the server after the arithmetic went wrong.
        if (preg_match('/₱([0-9][0-9,]*(?:\.\d\d)?)\s+(?:taken|held|paid)?\s*against a ₱([0-9][0-9,]*(?:\.\d\d)?)\s+sheet (?:that still owes|leaves|with) ₱([0-9][0-9,]*(?:\.\d\d)?)/u', $ctx, $tr) === 1) {
            $paid = (float) str_replace(',', '', $tr[1]);
            $sheet = (float) str_replace(',', '', $tr[2]);
            $owing = (float) str_replace(',', '', $tr[3]);
            $sums = abs($paid + $owing - $sheet) < 0.005;
            $holds = false;
            foreach ($accounts as $a) {
                if (abs($a['assessed'] - $sheet) < 0.005 && abs($a['held'] - $paid) < 0.005 && abs($a['balance'] - $owing) < 0.005) {
                    $holds = true;
                    break;
                }
            }
            $bound++;
            $tripleKey = $ln.'|'.$tr[1].'|'.$tr[2].'|'.$tr[3];
            if (! $sums && ! isset($seenTriples[$tripleKey])) {
                $seenTriples[$tripleKey] = true;
                $bad[] = "line {$ln}: the sentence states ₱{$tr[1]} against a ₱{$tr[2]} sheet leaving ₱{$tr[3]}, which does not add up";
            } elseif (! $holds && ! isset($seenTriples[$tripleKey])) {
                $seenTriples[$tripleKey] = true;
                $bad[] = "line {$ln}: no account on '{$schema}' is assessed {$sheet} with {$paid} held and {$owing} owing";
            }

            continue;
        }
        // A fee named in the same sentence as its catalogue row must equal that row.
        $feeHit = null;
        foreach ($fees as $name => $f) {
            if (stripos($ctx, $f['short']) !== false && abs($f['amount'] - $value) < 0.005) {
                $feeHit = $name;
                break;
            }
        }
        if ($feeHit !== null) {
            $bound++;

            continue;
        }
        // A fee named in the sentence whose amount the server states differently is a false claim. The
        // name must be adjacent to THIS figure: an earlier draft matched "Miscellaneous ₱" anywhere in the
        // row and so accused the document of pairing Miscellaneous with Tuition's own amount.
        foreach ($fees as $name => $f) {
            $short = $f['short'];
            if (abs($f['amount'] - $value) >= 0.005
                && preg_match('/'.preg_quote($short, '/').'\s*₱\s?'.preg_quote($amt, '/').'/iu', $ctx) === 1) {
                $bad[] = "line {$ln}: the text pairs \"{$short}\" with ₱{$amt} while feetypes answers ".number_format($f['amount'], 2);
                $bound++;

                continue 2;
            }
        }
        // An account triple: "<held> … <sheet> … leaves <balance>" or any pair of them.
        foreach ($accounts as $a) {
            if (abs($a['balance'] - $value) < 0.005 || abs($a['assessed'] - $value) < 0.005 || abs($a['held'] - $value) < 0.005) {
                $bound++;

                continue 2;
            }
        }
        if (in_array($value, $assessed, true) || in_array($value, $balances, true) || in_array($value, $held, true)) {
            $bound++;

            continue;
        }
        $blind[] = ":{$ln} ₱{$amt}  ".preg_replace('/\s+/u', ' ', mb_substr($ctx, 0, 150));
    }
}

printf("schema %s | feetypes %d | assessments %d | ₱ tokens read: %d = bound %d + history %d + illustrative %d + unbound %d\n",
    $schema, count($fees), count($accounts), $seen, $bound, $named, $illus, count($blind));
if ($verify && $bound === 0) {
    fwrite(STDERR, "REFUSE: the run bound ZERO peso claims. A parser that matches nothing and a document with no claims look identical here, so a clean verdict would be decoration.\n");
    exit(2);
}
echo "\n--- present-tense peso figures this run could NOT bind (read them; they are not cleared) ---\n";
if ($blind === []) {
    echo "  none — and that is only meaningful because the bound count above is non-zero\n";
}
foreach (array_slice($blind, 0, 25) as $b) {
    echo '  '.$b."\n";
}
printf("  (%d unbound)\n\n", count($blind));

if ($bad !== []) {
    echo 'VERDICT: REFUSED — '.count($bad)." peso claim(s) disagree with the money tables\n";
    foreach ($bad as $b) {
        echo '  * '.$b."\n";
    }
    exit($verify ? 1 : 0);
}
echo $verify
    ? "VERDICT: every peso claim this checker binds equals what the money tables answer on '{$schema}'.\n"
    : "VERDICT: report only — pass --verify to make a disagreement refuse.\n";
exit(0);
