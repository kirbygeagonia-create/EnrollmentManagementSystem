<?php

/**
 * Pass 432 — §28.3's gap-row enumeration gains the permission list and the role list, and the batch row's own false
 * claim about that sentence is retired.
 *
 * Pass 430 recorded this as an unreachable residual, and that record was wrong as written: its probe had searched the
 * requirement matrix for a parallel sentence, while the docx list is ONE contiguous run — `and on the academic term
 * list` occurs exactly once in `word/document.xml` — so a plain text edit reaches it and no cell rewrite is needed.
 * The lesson travels: probe the sentence in the artefact named, never a document that restates it.
 *
 * The file is a one-shot. Once applied, its needles are consumed, so a re-run prints ALREADY APPLIED and exits 0
 * rather than refusing like a broken instrument (lane 108's rule for `lane108-pin-…`, which reported the same thing).
 *
 * Usage: php storage/app/scratch/refresh-docx-pass432.php [--apply]
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

// [from, to] — every `to` retires its `from` except edit 1, which is an append.
$edits = [
    [
        'and on the academic term list',
        'and on the academic term list, on the permission list and on the role list',
    ],
    [
        'a text edit cannot reach it',
        'a text edit was said to be unable to reach it, which was wrong as written: the probe had searched the '
        .'requirement matrix for a parallel sentence instead of this one',
    ],
    [
        'Rewriting that cell through the engine would flatten its styled runs, so the enumeration is left for a pass '
        .'written for the cell kind, and this row carries the two screens in the meantime.',
        'Rewriting the cell would have flattened its styled runs for no reason: the sentence is one contiguous run, so '
        .'the two names were appended where they belong, and a claim that an edit cannot reach a prose sentence is a '
        .'claim about the needle that was tried.',
    ],
];

$readPart = static function (string $docx) use ($fail): string {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail('the docx did not open as an archive.');
    }
    $body = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    if ($body === '') {
        $fail('word/document.xml read empty.');
    }

    return $body;
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$xml = $readPart($docx);

// ------------------------------------------------- already-applied? (a one-shot must say so, not look broken)
$appliedAll = true;
foreach ($edits as [$from, $to]) {
    if (substr_count($xml, $escape($to)) !== 1 || substr_count($xml, $escape($from)) !== (str_contains($to, $from) ? 1 : 0)) {
        $appliedAll = false;

        break;
    }
}
if ($appliedAll) {
    echo "ALREADY APPLIED: all three replacements are present and every retired needle is gone where it should be.\n";
    exit(0);
}

// ------------------------------------------------- stage 1: validate everything before writing anything
foreach ($edits as $i => [$from, $to]) {
    $hits = substr_count($xml, $escape($from));
    if ($hits !== 1) {
        $fail('edit '.($i + 1)." occurs {$hits} time(s) in the archive, expected exactly 1 — the needle has aged, or this pass has already run.\n"
            .'           needle: '.mb_substr($from, 0, 96));
    }
    if (substr_count($xml, $escape($to)) !== 0) {
        $fail('edit '.($i + 1).' is a no-op: its replacement already occurs in the document.');
    }
}
printf("stage 1: %d needle(s) each occur exactly once. %s\n", count($edits), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

// ------------------------------------------------- stage 2: write through the engine, two-element edits
$backup = $root.'/storage/app/scratch/pass432-backup-'.date('Ymd-His').'.docx';
docxApply($docx, $backup, array_map(static fn (array $e): array => [$e[0], $e[1]], $edits), [], true);

// ------------------------------------------------- stage 3: read back from the archive, per-edit expectation
$after = $readPart($docx);
foreach ($edits as $i => [$from, $to]) {
    $appends = str_contains($to, $from);
    $oldLeft = substr_count($after, $escape($from));
    $newCount = substr_count($after, $escape($to));
    $wantOld = $appends ? 1 : 0;
    if ($newCount !== 1 || $oldLeft !== $wantOld) {
        printf("REFUSED: edit %d read back as needle=%d (wanted %d for a %s) and new=%d (wanted 1); restoring from %s\n",
            $i + 1, $oldLeft, $wantOld, $appends ? 'append' : 'rewrite', $newCount, basename($backup));
        copy($backup, $docx);

        exit(1);
    }
    printf("edit %d read back: %s; new text occurs once\n", $i + 1,
        $appends ? 'needle survives inside the appended text' : 'retired needle is gone');
}
printf("WROTE %d phrase edits; backup at %s\n", count($edits), basename($backup));
