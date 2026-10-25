<?php

/**
 * Pass 201 — §25's issuance paragraph: three bare numbers, one of which quoted a BLANK line.
 *
 * The paragraph names `PrintService.php:115-164` for recordIssue() and then continues with three pointers that
 * name no file — they sit more than 160 characters from it, so the sweep prints them as unattributable and the
 * bounds sweep never opens them. Read against the file, all three are wrong:
 *
 *   "( :168-181 )" for the scope chooser  -> PrintService.php:176-191   (:168 is a docblock line and the old
 *                                            range stops in the middle of the signature, before the match arms)
 *   "( :130 )" for the row lock            -> PrintService.php:140       (:130 is a BLANK line; the lock is
 *                                            `$issue->lockForUpdate()->max('documentNumber')` at :140)
 *   "( :132-142 )" for stepping over reserved numbers -> :142-152        (:132-135 is the scope's where-clause;
 *                                            the reserved list and the skip loop are :142-152)
 *
 * It goes through the engine's **para** kind because the runs split at every paren: no contiguous needle reaches
 * all three, and `</w:t>` cannot be written into a replacement. The rest of the paragraph is carried over
 * verbatim; only the three numbers and their file change.
 *
 *   php storage/app/scratch/refresh-docx-pass201.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass201.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$L = (array) file($root.'/app/Services/PrintService.php');
$claims = [
    [176, 'private function issueScope('],
    [182, 'return match ($documentType)'],
    [191, '}'],
    [140, "lockForUpdate()->max('documentNumber')"],
    [142, '$reserved = Documentprintlog::where'],
    [150, 'in_array($number, $reserved'],
    [130, ''],
];
$fails = 0;
foreach ($claims as [$n, $needle]) {
    $t = trim($L[$n - 1] ?? '');
    if ($needle === '') {
        $ok = $t === '';
        printf("  %s :%-4d is [%s] — expected BLANK, which is why the old pointer could not be seen as wrong\n",
            $ok ? 'ok  ' : 'BAD ', $n, $t);
        $fails += $ok ? 0 : 1;

        continue;
    }
    $ok = str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 72));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "\nREFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

$paraEdits = [[
    'documentNumber is one sequence, minted in one place',
    'documentNumber is one sequence, minted in one place — PrintService::recordIssue() ( PrintService.php:115-164 ) '
    .'— but its scope depends on which column identifies the paper: the student’s own series for a clearance slip that '
    .'carries a studentId, otherwise the enrollment’s series, the block’s series for a block schedule, and the '
    .'enrollment’s series for everything else ( PrintService.php:176-191 ). Each new number is one above the highest '
    .'already issued in that scope, taken under a row lock on the maximum rather than a row count '
    .'( PrintService.php:140 ), and it steps over the numbers the key-less legacy rows reserve ( PrintService.php:142-152 ). '
    .'The class card is the exception in form rather than in numbering: one document is issued per subject, its number is '
    .'only ever its own title line ( class-card.blade.php:6 ) and the card prints no Document No. row, while the slip and '
    .'the certificate both print theirs ( clearance-slip.blade.php:40 , enrollment-certificate.blade.php:46 ). The unique '
    .'index over ( enrollmentId , documentType , documentNumber ) was added with the print keys (§25, P-14), and it '
    .'covers the rows that carry an enrollment — any row with no key at all stays outside it, because MySQL treats their '
    .'NULLs as distinct — which is why the 21 such rows were reserved rather than repaired under ruling 6, and were '
    .'pruned on 2026-10-15 rather than left to keep the exception alive.',
]];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass201.docx', [], [], $apply, $paraEdits);
