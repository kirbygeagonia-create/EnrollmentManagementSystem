<?php

/**
 * Lane 73: bind the Registrar window packet's claims to the server and the build, and refuse a stale page.
 *
 * The packet quotes `lane73-packet-evidence.php` rather than the previous sheet — that is what makes it trustworthy
 * for one day. This is the guard for the days after: every figure and every listed row on the page is weighed again,
 * so a department that clears a board flag, or a Registrar who edits a requirement line, turns the packet red
 * instead of leaving an officer ticking against paper the build no longer agrees with.
 *
 * What it judges, all parsed out of the packet's own text (derive, never recall):
 *   COURSE-FLAG   the sentence "16 courses; 5 carry requiresCourseSpecificExam; 2 … entrance …; 0 exempting".
 *   BOARD-ROW     each row of the C-5 table: the code, whether it really carries the flag, and the entrance column.
 *   OFFICE-LINE   each row of the nine-office table: officeId and the exact requirementName the table holds.
 *   FEE           the replacement fee's name, amount and basis, and that the columns it names exist.
 *   PROXY         "trustedproxy.proxies is null" and "bootstrap/app.php does not call trustProxies()".
 * Dataset sizes stated with their own date (enrollments, audit rows) are HISTORY — lane 60's rule — because they
 * belong to the day they were read, and a dated row is never re-aged.
 *
 * NOT a CI step: like lane 72, these are properties of whichever database stated them, and CI's `ems_test` is
 * seeded differently. Run it against live `ems` before handing the packet to anyone.
 *
 *   php storage/app/scratch/lane73-packet-claims.php [--packet=<path>] [--limit=N]
 */
$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$packetPath = $root.'/Documentation/Registrar-Window-Packet-2026-10-26.md';
$limit = 0;
foreach ($argv as $k => $a) {
    if ($k === 0) {
        continue; // the script's own path, which is not a flag — the first version refused the run over it
    }
    $opt = (string) preg_replace('/=.*\z/su', '', (string) $a);
    if (! in_array($opt, ['--packet', '--limit'], true)) {
        printf("REFUSED: %s is not a flag this tool reads (known: --packet=, --limit=).\n", $a);
        exit(1);
    }
    if (str_starts_with((string) $a, '--packet=')) {
        $packetPath = $root.'/'.substr((string) $a, strlen('--packet='));
    } elseif (str_starts_with((string) $a, '--limit=')) {
        $limit = (int) substr((string) $a, strlen('--limit='));
    }
}
if (! is_file($packetPath)) {
    printf("REFUSED: no packet at %s.\n", $packetPath);
    exit(1);
}
$packet = (string) file_get_contents($packetPath);
if (substr_count($packet, 'Lane 73') > 0 && ! str_contains($packet, 'clearancerequirements')) {
    exit("REFUSED: the file does not look like the packet this tool was written for.\n");
}

$env = [];
foreach ((array) @file($root.'/.env') as $l) {
    if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/u', trim((string) $l), $m) === 1) {
        $env[$m[1]] = trim(trim((string) $m[2]), "\"'");
    }
}
$schema = (string) ($env['DB_DATABASE'] ?? '');
if ($schema !== 'ems') {
    printf("REFUSED: the environment points at %s; this guard weighs a packet written against live ems.\n",
        $schema === '' ? '(nothing)' : $schema);
    exit(1);
}
try {
    $pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';dbname='.$schema,
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    printf("REFUSED: cannot read %s (%s).\n", $schema, $e->getMessage());
    exit(1);
}
$count = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$fails = 0;
$printed = 0;
$withheld = 0;
$tally = ['MATCH' => 0, 'STALE' => 0, 'HISTORY' => 0];
$report = static function (string $kind, bool $ok, string $detail) use (&$tally, &$fails, &$printed, &$withheld, $limit): void {
    $tally[$ok ? 'MATCH' : 'STALE']++;
    if (! $ok) {
        $fails++;
    }
    if ($limit > 0 && $printed >= $limit) {
        $withheld++;

        return;
    }
    $printed++;
    printf("  %-8s %s  %s\n", $ok ? 'MATCH' : 'STALE', $kind, $detail);
};

