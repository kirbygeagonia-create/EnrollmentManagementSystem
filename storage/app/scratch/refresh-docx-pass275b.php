<?php

/**
 * Pass 275b: re-take the four pointers this batch's line insertions moved.
 *
 * The batch added a permission block to RbacSeeder, an ability to AuthServiceProvider, and an
 * import plus a route to web.php. Every citation into those files below the insert therefore
 * aged, and the bounds sweep told me which four landed on a blank line — the other two in the
 * same sentences (:483 and :474) sit on live lines of the right file and say nothing, which is
 * the shape only reading catches. Each new number below was asserted against the line it names
 * before the write, not shifted by arithmetic.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass275b.php [--apply]
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

/**
 * Each edit: [needle, replacement, what the named line must contain, dump-line label].
 *
 * The third slot is the assertion: the pointer is only written when the line it now names
 * really carries the claim the sentence makes. A shift tool that cannot check its own target is
 * how a stale pointer ends up looking valid.
 */
$edits = [
    [
        'from' => 'web.php:242-245',
        'to' => 'web.php:250-253',
        'assert' => [
            ['routes/web.php', 250, 'grade-scale'],
            ['routes/web.php', 251, 'storeGradeScale'],
            ['routes/web.php', 252, 'updateGradeScale'],
            ['routes/web.php', 253, 'destroyGradeScale'],
        ],
        'claim' => 'the four gradescale routes',
    ],
    [
        'from' => 'RbacSeeder.php:198 and :483 instead',
        'to' => 'RbacSeeder.php:222 and :532 instead',
        'assert' => [
            ['database/seeders/RbacSeeder.php', 222, "permissions across"],
            ['database/seeders/RbacSeeder.php', 532, 'desk and faculty roles'],
        ],
        'claim' => 'the two counts the seeder reads rather than types',
    ],
    [
        'from' => 'run from RbacSeeder.php:223 to :474',
        'to' => 'run from RbacSeeder.php:247 to :523',
        'assert' => [
            ['database/seeders/RbacSeeder.php', 247, 'Role::updateOrCreate'],
            ['database/seeders/RbacSeeder.php', 523, 'Role::updateOrCreate'],
        ],
        'claim' => 'the span holding every role declaration',
    ],
    [
        'from' => 'AuthServiceProvider.php:168-170',
        'to' => 'AuthServiceProvider.php:176-178',
        'assert' => [
            ['app/Providers/AuthServiceProvider.php', 176, 'assessment.computeAtDesk'],
            ['app/Providers/AuthServiceProvider.php', 177, 'AssessmentPolicy'],
        ],
        'claim' => 'the ability that delegates to AssessmentPolicy::compute',
    ],
];

$lines = [];
foreach ($edits as $e) {
    foreach ($e['assert'] as [$file, $no, $_]) {
        $lines[$file] ??= file($root.'/'.$file);
        if (! isset($lines[$file][$no - 1])) {
            fwrite(STDERR, "REFUSED: {$file} has no line {$no}.\n");

            exit(1);
        }
        if (! str_contains($lines[$file][$no - 1], $_)) {
            $hint = trim($lines[$file][$_ === '' ? 0 : $no - 1]);
            fwrite(STDERR, "REFUSED: {$file}:{$no} does not carry [{$_}] — it reads [".mb_substr($hint, 0, 120)."]\n");

            exit(1);
        }
    }
}

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as $e) {
    $n = substr_count($xml, $e['from']);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSED: needle [{$e['from']}] occurs {$n} time(s), expected exactly 1 — {$e['claim']}.\n");

        exit(1);
    }
    if (substr_count($xml, $e['to']) !== 0) {
        fwrite(STDERR, "REFUSED: the replacement [{$e['to']}] is already present; this pass already ran.\n");

        exit(1);
    }
    $xml = str_replace($e['from'], $e['to'], $xml);
}

foreach ($edits as $e) {
    if (substr_count($xml, $e['from']) !== 0 || substr_count($xml, $e['to']) !== 1) {
        fwrite(STDERR, "REFUSED: after-check failed for {$e['claim']}: old still present or new not exactly once.\n");

        exit(1);
    }
}

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: unbalanced {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%sre-took %d pointers (%s)\n", $apply ? '' : 'DRY RUN — ', count($edits),
    implode(', ', array_column($edits, 'claim')));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass275b-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

$w = new ZipArchive;

if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing. Restore from {$backup}.\n");

    exit(1);
}
$w->addFromString('word/document.xml', $xml);
$w->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $xml) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 275b applied: the four aged pointers now name the lines that carry their claims.\n";
