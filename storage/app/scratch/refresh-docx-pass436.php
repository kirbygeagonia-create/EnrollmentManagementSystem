<?php

/**
 * Pass 436 — the two sentences that state the import's publish rule, re-taken for `--fill-gaps`.
 *
 * The owner ruled the policy on 2026-11-03: fill the gaps, refuse the conflicts. Both places in the authority
 * document say the sheet is written "unless --commit is present AND the whole sheet resolved", which is true of
 * the default path and false of the ruling, so the mechanism sentence moves with the mechanism (lane 70's class:
 * a live pointer, a right file, an overtaken claim).
 *
 * Usage: php storage/app/scratch/refresh-docx-pass436.php [--apply]
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

$edits = [
    [
        'from' => 'and writes nothing unless `--commit` is present and the whole sheet resolved',
        'to' => 'and writes nothing unless `--commit` is present; the ruling of 2026-11-03 added `--fill-gaps`, '
            .'which skips the rows that collide with hours already booked and publishes the gaps beside them, '
            .'while a row this tool cannot read still refuses the whole sheet',
    ],
    [
        'from' => 'Nothing is written unless --commit is present AND the whole sheet resolved, so a preview cannot half-load a term',
        'to' => 'Nothing is written unless --commit is present; --fill-gaps, ruled 2026-11-03, skips the rows that '
            .'collide and publishes the gaps, while an unreadable row still refuses the whole sheet, so a preview '
            .'cannot half-load a term',
    ],
];

$readPart = static function (string $docx, string $part) use ($fail): string {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail("could not open {$part} inside the docx.");
    }
    $body = $zip->getFromName($part);
    $zip->close();
    if (! is_string($body) || $body === '') {
        $fail("{$part} read empty.");
    }

    return $body;
};

// The needles were probed against the archive before this pass was written: the flat dump joins runs with a space,
// so a phrase copied out of it can carry spacing the document never held.
$xml = $readPart($docx, 'word/document.xml');
foreach ($edits as $i => $e) {
    $n = substr_count($xml, $e['from']);
    if ($n !== 1) {
        $fail("edit {$i} needle matches {$n} time(s) in word/document.xml, expected exactly 1.");
    }
    if (substr_count($xml, $e['to']) !== 0) {
        $fail("edit {$i} replacement already occurs — this pass has already run, or the sentence exists elsewhere.");
    }
    printf("stage 1 ok: edit %d re-takes [%s…]\n", $i, substr($e['from'], 0, 46));
}

printf("%s\n", $apply ? 'applying 2 edits' : 'DRY RUN — nothing written');
if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass436-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
docxApply($docx, $backup, array_map(static fn (array $e): array => [$e['from'], $e['to']], $edits), [], true);

$read = $readPart($docx, 'word/document.xml');
foreach ($edits as $i => $e) {
    if (substr_count($read, $e['from']) !== 0) {
        unlink($backup);
        copy($backup, $docx);
        $fail("edit {$i} left its retired sentence in the document — the backup was restored.");
    }
    if (substr_count($read, $e['to']) !== 1) {
        unlink($backup);
        copy($backup, $docx);
        $fail('edit '.($i + 1)." does not read back exactly once (".substr_count($read, $e['to']).') — the backup was restored.');
    }
}
printf("WROTE two import-policy sentences; backup at %s\n", basename($backup));
