<?php

/**
 * Pass 179 — lane 63: four register items the registers themselves could settle, and the drop one
 * of them asked for.
 *
 * Measured 2026-10-23 before a word was written (stage 0 below re-measures all of it):
 *  - live `ems` answers 8 columns for `studentclearances`, 451 for the schema, 63 tables, and 4 rows
 *    in that table — so the column this pass removes held nothing on any row.
 *  - `extendedDeadline` names nothing outside `app/`, `resources/js/`, `tests/`, `tools/` or the
 *    seeders: no writer, no reader, no screen, no test. §27.5 had it recorded as vocabulary the
 *    schema carried and no code used; the owner ruled DROP rather than wire.
 *  - the drop migration is applied, so the September snapshot's guide sentences — "the 19 migrations
 *    the snapshot predates" in two files — are now 20 by the count of files after
 *    2026_09_20_1000000000, which is how those sentences were written.
 *  - a returned enrollment keeps its receipts because `AssessmentController::compute()` refuses a
 *    second fee sheet for the same enrollment ( :123-127 ) and `Studentassessments::outstandingBalance()`
 *    ( :81-86 ) subtracts `Payments::held()` — so "the payment is kept" is a policy the build already
 *    enforces, which is why rulings 1 and 2 move prose and no code.
 *
 * Stage 1 is raw XML through the zip, because three of its operations are not text splices: it
 * DELETES a dictionary row, MOVES "(this issue)" onto a new §1.1 row, and rewrites two identical
 * cells that no prefix can address apart. Stage 2 goes through docxApply() so its guards run.
 *
 * DRY RUN unless --apply.
 */

require_once __DIR__.'/docx-edit-engine.php';

use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$dropFile = '2026_10_23_1000000001_drop_extended_deadline_from_studentclearances_table.php';

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
    $guard($flat === $text, 'the cloned cell reads exactly this pass’s text, not merely contains it');

    return $out;
};

// ---------------------------------------------------------------- stage 0: the measurements the prose rests on

require_once $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = (int) DB::select("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='studentclearances'")[0]->c;
$has = (int) DB::select("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='studentclearances' AND COLUMN_NAME='extendedDeadline'")[0]->c;
$rows = (int) DB::select('SELECT COUNT(*) c FROM studentclearances')[0]->c;
$allCols = (int) DB::select('SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')[0]->c;
$tables = (int) DB::select('SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')[0]->c;
$migrated = (int) DB::select('SELECT COUNT(*) c FROM migrations WHERE migration LIKE ?', ['%drop_extended_deadline_from_studentclearances_table'])[0]->c;

printf("database: %s\n", config('database.connections.mysql.database'));
$guard($has === 0, 'live ems no longer has the extendedDeadline column (read '.$has.')');
$guard($cols === 8, 'studentclearances answers 8 columns (read '.$cols.')');
$guard($rows === 4, 'the table still holds its 4 rows (read '.$rows.')');
$guard($allCols === 451, 'the schema answers 451 columns (read '.$allCols.')');
$guard($tables === 63, 'the schema still answers 63 tables (read '.$tables.')');
$guard($migrated === 1, 'the drop migration is recorded as applied');

// The "it stored nothing" claim is about the schema BEFORE the drop, and that schema no longer
// exists on this box — so it is read off the dump taken minutes before `artisan migrate`, not asked
// of a column that cannot answer. Each clearance tuple is (id, studentId, periodId, 'status', NULL, …).
$preDump = 'C:/Users/ADMIN/ems-backups/ems-pre-drop-extendeddeadline-2026-10-23.sql';
$guard(is_file($preDump), 'the pre-drop dump of live ems exists, so the emptiness claim has a source');
if (is_file($preDump)) {
    $dumpText = (string) file_get_contents($preDump);
    // Scoped to the one statement: an unscoped tuple pattern matched 46 rows across every table
    // in the dump and reported the clearance table’s own four as a contradiction.
    $found = preg_match('/INSERT INTO `studentclearances` VALUES[^;]*/', $dumpText, $ins);
    $guard($found === 1, 'the dump’s studentclearances INSERT statement is findable');
    if ($found === 1) {
        preg_match_all('/\(\d+,\d+,\d+,\x27[a-z]+\x27,/', $ins[0], $tuples);
        preg_match_all('/\(\d+,\d+,\d+,\x27[a-z]+\x27,NULL,/', $ins[0], $nulls);
        $guard(count($tuples[0]) === 4, 'the dump carries 4 studentclearances tuples (read '.count($tuples[0]).')');
        $guard(count($nulls[0]) === 4, 'and all 4 had NULL in the extendedDeadline position (read '.count($nulls[0]).')');
    }
    $guard(str_contains($dumpText, '`extendedDeadline` date DEFAULT NULL'),
        'and the dump records the column the drop removed, so this file’s claim is checkable against it');
}

