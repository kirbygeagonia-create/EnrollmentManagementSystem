<?php

/**
 * Lane 64 — a section's MEMBERSHIP, judged against COUNT(*) rather than remembered.
 *
 * §24.17 is titled "Tables that are empty in the demo database". Pass 179 rewrote five of its rows
 * because those tables hold rows now, and named the domain empty set in its intro — and then stated
 * the split twice more, wrongly: the intro says "the last six of them framework tables" (the six
 * framework ones are not the last six of an alphabetical list), and §24.18's row still says "8 domain
 * tables are empty in the seeded database". Neither is a figure any existing checker binds: lane 60
 * counts tables and columns, lane 61 reads verdict words, lane 59 binds numbers to the tool that
 * prints them. This binds the sets themselves.
 *
 * information_schema.TABLES.TABLE_ROWS is an InnoDB ESTIMATE — it reported clearanceperiods as 0 while
 * every clearance row joins to it — so every base table is counted with COUNT(*).
 *
 * Report mode writes nothing and refuses nothing:
 *   php lane64-empty-table-inventory.php
 * Verify mode exits 1 unless the document's claims equal the server's truth:
 *   php lane64-empty-table-inventory.php --verify
 *   php lane64-empty-table-inventory.php --verify --dump=<file>   (judge a copy, for the mutant harness)
 * The framework partition is derived from the bundled 0001_ migrations, never recalled from a list.
 */

require_once dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$argvv = $_SERVER['argv'];
$verify = in_array('--verify', $argvv, true);
$dumpOpt = null;
foreach ($argvv as $a) {
    if (str_starts_with($a, '--dump=')) {
        $dumpOpt = substr($a, 7);
    }
}
if ($dumpOpt !== null && ! $verify) {
    fwrite(STDERR, "REFUSE: --dump= is only meaningful with --verify — judging a copy without refusing on it would let a mutant run report a clean verdict.\n");
    exit(2);
}
$dumpPath = $dumpOpt !== null ? $dumpOpt : dirname(__DIR__, 3).'/storage/app/scratch/docx-flat.txt';
if (! is_file($dumpPath)) {
    fwrite(STDERR, "REFUSE: the flat dump to judge does not exist: {$dumpPath}\n");
    exit(2);
}
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
if ($dumpOpt === null && is_file($docx) && filemtime($dumpPath) < filemtime($docx)) {
    fwrite(STDERR, "REFUSE: {$dumpPath} is older than the docx — re-run dump-docx-flat.php before believing a verdict.\n");
    exit(2);
}

$tables = array_map(
    fn (stdClass $r): string => $r->TABLE_NAME,
    DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")
);
$schema = (string) DB::connection()->getDatabaseName();
if ($verify && count($tables) < 50) {
    fwrite(STDERR, "REFUSE: schema '{$schema}' holds ".count($tables).' base tables; this document describes 63. A schema this project has not migrated cannot answer a membership claim.\n');
    exit(2);
}

$counts = [];
foreach ($tables as $t) {
    $counts[$t] = (int) DB::select("SELECT COUNT(*) c FROM `{$t}`")[0]->c;
}
$empty = array_keys(array_filter($counts, fn (int $c): bool => $c === 0));
sort($empty);

// The framework partition: tables the bundled 0001_ migrations create, plus `migrations` itself,
// which the framework writes rather than any migration in this repository.
$fw = ['migrations'];
foreach (glob(dirname(__DIR__, 3).'/database/migrations/0001_*.php') ?: [] as $mf) {
    $src = (string) file_get_contents($mf);
    $up = preg_split('/public function down\(\)/', $src)[0];
    if (preg_match_all("/Schema::create\('([a-zA-Z_]+)'/", $up, $mm) === false) {
        continue;
    }
    foreach ($mm[1] as $created) {
        $fw[] = $created;
    }
}
$fw = array_values(array_unique($fw));
$frameworkEmpty = array_values(array_intersect($empty, $fw));
$domainEmpty = array_values(array_diff($empty, $fw));
sort($frameworkEmpty);
sort($domainEmpty);

