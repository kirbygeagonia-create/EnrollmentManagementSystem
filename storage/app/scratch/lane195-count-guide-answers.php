<?php

/**
 * Is every answerable item of the ADS guide actually answered, and answered by its own words?
 *
 * The guide (`ADS_Final_Project_Guide_EMS_updated.docx`, flattened to `ads-guide-flat.txt`) asks 24
 * questions sets, a 12-row concept table, a 25-item presentation list, one defense question, four
 * fill-ins and six task verbs. `Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md` claims to answer all of
 * them. Nothing in this repository could check that claim: `lane155` counts the guide's population
 * but reads no answer file, `lane189-count-ads-fields` weighs the *shape* of the test-case tables in
 * another document, and every citation gate asks about paths and line numbers, not about whether an
 * answer exists. A deliverable whose required shape is a list of questions needs a counter over the
 * questions, or "we answered everything" is a sentence.
 *
 * So this reads the guide's own structure first and only then the answers:
 *   III   every `Problem N — Title` block: its title, its guide questions (verbatim), its
 *         Applicability line and its "If N/A or different from our workflow" line;
 *   IV    every concept row (a name line followed by a `☐` status line) and the N/A explanation line;
 *   V     every numbered group and every sub-item beneath it;
 *   VI    the defense question;  VII  the four fill-ins;  I  the six task verbs.
 * Then, per item, it asks a question a reader could not answer by skimming: is the item's own text
 * present, is an answer attached to it (not just a heading), is exactly one Applicability box checked
 * and does that check agree with the P register's verdict word for the same problem, and is each
 * concept status word one the guide's own row offers.
 *
 * Matching is by alphanumeric fingerprint (case, punctuation, dashes and wrapping discarded) because
 * a flat dump wraps a sentence and an answer file may set the same words in italics — but the
 * *presence of an answer* is judged on length and structure, so a quoted question with nothing after
 * it is reported ANSWER-SHORT rather than counted.
 *
 * Refuses: a missing guide or answer file, an empty population in any family (an unread family would
 * look like a clean one), a `--guide=`/`--answers=`/`--register=` pointing at nothing, an unknown
 * flag by name, and a guide whose problem count is not 24 — the census is the guard.
 *
 * Usage: php storage/app/scratch/lane195-count-guide-answers.php [--verbose]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$opts = [];
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $opts[$m[1]][] = $m[2] ?? true;
    } else {
        fwrite(STDERR, 'REFUSED: this tool does not understand '.escapeshellarg($a).".\n");
        exit(1);
    }
}
$unknown = array_values(array_diff(array_keys($opts), ['guide', 'answers', 'register', 'verbose']));
if ($unknown !== []) {
    fwrite(STDERR, 'REFUSED: flag(s) not read by this tool: --'.implode(', --', $unknown).".\n");
    exit(1);
}
$verbose = isset($opts['verbose']);

$guidePath = ($opts['guide'][0] ?? 'storage/app/scratch/ads-guide-flat.txt');
$answersPath = ($opts['answers'][0] ?? 'Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md');
$registerPath = ($opts['register'][0] ?? 'Documentation/REQUIREMENTS-TRACEABILITY-ADS.md');
foreach (['guide' => $guidePath, 'answers' => $answersPath, 'register' => $registerPath] as $name => $p) {
    if (! is_file($p)) {
        fwrite(STDERR, "REFUSED: the --{$name} file cannot be read: {$p}\n");
        exit(1);
    }
}
$guide = file_get_contents($guidePath);
$answers = file_get_contents($answersPath);
$register = file_get_contents($registerPath);

/** Keep letters and digits only: a flat dump wraps, an answer file italicises, both are noise here. */
$fp = static function (string $s): string {
    return preg_replace('/[^a-z0-9]+/', '', strtolower($s)) ?? '';
};
$answersFp = $fp($answers);
$answersLines = preg_split('/\n/', $answers) ?: [];

/**
 * A markdown paragraph continues to the next blank line, so an answer's length cannot be measured on
 * the line that carries its label — that reading judged P16's "none." a complete answer and a wrapped
 * one incomplete. Join the paragraph, stop at a blank line or the next bold label.
 */
$paragraph = static function (array $lines, int $start, int $end): string {
    $buf = [trim($lines[$start])];
    for ($k = $start + 1; $k < $end; $k++) {
        $l = trim($lines[$k]);
        if ($l === '' || str_starts_with($l, '**') || str_starts_with($l, '#') || str_starts_with($l, '- ')) {
            break;
        }
        $buf[] = $l;
    }

    return trim(implode(' ', $buf));
};

