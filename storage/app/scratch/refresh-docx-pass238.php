<?php

/**
 * Lane 70, pass 238: §19.4, §20 and §24.14 still narrate gap N-1 as open, and the code closed it on 2026-10-01.
 *
 * The register (§28.3 N-1, dump row 2572) reads "Closed 2026-10-01. Both listeners write Staff-addressed rows now,
 * through App\Support\WorkflowInbox". The code agrees: `SendEnrollmentNotification.php:62-63` and
 * `SendWorkflowNotification.php:88-89` both write `notifiable_type = Staffusers::class` / `notifiable_id = $userId`,
 * `WorkflowInbox::actors()` (app/Support/WorkflowInbox.php:87-99) plucks **staff userIds** for an office, and the
 * listener's own docblock says "students are not notifiable in this application". Live `ems` is measured here rather
 * than recalled: the query below must answer exactly one notifiable_type, `App\Models\Staffusers`.
 *
 * Four places still state the retired shape in the present tense, and three of them cite lines that never addressed
 * anybody:
 *  - §19.4's table: "Writer — status changes … notifiable_type = \"App\Models\Students\", notifiable_id = studentId"
 *    citing `SendEnrollmentNotification.php:31-32` (the status-message `match` arms), and the same claim for
 *    "Writer — signatures" citing `:38-39, :55-56` (the completion branch and the signature type string).
 *  - §19.4's consequence: "no real notification is ever displayed. The writers address Students; the only reader
 *    filters on Staffusers."
 *  - §20's FK-purpose row: "Broken today: writers store App\Models\Students rows that no reader queries".
 *  - §24.14: "Correction established in this edition: … both listeners write notifiable_type = App\Models\Students
 *    while NotificationController queries only App\Models\Staffusers rows" — a correction that the build overtook.
 *
 * `App\Models\Students` occurs SEVEN times in document.xml, so no blanket replace is allowed: the three cells go
 * through the cell kind, the paragraph through the para kind (its class name sits in a styled run, so no text needle
 * spans it), and the remaining copies are printed for a separate read. This is the lane 234 rule applied to a whole
 * document: a token's count is not its owner list.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass238-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$rows = static function (string $path): array {
    return explode("\n", (string) file_get_contents($path));
};
$se = $rows($root.'/app/Listeners/SendEnrollmentNotification.php');
$sw = $rows($root.'/app/Listeners/SendWorkflowNotification.php');
$wi = $rows($root.'/app/Support/WorkflowInbox.php');
$asStrings = ['SendEnrollmentNotification.php' => $se, 'SendWorkflowNotification.php' => $sw, 'WorkflowInbox.php' => $wi];

$fails = 0;
foreach ([
    ['SendEnrollmentNotification.php', 59, 'foreach (WorkflowInbox::actors('],
    ['SendEnrollmentNotification.php', 60, 'Notifications::create(['],
    ['SendEnrollmentNotification.php', 62, "'notifiable_type' => Staffusers::class"],
    ['SendEnrollmentNotification.php', 63, "'notifiable_id' => \$userId"],
    ['SendEnrollmentNotification.php', 31, "'dropped' =>"],
    ['SendWorkflowNotification.php', 19, 'students are not notifiable'],
    ['SendWorkflowNotification.php', 30, 'WorkflowService::stepLabels()'],
    ['SendWorkflowNotification.php', 38, "\$progress['remaining'] === 0"],
    ['SendWorkflowNotification.php', 55, "'workflow_step_signed'"],
    ['SendWorkflowNotification.php', 85, 'foreach ($userIds as $userId)'],
    ['SendWorkflowNotification.php', 86, 'Notifications::create(['],
    ['SendWorkflowNotification.php', 88, "'notifiable_type' => Staffusers::class"],
    ['SendWorkflowNotification.php', 89, "'notifiable_id' => \$userId"],
    ['WorkflowInbox.php', 87, 'public static function actors'],
    ['WorkflowInbox.php', 93, 'Staffusers::where'],
    ['WorkflowInbox.php', 95, "pluck('userId')"],
] as [$file, $no, $needle]) {
    $actual = trim((string) ($asStrings[$file][$no - 1] ?? ''));
    if (! str_contains($actual, $needle)) {
        echo "ASSERT FAILED {$file}:{$no} must carry [$needle] — reads [$actual]\n";
        $fails++;
    }
}
// Neither listener may address a student anywhere.
foreach ([$se, $sw] as $lines) {
    foreach ($lines as $i => $l) {
        if (str_contains($l, "'notifiable_type'") && str_contains($l, 'Students')) {
            echo 'REFUSED: a listener addresses Students at line '.($i + 1)."\n";
            $fails++;
        }
    }
}

// Measure live ems rather than recall it: one notifiable population, and it is staff.
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=ems', 'root', '');
    $groups = $pdo->query('select notifiable_type, count(*) c from notifications group by notifiable_type')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo 'REFUSED: live ems could not be read ('.$e->getMessage().') — this pass rewrites a claim about its rows'."\n";
    exit(1);
}
if (count($groups) !== 1 || $groups[0]['notifiable_type'] !== 'App\Models\Staffusers') {
    echo 'REFUSED: notifications holds '.json_encode($groups).' — the new sentences assert one staff population'."\n";
    exit(1);
}
echo 'live ems measured: '.count($groups)." group, notifiable_type={$groups[0]['notifiable_type']} ({$groups[0]['c']} rows)\n";

if ($fails > 0) {
    echo "REFUSED: {$fails} assertion(s) failed — no sentence written\n";
    exit(1);
}

if (! copy($src, $tmp)) {
    echo "REFUSED: no backup at {$tmp}\n";
    exit(1);
}

$recipient = 'notifiable_type = Staffusers::class , notifiable_id = the recipient userId — the bell reads exactly this population';

docxApply(
    $src,
    $tmp,
    [
        ['Consequence (N-1): no real notification is ever displayed.',
            'Consequence (N-1): closed 2026-10-01 — the bell had been reading a different population than either writer addressed.',
        ],
        ['The writers address Students; the only reader filters on Staffusers.',
            'Both listeners now write notifiable_type = Staffusers::class with the recipient userId — which is exactly what the reader filters on — so a signature or a status move reaches the desk the workflow names next.',
        ],
        ['Even with a queue worker running, the bell counts stay at whatever the demo seeder inserted.',
            'What the table still cannot do is reach a student: no listener writes a student-addressed row, so the student half of the polymorphic shape is unused — and §28.3 records that these rows are never displayed to a student, with no queue worker by default and no email channel configured for them.',
        ],
    ],
    [
        ['Writer — status changes', 'SendEnrollment', 'SendEnrollmentNotification.php:59-66'],
        ['Writer — status changes', 'notifiable_type', $recipient],
        ['Writer — signatures', 'SendWorkflowNotification', 'SendWorkflowNotification.php:85-96'],
        ['Writer — signatures', 'notifiable_type', $recipient],
        ['The listener that writes the row', 'The listener that writes the row',
            'The listener that writes the row. Closed 2026-10-01 (gap N-1, §19.4): both listeners store Staff-addressed rows — notifiable_type = Staffusers::class, notifiable_id = userId — because App\Support\WorkflowInbox::actors() answers the office’s staff userIds (app/Support/WorkflowInbox.php:87-99), and that is the population NotificationController reads.',
        ],
    ],
    in_array('--apply', $argv, true),
    [
        ['The row shape is Laravel-native as described',
            'Correction established in this edition: the row shape is Laravel-native as described, and delivery reaches a desk. Both listeners write notifiable_type = App\Models\Staffusers with notifiable_id = the recipient’s userId, which is the population NotificationController queries, so a signature or a status move is shown to the office the workflow names next (§19.4, gap N-1, closed 2026-10-01). No listener writes a student-addressed row: the morph column could still name a student, and today nothing does.',
        ],
    ]
);

// The copies this pass deliberately did NOT touch, printed for the next read.
$zip = new ZipArchive();
if ($zip->open($src) === true) {
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    $pos = 0;
    $n = 0;
    while (($p = strpos($xml, 'App\\Models\\Students', $pos)) !== false) {
        $n++;
        $pos = $p + 1;
        printf("copy %d context: …%s…\n", $n, preg_replace('/\s+/', ' ', mb_substr(strip_tags(substr($xml, max(0, $p - 260), 420)), 0, 200)));
    }
    echo 'App\Models\Students copies before this pass: '.$n."\n";
}

echo in_array('--apply', $argv, true) ? "APPLIED\n" : "DRY RUN — nothing written\n";
