<?php

/**
 * Pass 438 — five pointers `lane76-shift-cites.php` refused, plus two it could not know were wrong.
 *
 * The batch inserted six lines at AuthServiceProvider.php:244 and one at RbacSeeder.php's permission list, so
 * ranges straddling those lines aged and ranges below them moved. The shift tool wrote what it could locate by
 * bytes and refused the rest; every item here was then read at BOTH endpoints in BOTH versions before the number
 * it is about to type was asserted against the line it names.
 *
 * Two of the five were not aged by this batch at all — they were wrong targets before it touched the file:
 *   - `AuthServiceProvider.php:180 and :198` names clinic.viewAtDesk and id.viewAtDesk, which are defined at
 *     196-198 and 214-216; line 180 sits inside assessment.computeAtDesk's body.
 *   - §22.4's collision table cites `180-182` for the clinic.view row and `198-200` for the id.view row, and
 *     those spans hold the tail of assessment.computeAtDesk and a NOTE comment, not the two gates the rows claim.
 * A bounds sweep clears all four of those numbers because each lands on live code in the right file.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass438.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");

    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
if (! is_file($docx)) {
    $fail('the authority docx is not on disk.');
}

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$readPart = static function (string $path, string $part) use ($fail): string {
    $zip = new ZipArchive;
    // ZipArchive::open answers an ERROR CODE for a file that is not an archive, and an error code is truthy.
    if ($zip->open($path) !== true) {
        $fail("could not open {$path} to read {$part}.");
    }
    $xml = $zip->getFromName($part);
    $zip->close();
    if (! is_string($xml) || $xml === '') {
        $fail("{$part} came back empty from {$path}.");
    }

    return $xml;
};

$readLine = static function (string $file, int $n) use ($fail, $root): string {
    $lines = file($root.'/'.$file);
    if ($lines === false) {
        $fail("could not read {$file}.");
    }
    if (! isset($lines[$n - 1])) {
        $fail("{$file} has no line {$n} (the file ends at ".count($lines).').');
    }

    return rtrim($lines[$n - 1]);
};

/**
 * Each item: the needle as the document writes it, the replacement, and the source lines the new numbers must
 * carry. `holds` is matched against the line's own text, so a pointer cannot be written onto a construct that
 * does not name it.
 */
$edits = [
    [
        'from' => ':180 and :198',
        'to' => ':196 and :214',
        'expect' => 1,
        'holds' => [
            ['app/Providers/AuthServiceProvider.php', 196, "Gate::define('clinic.viewAtDesk'"],
            ['app/Providers/AuthServiceProvider.php', 197, "hasPermissionTo('clinic.view')"],
            ['app/Providers/AuthServiceProvider.php', 214, "Gate::define('id.viewAtDesk'"],
            ['app/Providers/AuthServiceProvider.php', 215, "hasPermissionTo('id.view')"],
        ],
    ],
    [
        'from' => 'AuthServiceProvider.php:180-182',
        'to' => 'AuthServiceProvider.php:196-198',
        'expect' => 1,
        'holds' => [
            ['app/Providers/AuthServiceProvider.php', 196, "Gate::define('clinic.viewAtDesk'"],
            ['app/Providers/AuthServiceProvider.php', 198, '});'],
        ],
    ],
    [
        'from' => 'AuthServiceProvider.php:198-200',
        'to' => 'AuthServiceProvider.php:214-216',
        'expect' => 1,
        'holds' => [
            ['app/Providers/AuthServiceProvider.php', 214, "Gate::define('id.viewAtDesk'"],
            ['app/Providers/AuthServiceProvider.php', 216, '});'],
        ],
    ],
    [
        // The dated §1.1 row's span of the seventeen role calls: the first line moved 247 -> 248 with the
        // batch (the shift tool wrote it), the second never named the last call at all and now lands on it.
        'from' => ':523, seventeen',
        'to' => ':530, seventeen',
        'expect' => 1,
        'holds' => [
            ['database/seeders/RbacSeeder.php', 248, 'Role::updateOrCreate('],
            ['database/seeders/RbacSeeder.php', 530, 'Role::updateOrCreate('],
        ],
    ],
    [
        'from' => ':532 instead',
        'to' => ':539 instead',
        'expect' => 1,
        'holds' => [
            ['database/seeders/RbacSeeder.php', 223, "info('Created '."],
            ['database/seeders/RbacSeeder.php', 539, 'Role::count()'],
        ],
    ],
];

// ------------------------------------------------- stage 1: read, do not write
$xml = $readPart($docx, 'word/document.xml');

$before = [];
foreach ($edits as $i => $e) {
    $needle = $escape($e['from']);
    $n = substr_count($xml, $needle);
    if ($n !== $e['expect']) {
        $fail('edit '.$i." matched {$n} time(s), expected {$e['expect']}: [{$e['from']}]");
    }
    foreach ($e['holds'] as [$file, $line, $phrase]) {
        $text = $readLine($file, $line);
        if (! str_contains($text, $phrase)) {
            $fail("edit {$i} would point at {$file}:{$line}, which reads [{$text}] and does not carry [{$phrase}].");
        }
    }
    // A replacement that already occurs elsewhere cannot be post-checked by document-wide absence, so take its
    // count now and require exactly this edit's contribution afterwards.
    $before[$i] = substr_count($xml, $escape($e['to']));
}

echo 'stage 1: 5 edits, '.count(array_merge(...array_column($edits, 'holds'))).' source lines asserted, all carry their claim.'."\n";
foreach ($edits as $e) {
    echo '  '.$e['from'].'  ->  '.$e['to']."\n";
}

if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass438-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}

docxApply($docx, $backup, array_map(
    static fn (array $e): array => [$e['from'], $e['to'], null, $e['expect']],
    $edits
), [], true);

// ------------------------------------------------- read back from the archive, not from stdout
$read = $readPart($docx, 'word/document.xml');
foreach ($edits as $i => $e) {
    $gone = substr_count($read, $escape($e['from']));
    if ($gone !== 0) {
        unlink($backup);
        copy($backup, $docx);
        $fail("the retired pointer [{$e['from']}] is still in the document ({$gone} copies) — the backup was restored.");
    }
    $now = substr_count($read, $escape($e['to']));
    if ($now !== $before[$i] + $e['expect']) {
        unlink($backup);
        copy($backup, $docx);
        $fail("[{$e['to']}] came back {$now} time(s), expected ".($before[$i] + $e['expect']).' (it occurred '.$before[$i].' time(s) before this pass) — the backup was restored.');
    }
}

echo 'WROTE 5 pointers. backup: '.basename($backup)."\n";