foreach (['app', 'resources/js', 'tests', 'tools', 'database/seeders'] as $dir) {
    $hits = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\.(php|js|jsx|blade\.php)$/', $f->getFilename())
            && str_contains((string) file_get_contents($f->getPathname()), 'extendedDeadline')) {
            $hits[] = $f->getFilename();
        }
    }
    $guard($hits === [], $dir.' names extendedDeadline nowhere (read '.implode(', ', $hits).')');
}

$snapNewest = '2026_09_20_1000000000_add_schedule_status_to_blocks_table.php';
$after = array_filter(scandir($root.'/database/migrations'),
    fn (string $m): bool => $m > $snapNewest && str_starts_with($m, '2026'));
$guard(count($after) === 20, '20 migrations post-date the September snapshot (read '.count($after).')');
foreach (['Documentation/Demo-Day-Guide.md', 'Documentation/Offline-Presentation-Guide.md'] as $g) {
    $guard(str_contains((string) file_get_contents($root.'/'.$g), '20 migrations'),
        $g.' already says 20 migrations');
}
$guard(str_contains((string) file_get_contents($root.'/app/Http/Controllers/Assessment/AssessmentController.php'),
    'prevent duplicate assessments'), 'the idempotency guard ruling 2 leans on exists');
$acFile = (string) file_get_contents($root.'/app/Http/Controllers/Assessment/AssessmentController.php');
$saFile = (string) file_get_contents($root.'/app/Models/Studentassessments.php');
$guard(str_contains($acFile, '$existing = Studentassessments::where'), 'AssessmentController.php:123-127 is the compute() guard the row cites');
$guard(str_contains($saFile, 'public function outstandingBalance(): float'), 'Studentassessments.php:81-86 is the balance rule the row cites');
$guard(str_contains((string) file_get_contents($root.'/app/Http/Controllers/Registrar/RegistrarController.php'),
    'function returnToEvaluation'), 'returnToEvaluation() is where the document says it is');

// ---------------------------------------------------------------- stage 1: raw XML — one row deleted, one cell pair rewritten, one row cloned

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$parts = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

$rowWindow = function (string $hay, string $anchor): ?array {
    $at = strpos($hay, $anchor);
    if ($at === false) {
        return null;
    }
    $start = (int) strrpos(substr($hay, 0, $at), '<w:tr');
    $end = (int) strpos($hay, '</w:tr>', $at) + 7;

    return [$start, $end];
};

// (1a) the §24 dictionary row for the column.
$guard(substr_count($new, '>extendedDeadline<') === 1, 'the dictionary row is addressed by a run nothing else owns');
$dWindow = $rowWindow($new, '>extendedDeadline<');
$guard($dWindow !== null, 'the dictionary row window resolves');
if ($dWindow !== null) {
    [$dStart, $dEnd] = $dWindow;
    $deadRow = substr($new, $dStart, $dEnd - $dStart);
    $guard(substr_count($deadRow, '<w:tr') === 1 && substr_count($deadRow, '</w:tr>') === 1,
        'the window is exactly one row ('.strlen($deadRow).' bytes)');
    $guard(substr_count($deadRow, '<w:tc>') === 6, 'that row has six cells, the shape of a column line');
    $new = substr_replace($new, '', $dStart, $dEnd - $dStart);
}
$guard(substr_count($new, '>extendedDeadline<') === 0, 'the column row is gone from the staged bytes');