$wordNum = ['no' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6,
    'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12];

printf("schema: %s   base tables: %d   COUNT(*)=0: %d\n", $schema, count($tables), count($empty));
printf("  framework empty (%d): %s\n", count($frameworkEmpty), implode(', ', $frameworkEmpty));
printf("  domain    empty (%d): %s\n", count($domainEmpty), implode(', ', $domainEmpty));

$bad = [];   // refusals in verify mode
$flat = explode("\n", (string) file_get_contents($dumpPath));

// --- locate §24.17 by its heading, never by a line ordinal (a generated ordinal moves) ---------
$head = null;
foreach ($flat as $i => $line) {
    if (preg_match('/^P \d+: 24\.17 Tables that are empty/', $line) === 1) {
        $head = $i;
    }
}
if ($head === null) {
    $bad[] = 'the §24.17 heading "24.17 Tables that are empty in the demo database" is not in the dump — the section was retitled or the dump is not this document\'s';
    echo "\n";
} else {
    $intro = '';
    $rows = [];
    foreach (array_slice($flat, $head + 1) as $line) {
        if (preg_match('/^ROW \d+: (.*)$/', $line, $m) === 1) {
            $cells = array_map('trim', explode(' | ', $m[1]));
            if ($cells[0] !== 'Table') {
                $rows[$cells[0]] = $cells;
            }

            continue;
        }
        if (preg_match('/^P \d+: (.*)$/', $line, $m) === 1) {
            if ($intro === '') {
                $intro = $m[1];
            } else {
                break;
            }
        }
    }
    echo "\n--- §24.17 intro, as the document words it ---\n".$intro."\n";

    $claimedEmpty = null;
    if (preg_match('/COUNT\(\*\) is 0 are (.+?) — /u', $intro, $m) === 1) {
        $claimedEmpty = array_values(array_unique(array_map('trim', explode(',', $m[1]))));
    } else {
        $bad[] = "the intro states no list of COUNT(*)-zero tables; the sentence the checker binds is not there (rewriting it must not silently unschedule the claim)";
    }
    $fwNum = null;
    $fwWord = null;
    if (preg_match('/\b(\d+|no|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve) of them\b/u', $intro, $m) === 1) {
        $fwNum = ctype_digit($m[1]) ? (int) $m[1] : ($wordNum[$m[1]] ?? null);
        $fwWord = $m[1];
    } else {
        $bad[] = 'the intro states no count of framework tables inside the empty set';
    }
    $claimedFw = null;
    if (preg_match('/framework tables[^.(]*\(([^)]*)\)/u', $intro, $m) === 1) {
        $claimedFw = array_values(array_unique(array_map('trim', explode(',', $m[1]))));
    } else {
        $bad[] = 'the intro does not NAME the framework tables inside the empty set — "the last N of them" cannot be checked and is how a positional claim goes stale';
    }
    $claimedDomain = null;
    if (preg_match('/domain set this section is about is ([^.]*)\./u', $intro, $m) === 1) {
        $parts = preg_split('/, | and /u', trim($m[1]));
        $claimedDomain = array_values(array_filter(array_map('trim', $parts), fn (string $s): bool => $s !== ''));
    } else {
        $bad[] = 'the intro states no domain empty set';
    }

    $say = function (string $label, ?array $claim, array $server) use (&$bad, $verify): void {
        if ($claim === null) {
            return;
        }
        sort($claim);
        $d = array_merge(array_diff($claim, $server), array_diff($server, $claim));
        printf("%-22s document: %-58s server: %-58s %s\n", $label, implode(', ', $claim), implode(', ', $server),
            $d === [] ? 'MATCHES' : 'DIFFERS ('.implode(', ', $d).')');
        if ($d !== [] && $verify) {
            $bad[] = "{$label}: the document's set is not the server's set — differs on ".implode(', ', $d);
        }
    };
    echo "\n--- sets ---\n";
    $say('all COUNT(*)=0 tables', $claimedEmpty, $empty);
    $say('framework inside it', $claimedFw, $frameworkEmpty);
    $say('domain inside it', $claimedDomain, $domainEmpty);
    if ($fwNum !== null) {
        printf("%-22s document: %-58s server: %-58s %s\n", 'framework count', $fwWord.' of them', count($frameworkEmpty),
            $fwNum === count($frameworkEmpty) ? 'MATCHES' : 'DIFFERS (server says '.count($frameworkEmpty).')');
        if ($fwNum !== count($frameworkEmpty) && $verify) {
            $bad[] = "framework count: the intro says \"{$fwWord} of them\", the server answers ".count($frameworkEmpty);
        }
    }

    // --- the section's rows ---------------------------------------------------------------------
    echo "\n--- §24.17 rows (".count($rows).' rows) ---'."\n";
    foreach ($rows as $name => $cells) {
        if (! array_key_exists($name, $counts)) {
            printf("  %-24s %-8s NOT A TABLE of schema '%s'\n", $name, '', $schema);
            if ($verify) {
                $bad[] = "row for '{$name}': no such table in this schema";
            }

            continue;
        }
        $body = strtolower(implode(' ', array_slice($cells, 1)));
        $claimsEmpty = preg_match('/\b(is empty|was empty|empty in the demo|has no rows|holds no rows|carries no rows|no rows)\b/u', $body) === 1
            && preg_match('/not empty|no longer|was (?:already )?(?:stale|wrong)|contradicted|corrections/iu', $body) !== 1;
        $isDomainEmpty = in_array($name, $domainEmpty, true);
        $verdict = match (true) {
            $claimsEmpty && $counts[$name] > 0 => 'EMPTY-CLAIM OVER ROWS THAT EXIST',
            ! $claimsEmpty && $counts[$name] === 0 && ! $isDomainEmpty => 'framework/extra row',
            default => 'reads true',
        };
        printf("  %-24s %6d  %-30s %s\n", $name, $counts[$name], $verdict, substr($cells[1] ?? '', 0, 46));
        if ($verdict === 'EMPTY-CLAIM OVER ROWS THAT EXIST' && $verify) {
            $bad[] = "row for '{$name}': the row states it holds nothing while COUNT(*) answers {$counts[$name]}";
        }
    }
    $noRow = array_values(array_diff($domainEmpty, array_keys($rows)));
    if ($noRow !== []) {
        echo 'domain tables with rows=0 that the section names in its intro but carries no row for: '.implode(', ', $noRow)."\n";
        if ($verify) {
            $bad[] = 'missing row: '.implode(', ', $noRow).' — the section is titled after them';
        }
    }
}

// --- every other place in the document that counts the domain empty set -------------------------
echo "\n--- other emptiness-membership claims in the document ---\n";
$others = 0;
foreach ($flat as $line) {
    if (preg_match('/\b(\d+|no|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)[ -]domain tables? (?:are|were|stand) empty/u', $line, $m) === 1) {
        $n = ctype_digit($m[1]) ? (int) $m[1] : ($wordNum[$m[1]] ?? null);
        $where = preg_match('/^(\w+ \d+):/', $line, $w) === 1 ? $w[1] : '?';
        printf("  %s claims %-6s server domain-empty = %d   %s\n", $where, $m[1], count($domainEmpty),
            $n === count($domainEmpty) ? 'MATCHES' : 'DIFFERS');
        $others++;
        if ($n !== count($domainEmpty) && $verify) {
            $bad[] = "a domain-empty count elsewhere: the text says \"{$m[1]} domain tables are empty\", the server answers ".count($domainEmpty);
        }
    }
}
if ($others === 0) {
    echo "  none found — and that is not a pass: this pattern bound nothing on its first run, so mutate-lane64-empty-tables.php must prove it reads the §24.18 row.\n";
    if ($verify) {
        $bad[] = 'no domain-empty claim outside §24.17 bound; either the document dropped it or the pattern stopped matching';
    }
}

echo "\n";
if ($bad !== []) {
    echo 'VERDICT: REFUSED — '.count($bad)." membership claim(s) do not match COUNT(*) on '{$schema}'\n";
    foreach ($bad as $b) {
        echo '  * '.$b."\n";
    }
    exit($verify ? 1 : 0);
}
echo $verify
    ? "VERDICT: §24.17's sets, its rows and the counts elsewhere all equal what COUNT(*) answers on '{$schema}'.\n"
    : "VERDICT: report only — pass --verify to make any of the above refuse.\n";
exit(0);
