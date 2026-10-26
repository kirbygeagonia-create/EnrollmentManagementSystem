<?php

/**
 * Lane 72: bind the document's row-count sentences to COUNT(*) on the database that is asked about.
 *
 * Lane 60 judges the SCHEMA (tables, columns, foreign keys, per-table column headings) and deliberately only NAMES
 * row counts — `42 assignments over 17 roles`, `gradescale has 4 rows`, the settings rows — because a row count
 * belongs to whichever dataset stated it, and CI's `ems_test` is seeded differently. That is right for CI and wrong
 * for this box: on live `ems` those sentences are decidable, and pass 244 found one of them had silently drifted (§20
 * said the ID desk's Issued tab held "seven" where the controller's own predicate answers nine). Finding that by
 * chasing an unrelated audit note is not a method, so this is the method.
 *
 * What it does: read the flat dump, take each sentence that states a count of rows of a named table, resolve the
 * noun to a real table by asking `information_schema` (derive the partition, never recall it — lane 60's lesson),
 * run COUNT(*), and classify. Sentences carrying their own date are HISTORY and are not judged: "Closed 2026-10-17 …
 * seven on this box" records that day, and re-aging it would state a figure that was not true on the day it was written.
 *
 * Seven buckets, because the first run's queue was five sentences and every one of them was the instrument's own
 * breadth, not a defect in the document:
 *   MATCH       the number equals COUNT(*) on the dataset this tool was told to read.
 *   STALE       it does not — a real claim the server disproves, printed with its phrase and its sentence.
 *   AMBIGUOUS   the noun maps to several tables; a question for a reader, printed.
 *   COMPARISON  one number repeated beside several tables in one sentence ("two stages, two offices, two
 *               permissions") counts the thing being compared, not each table; printed, never charged as stale.
 *   NARRATIVE   a singular instance ("on one student") or a sentence naming no row-word at all ("One receipt number
 *               is one payment, never two"); tallied, not judged.
 *   CODEISH     the number sits on a class member ("the two Enrollments::create sites in app/"), which counts call
 *               sites, not rows — a grep answers it and this tool deliberately does not; measured 2026-10-25, that
 *               claim is true: `AdmissionController.php:394` and `EnrollmentIssuer.php:73` are the only two.
 *   PER-RECORD  a lone number inside an enumeration of tables (§3's walkthrough row: "…payments,
 *               enrollmentstatushistory, 7 workflowsteps, clinicrecords, idrequests") — the trail of the record the
 *               row describes, so the table's own COUNT(*) is the wrong denominator; printed, not charged as stale.
 *   TABLELESS   a number beside a noun that is not a table (columns, tests, acts); tallied, not printed.
 *   HISTORY     the sentence states its own day.
 *
 * Every row lands in a named bucket and the run refuses unless the buckets add up, because a checker whose summary
 * does not add up is reporting a clean document it never read. NOT a CI step: these counts are properties of the
 * dataset they were taken from (lane 60's rule), and the SQLite job has no persistent MySQL.
 *
 *   php storage/app/scratch/lane72-row-count-claims.php --verify [--dump=<file>] [--allow-schema=ems] [--limit=N] [--show-match]
 */
$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$dump = $scratch.'/docx-flat.txt';
$verify = in_array('--verify', $argv, true);
$allow = null;
$limit = 0;
$showMatch = false;
foreach ($argv as $a) {
    if (str_starts_with($a, '--')) {
        $opt = (string) preg_replace('/=.*\z/su', '', $a);
        if (! in_array($opt, ['--verify', '--dump', '--allow-schema', '--limit', '--show-match'], true)) {
            printf("REFUSED: %s is not a flag this tool reads (known: --verify, --dump=, --allow-schema=, --limit=,\n"
                ."        --show-match). An accepted-and-inert flag makes a mutant run look like a clean one.\n", $a);
            exit(1);
        }
    }
    if (str_starts_with($a, '--dump=')) {
        // `substr`, not `$a[strlen(...)]`: a string offset hands back ONE character, so the first version of this
        // flag resolved the dump to `<root>/s` and refused. An inert flag is how a mutant run looks like a clean
        // one; here the harness caught it on its first run because a refusal is at least loud.
        $dump = $root.'/'.substr($a, strlen('--dump='));
    } elseif (str_starts_with($a, '--allow-schema=')) {
        $allow = substr($a, strlen('--allow-schema='));
    } elseif (str_starts_with($a, '--limit=')) {
        $limit = (int) substr($a, strlen('--limit='));
    } elseif ($a === '--show-match') {
        $showMatch = true;
    }
}
if (! $verify) {
    exit("REFUSED: this tool judges rows, so it must be asked to: pass --verify (lane 64's rule — an unread\n"
        ."        surface that answers nothing looks like a clean document).\n");
}
if (! is_file($dump)) {
    printf("REFUSED: no dump at %s — run dump-docx-flat.php.\n", $dump);
    exit(1);
}
$lines = (array) file($dump);
if (count($lines) < 500) {
    printf("REFUSED: the dump holds %d line(s); re-run dump-docx-flat.php.\n", count($lines));
    exit(1);
}

