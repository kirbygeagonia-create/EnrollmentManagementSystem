<?php

/**
 * Rewrites the matrix's G39 evidence cell, because the page it described no longer carries its own tone map.
 *
 * The citation gate caught the break the deletion caused — the row quoted lines 139 to 148 of a file that is
 * now 137 lines — but re-pointing the number would have left the SENTENCE false: it promises "each print view
 * re-declares its own tone map", and the scan that found the one hold-out cleared all the others. So the cell
 * is re-taken whole, the way §25's identity-matrix cells were, rather than patched a digit at a time.
 *
 * Usage: php storage/app/scratch/patch-matrix-g39-cite.php [--apply]
 */
$path = dirname(__DIR__, 3).'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$src = (string) file_get_contents($path);
$apply = in_array('--apply', $argv, true);

$pairs = [
    '`resources/js/Components/ui/statusLabel.js` is shared by desks, the stepper and the print pages\' **labels**, but each print view re-declares its own **tone** map — `resources/js/Pages/Registrar/PrintCertificate.jsx:139-148` lists six statuses and omits the two return states, so a returned record prints neutral where the desk shows a colour'
        => '`resources/js/Components/ui/statusLabel.js:13-26` holds the one enrollment tone map and every desk reads it; the certificate screen was the last surface carrying its own copy — a six-key map that had never learned either return state, so a returned record printed neutral where the desk showed a colour. It now reads the shared map at `resources/js/Pages/Registrar/PrintCertificate.jsx:94`. Measured, NOT all print views did this: the scan that found that one page cleared every other surface, which retires the claim this row first carried. Pinned by `tests/Feature/Enrollment/EnrollmentToneVocabularyTest.php`, which reads the sources because the repository has no JavaScript test suite.',
    '| PARTIAL → G39 |' => '| G39 CLOSED 2026-10-29 |',
];

foreach ($pairs as $from => $to) {
    $hits = substr_count($src, $from);
    if ($hits !== 1) {
        fwrite(STDERR, "REFUSED: the needle occurs {$hits} time(s); expected exactly 1. Its first 60 bytes: ["
            .mb_substr($from, 0, 60)."]\n");

        exit(1);
    }
    $src = str_replace($from, $to, $src);
    echo "replaced one copy (needle of ".strlen($from)." bytes)\n";
}

foreach (explode("\n", $src) as $l) {
    if (str_contains($l, 'G39 CLOSED')) {
        echo "\nafter:\n".wordwrap($l, 108)."\n";

        break;
    }
}

if (! $apply) {
    echo "\nDRY RUN — nothing written.\n";

    exit(0);
}

file_put_contents($path, $src);
echo "\nwritten\n";