// (1b) §27.5's two identical cells: no prefix addresses one of them apart, so both are rewritten in place.
$guard(substr_count($new, '>studentclearances.extendedDeadline<') === 1, 'the §27.5 row is findable');
$vWindow = $rowWindow($new, '>studentclearances.extendedDeadline<');
$guard($vWindow !== null, 'the §27.5 row window resolves');
if ($vWindow !== null) {
    [$vStart, $vEnd] = $vWindow;
    $body = substr($new, $vStart, $vEnd - $vStart);
    $guard(substr_count($body, '>no code<') === 2, 'that row carries exactly two bare “no code” cells (read '.substr_count($body, '>no code<').')');
    $body = str_replace('>no code<', '>no code, and no column since 2026-10-23<', $body, $cNc);
    $guard($cNc === 2, 'both were rewritten ('.$cNc.')');
    $guard(substr_count($body, '<w:tr') === 1 && substr_count($body, '<w:tc>') === 5, 'the row still holds five cells');
    $new = substr_replace($new, $body, $vStart, $vEnd - $vStart);
}

// (1c) "(this issue)" moves onto the new §1.1 row, which is then cloned from the 2026-10-21 one.
$guard(substr_count($new, '>2026-10-21 (this issue)<') === 1, 'exactly one §1.1 row is the living one');
$new = str_replace('>2026-10-21 (this issue)<', '>2026-10-21<', $new, $cThis);
$guard($cThis === 1, 'the 2026-10-21 row is now a dated record ('.$cThis.')');
$guard(substr_count($new, '>2026-10-21<') === 1, 'and its date cell is addressable');

$revisionRow = [
    '2026-10-22–23 (this issue)',
    'P-2 was pinned from the desk side rather than remembered: '
        .'tests/Feature/Print/PrintEnrollmentFormPayloadTest.php fixes the eager-load list the Registrar desk and '
        .'ems:print-fidelity each write by hand — nothing shares them — and proves that the identity facts the paper '
        .'lacks (student ID, program, year level, semester, school year) all resolve from the list the desk already '
        .'runs, at zero further queries, so closing P-2 is a blade’s layout once the blank form arrives. '
        .'storage/app/scratch/mutate-p2-payload-tests.php plants a dropped relation in each of the two lists and in '
        .'the test’s own counter, because a load list a print never reads is invisible to that print. Then four '
        .'register items that the registers themselves could settle were ruled and applied: the code comments’ '
        .'numbering is the register of record and the storyboard’s is not; a returned enrollment keeps its receipts, '
        .'so a return neither voids nor refunds and Accounting re-collects nothing; an extension moves the clearance '
        .'window and not the student, so the nullable studentclearances.extendedDeadline column that implied a '
        .'per-student deadline was dropped rather than wired — written by no code, read by no code, NULL on every row '
        .'live ems holds; and the school’s own questions are packaged for one meeting in '
        .'Documentation/Registrar-Print-Decision-Sheet-2026-10-23.md. The schema census, §24’s studentclearances '
        .'count, and the two demo-day guides’ tally of the migrations the September snapshot predates all move with '
        .'the drop. P-16, the spelling of the name the letterhead prints, stays the school’s to state.',
];
$templateWindow = $rowWindow($new, '>2026-10-21<');
$guard($templateWindow !== null, 'the 2026-10-21 row is findable as the clone template');
if ($templateWindow !== null) {
    [$tStart, $tEnd] = $templateWindow;
    $template = substr($new, $tStart, $tEnd - $tStart);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $tCells);
    $guard(count($tCells[0]) === count($revisionRow),
        'template row has '.count($tCells[0]).' cells, the pass supplies '.count($revisionRow));
    if (count($tCells[0]) === count($revisionRow)) {
        $clone = $template;
        foreach ($tCells[0] as $i => $cell) {
            $assertPlain($revisionRow[$i], '§1.1 new row cell '.($i + 1));
            $clone = str_replace($cell, $setCellText($cell, $revisionRow[$i]), $clone);
        }
        $guard($clone !== $template, 'the cloned §1.1 row carries new text');
        foreach (['<w:tr', '</w:tr>', '<w:tc>', '</w:p>', '</w:t>'] as $n) {
            $guard(substr_count($clone, $n) === substr_count($template, $n),
                'the cloned row’s container '.$n.' is one row’s worth');
        }
        $new = substr_replace($new, $clone, $tEnd, 0);
    }
}
$guard(substr_count($new, '>2026-10-22–23 (this issue)<') === 1, 'the new §1.1 row is on the staged bytes');
$guard(substr_count($new, '(this issue)') === 1, 'and exactly one §1.1 row is the living claim ('.substr_count($new, '(this issue)').')');

