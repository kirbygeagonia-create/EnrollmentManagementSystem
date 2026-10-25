<?php

/**
 * Lane 70, pass 240: §19.3 is the same defect G-31 names — a section titled after a finding closed on 2026-10-01.
 *
 * G-31's own consequence says to grep the whole document for the sentence shape when a mechanism claim is corrected.
 * Doing that on the N-1 fix found §19.3: it is titled "Workflow step labels are off by one (N-2)", its first
 * paragraph says `SendWorkflowNotification.php:21-30` HOLDS a label table keyed by `stepOrder`, and its last says
 * `stepLabel` "is derived from the stale map above and is wrong in both pipelines and both column sets". The
 * listener holds no such table: :30 reads `WorkflowService::stepLabels()` and :31 takes the signed step's OWN
 * officeId out of it — which is exactly what §28.3 N-2 records as the closure ("`stepLabels()` is the one
 * definition, keyed by OfficeId rather than by box number"). So the section describes the retired build in the
 * present tense, in a chapter a desk reads to learn why a notice names the wrong box.
 *
 * Treatment: the table stays as the record of the pre-ruling build, but every present-tense verb in the surrounding
 * prose goes past, the heading says it is closed, and one sentence states what the code does now with its own
 * verified citation. Nothing is re-aged silently — a dated record keeps its reading (lane 60's rule), which is why
 * the table is left standing rather than deleted.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass240-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$li = explode("\n", (string) file_get_contents($root.'/app/Listeners/SendWorkflowNotification.php'));
$wf = explode("\n", (string) file_get_contents($root.'/app/Services/WorkflowService.php'));
$fails = 0;
foreach ([
    [$li, 19, 'students are not notifiable'],
    [$li, 23, 'public function handle'],
    [$li, 30, 'WorkflowService::stepLabels()'],
    [$li, 31, '$labels[$step->officeId]'],
    [$li, 63, "'stepOrder' =>"],
    [$li, 64, "'stepLabel' =>"],
    [$wf, 30, 'public static function stepLabels'],
    [$wf, 33, 'OfficeId::Guidance'],
    [$wf, 41, '}'],
] as [$rows, $no, $needle]) {
    $actual = trim((string) ($rows[$no - 1] ?? ''));
    if (! str_contains($actual, $needle)) {
        echo 'ASSERT FAILED line '.$no." must carry [$needle] — reads [$actual]\n";
        $fails++;
    }
}
// The listener must hold no label table: nothing between its docblock and the first statement maps a step number.
$region = implode("\n", array_slice($li, 19, 12));
if (preg_match('/\d+\s*=>\s*[\'"]/', $region) === 1) {
    echo "ASSERT FAILED  lines 20-31 DO hold a stepOrder-keyed map — the old paragraph was right\n";
    $fails++;
}
if ($fails > 0) {
    echo "REFUSED: {$fails} assertion(s) failed — no sentence written\n";
    exit(1);
}

$edits = [
    ['Workflow step labels are off by one', 'Workflow step labels were off by one, and are corrected'],
    [' holds a label table keyed by ', ' held a label table keyed by '],
    ['begins with a step the workflow does not have', 'began with a step the workflow did not have'],
    ['therefore shifts every later label by one', 'therefore shifted every later label by one'],
    ['Every signature is announced', 'Every signature was announced'],
    ['and the ID Office box is announced as', 'and the ID Office box was announced as'],
    ['is derived from the stale map above and is wrong in both pipelines and both column sets',
        'was derived from the map above and wrong in both pipelines; both were fixed with §28.3 N-2 on 2026-10-01, and today the listener reads the one definition it should — `WorkflowService::stepLabels()` keyed by office (`app/Listeners/SendWorkflowNotification.php:30-31`), so the notice names the box its own signature closed',
    ],
];
if (! copy($src, $tmp)) {
    exit("REFUSED: no backup\n");
}
// Count inside document.xml, never in the .zip bytes — a plaintext needle finds nothing in the compressed file, and
// this exact guard refused a correct edit on that reasoning once already (pass 236).
$zip = new ZipArchive();
if ($zip->open($tmp) !== true) {
    exit("REFUSED: cannot read the backup\n");
}
$before = (string) $zip->getFromName('word/document.xml');
$zip->close();
foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($before, $from);
    if ($n !== 1) {
        printf("REFUSED: edit %d occurs %d time(s) in document.xml — [%s]\n", $i, $n, mb_substr($from, 0, 44));
        exit(1);
    }
}

// The anchor is the row's own opening text, not its id: `G-31` occurs THREE times now — the id cell, §28.1's
// `G-1 … G-31` range, and the sentence that names which lane added it — and an anchor that matches several rows
// would rewrite the wrong ones. The finding cell was written by pass 239 as one run, so this phrase is contiguous.
$cell = ['A sentence that says how the code behaves today', 'A sentence that says',
    'A sentence that says how the code behaves today is checked by nothing. Four sections narrated gap N-1 as the live state after §28.3 had closed it on 2026-10-01: §19.4’s writer table said both listeners address `App\Models\Students`, §19.4’s consequence said no notification is ever displayed, §20’s foreign-key purpose row said “Broken today: writers store `App\Models\Students` rows that no reader queries”, and §24.14 stated the same as *this edition’s* correction. Both listeners write `Staffusers::class` with the recipient’s userId (`app/Listeners/SendEnrollmentNotification.php:62-63`, `app/Listeners/SendWorkflowNotification.php:88-89`), `App\Support\WorkflowInbox::actors()` answers the office’s staff userIds (app/Support/WorkflowInbox.php:87-99), and the signature listener’s own docblock says students are not notifiable. The same class turned up again the moment the rule was followed: §19.3 was titled after its own finding and its prose said in the present tense that the listener HOLDS a `stepOrder`-keyed label table and that `stepLabel` “is wrong in both pipelines”, twenty-four days after §28.3 N-2 closed it — the heading now reads as corrected and the table of labels stands as a record of the pre-ruling build, kept as such.',
];
docxApply($src, $tmp, $edits, [$cell], in_array('--apply', $argv, true));
echo in_array('--apply', $argv, true) ? "APPLIED\n" : "DRY RUN — nothing written\n";