/** ---------- III. the problems, from the guide's own structure ---------- */
$problems = [];
$gl = preg_split('/\n/', $guide) ?: [];
for ($i = 0; $i < count($gl); $i++) {
    if (preg_match('/^Problem (\d+) — (.+)$/', trim($gl[$i]), $m)) {
        $n = (int) $m[1];
        $title = trim($m[2]);
        $questions = [];
        $inQ = false;
        for ($j = $i + 1; $j < count($gl); $j++) {
            $line = trim($gl[$j]);
            if ($line === 'Guide Questions') {
                $inQ = true;

                continue;
            }
            if ($inQ && ($line === 'Your Analysis / Solution' || preg_match('/^Problem \d+ —/', $line))) {
                break;
            }
            if ($inQ && $line !== '') {
                $questions[] = $line;
            }
        }
        $app = 'Applicability:  ☐ Applies to our system   ☐ Partially applies   ☐ N/A (explain below)';
        $naLine = 'If N/A or different from our workflow, explain why and describe the equivalent process or alternative test:';
        $problems[$n] = ['title' => $title, 'questions' => $questions, 'app' => $app, 'na' => $naLine];
    }
}
ksort($problems);
if (count($problems) !== 24) {
    fwrite(STDERR, 'REFUSED: the guide census read '.count($problems)." problem blocks, not 24.\n");
    exit(1);
}
$qTotal = array_sum(array_map(static fn ($p) => count($p['questions']), $problems));
if ($qTotal < 100) {
    fwrite(STDERR, "REFUSED: the guide census read {$qTotal} guide questions; the guide holds 106.\n");
    exit(1);
}

/** ---------- the P register's verdict words, so one question has one answer ---------- */
$verdicts = [];
foreach (preg_split('/\n/', $register) ?: [] as $line) {
    if (preg_match('/^\|\s*P(\d{2})\s*\|[^|]*\|\s*([^|]+?)\s*\|/', $line, $m)) {
        $cell = trim($m[2]);
        $word = 'Applies';
        if (preg_match('/^\s*(n\/a|not applicable)/i', $cell)) {
            $word = 'N/A';
        } elseif (preg_match('/^partial/i', $cell)) {
            $word = 'Partially';
        }
        $verdicts[(int) $m[1]] = $word;
    }
}
if (count($verdicts) < 24) {
    fwrite(STDERR, 'REFUSED: the P register answered '.count($verdicts)." problem verdicts, not 24.\n");
    exit(1);
}

$OPTION = [
    'Applies' => '☑ Applies to our system',
    'Partially' => '☑ Partially applies',
    'N/A' => '☑ N/A',
];

$findings = [];
$report = [];
$answer = static function (string $needleFp) use ($answersFp): bool {
    return $needleFp !== '' && str_contains($answersFp, $needleFp);
};

