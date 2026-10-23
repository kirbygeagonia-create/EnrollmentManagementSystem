<?php

/**
 * Pass 179f — P-17 opens in §25.4, and P-10's consequence cell stops saying the derivation is settled.
 *
 * Why this is a finding and not a tidy-up: `Studentclearances::termEnrollment()` is the one method all
 * three clearance-slip print paths call, and it has two branches. The term-matched branch is what
 * P-10 recorded as closed on 2026-10-02; the other branch — a slip raised after its own term closed —
 * returns the student's newest enrollment by id, under a comment that says the Registrar should
 * decide. P-10's consequence cell reads "Nothing for the derivation", so the document stated the
 * question was settled while the code carried it as provisional. No checker could see it: every
 * pointer lands on a live line, every §-reference resolves, and lane 61 only catches a sentence that
 * presents an ANSWERED id as undecided, never an OPEN branch inside a CLOSED row.
 *
 * Measured on live `ems` before writing (stage 0 re-measures): all four studentclearances rows hold an
 * enrollment in their period's own term, so the fallback has never fired on this box — which is
 * exactly why it survived every print rehearsal.
 *
 * Stage 1 clones the P-16 row; stage 2 moves the four range statements, §29.4's count cell, the
 * §28.4 group row's ids and its count sentence, §28.8's tally of findings outside the register,
 * §30.5's cross-reference, §30.6's open list in both copies, P-10's cells, and the living §1.1 row.
 *
 * DRY RUN unless --apply.
 */

require_once __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};
$assertPlain = function (string $text, string $what) use (&$fails): void {
    $bad = [];
    foreach (['&', '<', '>'] as $c) {
        if (str_contains($text, $c)) {
            $bad[] = $c;
        }
    }
    if ($bad !== []) {
        printf("  FAIL %s carries %s — it would be escaped twice or corrupt the part\n", $what, implode(' ', $bad));
        $fails++;
    }
};
$setCellText = function (string $cell, string $text) use ($guard): string {
    $encoded = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $first = true;
    $out = preg_replace_callback('/<w:t(?:\s[^>]*)?>.*?<\/w:t>/s', function (array $x) use (&$first, $encoded): string {
        if ($first) {
            $first = false;

            return preg_replace('/>.*?<\/w:t>/s', '>'.$encoded.'</w:t>', $x[0], 1);
        }

        return preg_replace('/>.*?<\/w:t>/s', '></w:t>', $x[0], 1);
    }, $cell);

    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $out, $t);
    $flat = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $guard($flat === $text, 'the cloned cell reads exactly this pass’s text');

    return $out;
};

// ---------------------------------------------------------------- stage 0: the measurements the row rests on

require_once $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$modelLines = explode("\n", (string) file_get_contents($root.'/app/Models/Studentclearances.php'));
$at = 0;
foreach ($modelLines as $i => $l) {
    if (str_contains($l, 'public function termEnrollment')) {
        $at = $i + 1;
    }
}
$guard($at === 76, 'termEnrollment() begins at Studentclearances.php:76 (read '.$at.')');
$guard(str_contains($modelLines[93] ?? '', "return \$exact ?? \$enrollments->sortByDesc('enrollmentId')->first();"),
    'the fallback is line 94, so the citation this row writes is the fallback itself');
$guard(str_contains($modelLines[90] ?? '', 'PROVISIONAL'), 'and the provisional marker is at line 91');

foreach ([
    'app/Http/Controllers/Clearance/ClearanceController.php:386',
    'app/Services/PrintService.php:77',
    'resources/views/prints/clearance-slip.blade.php:65',
] as $cite) {
    [$p, $line] = explode(':', $cite);
    $l = explode("\n", (string) file_get_contents($root.'/'.$p))[(int) $line - 1];
    $guard(str_contains($l, 'termEnrollment'), $cite.' calls the method this row names');
}

$scene = DB::select('SELECT sc.studentClearanceId id, (SELECT COUNT(*) FROM enrollments e
    WHERE e.studentId = sc.studentId AND e.termId = cp.termId) in_term
    FROM studentclearances sc JOIN clearanceperiods cp ON cp.clearancePeriodId = sc.clearancePeriodId');
$never = count(array_filter($scene, fn ($r) => (int) $r->in_term === 0));
$guard(count($scene) === 4 && $never === 0,
    'live ems holds 4 clearances and none of them needs the fallback (read '.count($scene).' rows, '.$never.' without a term enrollment)');
