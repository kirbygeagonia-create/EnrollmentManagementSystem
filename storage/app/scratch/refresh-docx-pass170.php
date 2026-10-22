<?php

/**
 * Pass 170 — the cover line stops dating itself, and §24.1's decomposition is re-read from the tree.
 *
 * Ruled 2026-10-21: the cover carries no revision date, because a hand-edited document accrues passes faster than
 * anyone remembers to repaint the cover — it read "Revised 2026-10-01" sixty-nine passes later — and §1.1 is where the
 * revisions are actually recorded, each with the day it was read.
 *
 * The same ruling's edit surfaced the rows beside it. §24.1 says 51 tables are created by this project's migrations
 * and 3 are published by a package; a scan of database/migrations answers 50 and 5, and 49 of the 50 came in the
 * 2026-08 baseline wave while `shiftingrequests` arrived 2026-10-03 — so "all of them in one baseline wave" is true of
 * all but one. The row's own explanation says "no migration in database/migrations/ creates them", which §24's
 * cross-check contradicts two sections away: the published Spatie migration is in that directory and builds its table
 * names from config/permission.php at runtime, which is exactly why a scan for a literal Schema::create finds none of
 * them. Every number here is read out of lane60-readings.json, so the pass cannot write one its instrument did not
 * print.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$readPath = $root.'/storage/app/scratch/lane60-readings.json';
if (! is_file($readPath)) {
    exit("REFUSED: no lane60-readings.json — run lane60-probe-ratios.php --write-readings=… first.\n");
}
$R = json_decode((string) file_get_contents($readPath), true);
foreach (['domainTables', 'tablesByOwnMigrations', 'tablesByPackageMigration', 'tablesCreatedInBaselineWave',
    'tablesByPackageMigrationNames', 'tablesCreatedAfterBaselineNames'] as $need) {
    if (! isset($R[$need])) {
        printf("REFUSED: the readings carry no %s, so this pass has nothing to write.\n", $need);
        exit(1);
    }
}
if (! is_array($R['tablesByPackageMigrationNames']) || $R['tablesByPackageMigrationNames'] === []) {
    exit("REFUSED: no package table names in the readings.\n");
}
if (($R['database'] ?? '') !== 'ems') {
    exit('REFUSED: readings taken on '.$R['database'].", not on live `ems`.\n");
}
if (! empty($R['failedReadings'])) {
    exit("REFUSED: the probe reports ".count($R['failedReadings'])." reading(s) it could not take.\n");
}

$domain = (int) $R['domainTables'];
$own = (int) $R['tablesByOwnMigrations'];
$pkg = (int) $R['tablesByPackageMigration'];
$wave = (int) $R['tablesCreatedInBaselineWave'];
$pkgNames = implode(', ', $R['tablesByPackageMigrationNames']);
$lateNames = implode(', ', $R['tablesCreatedAfterBaselineNames']);
$pkgFile = '2026_08_02_081526_create_permission_tables.php';
$unaccounted = (int) ($R['notFromMigrations'] ?? 0);
if ($unaccounted !== $pkg + (int) ($R['tablesByFrameworkBookkeeping'] ?? 0)) {
    printf("REFUSED: the cross-check says %d tables are unaccounted for, and %d package + %d bookkeeping does not make that.\n",
        $unaccounted, $pkg, (int) ($R['tablesByFrameworkBookkeeping'] ?? 0));
    exit(1);
}

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$textEdits = [
    // The cover: no date, the count it shares with §24.1, and a pointer to where revisions are actually kept.
    ['Revised 2026-10-01 (the 2026-09-28 description, re-read against the build)',
        'Revised against the running build'],
    ['· 54 application tables ·', sprintf('· %d application tables ·', $domain)],
    ['schema read from the current migration set',
        sprintf('schema read from the live database and the current migration set — %s carries every revision and the day each was read', '§1.1')],
    // §24.1's explanation of its own two counts, which was written when one scan answered 51 and one table was still missing.
    ['All of them in one baseline wave (2026-08), which is why no table here has a long migration history of its own',
        sprintf('%d of them in the 2026-08 baseline wave; the other %d, %s, came 2026-10-03 — which is why no table here has a long migration history of its own',
            $wave, $own - $wave, $lateNames)],
];

$cellEdits = [
    ['Created by this project', '51', (string) $own, 1],
    ['Published by a package, not by this project', '3 —',
        $pkg.' — '.$pkgNames, 1],
    // The row's explanation cell is rewritten whole, so the "and the 51 a scan…" fragment inside it is NOT edited
    // separately: one cell cannot carry a text edit and a cell edit at once.
    ['Published by a package, not by this project', 'The Spatie permission pivots',
        'The Spatie permission tables. They are counted as EMS tables because staff_roles is how every desk account gets '
            .'its role (§22.4), but they are not this project’s own: '.$pkgFile.' publishes them into database/migrations/ and '
            .'builds their names from config/permission.php at runtime, which is why a scan for a literal Schema::create '
            .'finds none of them and the count is '.$pkg.' and not 3. Together with the Laravel framework’s own migrations '
            .'table, these are the '.$unaccounted.' the §24 cross-check lists as unaccounted for by this project’s migrations. '
            .'This is the whole of the difference between “'.$domain.' application tables” and the '.$own.' a scan of this '
            .'project’s migrations alone will find.', 1],
];

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$parts = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

printf("readings: %d domain, %d by this project's migrations, %d by the published package, %d in the baseline wave\n\n",
    $domain, $own, $pkg, $wave);
foreach ($textEdits as [$old, $new]) {
    $guard(substr_count($xml, $old) === 1, 'needle once: '.mb_substr($old, 0, 66));
    foreach (['&', '<', '>'] as $c) {
        $guard(! str_contains($new, $c), 'the replacement carries no raw '.$c);
    }
}
foreach ($cellEdits as [$a, $p, $v, $want]) {
    $seen = substr_count($xml, $a);
    $guard($seen === $want, sprintf('cell anchor seen %d, expected %d: %s', $seen, $want, mb_substr($a, 0, 44)));
    foreach (['&', '<', '>'] as $c) {
        $guard(! str_contains($v, $c), 'the cell text carries no raw '.$c);
    }
}
// The 54 and the 3 must be gone from the whole document, not just from the places this pass edits.
$guard(substr_count($xml, '· 54 application tables ·') === 1, 'exactly one cover count to fix');
$guard(substr_count($xml, 'Revised 2026-10-01') === 1, 'exactly one cover date to drop');

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "This pass would write:\n";
    foreach ($textEdits as [$old, $new]) {
        echo "\n  was: ".mb_substr($old, 0, 120)."\n  now: ".mb_substr($new, 0, 200)."\n";
    }
    foreach ($cellEdits as [$a, $p, $v]) {
        echo "\n  cell of \"".$a.'" ('.$p."):\n    ".mb_substr($v, 0, 320)."\n";
    }
    echo "\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass170.docx', $textEdits, $cellEdits, true);

$z3 = new ZipArchive();
$z3->open($src);
$final = (string) $z3->getFromName('word/document.xml');
$z3->close();
$flat = html_entity_decode((string) preg_replace('/<[^>]+>/', '', $final), ENT_QUOTES | ENT_XML1);
$guard(substr_count($flat, 'Revised 2026-10-01') === 0, 'the cover no longer carries a date');
$guard(str_contains($flat, sprintf('· %d application tables ·', $domain)), 'the cover states the measured count');
$guard(str_contains($flat, '§1.1 carries every revision'), 'the cover points at the section that keeps the dates');
$guard(substr_count($xml, '· 54 application tables ·') === 1 && substr_count($flat, '54 application tables') <= 1,
    'the stale 54 remains only where the document quotes a superseded claim, if at all ('.substr_count($flat, '54 application tables').')');
$guard(str_contains($flat, sprintf('%d of them in the 2026-08 baseline wave', $wave)), 'the baseline wave is a measured count now');
$guard(str_contains($flat, $pkgFile), 'the row names the migration that publishes the package tables');
if ($fails > 0) {
    echo "\nGuard(s) failed after writing; backup at storage/app/scratch/ems-docx-pre-pass170.docx\n";
    exit(1);
}
echo "\nPass 170 written; backup at storage/app/scratch/ems-docx-pre-pass170.docx\n";
exit(0);
