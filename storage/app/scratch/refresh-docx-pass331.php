<?php

/**
 * Docx pass 331 — G-52's row carries the census as re-measured, and the `id` incident is stated per driver.
 *
 * Pass 330 wrote "12 desk list pages, 8 detail screens and 4 print paths" from a census that merged two call sites
 * in one action. Re-cutting the census to one row per CALL SITE — the merged pair was Accounting's daily report,
 * which loads the student twice — moved the figures to 13 list, 8 detail, 4 print over 26 sites. Then this pass
 * narrowed all 13 list sites, so the row now has to say what is left (8 detail, 4 print) rather than what the
 * batch started from, and name the guard that keeps the remainder honest.
 *
 * Second correction, and the one that matters for accuracy: pass 330 said the bad column made "all three queues
 * answer 500". That is what the school's MySQL does. On SQLite — the suite's surface — the same list arrives as an
 * EXTRA PAYLOAD KEY, and the case dies on its exact key-set assertion instead. Both halves are now measured, and
 * the row says which driver shows which, because a document that states one behaviour while two exist is the
 * §12 Gate-4 class this file has refused before.
 *
 * Text edits only; each needle asserted to occur exactly once, the retired wording asserted gone, and the verdict
 * cell must still LEAD with `Open` — this row is not closed.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass331.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass331-backup.docx';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    [
        'Open 2026-10-31, found by reading the real payload rather than the code, and narrowed the same day on the three queues that read named.',
        'Open 2026-10-31, found by reading the real payload rather than the code, narrowed on the three queues that read named, and narrowed again on every desk list the census counted.',
    ],
    [
        'The four sites behind the Registrar, Accounting and Assessment queues were made line-neutral `with` rewrites, so no citation aged; the census that sized the job then measured 25 whole-student load sites over 25 controllers, of which 24 answer a page that reads nothing beyond the name and one (the admission case screen) legitimately edits them. What is left is 12 desk list pages, 8 detail screens and 4 print paths — read 2026-10-30, and the last two groups are narrowed only after the browser and the paper have been asked, because a column list written from a guess is a 500 at the desk rather than a wrong value.',
        'The census that sized the job counts one row per CALL SITE, not one per action, because Accounting’s daily report loads the student twice; read before this pass it answered 26 whole-student load sites over 25 controllers, of which 25 serve a page that reads nothing beyond the name and one — the admission case screen — legitimately edits those fields. Every one of the 13 desk list sites is now narrowed alongside the four the first reading named, each a line-neutral `with` rewrite so no citation aged, and the narrowing is bounded by a reader that walks the page’s whole import graph before it calls a column unread: a suffix is half of a NAME, so the four detail screens that paint one would have lost “Jr.” to the five-column list, and the tool refuses any site whose page reads one rather than writing it anyway. The same census read afterwards answers 13 rows — 8 detail screens, 4 print paths and that one legitimate editor — which is the whole remaining debt, and `tests/Feature/DeskQueue/DeskStudentLoadColumnsTest.php` holds those twelve as a list that FAILS when a new bare load appears and FAILS when a paid one stays in it, while `mutate-g52-desk-payload-tests.php` kills the leak and the bad column by putting each shape back.',
    ],
    [
        'The first attempt named a column `students` never had, and all three queues answered 500 while the sort classes reported green — a case that compares the columns of a page without asking whether the desk answered is a case that cannot fail, so each of the three now asserts a 200 on every request it compares before it compares anything.',
        'The first attempt named a column `students` never had, and the sort classes reported green over it, because a case that compares the columns of a page without asking whether the desk answered cannot fail. The two drivers show that defect differently and both readings are now measured: on MySQL 8 the handler throws, so the case dies on the response — `The response is not a view` — while on SQLite the same list arrives as an EXTRA KEY on the nested student, so the exact key-set assertion is what refuses. Each narrowed desk’s case therefore carries both guards, and the harness proves it by planting the bad column and requiring the named case to print one failure on whichever surface is open.',
    ],
    [
        'that is G-52, registered open and narrowed the same day on the three queues the live read named, with its row carrying the census that sized the rest.',
        'that is G-52, registered open, narrowed first on the three queues the live read named and then on all thirteen desk lists the census counted, with its row carrying the remaining twelve sites as a measured debt.',
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
        $failures[] = "edit {$i}'s replacement carries a literal pipe, which the flat dump cannot tell from a cell boundary";
    }
    if (preg_match('/\.php:\d/u', $to) === 1) {
        $failures[] = "edit {$i}'s replacement introduces a line citation the pass has not verified";
    }
    if ($i === 0 && ! str_starts_with($to, 'Open ')) {
        $failures[] = 'edit 0 would move the verdict off `Open`, and this row is not closed';
    }
    // The figures written into the row must be the census's own, so the pass refuses its own numbers if they move.
    if ($i === 1) {
        foreach (['26 whole-student load sites', '25 serve a page', '13 desk list sites', 'answers 13 rows', '8 detail screens, 4 print paths'] as $claim) {
            if (! str_contains($to, $claim)) {
                $failures[] = "edit 1 lost the claim `{$claim}` it is required to state";
            }
        }
    }
}
if ($failures !== []) {
    $already = true;
    foreach ($textEdits as [$from, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $already = false;

            break;
        }
    }
    if ($already) {
        echo "ALREADY APPLIED — the row already carries the re-measured census and the per-driver reading.\n";

        exit(0);
    }
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

// Read the census's own summary out of its JSON, so the figures typed above are cross-checked against the tool.
$json = $root.'/storage/app/scratch/lane108-census.json';
if (! is_file($json)) {
    fwrite(STDERR, "REFUSED: no census JSON to check the row's figures against — run the census with --json= first.\n");

    exit(1);
}
$rows = json_decode((string) file_get_contents($json), true);
if (! is_array($rows) || $rows === []) {
    fwrite(STDERR, "REFUSED: the census JSON read empty, so it could not confirm any figure.\n");

    exit(1);
}
$kinds = array_count_values(array_column($rows, 'kind'));
printf("census now: %d row(s); LIST %d, DETAIL %d, PRINT %d, unclassified %d\n",
    count($rows), $kinds['LIST'] ?? 0, $kinds['DETAIL'] ?? 0, $kinds['PRINT'] ?? 0, $kinds[''] ?? 0);
if (($kinds['LIST'] ?? 0) !== 0) {
    fwrite(STDERR, "REFUSED: the census still counts ".($kinds['LIST'] ?? 0)." list site(s) with a whole-student"
        ." load, so this row must not claim they are narrowed.\n");

    exit(1);
}
if (($kinds['DETAIL'] ?? 0) !== 8 || ($kinds['PRINT'] ?? 0) !== 4) {
    fwrite(STDERR, "REFUSED: the census answers DETAIL ".($kinds['DETAIL'] ?? 0).' and PRINT '.($kinds['PRINT'] ?? 0)
        ." — the row's figures do not match the instrument, so the row is wrong or the census moved.\n");

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
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed.\n");
    if (is_file($backup) && copy($backup, $docx)) {
        fwrite(STDERR, 'restored '.basename($backup).'; nothing of the bad write is left.'."\n");
    } else {
        fwrite(STDERR, "AND no backup was kept, so the write stands — read the row back before editing anything else.\n");
    }

    exit(1);
}
echo 'written, re-read: G-52 carries the census the instrument answers today, and the two drivers are told apart.'."\n";