/** ---------- III: each problem block ---------- */
foreach ($problems as $n => $p) {
    $block = null;
    $headFp = $fp('Problem '.$n.' '.$p['title']);
    foreach ($answersLines as $idx => $line) {
        if ($answer($headFp) && str_contains($fp($line), $headFp) && str_starts_with($line, '### Problem '.$n)) {
            $block = $idx;
            break;
        }
    }
    if ($block === null) {
        $findings[] = "MISSING-BLOCK  P{$n} — no block whose heading carries the guide's own title.";

        continue;
    }
    $end = count($answersLines);
    for ($k = $block + 1; $k < count($answersLines); $k++) {
        if (preg_match('/^### Problem \d+ —/', $answersLines[$k]) || preg_match('/^## /', $answersLines[$k])) {
            $end = $k;
            break;
        }
    }
    $body = trim(implode("\n", array_slice($answersLines, $block, $end - $block)));
    $bodyFp = $fp($body);

    // questions
    $missQ = 0;
    $shortQ = 0;
    foreach ($p['questions'] as $qi => $q) {
        $qfp = $fp($q);
        $found = null;
        foreach ($answersLines as $idx => $line) {
            if ($idx < $block || $idx >= $end) {
                continue;
            }
            if (str_starts_with($line, '**Q') && str_contains($fp($line), $qfp)) {
                $found = $idx;
                break;
            }
        }
        if ($found === null) {
            $missQ++;
            $findings[] = "QUESTION-MISSING P{$n}.".($qi + 1).' '.$q;

            continue;
        }
        $ans = '';
        for ($a = $found + 1; $a < $end; $a++) {
            $l = trim($answersLines[$a]);
            if ($l === '') {
                continue;
            }
            if (preg_match('/^\*\*Answer:\*\*\s*(.+)$/u', $l, $m)) {
                $ans = trim($paragraph($answersLines, $a, $end));
                $ans = preg_replace('/^\*\*Answer:\*\*\s*/u', '', $ans) ?? $ans;
            } elseif (preg_match('/^\*\*(Q\d|Concept involved|Applicability|Design|Implementation|Test or|Evidence|Workflow|N\/A)/', $l)) {
                break;
            } else {
                $ans = trim($l);
            }
            break;
        }
        if (mb_strlen($ans) < 40) {
            $shortQ++;
            $findings[] = "ANSWER-SHORT     P{$n}.".($qi + 1).' — '.mb_substr($q, 0, 60)
                .' answers '.mb_strlen($ans).' characters.';
        }
    }

    // applicability: exactly one box, agreeing with the register
    $appLine = null;
    for ($k = $block; $k < $end; $k++) {
        if (str_contains($answersLines[$k], 'Applicability:')) {
            $appLine = $answersLines[$k];
            break;
        }
    }
    if ($appLine === null) {
        $findings[] = "NO-APPLICABILITY P{$n} — the block carries no Applicability line.";
    } else {
        $checks = substr_count($appLine, '☑');
        if ($checks !== 1) {
            $findings[] = "BOX-COUNT        P{$n} — {$checks} boxes checked; the guide asks for exactly one.";
        }
        $want = $verdicts[$n];
        if (! str_contains($appLine, $OPTION[$want])) {
            $findings[] = "DISAGREES-REGISTER P{$n} — the register reads {$want}, this file checks "
                .trim(preg_replace('/^\s*[*]*\s*Applicability:?\s*\|?\s*/i', '', $appLine));
        }
    }

    // the N/A / different-workflow line
    $na = null;
    for ($k = $block; $k < $end; $k++) {
        if (preg_match('/^\*\*Workflow difference[^*]*:\*\*/u', trim($answersLines[$k]))) {
            $na = trim(preg_replace('/^\*\*Workflow difference[^*]*:\*\*\s*/u', '', $paragraph($answersLines, $k, $end)) ?? '');
            break;
        }
    }
    if ($na === null || mb_strlen($na) < 40) {
        $findings[] = "NO-WORKFLOW-LINE P{$n} — the guide's 'If N/A or different from our workflow…' line"
            .($na === null ? ' is not answered.' : ' answers '.mb_strlen($na).' characters.');
    }

    // the six task verbs, per block
    foreach ([
        'Concept involved' => 'CONCEPT-MISSING',
        'Applicability' => 'APPLICABILITY-MISSING',
        'Design:' => 'DESIGN-MISSING',
        'Implementation:' => 'IMPLEMENTATION-MISSING',
        'Test or demonstration:' => 'TEST-MISSING',
        'Evidence:' => 'EVIDENCE-MISSING',
    ] as $needle => $label) {
        if ($needle === 'Applicability') {
            continue;
        }
        if (! str_contains($body, '**'.str_replace(':', '', $needle)) && ! str_contains($body, $needle)) {
            $findings[] = "{$label}         P{$n} — the block does not carry the guide's '$needle' part.";
        }
    }

    $report[] = sprintf('P%02d  questions %2d  missing %d  short %d', $n, count($p['questions']), $missQ, $shortQ);
}