$trDelta = substr_count($new, '<w:tr') - substr_count($xml, '<w:tr');
$guard($trDelta === 0, 'rows: one dictionary row deleted and one §1.1 row added net to no change (read '.$trDelta.')');
$guard(@(new DOMDocument)->loadXML($new) !== false, 'the staged document.xml parses');

if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "\nStage 1 would delete the §24 extendedDeadline row, rewrite §27.5’s two bare cells, move “(this issue)”\n"
        ."onto a new 2026-10-22–23 §1.1 row and record the four rulings in it.\n"
        ."Stage 2 would move the census to 451, §24 to 8 columns and its role and created-by lines, E-3 and E-8,\n"
        ."§27.7 and its §28.5 copy, §26.7 and §28.5’s BR-numbering and returned-payment rows in both places.\n\n"
        ."DRY RUN. Re-run with --apply.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass179.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: cannot reopen for write\n");
}
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
$guard(substr_count($round, '>extendedDeadline<') === 0, 'the deleted row stays deleted on disk');
$guard(substr_count($round, '>2026-10-22–23 (this issue)<') === 1, 'the new row is on disk');
if ($fails > 0) {
    printf("\n%d guard(s) failed after the stage 1 write — restore from storage/app/scratch/ems-docx-pre-pass179.docx.\n", $fails);
    exit(1);
}
echo "     stage 1 written and re-read\n";

// ---------------------------------------------------------------- stage 2: the prose, through the engine

$sharedFrom = 'What is still not institutional is the per-student deadline: studentclearances.extendedDeadline is written by nobody and read by nothing, so an extension moves the window rather than the individual.';
$sharedTo = 'The per-student deadline is now ruled out rather than missing: the owner ruled on 2026-10-23 that an extension moves the window and not the individual, and the column that implied otherwise — nullable, written by nobody, read by nothing, NULL on all four rows live ems holds — was dropped the same day by '
    .$dropFile.' (§27.2 E-8, §27.5).';

$textEdits = [
    [
        'Counts: 63 tables (55 EMS domain + 8 framework), 452 columns',
        'Counts: 63 tables (55 EMS domain + 8 framework), 451 columns',
    ],
    [
        'studentclearances  —  9 columns',
        'studentclearances  —  8 columns',
    ],
    [
        " One per student per clearance period, with an overall status and an optional individually-extended deadline for a student's own situation, separate from the period's general window.",
        ' One per student per clearance period, with an overall status and the desk’s record of who received the signed slip and when. '
            .'The optional individually-extended deadline this line described was removed on 2026-10-23: the column was nullable, '
            .'named by no code outside the model’s own $fillable, and NULL on every one of the four rows live ems holds, and the '
            .'owner ruled that an extension moves the window and not the student (§27.2 E-8, §27.5).',
    ],
    [
        '2026_08_02_1000000021_create_studentclearances_table.php',
        '2026_08_02_1000000021_create_studentclearances_table.php, and the extendedDeadline column it also created was '
            .'dropped on 2026-10-23 by '.$dropFile,
        '$primaryKey = \'studentClearanceId\'',
    ],
    [$sharedFrom, $sharedTo, null, 2],
    [
        'Half answered and built 2026-10-03 (ruling 15)',
        'Answered and built 2026-10-03 (ruling 15), and the half left open was ruled on 2026-10-23',
        null,
        2,
    ],
];

