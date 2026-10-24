<?php

/**
 * Pass 185 — the owner ruled the year level's proper format is "2nd Year", not "Year 2" (2026-10-24).
 *
 * That reverses the one half of P-19 the previous pass deliberately did not adopt, so every passage that
 * recorded the deviation has to say what the build now does: §25.4's P-19 row (its paper-reading sentence
 * and its consequence cell), §25.6's identity paragraph, §25.11's question row, §28.5's decision row,
 * §24's vocabulary cell and §4.5's Example B — which contradicted its own neighbours before this batch:
 * Example A read "2nd Year" and Example C read "3rd Year" while Example B read "Year 1".
 *
 * The second half is the cascade. Two desk-facing messages in EvaluationController.php had to stop building
 * "Year " . the integer, and Pint will not let a static call travel through a fully-qualified name in app/ —
 * it files the import for you (proved on a test file this batch also touched). One line added at :46 moved
 * every line below it, so every explicit citation into that file moves +1.
 *
 * Bare continuations (`… , :258-292`) name no file, so they are attributed by reading the sentence and then
 * rewritten BY BYTE OFFSET, never by a global string replace: `:94` occurs seven times in this document and
 * only one of them belongs to the moved file — the other six are PrintCertificate.jsx, HandleInertiaRequests.php,
 * EnrollmentReadiness.php and SendWorkflowNotification.php pointers that a global replace would have corrupted.
 * `:55` was already pointing at a blank line in HEAD; this batch's inserted line slid live code onto that
 * number, which is how a bounds sweep would have started clearing a pointer that never was true. It is
 * re-pointed by reading and verified against the lines it lands on, and every other unverifiable number is
 * reported instead of moved.
 *
 *   php storage/app/scratch/refresh-docx-pass185.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass185.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$controller = 'EvaluationController.php';
$headFile = $root.'/storage/app/scratch/lane50-head/app__Http__Controllers__Evaluation__EvaluationController.php';

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$headLines = explode("\n", (string) file_get_contents($headFile));
$movedLines = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Evaluation/EvaluationController.php'));
$say(count($headLines) + 1 === count($movedLines), 'the controller moved by exactly one line ('.count($headLines).' → '.count($movedLines).')');

// ── 1. the sentences ────────────────────────────────────────────────────────────────────────────────
$edits = [
    ['Year 1 (Freshman) · Year 2 (Sophomore) · Year 3 (Junior) · Year 4 (Senior), rendered through one shared formatter (',
        '1st Year (Freshman) · 2nd Year (Sophomore) · 3rd Year (Junior) · 4th Year (Senior), rendered through one formatter per side (',
        1, '§24 vocabulary cell reads ordinals and names both definitions'],

    ['Program: BSIT · Year Level: Year 1. The only case where new applicant and Freshman coincide.',
        'Program: BSIT · Year Level: 1st Year. The only case where new applicant and Freshman coincide.',
        1, '§4.5 Example B agrees with Examples A and C'],

    ['Information Technology - Year 2, and the office table is unchanged at nine rows.',
        'Information Technology - 2nd Year, and the office table is unchanged at nine rows.',
        1, 'P-19 records what the re-rendered PDF actually says'],

    ['What the ruling did not adopt is the school’s spelling of the year level: the paper writes “2nd Year”, and this build prints “Year 2”, because ',
        'Ruled again the same day, in the school’s favour: the level is now written as an ordinal on every surface. ',
        1, 'the consequence cell stops describing a deviation'],

    ['records that six pages once carried four vocabularies for one number and the shared formatter exists to stop that.',
        'App\Support\YearLevel.php:13-35 and resources/js/Components/ui/statusLabel.js:50-78 carry the one rule on each side, and tests/Feature/YearLevelVocabularyTest.php pins the two against each other and refuses any surface that spells a level by hand — five pick-lists had been doing exactly that while three sibling tables already read the formatter.',
        1, 'the sentence names where the vocabulary lives now'],

    ['the level stays “Year 2” against the school’s “2nd Year”, for the reason §25.4 records.',
        'and its level now reads “2nd Year”, which the owner ruled on 2026-10-24 as the school’s own format (§25.4 P-19).',
        1, '§25.6 stops reporting a divergence'],

    ['The one half not adopted is the school’s spelling of the level — “2nd Year” against this build’s “Year 2”, which ',
        'The school’s spelling of the level was adopted the same day: every surface now reads “2nd Year”, through ',
        1, '§25.11 records the answer, not the deferral'],

    ['exists to keep single — and the wording of the nine lines is still provisional above.',
        'and the wording of the nine lines is still provisional above.',
        1, 'the row’s tail no longer claims the level was left out'],

    ['§25.4 P-19 carries the comparison, the render it was read off, and the one half of the school’s wording that was not adopted;',
        '§25.4 P-19 carries the comparison, the render it was read off, and the level wording the owner ruled in on 2026-10-24;',
        1, 'the §28.5 decision row stops pointing at a deferral'],

    ['the office table stays and the face now marks the copy.',
        'the office table stays and the face now marks the copy. The owner then ruled the one wording this build had kept for itself — “the proper format is 2nd year, not year 2” — so the level is an ordinal on every surface, one definition per side (App\Support\YearLevel for the papers, formatYearLevel and yearLevelChoices for the screens) with tests/Feature/YearLevelVocabularyTest.php pinning the two against each other and refusing a hand-spelled level: five pick-lists had been writing “Year 2” while three sibling tables already read the formatter, and the import that two desk messages needed moved every citation into EvaluationController.php by one line.',
        1, 'the living §1.1 revision row records the reversal'],
];

foreach ($edits as [$from, $to, $want, $label]) {
    $c = substr_count($xml, $from);
    $say($c === $want, $label.' — needle found '.$c.' time(s), expected '.$want);
    foreach (['<', '>'] as $ch) {
        $say(! str_contains($to, $ch), $label.': replacement carries no '.$ch);
    }
}

// ── 2. the bare continuations, attributed by reading and rewritten by offset ────────────────────────
preg_match_all('/'.preg_quote($controller, '/').':([0-9]+)(?:-([0-9]+))?/', $xml, $explicit);
$say(count($explicit[0]) >= 40, 'the sweep found '.count($explicit[0]).' explicit citations into the moved file');
foreach ($explicit[1] as $a) {
    $say((int) $a >= 47, $controller.':'.$a.' opens at a line that moved');
}

$bare = [':958-999', ':235-256', ':585-604', ':258-292', ':630-640', ':465-484', ':465-489', ':55', ':94', ':272', ':646'];
$repoint = [':55' => [':93-95', "->whereIn('enrollmentStatus'", "->orderByDesc('enrollmentId')"]];
$ops = [];
$reported = [];

foreach ($bare as $token) {
    $at = 0;
    $n = 0;
    while (($pos = strpos($xml, $token, $at)) !== false) {
        $n++;
        $at = $pos + strlen($token);

        // Not a bare number: the tail of an explicit citation, already handled by the file-name pass.
        if (preg_match('/'.preg_quote($controller, '/').':[0-9]*$/u', substr($xml, max(0, $pos - 60), 60))) {
            continue;
        }

        // Runs are joined WITH a space. strip_tags() welds one run's end onto the next run's start, and the
        // first dry run read `curriculumsubjects` + `EvaluationController.php` as a single file name and
        // refused to shift pointers that did belong to the moved file.
        $before = preg_replace('/\s+/u', ' ', preg_replace('/<[^>]*>/', ' ', substr($xml, max(0, $pos - 8000), 8000)));
        preg_match_all('/([A-Za-z0-9_.\/\\\\-]+\.(?:php|jsx|js|blade\.php))(?![A-Za-z0-9_])/u', $before, $names);
        $nearest = $names[1] === [] ? '(none in 8000 bytes)' : (string) end($names[1]);
        [$a, $b] = array_map('intval', array_pad(explode('-', ltrim($token, ':')), 2, 0));

        if ($nearest !== $controller) {
            $reported[] = "{$token} hit {$n} at byte {$pos}: the sentence names {$nearest}, not the moved controller — left alone";

            continue;
        }

        if (trim($headLines[$a - 1] ?? '') === '') {
            if (! isset($repoint[$token])) {
                $reported[] = "{$token} hit {$n} at byte {$pos}: {$controller} line {$a} was already blank in HEAD and no re-point is on file — reported, not re-aged";

                continue;
            }
            [$target, $first, $last] = $repoint[$token];
            [$startLine, $endLine] = array_map('intval', explode('-', ltrim($target, ':')));
            $onStart = trim((string) ($movedLines[$startLine - 1] ?? ''));
            $onEnd = trim((string) ($movedLines[$endLine - 1] ?? ''));

            if (! str_contains($onStart, $first) || ! str_contains($onEnd, $last)) {
                $reported[] = "{$token} → {$target} REFUSED: :{$startLine} reads “{$onStart}” and :{$endLine} reads “{$onEnd}”";

                continue;
            }
            $ops[] = [$pos, strlen($token), $target, "re-pointed (was false in HEAD) → {$target}, verified: :{$startLine} and :{$endLine}"];

            continue;
        }

        $ops[] = [$pos, strlen($token), ':'.($a + 1).($b && $b !== $a ? '-'.($b + 1) : ''),
            sprintf('named by %s, HEAD line %d reads “%s”', $nearest, $a, mb_substr(trim($headLines[$a - 1]), 0, 58))];
    }
    $say($n > 0, $token.' is present in the raw XML');
}

echo "\nBare continuations: ".count($ops).' rewritten, '.count($reported)." reported.\n";
foreach ($ops as [$pos, $len, $to, $why]) {
    echo "  .. byte {$pos} ".substr($xml, $pos, $len)." → {$to}  ({$why})\n";
}
foreach ($reported as $r) {
    echo "  ! {$r}\n";
}

// ── 3. apply: offset splices first (right to left), then the file-name pass, then the sentences ─────
$staged = $xml;
usort($ops, fn ($x, $y) => $y[0] <=> $x[0]);
foreach ($ops as [$pos, $len, $to]) {
    $staged = substr_replace($staged, $to, $pos, $len);
}

$staged = preg_replace_callback(
    '/'.preg_quote($controller, '/').':([0-9]+)(?:-([0-9]+))?/',
    fn ($m) => $controller.':'.((int) $m[1] + 1).(($m[2] ?? '') === '' ? '' : '-'.((int) $m[2] + 1)),
    $staged
);
$say(substr_count($staged, $controller.':') === count($explicit[0]),
    'the explicit pass rewrote exactly '.count($explicit[0]).' file-name citations and no other bytes');

// A bare splice may have landed inside an explicit citation if the two overlap; assert they never did.
$say(substr_count($staged, 'EvaluationController.php:55') === 0, 'no citation still reads :55');

foreach ($edits as [$from, $to]) {
    $staged = str_replace($from, $to, $staged);
}

$say(substr_count($staged, 'Year 2 (Sophomore)') === 0, 'no §24 cell still reads the rejected vocabulary');
$say(substr_count($staged, 'Year Level: Year 1') === 0, 'no worked example still reads "Year Level: Year 1"');
$say(substr_count($staged, 'this build prints “Year 2”') === 0, 'no register row still reports the deviation');
$say($staged !== $xml, 'the staged bytes differ from the document on disk');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');

printf("\n%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: ".count($explicit[0])." explicit + ".count($ops)." bare citation(s) and ".count($edits)." sentence(s) would move.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass185.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