$guard(is_file($root.'/tests/Feature/Print/ClearanceSlipTermEnrollmentTest.php'), 'the test this row cites exists');
$guard(is_file($root.'/storage/app/scratch/mutate-p17-term-enrollment-tests.php'), 'and so does its mutant harness');
$guard(str_contains((string) file_get_contents($root.'/Documentation/School-Decision-Sheet-2026-10-23.md'),
    'P-17'), 'the school packet already carries the question');

// ---------------------------------------------------------------- stage 1: clone the P-16 row into P-17

$zip = new ZipArchive;
$zip->open($src);
$parts = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

$guard(substr_count($new, '>P-16<') === 1, 'P-16 is findable as the clone template');
$guard(substr_count($new, '>P-17<') === 0, 'P-17 is not already in the document');

$p17 = [
    'P-17',
    'Open since 2026-10-23, and found by reading the model rather than by probing the print: the clearance '
        .'slip’s enrollment is decided in one method that has two branches. When the student holds an '
        .'enrollment in the clearance period’s own term, that is the record printed — the half P-10 closed on '
        .'2026-10-02. When the period’s term holds none, which is what a slip raised after its term closed '
        .'looks like, the method returns the student’s newest enrollment by id, under a comment that asks the '
        .'Registrar to say which enrollment the paper should name. §25.4 carried no row for that branch, and '
        .'P-10’s consequence cell said the derivation was settled, so the document stated as closed a question '
        .'the code still marks provisional. Measured first: live `ems` holds four clearances and every one of '
        .'them has an enrollment in its period’s term, so the fallback has never fired here — which is why no '
        .'print rehearsal, fidelity run or audit rule could have found it.',
    'The fallback at Studentclearances.php:94, its own PROVISIONAL comment at :91, and the method’s start at '
        .':76; all three slip-print paths call it ( ClearanceController.php:386 , PrintService.php:77 , '
        .'clearance-slip.blade.php:65 ). Both branches are pinned by '
        .'tests/Feature/Print/ClearanceSlipTermEnrollmentTest.php, and that pinning is proved to be able to fail '
        .'by storage/app/scratch/mutate-p17-term-enrollment-tests.php, which plants the fallback removed, the '
        .'term filter dropped, the template re-deriving the answer inline, and the marker renamed.',
    'A slip printed after its term closed states a course and year level from a term the clearance is not '
        .'about, and says nothing on its face about having done so. Which enrollment the paper should name — '
        .'the one being cleared, the last one attended, or none until the Registrar resolves it — is the '
        .'school’s to state, and it is item 14 of Documentation/School-Decision-Sheet-2026-10-23.md. Until an '
        .'answer arrives the code keeps its current choice and the tests keep describing it, so a later change '
        .'is a ruling and not a drift.',
    'Institutional decision',
];

$at16 = strpos($new, '>P-16<');
$tStart = (int) strrpos(substr($new, 0, (int) $at16), '<w:tr');
$tEnd = (int) strpos($new, '</w:tr>', (int) $at16) + 7;
$template = substr($new, $tStart, $tEnd - $tStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $tCells);
$guard(count($tCells[0]) === count($p17), 'the P-16 row has '.count($tCells[0]).' cells, the pass supplies '.count($p17));
if (count($tCells[0]) === count($p17)) {
    $clone = $template;
    foreach ($tCells[0] as $i => $cell) {
        $assertPlain($p17[$i], 'P-17 cell '.($i + 1));
        $clone = str_replace($cell, $setCellText($cell, $p17[$i]), $clone);
    }
    $guard($clone !== $template, 'the cloned P-17 row carries new text');
    foreach (['<w:tr', '</w:tr>', '<w:tc>', '</w:p>', '</w:t>'] as $n) {
        $guard(substr_count($clone, $n) === substr_count($template, $n), 'the cloned row’s container '.$n.' is one row’s worth');
    }
    $new = substr_replace($new, $clone, $tEnd, 0);
}

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $new, $all);
$ids = [];
foreach ($all[0] as $row) {
    if (preg_match('/>P-(\d+)</', $row, $m) === 1) {
        $ids[] = (int) $m[1];
    }
}
// P-8 and P-10 are also named by rows outside §25.4 (§28.4 lists closed findings by id), so the first
// occurrence of each id is the register and later ones are pointers. The claim is about the register.
$first = array_values(array_unique($ids));
$guard($first === range(1, 17), 'the print register reads P-1 … P-17 in order, once each (read '
    .implode(',', $first).'; pointers '.implode(',', array_diff($ids, $first)).')');
$guard(substr_count($new, 'P-1 … P-16') === 4, 'the four range statements are still present to move '
    .'(read '.substr_count($new, 'P-1 … P-16').')');
$guard(substr_count($new, '>16<') === 1, 'exactly one count cell still says 16');