$env = [];
foreach ((array) @file($root.'/.env') as $l) {
    if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/u', trim((string) $l), $m) === 1) {
        $env[$m[1]] = trim(trim($m[2]), "\"'");
    }
}
$schema = $env['DB_DATABASE'] ?? '';
if ($allow !== null && $allow !== $schema) {
    printf("REFUSED: asked to judge schema %s but the environment answers %s.\n", $allow, $schema ?: '(nothing)');
    exit(1);
}
if ($allow === null && $schema !== 'ems') {
    printf("REFUSED: the environment points at %s; say --allow-schema=%s to judge another dataset.\n",
        $schema ?: '(nothing)', $schema);
    exit(1);
}
try {
    $pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';dbname='.$schema,
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    printf("REFUSED: cannot read %s (%s) — an unread database is not an agreement.\n", $schema, $e->getMessage());
    exit(1);
}
$tables = array_map(static fn (array $r): string => (string) $r[0],
    $pdo->query("select TABLE_NAME from information_schema.TABLES where TABLE_SCHEMA = DATABASE() and TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM));
if ($tables === []) {
    exit("REFUSED: $schema answered no base tables.\n");
}

/**
 * Nouns that name a table. Built from the server's own table list, not from a remembered vocabulary: a word is a
 * candidate when the sentence's noun equals a table, or is its plural, or is a singular formed by dropping an `s`,
 * or the noun is a table name with a trailing `s` already stripped. Two words are refused rather than guessed: a noun
 * that maps to more than one table is UNBOUND, because the document's "roles" and the schema's `roles` can still be
 * different things (lane 46's FICTION class, one level up).
 */
$map = [];
foreach ($tables as $t) {
    $lower = strtolower($t);
    $map[$lower][] = $t;
    $map[rtrim($lower, 's')][] = $t;
    if (str_ends_with($lower, 's')) {
        $map[$lower.'es'][] = $t;
    }
}
$map = array_map(static fn (array $v): array => array_values(array_unique($v)), $map);

$numberWords = ['zero' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6,
    'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13,
    'fourteen' => 14, 'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19,
    'twenty' => 20, 'thirty' => 30, 'forty' => 40, 'fifty' => 50, 'sixty' => 60, 'seventy' => 70, 'eighty' => 80,
    'ninety' => 90, 'hundred' => 100];

$counts = ['MATCH' => 0, 'STALE' => 0, 'AMBIGUOUS' => 0, 'TABLELESS' => 0, 'HISTORY' => 0,
    'NARRATIVE' => 0, 'COMPARISON' => 0, 'CODEISH' => 0, 'PER-RECORD' => 0];
$printed = 0;
$withheld = 0;
$claimRe = '/\b(\d{1,4}|'.implode('|', array_keys($numberWords)).")\s+((?:[A-Za-z][A-Za-z0-9_]*\s+){0,2}[A-Za-z][A-Za-z0-9_]*)\b/u";
$censusRe = '/\b(rows?|records?|entries|lines?|tables?|database|dataset|counts?|total[s]?|holds?|hold|contains?|carries?|create[sd]?|seeds?|seeded|answers|describe[s]?|sits?|seats?|grants?|directory)\b/u';
$pluralRe = '/(s|es)\b$/u';
$mayPrint = static function () use (&$printed, &$withheld, $limit): bool {
    if ($limit > 0 && $printed >= $limit) {
        $withheld++;

        return false;
    }
    $printed++;

    return true;
};

$readings = [];
foreach ($lines as $i => $raw) {
    $line = html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8');
    // Sentences, split on the terminal marks this document actually uses. Rows are one flat line each.
    foreach (preg_split('/(?<=[.;]) (?=[A-Z(“"§])| \| /u', $line) ?: [] as $sent) {
        if (! preg_match_all($claimRe, $sent, $ms, PREG_SET_ORDER)) {
            continue;
        }
        $pairs = [];
        $same = [];
        // Which tables does this whole sentence name? Used for the enumeration rule below.
        $named = [];
        foreach (preg_split('/[^A-Za-z0-9_]+/u', $sent) ?: [] as $w) {
            $lw = strtolower((string) $w);
            if ($lw !== '' && isset($map[$lw]) && count($map[$lw]) === 1) {
                $named[strtolower($map[$lw][0])] = true;
            }
        }
        foreach ($ms as $m) {
            $num = $m[1];
            $noun = strtolower(trim((string) $m[2]));
            $value = is_numeric($num) ? (int) $num : ($numberWords[$num] ?? null);
            if ($value === null || $noun === '') {
                continue;
            }
            // HISTORY: a sentence that names its own day, or sits under a heading that does, is a record.
            if (preg_match('/\b(20\d\d-\d\d-\d\d|as of \w+ \d{1,2}, 20\d\d|Closed \d|on 20\d\d)\b/u', $sent) === 1) {
                $counts['HISTORY']++;

                continue;
            }
            // NARRATIVE, and this is the rule that took the false findings out of the first queue. A number beside
            // a singular noun counts an *instance*, not the set: "seated a second active enrollment on one student"
            // says nothing about how many rows `students` holds. And a sentence that never names a row-word, a
            // table, or a verb of holding is not a census of anything: "One receipt number is one payment, never
            // two" is a rule about identity. Lane 58's lesson, applied one level down — a number-and-noun scan
            // finds hits, and most of them are not readings.
            if (preg_match($censusRe, $noun) !== 1 && ! preg_match($pluralRe, $noun)) {
                $counts['NARRATIVE']++;

                continue;
            }
            if (preg_match($censusRe, $sent) !== 1) {
                $counts['NARRATIVE']++;

                continue;
            }
            // A number attached to a CLASS MEMBER is not a row count: "the two Enrollments::create sites in app/"
            // counts call sites, and the token the noun regex caught (`Enrollments`) is a PHP class name that happens
            // to spell a table. Lane 32's rule, one level down: read what the token is, not what it spells.
            // `substr`, not `mb_substr`: the offset is a BYTE offset from strlen(), and this document's sentences are
            // full of em dashes, so a character slice lands two or three bytes short and reads the wrong pair.
            $phrase = trim((string) $m[0]);
            $at = strpos($sent, $phrase);
            if ($at !== false && substr($sent, $at + strlen($phrase), 2) === '::') {
                $counts['CODEISH']++;

                continue;
            }
            $candidates = $map[$noun] ?? ($map[preg_replace('/ies$/', 'y', $noun) ?? ''] ?? []);
            if ($candidates === []) {
                $candidates = $map[rtrim($noun, 's')] ?? [];
            }
            if ($candidates === []) {
                // A number beside a noun that is not a table is not a row count: "5 signature acts", "1 of 17
                // accounts", "§18.3's 12 columns", "274 tests". Counting those as a queue asked a human to read 1,465
                // lines to find five — a queue that big is not a queue, it is the instrument's own breadth, so it is
                // tallied here and never printed. Only rows this tool can actually weigh reach a reader.
                $counts['TABLELESS']++;

                continue;
            }
            if (count($candidates) !== 1) {
                $counts['AMBIGUOUS']++;
                if ($mayPrint()) {
                    printf("AMBIGUOUS  line %-5d  \"%s %s\" — the noun maps to %d tables (%s), which is a question for a reader\n",
                        $i + 1, $num, $noun, count($candidates), implode(', ', $candidates));
                }

                continue;
            }
            $pairs[] = ['m' => $m, 'num' => $num, 'noun' => $noun, 'value' => $value, 'table' => $candidates[0], 'line' => $i + 1];
            $same[$value][$candidates[0]] = true;
        }
        foreach ($pairs as $p) {
            // PER-RECORD: a LONE number dropped inside an enumeration of tables counts the SUBJECT's rows, not the
            // table's. §3's walkthrough row reads "Accumulates every record type: admissions, …, payments,
            // enrollmentstatushistory, 7 workflowsteps, clinicrecords, idrequests" — that 7 is the trail of one
            // enrollment, and `workflowsteps` answers 199 because every enrollment has its own boxes. Weighing it
            // against the whole table would have "found" a stale claim in a true sentence about a record.
            // The discriminator is the list's own shape, measured: a CENSUS list puts a number beside each item
            // ("154 subjects, 16 curriculums, 9 clearance requirements and the seeded roles" — a real table count,
            // and the first version of this rule swallowed it), while a trail lists the names unnumbered and carries
            // one number. So: several numbered table items → judge; exactly one, amid three or more other table
            // names → a question for the reader who knows which record the row describes.
            if (count($pairs) < 2) {
                $others = count(array_diff_key($named, [strtolower($p['table']) => true]));
                if ($others >= 3) {
                    $counts['PER-RECORD']++;
                    if ($mayPrint()) {
                        printf("PER-RECORD line %-5d  %s is the only number in a list naming %d other tables — the count belongs to the record this row describes, not to `%s` (which answers %d)\n",
                            $p['line'], trim((string) $p['m'][0]), $others, $p['table'],
                            $readings[$p['table']] ??= (int) $pdo->query('select count(*) from `'.$p['table'].'`')->fetchColumn());
                    }

                    continue;
                }
            }
            // COMPARISON: one number repeated beside several tables is the count of the THING being compared, not of
            // each table. "They are two stages, two offices, two permissions, and two rows of examresults" states
            // that the two examination flows differ in these dimensions — the `offices` table holds nine and the
            // checker that called that a stale document claim was wrong, the sentence was. Printed, not cleared.
            if (count($same[$p['value']] ?? []) > 1) {
                $counts['COMPARISON']++;
                if ($mayPrint()) {
                    printf("COMPARISON line %-5d  %s names %d tables in one sentence (%s) — read it, but no COUNT(*) answers it\n",
                        $p['line'], trim((string) $p['m'][0]), count($same[$p['value']]), implode(', ', array_keys($same[$p['value']])));
                }

                continue;
            }
            $table = $p['table'];
            if (! isset($readings[$table])) {
                $readings[$table] = (int) $pdo->query('select count(*) from `'.$table.'`')->fetchColumn();
            }
            $server = $readings[$table];
            $value = $p['value'];
            $m = $p['m'];
            if ($server === $value) {
                $counts['MATCH']++;
                // --show-match exists so a harness can find a live correct claim to mutate instead of remembering
                // one (lane 70's --show-ok rule).
                if ($showMatch) {
                    printf("MATCH      line %-5d  document %4d, `%s` answers %4d — %s\n",
                        $i + 1, $value, $table, $server, trim((string) $m[0]));
                    printf("           …%s…\n", trim(mb_substr($sent, max(0, (int) strpos($sent, trim((string) $m[0])) - 70), 170)));
                }

                continue;
            }

            $counts['STALE']++;
            if ($mayPrint()) {
                // Quote the phrase and its sentence: the noun alone is the LAST word of a up-to-three-word phrase
                // ("1 new student" prints as "student"), so a reader told only that cannot find the line again — the
                // first version of this tool made its own queue unlocatable.
                printf("STALE      line %-5d  document %4d, `%s` answers %4d — %s\n",
                    $i + 1, $value, $table, $server, trim((string) $m[0]));
                printf("           …%s…\n", trim(mb_substr($sent, max(0, (int) strpos($sent, trim((string) $m[0])) - 70), 170)));
            }
        }
    }
}

$total = array_sum($counts);
$queue = $counts['STALE'] + $counts['AMBIGUOUS'] + $counts['COMPARISON'] + $counts['PER-RECORD'];
printf("\nreadings taken on this box (live $schema) (".gmdate('Y-m-d')."); %d base table(s) read from\n"
    ."information_schema, %d distinct table(s) counted this run.\n", count($tables), count($readings));
printf("%d number-and-noun pair(s) read: %d MATCH, %d STALE, %d AMBIGUOUS-NOUN, %d COMPARISON, %d PER-RECORD,\n"
    ."                                     %d NARRATIVE, %d CODEISH, %d TABLELESS, %d HISTORY\n",
    $total, $counts['MATCH'], $counts['STALE'], $counts['AMBIGUOUS'], $counts['COMPARISON'],
    $counts['PER-RECORD'], $counts['NARRATIVE'], $counts['CODEISH'], $counts['TABLELESS'], $counts['HISTORY']);
if ($total === 0) {
    echo "REFUSED: no count claim was read, so this run judged nothing.\n";
    exit(1);
}
if ($counts['MATCH'] + $queue === 0) {
    echo "REFUSED: the walk bound nothing (no pair reached a single table out of $total) — that is a reader that\n"
        ."        found no table to weigh, not a document whose counts agree.\n";
    exit(1);
}
if ($printed + $withheld !== $queue) {
    printf("REFUSED: %d row(s) were put in the queue but %d written and %d withheld.\n", $queue, $printed, $withheld);
    exit(1);
}
if ($withheld > 0) {
    printf("the queue above is INCOMPLETE: %d row(s) withheld by --limit.\n", $withheld);
}
echo $counts['STALE'] === 0
    ? "VERDICT: every present-tense row count this checker bound equals what COUNT(*) answers on '$schema'"
        ." ($counts[MATCH] claim(s)); the $queue row(s) above are shape questions, not verdicts.\n"
    : "VERDICT: $counts[STALE] sentence(s) state a count the server disagrees with — read them, do not retype them.\n";
exit($counts['STALE'] > 0 ? 1 : 0);