$cellEdits = [
    // §27.5's dead-vocabulary row: the value is gone, so the row records the removal the way the
    // idrequests row above it records its own.
    ['>studentclearances.extendedDeadline<', 'studentclearances.extendedDeadline',
        'studentclearances.extendedDeadline — REMOVED from the column'],
    ['>studentclearances.extendedDeadline<', 'nullable date column',
        'was a nullable date, $fillable and date-cast ( app/Models/Studentclearances.php ) from 2026_08_02_1000000021 until '
            .$dropFile.' removed it'],
    ['>studentclearances.extendedDeadline<', 'The column makes the schema look',
        'Ruled and built 2026-10-23: an extension is the window’s state, not a student’s, so the per-student deadline is not a '
            .'thing the school’s process has and the column was dropped rather than wired. Nothing was lost — 0 of the four rows '
            .'live ems holds carried a value — and a reader of this document no longer meets a deadline no desk can set '
            .'(§27.2 E-8, §27.7).'],
    // E-3: what the build does, with the ruling named as the reason the receipts stay.
    ['>E-3<', 'Status moves to returnedToEvaluation',
        'Status moves to returnedToEvaluation, the reason is stored, and the payments and the already signed workflow boxes stay '
            .'exactly as they are. Ruled 2026-10-23 that this is the policy and not an omission: a returned enrollment keeps its '
            .'receipts, because the receipt is evidence that money was handed over and a return does not un-hand it. '
            .'AssessmentController::compute() refuses a second fee sheet for the same enrollment ( AssessmentController.php:123-127 ) '
            .'and outstandingBalance() subtracts Payments::held() from what that sheet owes '
            .'( app/Models/Studentassessments.php:81-86 ), so the student who comes back pays the difference.'],
    // E-8: the consequence and the evidence, both of which named the column as a remainder.
    ['>E-8<', 'An extension is now honoured',
        'An extension is honoured by the screens and the gates, and it is the window that moves. The per-student date this row '
            .'carried as its remainder is ruled out, not missing: the owner ruled on 2026-10-23 that an extension extends the season, '
            .'and '.$dropFile.' removed the column that suggested otherwise, so nothing in the schema now offers a deadline for one '
            .'student alone.'],
    ['>E-8<', 'grep extendedDeadline app',
        'a scan of app/, resources/js/, tests/, tools/ and database/seeders/ for extendedDeadline named only the model’s $fillable '
            .'and casts() before the drop and names nothing after it; two files under database/migrations/ still carry the name, the '
            .'create and this drop. Live ems held 4 studentclearances rows and COUNT(extendedDeadline) answered 0. '
            .'ClearancePeriodStatus::Extended has exactly one writer, extendPeriod, and is read through the accepting() scope rather '
            .'than a literal comparison.'],
    // Rulings 1 and 2, in both copies of each decision row (§26.7's list and §28.5's).
    ['storyboard list or the code comments?', 'The two disagree on BR5',
        'Answered 2026-10-23 by the owner: the code comments’ numbering is the register of record, and where the storyboard’s list '
            .'differs the code and this document win — so the five disagreements §26.2 records, BR5, BR7, BR8, BR9 and BR10, are the '
            .'storyboard’s and not the system’s. Citations here keep a file:line beside every BR number, because the number names a '
            .'rule and the line shows the rule in force.',
        2],
    ['Is a returned enrollment’s payment kept', 'the state machine allows paid',
        'Answered 2026-10-23 by the owner: kept. returnToEvaluation() moves the status and stores the reason and touches no payments '
            .'row ( RegistrarController.php:288-310 ), and that is now policy rather than a gap — a return voids nothing and refunds '
            .'nothing, the receipts keep counting through Payments::held(), and Accounting neither re-collects nor reverses '
            .'(E-3, §17).',
        2],
    ['must the payments be voided or refunded first?', 'returnToEvaluation()',
        'Answered 2026-10-23 by the owner: neither. returnToEvaluation() stores a reason and moves the status and nothing else '
            .'( RegistrarController.php:288-310 ), and the money stays counted: outstandingBalance() reads the receipts on the '
            .'enrollment ( app/Models/Studentassessments.php:81-86 ), so an account whose paper went back to Evaluation is not '
            .'re-billed for pesos already handed over (E-3).',
        2],
];

docxApply($src, $root.'/storage/app/scratch/pass179-backup.docx', $textEdits, $cellEdits, $apply);