// §29.4’s count cell is a whole run whose text is `16`; a text edit would escape the replacement, so the
// digit moves here, on the raw bytes, addressed by the only run in the document that is exactly “16”.
$new = str_replace('>16<', '>17<', $new, $cSix);
$guard($cSix === 1, 'the §29.4 count cell moved to 17 ('.$cSix.')');

$guard(@(new DOMDocument)->loadXML($new) !== false, 'the staged document.xml parses');

if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "\nStage 1 would clone P-17 into §25.4 after P-16. Stage 2 would move the four P-1 … P-16 ranges and\n"
        ."§29.4’s count cell, add P-17 to §28.4’s decision group and §28.8’s tally of findings outside the\n"
        ."register, extend §30.5’s cross-reference and §30.6’s open list in both copies, correct P-10’s finding\n"
        ."and consequence cells, and add the branch to the living §1.1 row.\n\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass179f.docx');
$z = new ZipArchive;
$z->open($src);
$z->addFromString('word/document.xml', $new);
$z->close();

$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: stage 1 wrote a file the zip will not open.\n");
}
$guard($z->numFiles === $parts, 'the archive still holds '.$parts.' members');
$round = (string) $z->getFromName('word/document.xml');
$z->close();
$guard($round === $new, 'the bytes read back are the bytes staged');
$guard(substr_count($round, '>P-17<') === 1, 'P-17 is on disk');
if ($fails > 0) {
    printf("\n%d guard(s) failed after the stage 1 write — restore from storage/app/scratch/ems-docx-pre-pass179f.docx.\n", $fails);
    exit(1);
}
echo "     stage 1 written and re-read\n";

// ---------------------------------------------------------------- stage 2: the prose, through the engine

$textEdits = [
    ['P-1 … P-16', 'P-1 … P-17', null, 4],
    ['P-12, P-4, P-9, P-16', 'P-12, P-4, P-9, P-16, P-17'],
    [
        'and the third is the one place left where only the school can settle what the paper says (P-16).',
        'and two are places where only the school can settle what the paper says — the spelling of the name '
            .'(P-16), and which enrollment a slip names when its own period’s term holds none (P-17).',
    ],
    [
        'Three print findings sit outside this table because it counts only 28.2 and 28.3',
        'Four print findings sit outside this table because it counts only 28.2 and 28.3',
    ],
    [
        'P-7, P-12 and P-16 in §25.4, §25.5',
        'P-7, P-12, P-16 and P-17 in §25.4, §25.5',
    ],
    ['fourteen items', 'fifteen items'],
    ['The fourteen that do wait', 'The fifteen that do wait'],
    [
        'P-16, the spelling of the name the letterhead prints, stays the school’s to state.',
        'P-17 opened from the same reading: the clearance slip falls back to the student’s newest enrollment when '
            .'its period’s term holds none, under a comment that asks the Registrar which enrollment the paper '
            .'should name, while §25.4 had recorded the derivation as settled. P-16, the spelling of the name the '
            .'letterhead prints, stays the school’s to state.',
    ],
];

$cellEdits = [
    // §28.4's group row: the ids live in one cell and the count sentence in another, and that sentence is
    // split across runs, so the whole cell is rewritten rather than spliced.
    ['A decision before any code', 'Three are settled',
        'Three of the five are settled: the fee is Reference Data’s alone (ruling 8), office 8 is retired in data '
            .'(ruling 12), and the subject load has its own template, so the act this row waited for was taken and '
            .'built. The other two are open and neither is a code decision at all: which spelling of the '
            .'institution’s name the paper carries (P-16), and which enrollment a clearance slip names when its own '
            .'period’s term holds none (P-17).'],
    // P-10 keeps its closed half and stops claiming the derivation is finished.
    ['the slip takes course, year level and the log', 'Closed 2026-10-02, code landed 2026-10-01',
        'Closed 2026-10-02, code landed 2026-10-01, and narrower than it was written: the slip takes course, year '
            .'level and the log’s enrollment from the enrollment of the clearance period’s own term WHEN THE STUDENT '
            .'HOLDS ONE in that term. The other branch — a slip raised after its term closed — was never answered '
            .'and is P-17, so this row is closed for the derivation it actually describes.'],
    ['the slip takes course, year level and the log', 'Nothing for the derivation',
        'Nothing for the term-matched half. The window-vs-term question that remains is the acceptance script’s §18 '
            .'item 1 and is a ruling about which window a term owns, not a code defect; the enrollment a slip names '
            .'when its period’s term holds none is P-17, and it is the school’s to state.'],
];

docxApply($src, $root.'/storage/app/scratch/pass179f-backup.docx', $textEdits, $cellEdits, $apply);
