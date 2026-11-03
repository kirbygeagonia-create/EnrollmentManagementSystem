<?php

/**
 * Pass 426 — records the filter-plus-hop ambiguity inside the §1.1 row pass 425 wrote for the same batch.
 *
 * The academic term list produced a defect of a kind no gate in this repository weighed: a desk action joins a
 * table to ORDER by one of its columns while its own filter names a column BOTH sides hold. Bare, that name is
 * ambiguous, so MySQL answered 500 at the window and SQLite refuses with "ambiguous column name". The census that
 * found the rest of the class is `lane224-census-join-ambiguity.php`; this pass writes the finding into the
 * document that tells a reader what the build does today.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass426.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';

$needle = 'The same screen carried the two-copies defect this tranche keeps meeting on new pages:';
$clause = 'A filter and an order can also collide on one name. The school-year table holds `startDate` and `endDate` too, '
    .'and the School Year header joins it, so the term list’s Active and Inactive filters — which asked for those '
    .'columns by bare name — were ambiguous the moment a desk set the tab and clicked the header: live `ems` answered '
    .'500 and the suite refused with "ambiguous column name: startDate". No case in the family had ever sent the two '
    .'together, or had sent them beside a header that joins nothing. The fix qualifies the day columns, and '
    .'`storage/app/scratch/lane224-census-join-ambiguity.php` weighs the whole tree for the shape by reading each '
    .'hop’s own `local` for its base table — which named three more: the courses catalogue’s unit filter, the majors '
    .'list’s course filter and the Blocking roster’s program filter, each joined to the table whose primary key the '
    .'filter asks. All four are qualified, each is pinned by a case that puts the filter and the header on one '
    .'request, and the census answers no ambiguous name over the hopped actions it reads, refusing while any remains. '
    .'That same page also carried the two-vocabulary defect this tranche keeps meeting on new screens:';
// The replacement must NOT contain its own needle: an append that repeats the anchor can never make the anchor
// disappear, and a read-back demanding its absence refuses a correct write (pass 335 and 341's lesson, met in the
// draft of this pass). Hence the closing line re-spells the sentence it joins on rather than copying it.
if (str_contains($clause, $needle)) {
    $fail('the replacement embeds its own needle, so the read-back could never report the anchor retired.');
}

if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|mjs):\d/', $clause) === 1) {
    $fail('the new clause carries a File.php:NN citation this pass cannot verify — spell the pointer in words.');
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open for reading.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$hits = substr_count($xml, $needle);
if ($hits !== 1) {
    $fail("the anchor occurs {$hits} time(s) in document.xml, wanted exactly 1 — the row's prose moved; re-read it before writing.");
}
if (substr_count($xml, $clause) !== 0) {
    $fail('this pass has already run: its own clause is in the document.');
}

printf("anchor found once; clause is %d bytes. %s\n", strlen($clause), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    docxApply($docx, $root.'/storage/app/scratch/pass426-dryrun-backup.docx', [[$needle, $clause]], [], false);
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass426-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}

docxApply($docx, $backup, [[$needle, $clause]], [], true);

$read = (static function (string $docx): string {
    $z = new ZipArchive;
    if ($z->open($docx) !== true) {
        return '';
    }
    $x = (string) $z->getFromName('word/document.xml');
    $z->close();

    return $x;
})($docx);

$checks = [
    'the anchor is gone' => substr_count($read, $needle) === 0,
    'the clause is present once' => substr_count($read, $clause) === 1,
    'the row still names both tables' => substr_count($read, 'The twenty-seventh and twenty-eighth list tables sort') === 1,
];
$failed = array_keys(array_filter($checks, static fn (bool $v): bool => ! $v));
if ($failed !== []) {
    copy($backup, $docx);
    $fail('read-back refused ['.implode('; ', $failed).'] — restored from '.$backup);
}

$prev = libxml_use_internal_errors(true);
$doc = new DOMDocument;
$parsed = $doc->loadXML($read);
libxml_use_internal_errors($prev);
if (! $parsed) {
    copy($backup, $docx);
    $fail('document.xml no longer parses — restored from '.$backup);
}

exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/dump-docx-flat.php').' 2>&1');

printf("written; anchor retired, clause present once, the row still carries both screens; backup %s\n", basename($backup));
echo "Now re-dump (done above), then run sweep-docx-citations, lane37, lane140 --check-docx, verify-docx, docx-ooxml-check and the Word open test.\n";