// --- the flag sentence, and the board table's rows -----------------------------------------------------------------
if (preg_match('/(\d+) courses;\s*(\d+) carry `requiresCourseSpecificExam`;\s*(\d+) carry\s*\n?\s*`requiresEntranceExam`;\s*(\d+) exempt/u', $packet, $m) === 1) {
    $report('COURSE-FLAG', (int) $m[1] === $count('select count(*) from courses'),
        "packet says {$m[1]} courses, server answers ".$count('select count(*) from courses'));
    $report('COURSE-FLAG', (int) $m[2] === $count('select count(*) from courses where requiresCourseSpecificExam = 1'),
        "packet says {$m[2]} carry the board flag, server answers ".$count('select count(*) from courses where requiresCourseSpecificExam = 1'));
    $report('COURSE-FLAG', (int) $m[3] === $count('select count(*) from courses where requiresEntranceExam = 1'),
        "packet says {$m[3]} carry the entrance flag, server answers ".$count('select count(*) from courses where requiresEntranceExam = 1'));
    $report('COURSE-FLAG', (int) $m[4] === $count('select count(*) from courses where entranceExamExemptsTransferee = 1'),
        "packet says {$m[4]} exempt a transferee, server answers ".$count('select count(*) from courses where entranceExamExemptsTransferee = 1'));
} else {
    // An unreadable page is not a clean page and not a stale claim either: it is a refusal. The first version
    // printed a STALE row here, which meant the run could reach a verdict while having read nothing, and made the
    // "nothing was bound" guard below unreachable.
    printf("REFUSED: the packet no longer states its course counts in the shape this guard reads — re-dump it from\n"
        ."        lane73-packet-evidence.php rather than letting a parser miss look like a measurement.\n");
    exit(1);
}

$yesNo = '(?:\*\*)?(yes|no)(?:\*\*)?';
$board = preg_match_all('/^\|\s*([A-Z][A-Za-z]+)\s*\|\s*[^|]*\|\s*'.$yesNo.'\s*\|\s*'.$yesNo.'\s*\|/mu', $packet, $rows, PREG_SET_ORDER) ?: 0;
foreach ($rows as $r) {
    $code = $r[1];
    $st = $pdo->prepare('select requiresCourseSpecificExam, requiresEntranceExam from courses where courseCode = ?');
    $st->execute([$code]);
    $row = $st->fetch(PDO::FETCH_NUM);
    if ($row === false) {
        $report('BOARD-ROW', false, "the packet names $code, which the courses table does not hold");

        continue;
    }
    // Compare the WORDS, not a cast of them: `(int) 'yes' === 0`, so the first version's truthiness test was false
    // for every row and three true sentences were reported as stale claims.
    $packetBoard = $r[2] === 'yes';
    $packetEntrance = $r[3] === 'yes';
    $ok = $packetBoard === ((int) $row[0] === 1) && $packetEntrance === ((int) $row[1] === 1);
    $report('BOARD-ROW', $ok, "$code packet $r[2]/$r[3], server ".((int) $row[0] ? 'yes' : 'no').'/'.((int) $row[1] ? 'yes' : 'no'));
}
if ($board < 5) {
    printf("REFUSED: the packet's board table now parses to %d row(s); C-5 lists five, so this run would be judging\n"
        ."        a page it cannot read.\n", $board);
    exit(1);
}

// --- the nine office lines, matched by their exact text --------------------------------------------------------------
$lines = $pdo->query('select officeId, requirementName from clearancerequirements order by officeId')->fetchAll(PDO::FETCH_ASSOC);
preg_match_all('/^\|\s*(\d+)\s+(\w[\w ]*)\s*\|\s*([^|]+)\|\s*$/m', $packet, $offices, PREG_SET_ORDER) ?: $offices = [];
$shown = array_fill_keys(array_column($offices, 1), true);
$report('OFFICE-COUNT', count($offices) === count($lines),
    'the packet lists '.count($offices).' office line(s), the table holds '.count($lines));