/** ---------- IV. the concept table ---------- */
$concepts = [];
$inIV = false;
for ($i = 0; $i < count($gl); $i++) {
    $line = trim($gl[$i]);
    if (preg_match('/^IV\.\s/', $line)) {
        $inIV = true;

        continue;
    }
    if ($inIV && preg_match('/^V\.\s/', $line)) {
        break;
    }
    if (! $inIV) {
        continue;
    }
    if (str_contains($line, '☐') && isset($concepts[count($concepts) - 1]) === false) {
        // fallthrough handled below
    }
    if ($line !== '' && ! str_contains($line, '☐') && isset($gl[$i + 1]) && str_contains(trim($gl[$i + 1]), '☐')) {
        $options = [];
        if (preg_match_all('/☐\s*([^☐]+)/u', trim($gl[$i + 1]), $mo)) {
            foreach ($mo[1] as $o) {
                $options[] = trim($o);
            }
        }
        $concepts[] = ['name' => $line, 'options' => $options];
    }
    if (preg_match('/^N\/A explanation/i', $line)) {
        $concepts[] = ['name' => $line, 'options' => []];
    }
}
if (count($concepts) < 12) {
    fwrite(STDERR, 'REFUSED: part IV read '.count($concepts)." concept rows; the guide's table holds 12.\n");
    exit(1);
}
$conceptRows = array_values(array_filter($concepts, static fn ($c) => $c['options'] !== []));
$naRow = array_values(array_filter($concepts, static fn ($c) => $c['options'] === []));
if (count($conceptRows) !== 12) {
    fwrite(STDERR, 'REFUSED: part IV read '.count($conceptRows)." status rows, not 12.\n");
    exit(1);
}
foreach ($conceptRows as $ci => $c) {
    $rowNo = $ci + 1;
    $line = null;
    foreach ($answersLines as $l) {
        if (preg_match('/^\*\*Row '.$rowNo.' — /u', trim($l)) && str_contains($fp($l), $fp($c['name']))) {
            $line = trim($l);
            break;
        }
    }
    if ($line === null) {
        $findings[] = "CONCEPT-MISSING  IV row {$rowNo} — ".$c['name'].' has no row in the answers.';

        continue;
    }
    if (! preg_match_all('/(☑|☐)\s*([^☑☐]+)/u', $line, $mm)) {
        $findings[] = "CONCEPT-UNMARKED IV row {$rowNo} — ".$c['name'].' checks no box.';

        continue;
    }
    $marked = [];
    foreach ($mm[1] as $k => $state) {
        if ($state === '☑') {
            $marked[] = trim($mm[2][$k]);
        }
    }
    if ($marked === []) {
        $findings[] = "CONCEPT-UNMARKED IV row {$rowNo} — ".$c['name'].' checks no box.';

        continue;
    }
    $allowed = array_map($fp, $c['options']);
    foreach ($marked as $word) {
        $wfp = $fp($word);
        $hit = false;
        foreach ($allowed as $a) {
            if ($a !== '' && (str_contains($a, $wfp) || str_contains($wfp, $a))) {
                $hit = true;
                break;
            }
        }
        if (! $hit) {
            $findings[] = "INVENTED-WORD   IV row {$rowNo} — '{$word}' is not a status the guide's own row offers ("
                .implode(' / ', $c['options']).').';
        }
    }
}
$naAnswer = null;
foreach ($answersLines as $idx => $l) {
    if (preg_match('/^\*\*N\/A explanation[^*]*:\*\*/u', trim($l))) {
        $naAnswer = trim(preg_replace('/^\*\*N\/A explanation[^*]*:\*\*\s*/u', '', $paragraph($answersLines, $idx, count($answersLines))) ?? '');
        break;
    }
}
if ($naAnswer === null || mb_strlen($naAnswer) < 60) {
    $findings[] = 'IV-NA-LINE       part IV\'s "N/A explanation / alternative demonstration plan" line is '
        .($naAnswer === null ? 'absent.' : 'only '.mb_strlen($naAnswer).' characters.');
}

/** ---------- V. the presentation requirements ---------- */
$groups = [];
$inV = false;
for ($i = 0; $i < count($gl); $i++) {
    $line = trim($gl[$i]);
    if (preg_match('/^V\.\s/', $line)) {
        $inV = true;

        continue;
    }
    if ($inV && preg_match('/^VI\.\s/', $line)) {
        break;
    }
    if (! $inV || $line === '') {
        continue;
    }
    if (preg_match('/^(\d+)\.\s+(.+)$/', $line, $m)) {
        $groups[(int) $m[1]] = ['heading' => trim($m[2]), 'items' => []];

        continue;
    }
    if ($groups !== []) {
        $last = array_key_last($groups);
        $groups[$last]['items'][] = $line;
    }
}
$itemCount = array_sum(array_map(static fn ($g) => count($g['items']), $groups));
if (count($groups) !== 8 || $itemCount !== 25) {
    fwrite(STDERR, 'REFUSED: part V read '.count($groups)." groups and {$itemCount} sub-items; the guide holds 8 and 25.\n");
    exit(1);
}
foreach ($groups as $gno => $g) {
    if (! $answer($fp($g['heading']))) {
        $findings[] = "GROUP-MISSING    V.{$gno} — the group '{$g['heading']}' is not answered.";
    }
    foreach ($g['items'] as $item) {
        if (! $answer($fp($item))) {
            $findings[] = 'SUBITEM-MISSING  V.'.$gno.' — "'.$item.'" does not appear in the answers.';
        }
    }
}

/** ---------- VI, VII and the task verbs ---------- */
$inVI = false;
$defense = '';
for ($i = 0; $i < count($gl); $i++) {
    $line = trim($gl[$i]);
    if (preg_match('/^VI\.\s/', $line)) {
        $inVI = true;

        continue;
    }
    if ($inVI && preg_match('/^VII\.\s/', $line)) {
        break;
    }
    if ($inVI && $line !== '') {
        $defense .= ' '.$line;
    }
}
/**
 * Split the defense block into its sentences. A terminator is often followed by a closing curly quote
 * before the space — `students?” Be prepared` — so a lookbehind that demands whitespace straight after
 * the `?` never fires and the whole section arrives as one item that no answer can contain.
 */
