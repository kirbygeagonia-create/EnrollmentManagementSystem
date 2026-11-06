<?php

/**
 * Probe every needle the phase-vocabulary docx pass means to write.
 *
 * A needle that occurs zero times is unreachable (the flat dump joins runs, so a sentence
 * that reads continuously on screen can be five `<w:t>` elements in the file); a needle that
 * occurs more than the pass expects is a shared tail of another citation. Both are read here
 * before anything is written.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx archive.\n");
    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'Phase 7 · Clinic Desk and Phase 8 · ID Office',
    'Phase 7 / Phase 8',
    'Phase 0 · Admissions Desk',
    'Phase 0.5 · BR9',
    'Phase 1 · Campus Clearance',
    'Phase 2 · Department Evaluation',
    'Phase 3 · Assessment Desk',
    'Phase 4 · Cashier Desk',
    'Phase 5 · Registrar Central Desk',
    'Phase 6 · Section Blocking',
    'Phase 0.5',
    'Phase 0',
    'Phase 1',
    'Legacy UI badge',
    'The codebase uses three independent numbering systems, and screens display the legacy one.',
    'its summary control reads',
    'Workflow Phase — Phase 5',
    'That is §28.3 U-4 closed on 2026-10-01 — the screen no longer contradicts itself about which phase the Registrar sits in.',
    'The “Phase 6” that does survive in the chrome belongs to Blocking, the desk after this one',
    'and a reader who meets it in a navigation label is looking at the right number for the wrong desk only if they expected the Registrar to be sixth.',
    'Registrar/Index.jsx:150-151',
    'DeskSubNav.jsx:74',
    'MegaAppLauncher.jsx:139',
    'AuthenticatedLayout.jsx:20',
    '>109<',
    'Closed 2026-10-01: the summary and the page badge both read Phase 5, so the screen no longer contradicts itself about which phase the Registrar sits in.',
];

$counts = [];

foreach ($needles as $needle) {
    $counts[$needle] = substr_count($xml, $needle);
}

foreach ($counts as $needle => $count) {
    printf("%3d  %s\n", $count, $needle);
}