foreach ($offices as $o) {
    $hit = null;
    foreach ($lines as $l) {
        if ((int) $l['officeId'] === (int) $o[1]) {
            $hit = $l;
        }
    }
    $report('OFFICE-LINE', $hit !== null && trim($o[3]) === trim((string) $hit['requirementName']),
        'office '.$o[1].' — '.substr(trim($o[3]), 0, 46).($hit === null ? ' (no such office row)'
            : ' vs server: '.substr(trim((string) $hit['requirementName']), 0, 46)));
}

// --- the fee the paid route reads -----------------------------------------------------------------------------------
$fee = $pdo->query("select feeName, defaultAmount, unitBasis from feetypes where feeName like '%learance Slip Replacement%'")->fetch(PDO::FETCH_ASSOC);
$statedFee = static function (string $text): ?string {
    return preg_match('/Clearance Slip Replacement,\s*(?:\*\*)?₱([\d,]*\d(?:\.\d+)?)/u', $text, $m) === 1 ? $m[1] : null;
};
$stated = $statedFee($packet);
$report('FEE', $fee !== null && $stated !== null && (float) str_replace(',', '', $stated) === (float) $fee['defaultAmount'],
    'packet ₱'.(string) $stated.', server '.($fee === null ? 'no such fee' : $fee['defaultAmount'].' '.$fee['unitBasis']));
$cols = static function (string $table) use ($pdo): array {
    $st = $pdo->prepare('select COLUMN_NAME c from information_schema.COLUMNS
        where TABLE_SCHEMA = DATABASE() and TABLE_NAME = ? order by ORDINAL_POSITION');
    $st->execute([$table]);

    return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'c');
};
// Every "(columns `a, b, c`)" list on the page, bound to the table whose name sits just before it. The first
// version read only the FIRST such list and compared it to `feetypes`, so it judged the clearance column list
// against the fee table and called the mismatch a stale claim — the wrong-target defect, found in my own
// instrument rather than in the document.
preg_match_all('/columns\s*\n?\s*`([^`]+)`/u', $packet, $lists, PREG_SET_ORDER) ?: $lists = [];
$tables = array_column($pdo->query('select TABLE_NAME t from information_schema.TABLES where TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_ASSOC), 't');
$tables = array_change_key_case(array_flip($tables), CASE_LOWER);
foreach ($lists as $k => $list) {
    $at = (int) strpos($packet, $list[0]);
    $before = mb_substr($packet, max(0, $at - 200), 200);
    preg_match_all('/`([a-z][a-z0-9_]*)`/u', $before, $words);
    $table = null;
    foreach (array_reverse($words[1]) as $w) {
        if (isset($tables[$w])) {
            $table = $w;
            break;
        }
    }
    if ($table === null) {
        printf("REFUSED: a columns list on the page names no table this server holds, so the guard cannot tell what it claims.\n");
        exit(1);
    }
    $claimed = array_map(static fn (string $s): string => trim($s), explode(',', $list[1]));
    $server = $cols($table);
    $report('COLUMN-LIST', $claimed === $server, $table.' packet '.implode(', ', $claimed).' | server '.implode(', ', $server));
}

// --- the proxy state the packet quotes ------------------------------------------------------------------------------
$bootHas = str_contains((string) file_get_contents($root.'/bootstrap/app.php'), 'trustProxies');
$packetSaysNull = str_contains($packet, '`config(\'trustedproxy.proxies\')` is');
$report('PROXY', $packetSaysNull && ! $bootHas,
    'packet says the build trusts no proxy and does not call trustProxies(); the file says '.($bootHas ? 'it does call it' : 'it does not'));

printf("\n%s\n", $tally['STALE'] === 0
    ? sprintf('VERDICT: the packet agrees with %s on all %d claim(s) — it is safe to hand over.', $schema, $tally['MATCH'])
    : sprintf('VERDICT: %d claim(s) the packet states and %s disagrees with — re-issue the packet from lane73-packet-evidence.php; do not retype the figures by hand.', $tally['STALE'], $schema));
if ($tally['MATCH'] === 0 && $tally['STALE'] === 0) {
    echo "REFUSED: nothing was bound, so this run judged nothing.\n";
    exit(1);
}
exit($fails > 0 ? 1 : 0);