$defenseItems = array_values(array_filter(
    array_map('trim', preg_split('/(?<=[.?!])[”"\'\)\]]?\s+/u', $defense) ?: []),
    static fn ($s) => mb_strlen($s) > 20
));
foreach ($defenseItems as $d) {
    if (! $answer($fp($d))) {
        $findings[] = 'DEFENSE-MISSING  part VI does not quote: '.mb_substr($d, 0, 80).'…';
    }
}
$defenseAnswerLen = 0;
foreach ($answersLines as $idx => $l) {
    if (preg_match('/^## VI\./', $l)) {
        $buf = [];
        for ($k = $idx + 1; $k < count($answersLines); $k++) {
            if (preg_match('/^## /', $answersLines[$k])) {
                break;
            }
            $buf[] = trim($answersLines[$k]);
        }
        $defenseAnswerLen = mb_strlen(trim(implode(' ', $buf)));
        break;
    }
}
if ($defenseAnswerLen < 600) {
    $findings[] = 'DEFENSE-SHORT    part VI answers itself in '.$defenseAnswerLen.' characters; the defense question wants an argument.';
}

$fillIns = [];
$inVII = false;
for ($i = 0; $i < count($gl); $i++) {
    $line = trim($gl[$i]);
    if (preg_match('/^VII\.\s/', $line)) {
        $inVII = true;

        continue;
    }
    if ($inVII && $line !== '' && preg_match('/^[A-Za-z].*:/', $line)) {
        $fillIns[] = trim(preg_replace('/:.*$/', '', $line));
    }
    if ($inVII && preg_match('/^Final Note/i', $line)) {
        break;
    }
}
if (count($fillIns) < 4) {
    fwrite(STDERR, 'REFUSED: part VII read '.count($fillIns)." fill-ins; the guide holds four.\n");
    exit(1);
}
foreach ($fillIns as $label) {
    $hit = null;
    foreach ($answersLines as $l) {
        $t = trim($l);
        if (! preg_match('/^[-*]*\s*\*\*('.preg_quote($label, '/').'):?\*\*/iu', $t)
            && ! str_contains($fp($t), $fp($label.':'))) {
            continue;
        }
        $hit = $t;
        break;
    }
    if ($hit === null) {
        $findings[] = "FILLIN-MISSING   part VII's '{$label}' is not filled in.";

        continue;
    }
    $value = trim(preg_replace('/^.*?:\*\*\s*/u', '', $hit, 1) ?? '');
    $value = trim(str_replace(['*', '`', '-'], '', $value));
    if (mb_strlen($value) < 4) {
        $findings[] = "FILLIN-BLANK     part VII's '{$label}' carries no value.";
    }
}

$verbs = [];
$inTask = false;
for ($i = 0; $i < count($gl); $i++) {
    $line = trim($gl[$i]);
    if ($line === 'Your task is to:') {
        $inTask = true;

        continue;
    }
    if ($inTask) {
        if ($line === '' || preg_match('/^II\.\s/', $line)) {
            break;
        }
        if (preg_match('/^[A-Z]/', $line)) {
            $verbs[] = $line;
        }
    }
}
$verbs = array_slice($verbs, 0, 6);
if (count($verbs) !== 6) {
    fwrite(STDERR, 'REFUSED: part I read '.count($verbs)." task verbs; the guide lists six.\n");
    exit(1);
}
foreach ($verbs as $v) {
    if (! $answer($fp($v))) {
        $findings[] = 'VERB-MISSING     part I does not answer the task verb: '.$v;
    }
}

/** ---------- verdict ---------- */
echo 'ADS guide answer census — '.$answersPath."\n";
echo "population: 24 problems, {$qTotal} guide questions, ".count($conceptRows).' concept rows, '
    .count($groups).' groups / '.$itemCount.' sub-items, '.count($fillIns).' fill-ins, 6 task verbs, '
    .count($defenseItems)." defense sentences\n\n";
if ($verbose) {
    foreach ($report as $r) {
        echo "  {$r}\n";
    }
    echo "\n";
}
$findings = array_values(array_unique($findings));
if ($findings === []) {
    echo "EVERY ITEM ANSWERED: 0 missing, 0 short, 0 disagreement with the P register.\n";
    exit(0);
}
foreach ($findings as $f) {
    echo '  '.$f."\n";
}
echo count($findings)." finding(s) above — the guide is not fully answered.\n";
exit(1);
