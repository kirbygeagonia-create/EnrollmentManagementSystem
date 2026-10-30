<?php

/**
 * Pass 310: re-point the directory row's echo pointer, which lane 76 held back.
 *
 * §28.3's G-14 row cites `StudentController.php:33-35 and :49;` — the status test and the filters echo. The batch
 * that wired the directory's sort turned that one-line echo into a six-line array, so line 49 is no longer the
 * echo's home, and the byte-window shift tool refused to guess: `:49` also appears in a §25 print row, so a global
 * replace would have moved a citation belonging to another file. The tool therefore wrote 32-34 → 33-35 and left
 * this one for a writer that can address the whole token.
 *
 * The new number is not arithmetic. The pass reads the working file and refuses unless the line it is about to
 * name actually opens the `filters` array.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass310.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$live = $root.'/app/Http/Controllers/StudentController.php';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$lines = explode("\n", (string) file_get_contents($live));
$echoLine = null;
foreach ($lines as $i => $text) {
    if (trim($text) === "'filters' => [") {
        if ($echoLine !== null) {
            fwrite(STDERR, "REFUSED: the index action holds two 'filters' => [ lines; read the file before pointing at one.\n");

            exit(1);
        }
        $echoLine = $i + 1;
    }
}
if ($echoLine === null) {
    fwrite(STDERR, "REFUSED: no line of StudentController.php opens the filters array.\n");

    exit(1);
}
printf("the echo lives at StudentController.php line %d: %s\n", $echoLine, trim($lines[$echoLine - 1]));

$from = ':33-35 and :49;';
$to = ':33-35 and :'.$echoLine.';';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$hits = substr_count($xml, $from);
if ($hits !== 1) {
    fwrite(STDERR, "REFUSED: the needle occurs {$hits} time(s), expected exactly 1: [{$from}]\n");

    exit(1);
}

printf("%s: re-pointing the G-14 echo pointer\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass310-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass310-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([[$from, 0, 'the old echo number must be gone'], [$to, 1, 'the new echo number must be present once']] as [$needle, $want, $why]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED ({$why}): [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED: the row now points at the line that opens the echo.\n";
