<?php

/**
 * Pass 87 — the signature ruling and the class card's doubled day.
 *
 * Every citation that pointed inside WorkflowService::signStep was re-measured by the
 * statement it names, not by arithmetic: the guard, the office-scope throw, the BR13
 * order check, the sign write, the completion check and the academic-signer exception are
 * six different places, and four of them were already pointing at the wrong one before
 * this batch moved anything.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

// Citations re-pointed by meaning. expect = occurrences found in the document.
$cites = [
    ['WorkflowService.php:109-185', 'WorkflowService.php:182-278', 6, 'signStep(), whole method'],
    ['the office test at :134', 'the office test at :236', 4, 'the office-scope test, short form'],
    ['WorkflowService.php:113-124', 'WorkflowService.php:236-240', 1, 'the office-scope throw'],
    ['WorkflowService.php:114-124', 'WorkflowService.php:243-253', 1, 'BR13 order check'],
    ['WorkflowService.php:128-130', 'WorkflowService.php:256-259', 1, 'the sign write'],
    ['WorkflowService.php:139-146', 'WorkflowService.php:268-275', 2, 'completion of the workflow'],
    ['WorkflowService.php:150-174', 'WorkflowService.php:294-301', 1, 'resolve by officeId, return null'],
    ['WorkflowService.php:94-98', 'WorkflowService.php:223-227', 1, 'the academic-signer exception'],
    ['bootstrap/app.php:46-59', 'bootstrap/app.php:47-60', 3, 'the stage-message renderer'],
    ['bootstrap/app.php:124-137', 'bootstrap/app.php:148-161', 1, 'the missing-browser renderer'],
];

// Run-level prose edits inside the signature bullet. Each target is one <w:t> run.
$prose = [
    [
        'Signature overwrite, not signature history.',
        'One signature per box; only a record that came back takes a second.',
        1,
        'the bold lead-in of the bullet',
    ],
    [
        ' on that row (',
        ' on that row once (',
        1,
        'the row-write clause',
    ],
    [
        '). When the Registrar returns an enrollment to Department Evaluation (§16.4) and the department signs again, the ',
        ') — ruled 2026-10-11, WorkflowService.php:206-220. A box that is closed refuses a second signature, so an officer '
        .'who acts twice on a record another office already left cannot replace the name on it: the desk is told which box '
        .'refused it, whose signature it is and since when, and the whole action rolls back — the ID request that reaches a '
        .'signed box stays pending with no validator. The one case that may sign again is a record that genuinely came back, '
        .'so when the Registrar returns an enrollment to Department Evaluation (§16.4) and the department signs again, the ',
        1,
        'the sentence after the citation',
    ],
    [
        '), which is a second-look reconstruction, not a ledger.',
        ') — which is now the ledger this ruling rests on, because a re-signature is permitted precisely so that the earlier '
        .'officer and date survive in that row. The fall-back is proved from enrollmentstatushistory rather than from the '
        .'live status, because a desk collecting a reopened account writes the new status before it signs, and the live '
        .'status would refuse the one case the owner allowed. A box that moves no status at all — Blocking, Clinic, ID — '
        .'can therefore never show a fall-back and refuses a second signature outright; the ID desk consequently holds one '
        .'request per enrollment for the life of that enrollment (IDPolicy.php:36-65), which is why the demo row sitting '
        .'pending under an already-signed ID box was deleted rather than made into a new state.',
        1,
        'the closing clause of the bullet',
    ],
];

$fails = 0;
$statements = [];

foreach (array_merge($cites, $prose) as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    $statements[] = $label;
    printf("edit ok  %-42s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stale signStep span survives' => substr_count($xml, $esc('WorkflowService.php:109-185')) === 0
        && substr_count($xml, $esc('WorkflowService.php:113-124')) === 0
        && substr_count($xml, $esc('WorkflowService.php:114-124')) === 0
        && substr_count($xml, $esc('WorkflowService.php:128-130')) === 0
        && substr_count($xml, $esc('WorkflowService.php:139-146')) === 0
        && substr_count($xml, $esc('WorkflowService.php:150-174')) === 0
        && substr_count($xml, $esc('WorkflowService.php:94-98')) === 0
        && substr_count($xml, $esc('the office test at :134')) === 0,
    'no stale bootstrap span survives' => substr_count($xml, $esc('bootstrap/app.php:46-59')) === 0
        && substr_count($xml, $esc('bootstrap/app.php:124-137')) === 0,
    'the old claim is gone' => substr_count($xml, $esc('Signature overwrite, not signature history.')) === 0,
    'the ruling is in once' => substr_count($xml, $esc('One signature per box; only a record that came back takes a second.')) === 1,
    'the refusal rule is in once' => substr_count($xml, $esc('A box that is closed refuses a second signature')) === 1,
    'the history proof is in once' => substr_count($xml, $esc('The fall-back is proved from enrollmentstatushistory')) === 1,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    }
}

printf("\n%d statements verified; %s\n", count($statements), $fails === 0 ? 'all edits and guards ok' : "{$fails} problem(s)");

if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass87.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
