<?php

/**
 * Docx pass 333 — the census was reading one file too few, and the register's G-52 figures move again.
 *
 * `lane108-census-whole-student-loads.php` decided "this page reads nothing beyond the name" by searching the page
 * file alone. `Evaluation/Show` renders `EnrollmentProfileForm`, which reads gender, birthplace, birthdate,
 * citizenship, religion, civil status, contact number, telephone, semesters completed and years in institution —
 * the screen that EDITS the applicant's record, exactly like the admission case screen. The census had filed it as
 * a candidate, so the row it fed said "8 detail screens" owed, and a narrowing driven from that number would have
 * emptied a form on the desk's screen while every test stayed green — the `id`-column mistake arriving from the
 * reading side instead of the writing side.
 *
 * Both readers now share one graph walker (`page-read-graph.php`), and the corrected classification of the
 * remaining debt is 7 detail + 4 print = 11 sites, with 2 legitimate editors. This pass rewrites the two clauses
 * that carry those numbers and refuses to write unless the census JSON answers exactly the same counts — the
 * instrument is the source, as in pass 331.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass333.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass333-backup.docx';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$json = json_decode((string) file_get_contents($root.'/storage/app/scratch/lane108-census.json'), true);
if (! is_array($json) || $json === []) {
    fwrite(STDERR, "REFUSED: no census JSON to check the row's figures against.\n");

    exit(1);
}
$kinds = array_count_values(array_column($json, 'kind'));
$detail = $kinds['DETAIL'] ?? 0;
$print = $kinds['PRINT'] ?? 0;
// Rows carrying no kind are the ones classified READS-SENSITIVE: legitimate readers, not debt.
$legit = count($json) - $detail - $print - ($kinds['LIST'] ?? 0);
$rowsN = count($json);
// The suffix split is read from the same rows, per kind, because a five-column name list drops "Jr." and the
// register says which screens would need the six-column set. The census prints this tally beside its own split —
// a figure the document attributes to a tool has to be a line that tool writes (lane 59).
$sufDetail = 0;
$sufPrint = 0;
foreach ($json as $r) {
    if (! empty($r['readsSuffix'])) {
        if ($r['kind'] === 'DETAIL') {
            $sufDetail++;
        } elseif ($r['kind'] === 'PRINT') {
            $sufPrint++;
        }
    }
}

// The suffix split is read from the same rows, per kind: a five-column name list drops "Jr.", and the
// register states how many screens would need the six-column set. The census prints this tally beside its own
// split, because a figure a document attributes to a tool has to be a line that tool writes (lane 59).
$sufDetail = 0;
$sufPrint = 0;
foreach ($json as $r) {
    if (! empty($r['readsSuffix'])) {
        if ($r['kind'] === 'DETAIL') {
            $sufDetail++;
        } elseif ($r['kind'] === 'PRINT') {
            $sufPrint++;
        }
    }
}
if ($sufDetail > $detail || $sufPrint > $print) {
    fwrite(STDERR, "REFUSED: more suffix-painting rows than rows of that kind.\n");

    exit(1);
}
printf("census: %d row(s) — DETAIL %d, PRINT %d, LIST %d, legitimate readers %d; suffix painted on %d detail and %d print\n",
    count($json), $detail, $print, $kinds['LIST'] ?? 0, $legit, $sufDetail, $sufPrint);
if (($kinds['LIST'] ?? 0) !== 0 || $detail !== 7 || $print !== 4 || $legit !== 2) {
    fwrite(STDERR, "REFUSED: the census answers something other than the 7 detail / 4 print / 2 legitimate split this"
        ." pass was cut to write. Re-read the instrument; do not re-type the document.\n");

    exit(1);
}
if ($sufDetail > $detail || $sufPrint > $print) {
    fwrite(STDERR, "REFUSED: more suffix-painting rows than rows of their kind.\n");

    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    [
        'The census that sized the job counts one row per CALL SITE, not one per action, because Accounting’s daily report loads the student twice; read before this pass it answered 26 whole-student load sites over 25 controllers, of which 25 serve a page that reads nothing beyond the name and one — the admission case screen — legitimately edits those fields.',
        'The census that sizes the job counts one row per CALL SITE, not one per action, because Accounting’s daily report loads the student twice; read before this pass it answered 26 whole-student load sites over 25 controllers, of which 25 served a page that reads nothing beyond the name and one — the admission case screen — legitimately edited those fields. That reading then widened twice, and both widenings were the instrument’s own. A page paints through shared components, so deciding what it reads from the page file alone is not a reading of it: the evaluation record screen renders the profile form that edits gender, birthplace, birthdate, citizenship, religion, civil status, contact number, telephone, semesters completed and years in institution, so it is a second legitimate editor rather than a queue that forgot to narrow. And the reader that walks a page’s import graph recorded only the FIRST student column named on a line, so the Registrar queue’s own row — which paints surname, given name and middle initial in one expression — reported a surname alone. Every column named on a line is recorded now, and that is also what moved the suffix counts below.',
    ],
    [
        'The same census read afterwards answers 13 rows — 8 detail screens, 4 print paths and that one legitimate editor — which is the whole remaining debt, and `tests/Feature/DeskQueue/DeskStudentLoadColumnsTest.php` holds those twelve as a list that FAILS when a new bare load appears and FAILS when a paid one stays in it, while `mutate-g52-desk-payload-tests.php` kills the leak and the bad column by putting each shape back.',
        sprintf("The same census read afterwards answers %d rows \u{2014} %d detail screens, %d print paths and %d legitimate editors \u{2014} which is the whole remaining debt, and `tests/Feature/DeskQueue/DeskStudentLoadColumnsTest.php` holds it as a list that FAILS when a new bare load appears and FAILS when a paid one stays in it, while `mutate-g52-desk-payload-tests.php` kills the leak and the bad column by putting each shape back. %d of the detail screens and %d of the print paths also paint the name SUFFIX, which a five-column list does not carry: the narrowing rewriter refuses any site whose page paints a suffix rather than widening the set on its own, and the six-column name set is what those screens will get. The narrowing this batch already applied was then re-audited against the graph reader by `lane108c-check-the-narrowed-lists.php`, which binds every narrowed column list to the page its own action renders and refuses the run when that page names a column the list no longer carries; the audit states no count in the register, because a reading of this box's scratch surface is not one the runner can reproduce.", $rowsN, $detail, $print, $legit, $sufDetail, $sufPrint),
    ],
];

$failures = [];
$before = [];
foreach ($textEdits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    $before[$i] = $n;
    printf('edit %d: %d occurrence(s) — %s%s', $i, $n, substr(trim($from), 0, 58), PHP_EOL);
    if ($n !== 1) {
        $failures[] = "edit {$i} matched {$n} time(s), wanted exactly 1";
    }
    if (str_contains($to, '|')) {
        $failures[] = "edit {$i}'s replacement carries a literal pipe";
    }

    if ($i === 1 && (! str_contains($to, "{$sufDetail} of the detail screens") || ! str_contains($to, "{$sufPrint} of the print paths"))) {
        // A sentence that states a count the instrument did not answer is the defect this pass exists to refuse.
        $failures[] = "edit {$i} does not carry the census's own suffix split ({$sufDetail}/{$sufPrint})";
    }

    if (preg_match('/\.php:\d/u', $to) === 1) {
        $failures[] = "edit {$i}'s replacement introduces a line citation the pass has not verified";
    }
    if ($i === 1 && (! str_contains($to, "{$detail} detail screens") || ! str_contains($to, "{$print} print paths")
        || ! str_contains($to, "{$legit} legitimate"))) {
        // The figures in the sentence must be the ones the instrument answered, not the ones planned here.
        $failures[] = "edit {$i} does not carry the census's own numbers ($detail/$print/$legit)";
    }
}
if ($failures !== []) {
    $already = true;
    foreach ($textEdits as [, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $already = false;

            break;
        }
    }
    if ($already) {
        echo "ALREADY APPLIED — the row carries the graph-read classification.\n";

        exit(0);
    }
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

docxApply($docx, $backup, $textEdits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$bad = 0;
foreach ($textEdits as $i => [$from, $to]) {
    $g = substr_count($after, $to);
    $l = substr_count($after, $from);
    printf('  edit %d: new form %d (want 1), old form %d (want %d)%s', $i, $g, $l, $before[$i] - 1, PHP_EOL);
    if ($g !== 1 || $l !== $before[$i] - 1) {
        $bad++;
    }
}
if (str_contains($after, '8 detail screens')) {
    echo "  the superseded figure '8 detail screens' still occurs in the document\n";
    $bad++;
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed.\n");
    if (is_file($backup) && copy($backup, $docx)) {
        fwrite(STDERR, 'restored '.basename($backup).'; nothing of the bad write is left.'."\n");
    } else {
        fwrite(STDERR, "AND no backup was kept, so the write stands — read the row back before editing anything else.\n");
    }

    exit(1);
}
echo 'written, re-read: the G-52 row now states the debt the graph reader answers, and no superseded figure survives.'."\n";
