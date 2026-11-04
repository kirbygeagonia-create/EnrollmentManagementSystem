<?php

/**
 * Lane 262 — two Word documents out of the ADS guide's answers, split the way the owner asked for them.
 *
 * Document A is the question sheet: the guide's own problems, its own scenario, and its guide questions numbered as
 * the guide lists them (Problem 3, Question 2 = Q3.2), each answered in plain words with any hard term explained
 * underneath. It carries no field names, no file paths and no instrument output, because that is what "simple and
 * direct" asks for.
 *
 * Document B is everything else, separated per problem: concept involved, applicability, design, implementation,
 * test or demonstration, evidence, and the workflow difference — lifted verbatim from GUIDE-PROBLEM-ANSWERS-ADS.md,
 * which is the weighed source, so this document is a projection and not a second copy to drift.
 *
 * Both are refused rather than written short: the question population is read from the answer file, the plain answers
 * from GUIDE-QUESTIONS-PLAIN-ANSWERS-ADS.md, and the two sets must be equal in BOTH directions — a question with no
 * plain answer and a plain answer answering no question are different failures and both stop the run. The problem
 * count is checked against the guide's own flat text as a third opinion, so a lost row in either file cannot pass.
 *
 * Usage: php storage/app/scratch/lane262-make-guide-docx.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-writer.php';

$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: this tool reads only --apply, got '{$arg}'.\n");

        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");

    exit(1);
};

$read = static function (string $rel) use ($root, $fail): string {
    if (! is_file($root.'/'.$rel)) {
        $fail("{$rel} is not on disk, so a document built without it would be written from memory.");
    }
    $t = (string) file_get_contents($root.'/'.$rel);
    if (trim($t) === '') {
        $fail("{$rel} read back empty.");
    }

    return $t;
};

$answers = $read('Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md');
$plain = $read('Documentation/GUIDE-QUESTIONS-PLAIN-ANSWERS-ADS.md');
$guideFlat = is_file($root.'/storage/app/scratch/ads-guide-flat.txt')
    ? (string) file_get_contents($root.'/storage/app/scratch/ads-guide-flat.txt')
    : '';

// ---- the guide's own problems and scenarios (third opinion on the population)
$guideProblems = [];
if (preg_match_all('/^Problem (\d+) — (.+)$/mu', $guideFlat, $gm, PREG_SET_ORDER)) {
    foreach ($gm as $g) {
        $guideProblems[(int) $g[1]] = trim($g[2]);
    }
}
if (preg_match_all('/Scenario: ?(.+)/', $guideFlat, $sm, PREG_SET_ORDER)) {
    $i = 0;
    foreach ($sm as $s) {
        $i++;
        if (isset($guideProblems[$i])) {
            $guideProblems[$i] = [$guideProblems[$i], preg_replace('/\s+/', ' ', trim($s[1]))];
        }
    }
}
$guideProblemCount = count($guideProblems);
if ($guideProblemCount !== 0 && $guideProblemCount !== 24) {
    $fail("the guide's own flat text answered {$guideProblemCount} problems; the guide holds 24, so the scenario join "
        .'would mis-number the sheet.');
}

// ---- the answer file: per problem, the separated fields and the questions with their long answers
$fields = ['Concept involved', 'Applicability', 'Design', 'Implementation', 'Test or demonstration', 'Evidence',
    'Workflow difference, or why this is N/A'];
$problems = [];
if (preg_match_all('/^### Problem (\d+) — (.+)$/mu', $answers, $pm, PREG_SET_ORDER) === 0) {
    $fail('the answer file names no problems in the shape this tool reads.');
}
$titles = [];
foreach ($pm as $p) {
    $titles[(int) $p[1]] = trim($p[2]);
}
if (count($titles) !== 24) {
    $fail('the answer file holds '.count($titles).' problems; the guide holds 24, so a row was lost between them.');
}

$blocksOf = static function (string $text, array $fieldsList): array {
    $out = [];
    foreach ($fieldsList as $f) {
        if (preg_match('/\*\*'.preg_quote($f, '/').':\*\*[ \t]*(.+?)(?=\n\*\*[A-Z]|\n\n\*\*Q|\z)/su', $text, $m) === 1) {
            $out[$f] = preg_replace('/\s+/', ' ', trim($m[1]));
        }
    }

    return $out;
};

foreach ($titles as $n => $title) {
    $start = strpos($answers, "### Problem {$n} — ");
    $next = strpos($answers, "\n### Problem ", $start + 1);
    $chunk = substr($answers, $start, $next === false ? null : $next - $start);

    $q = [];
    // `|\z` matters: this chunk is already cut before the next problem's heading, so the LAST question of every
    // problem has no following `\n\n**Q` or `\n\n###` inside it. Without the end-of-string branch the reader silently
    // drops one question per problem and the sheet would answer three of every four.
    if (preg_match_all('/\*\*(Q\d+\.\d+)\*\*\s*(.+?)\n\*\*Answer:\*\*\s*(.+?)(?=\n\n\*\*Q|\n\n###|\n\n---|\z)/su', $chunk, $qm, PREG_SET_ORDER)) {
        foreach ($qm as $one) {
            $q[explode('.', trim($one[1], 'Q'))[1]] = [
                'question' => preg_replace('/\s+/', ' ', trim($one[2])),
                'answer' => preg_replace('/\s+/', ' ', trim(strip_tags($one[3]))),
            ];
        }
    }
    if ($q === []) {
        $fail("Problem {$n} carries no guide questions in the answer file, so the sheet would silently skip a problem.");
    }

    $problems[$n] = ['title' => $title, 'fields' => $blocksOf($chunk, $fields), 'questions' => $q];
}

// ---- the plain file: every question answered in short words
$plainAnswers = [];
if (preg_match_all('/\*\*Q(\d+)\.(\d+)\*\*\s*(.+?)\nPlain answer:\s*(.+?)(?=\nWords:|\n\*\*Q|\n##|\z)/su', $plain, $fm, PREG_SET_ORDER) === 0) {
    $fail('the plain-answers file holds no answer in the shape this tool reads.');
}
foreach ($fm as $one) {
    $words = preg_match('/\nWords:\s*(.+?)(?=\n\*\*Q|\n##|\z)/su', $plain, $wm, 0, (int) strpos($plain, $one[0])) === 1
        ? preg_replace('/\s+/', ' ', trim($wm[1]))
        : '';
    $plainAnswers[(int) $one[1]][(int) $one[2]] = [
        'question' => preg_replace('/\s+/', ' ', trim($one[3])),
        'answer' => preg_replace('/\s+/', ' ', trim($one[4])),
        'words' => $words,
    ];
}

// ---- the two directions of the population check
$missing = [];
$extra = [];
foreach ($problems as $n => $p) {
    foreach (array_keys($p['questions']) as $k) {
        if (! isset($plainAnswers[$n][$k])) {
            $missing[] = "Q{$n}.{$k}";
        } elseif (preg_replace('/[?.]$/', '', $plainAnswers[$n][$k]['question']) !== preg_replace('/[?.]$/', '', $p['questions'][$k]['question'])) {
            $missing[] = "Q{$n}.{$k} (the plain file answers a DIFFERENT question text than the guide asks)";
        }
    }
    foreach (array_keys($plainAnswers[$n] ?? []) as $k) {
        if (! isset($p['questions'][$k])) {
            $extra[] = "Q{$n}.{$k}";
        }
    }
}
if ($missing !== [] || $extra !== []) {
    $fail('the plain answers and the guide questions do not agree both ways. Missing or mismatched: '
        .implode(', ', array_slice($missing, 0, 12)).($missing !== [] && $extra !== [] ? ' | ' : '')
        .implode(', ', array_slice($extra, 0, 12)).'. Total: '.count($missing).' missing, '.count($extra).' extra.');
}
$plainCount = array_sum(array_map('count', $plainAnswers));

// ------------------------------------------------------------------ Document A: questions, plainly
$a = [];
$a[] = ['h1', 'ADS Guide — questions and answers in plain words'];
$a[] = ['p', 'SEAIT Enrollment Management System. This is the question sheet only: the guide\'s problems, its scenario, '
    .'and its guide questions numbered exactly as the guide lists them ("Problem 3, Question 2" is the Q3.2 used '
    .'throughout this project). Each answer leads with the plain verdict. A technical word that the answer cannot avoid '
    .'is explained on the line underneath it. The separated detail — concept, design, implementation, evidence, '
    .'applicability and workflow — lives in the companion document, and the full technical wording stays in '
    .'Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md.'];
$a[] = ['p', 'Questions answered: '.$plainCount.' across 24 problems. The generator refuses to write this page while any '
    .'guide question lacks a plain answer, or while a plain answer answers a different question text than the guide asks.'];

$gloss = [];
if (preg_match('/Words used again and below:(.+?)\n\n/su', $plain, $gkm) === 1) {
    foreach (preg_split('/\.\s+(?=\*\*)/', trim($gkm[1])) as $g) {
        $g = trim($g);
        if ($g !== '') {
            $gloss[] = preg_replace('/\s+/', ' ', str_replace(['**', ' = '], ['', ' means: '], $g));
        }
    }
}
if ($gloss !== []) {
    $a[] = ['h2', 'Words used throughout, in one place'];
    $a[] = ['bullets', $gloss];
}

foreach ($problems as $n => $p) {
    $a[] = ['h2', 'Problem '.$n.' — '.$p['title']];
    $gp = $guideProblems[$n] ?? null;
    if (is_array($gp) && ($gp[1] ?? '') !== '') {
        $a[] = ['p', 'The guide\'s scenario: '.$gp[1]];
    }
    foreach ($p['questions'] as $k => $qq) {
        $a[] = ['h3', 'Problem '.$n.', Question '.$k.' — '.$qq['question']];
        $a[] = ['p', $plainAnswers[$n][$k]['answer']];
        if ($plainAnswers[$n][$k]['words'] !== '') {
            $a[] = ['p', $plainAnswers[$n][$k]['words']];
        }
    }
}

// ------------------------------------------------------------------ Document B: the separated parts
$b = [];
$b[] = ['h1', 'ADS Guide — concepts, design, implementation and evidence, separated per problem'];
$b[] = ['p', 'The same 24 problems, with each part of the answer in its own row. Nothing here is rewritten: every cell is '
    .'lifted from Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md, which is the weighed source the guide copy is filled '
    .'from, so this document cannot drift from it without the generators disagreeing first. Where a field is absent in '
    .'the source, the row says ABSENT rather than leaving a gap a reader has to interpret.'];

$summary = [];
foreach ($problems as $n => $p) {
    $app = $p['fields']['Applicability'] ?? 'ABSENT';
    $summary[] = ['P'.$n, $p['title'], str_replace(['☑', '☐', '**'], ['[x]', '[ ]', ''], $app)];
}
$b[] = ['h2', 'Applicability at a glance, in the guide\'s own words'];
$b[] = ['table', ['Problem', 'Title', 'Applicability as ticked in the guide'], $summary];

foreach ($problems as $n => $p) {
    $b[] = ['h2', 'Problem '.$n.' — '.$p['title']];
    $rows = [];
    foreach ($fields as $f) {
        $rows[] = [str_replace(',', ', and', $f), $p['fields'][$f] ?? 'ABSENT in the source file'];
    }
    $b[] = ['table', ['Part of the answer', 'What our system states'], $rows];
    if (($p['fields']['Applicability'] ?? '') !== '' && ! str_contains($p['fields']['Applicability'], '☑')) {
        $b[] = ['p', 'Note: this problem\'s applicability line carries no tick in the source, which is stated rather than assumed.'];
    }
}

echo 'questions '.$plainCount.' | problems 24 | fields per problem '.count($fields)."\n";
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

docxPackage($root.'/Documentation/ADS-Guide-Questions-and-Simple-Answers.docx', docxBlocksToBody($a), true);
docxPackage($root.'/Documentation/ADS-Guide-Concepts-Design-and-Implementation.docx', docxBlocksToBody($b), true);
echo 'WROTE Documentation/ADS-Guide-Questions-and-Simple-Answers.docx'."\n";
echo 'WROTE Documentation/ADS-Guide-Concepts-Design-and-Implementation.docx'."\n";
