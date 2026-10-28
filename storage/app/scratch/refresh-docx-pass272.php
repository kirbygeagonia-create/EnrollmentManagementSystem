<?php

/**
 * Pass 272: §25.1's route table and the two reachability lists, re-taken against routes/web.php and the actions.
 *
 * The queue reading went file by file, and §25.1 is the table a desk worker follows when a print button 500s. Two
 * of its columns had drifted and no instrument could see either, because every number still lands on a live line
 * of a real file:
 *
 *   The Staff route column cited web.php 146/147, 148/149, 150/151 for the three Registrar documents, 124 for the
 *     slip's PDF sibling and 138/139 for the block schedule. Today those seven lines are the Blocking chapter
 *     banner and blocking.index/show/store/update/finalize, the Accounting banner, and two clearance acts — the
 *     print routes moved to 171-176, 144 and 158-159 when the modules above them grew. Row 2254's `web.php:178`
 *     and row 2258's `:143` are still right, so this pass moves the ten that are not and leaves the two alone.
 *   The "what the browser gets" column cited RegistrarController 388/421/444 for the certificate, class-card and
 *     subject-load pages. 388 is printClassCards, 421 is one line inside printSubjectLoad and 444 is the brace of
 *     downloadCertificate: three pages, three wrong actions. The PDFs fared no better — 437 is a closing brace,
 *     464 sits in downloadEnrollmentForm and 490 in downloadClassCard.
 *   Two lists of the same print acts — §25.1's reachability paragraph and §28's P-3 row — both cite
 *     `RegistrarController.php:388 , :405 , :428`, which is printClassCards' signature plus one line inside two
 *     other actions. The paragraph's own claim is "every Inertia::render whose component contains Print", so it
 *     gets the three render lines (378, 411, 434); the P-3 row's claim is that each desk print writes its log
 *     row, so it gets the three action signatures, spelled as §23's documentprintlog row now spells them.
 *
 * Every new number is asserted against the line it names before a byte is written, and each needle is the whole
 * run it replaces (all eight cells turn out to be single contiguous runs), so no short number can be another
 * citation's tail.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass272.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

function lines(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "REFUSED: cannot read {$path}, so no assertion over it can be trusted.\n");

        exit(1);
    }

    return explode("\n", (string) file_get_contents($path));
}

$failures = [];
$checks = 0;

function assertLine(string $file, int $n, string $needle, string $claim): void
{
    global $failures, $checks;
    $checks++;
    $at = lines($file)[$n - 1] ?? null;
    if ($at === null) {
        $failures[] = "{$file}:{$n} does not exist ({$claim}).";

        return;
    }
    if (! str_contains($at, $needle)) {
        $failures[] = "{$file}:{$n} reads [".trim($at)."] and was offered for \"{$claim}\", which needs [{$needle}].";
    }
}

$web = $root.'/routes/web.php';
$registrar = $root.'/app/Http/Controllers/Registrar/RegistrarController.php';
$clearance = $root.'/app/Http/Controllers/Clearance/ClearanceController.php';
$blocking = $root.'/app/Http/Controllers/Blocking/BlockingController.php';

// The route column, each line judged by the URI the document prints beside it.
assertLine($web, 171, "print/certificate'", 'the certificate page route');
assertLine($web, 172, "print/certificate/pdf'", 'its PDF sibling');
assertLine($web, 173, "print/class-cards'", 'the class-card page route');
assertLine($web, 174, "print/class-cards/{enrolledSubject}/pdf'", 'one PDF per subject');
assertLine($web, 175, "print/subject-load'", 'the subject-load page route');
assertLine($web, 176, "print/subject-load/pdf'", 'its PDF sibling');
assertLine($web, 143, "clearance/{clearance}/print'", 'the slip page route, already right in the row');
assertLine($web, 144, "clearance/{clearance}/print/pdf'", 'the slip PDF sibling');
assertLine($web, 158, "blocking/{block}/print'", 'the block schedule page route');
assertLine($web, 159, "blocking/{block}/print/pdf'", 'its PDF sibling');

// The action column: three pages and three downloads, and the acts the two reachability lists name.
assertLine($registrar, 366, 'function printCertificate', 'the certificate page act');
assertLine($registrar, 388, 'function printClassCards', 'the class-card page act');
assertLine($registrar, 419, 'function printSubjectLoad', 'the subject-load page act');
assertLine($registrar, 443, 'function downloadCertificate', 'the certificate PDF');
assertLine($registrar, 470, 'function downloadClassCard', 'the class-card PDF');
assertLine($registrar, 496, 'function downloadSubjectLoad', 'the subject-load PDF');
assertLine($registrar, 378, "Inertia::render('Registrar/PrintCertificate'", 'every Inertia::render naming Print');
assertLine($registrar, 411, "Inertia::render('Registrar/PrintClassCards'", 'every Inertia::render naming Print');
assertLine($registrar, 434, "Inertia::render('Registrar/PrintSubjectLoad'", 'every Inertia::render naming Print');
assertLine($clearance, 380, 'function printSlip', 'the slip print act, for the P-3 row');
assertLine($blocking, 542, 'function printBlockSchedule', 'the block schedule print act, for the P-3 row');

if ($failures !== []) {
    fwrite(STDERR, "REFUSED WRITE: ".count($failures)." assertion(s) over {$checks} failed — a new number that does"
        ." not carry the claim is a re-guess, not a re-point.\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - {$f}\n");
    }

    exit(1);
}

printf("assertions: %d line(s) read, every one carries the claim it is offered for\n", $checks);

$zip = new ZipArchive;

if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    // §25.1 Staff route column.
    ['GET /registrar/{enrollment}/print/certificate (web.php:146) and its PDF sibling (web.php:147)',
        'GET /registrar/{enrollment}/print/certificate (web.php:171) and its PDF sibling (web.php:172)'],
    ['GET /registrar/{enrollment}/print/class-cards (web.php:148) and one PDF per subject (web.php:149)',
        'GET /registrar/{enrollment}/print/class-cards (web.php:173) and one PDF per subject (web.php:174)'],
    ['GET /registrar/{enrollment}/print/subject-load (web.php:150) and its PDF sibling (web.php:151)',
        'GET /registrar/{enrollment}/print/subject-load (web.php:175) and its PDF sibling (web.php:176)'],
    ['GET /clearance/{clearance}/print (web.php:143) and its PDF sibling (web.php:124)',
        'GET /clearance/{clearance}/print (web.php:143) and its PDF sibling (web.php:144)'],
    ['GET /blocking/{block}/print (web.php:138) and its PDF sibling (web.php:139)',
        'GET /blocking/{block}/print (web.php:158) and its PDF sibling (web.php:159)'],
    // §25.1 "what the browser gets" column.
    ['Registrar/PrintCertificate page ( RegistrarController.php:388 ) — the download route answers the same screen with the PDF ( :437 )',
        'Registrar/PrintCertificate page ( RegistrarController.php:366 ) — the download route answers the same screen with the PDF ( :443 )'],
    ['Registrar/PrintClassCards — one page, every subject ( RegistrarController.php:421 ); downloadClassCard() serves one card ( :464 )',
        'Registrar/PrintClassCards — one page, every subject ( RegistrarController.php:388 ); downloadClassCard() serves one card ( :470 )'],
    ['Registrar/PrintSubjectLoad ( RegistrarController.php:444 ); downloadSubjectLoad() serves the PDF ( :490 )',
        'Registrar/PrintSubjectLoad ( RegistrarController.php:419 ); downloadSubjectLoad() serves the PDF ( :496 )'],
    // The two reachability lists, each judged by its own claim.
    ['RegistrarController.php:388 , :405 , :428 ), the clearance slip',
        'RegistrarController.php:378 , :411 , :434 ), the clearance slip'],
    ['RegistrarController.php:388 , :405 , :428 , ClearanceController.php:398 , BlockingController.php:542',
        'RegistrarController.php:366 , :388 , :419 , ClearanceController.php:380 , BlockingController.php:542'],
];

foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i}'s needle matched {$n} place(s), expected exactly 1.\n");

        exit(1);
    }
    if ($from === $to) {
        fwrite(STDERR, "REFUSED: edit {$i}'s needle and replacement are the same text.\n");

        exit(1);
    }
    // The whole-run needles are unique, but two of them *contain* a number another row already cites correctly
    // (`web.php:143`), so the refusal is on the replacement string as a whole, not on its digits.
    $already = substr_count($xml, $to);
    if ($already !== 0) {
        fwrite(STDERR, "REFUSED: edit {$i}'s replacement already occurs {$already} time(s) — either this pass ran"
            ." before or the new cell is another citation's bytes.\n");

        exit(1);
    }
}

$updated = $xml;
foreach ($edits as [$from, $to]) {
    $updated = str_replace($from, $to, $updated);
}

foreach ($edits as $i => [$from, $to]) {
    if (substr_count($updated, $from) !== 0) {
        fwrite(STDERR, "REFUSED: edit {$i}'s needle still occurs ".substr_count($updated, $from)." time(s).\n");

        exit(1);
    }
    if (substr_count($updated, $to) !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i}'s replacement occurs ".substr_count($updated, $to)." times, expected 1.\n");

        exit(1);
    }
}

// The stale pair must be gone, and so must the two page cells that named the wrong action. Counts of a *file-prefixed*
// token are not a usable check here: inside a list only the first number carries the file name, so `:411` and `:434`
// exist as bare continuations and `RegistrarController.php:366` legitimately appears three times once §23's
// print-action row, §25.1's certificate cell and §28's P-3 row all name it.
foreach (['RegistrarController.php:388 , :405 , :428', 'RegistrarController.php:421', 'RegistrarController.php:444',
    'RegistrarController.php:388 ) — the download route', '( RegistrarController.php:421 )', 'web.php:146', 'web.php:147',
    'web.php:148', 'web.php:149', 'web.php:150', 'web.php:151', 'web.php:124', 'web.php:138', 'web.php:139'] as $gone) {
    $c = substr_count($updated, $gone);
    if ($c !== 0) {
        fwrite(STDERR, "REFUSED: [{$gone}] still occurs {$c} time(s) after the edits.\n");

        exit(1);
    }
}

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $updated);
    $b = substr_count($updated, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($updated) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%sre-points %d cell(s): ten route numbers, six action numbers and the two reachability lists\n",
    $apply ? '' : 'DRY RUN — ', count($edits));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass272-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $updated);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $updated) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 272 applied: §25.1 names the routes and actions that actually serve each document.\n";
