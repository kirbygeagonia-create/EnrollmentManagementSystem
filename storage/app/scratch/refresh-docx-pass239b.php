<?php

/**
 * Lane 70, pass 239b: the G-31 row I just wrote contains a bare continuation of the kind G-31 is about.
 *
 * Its third cell says "`App\Support\WorkflowInbox::actors()` answers the office's staff userIds (:93-95)" — a class
 * name is not a file name, so the sweep attributes `:93-95` to the nearest preceding path on the row,
 * `app/Listeners/SendWorkflowNotification.php`, where 93-95 sits inside the payload of the very create call the row
 * cites. The lines exist and are non-blank, so the bounds sweep reports 0 problems: an invisible wrong target, in the
 * row that documents invisible wrong targets. The fix is the document's own rule — name the file rather than teach
 * a checker to guess — and `app/Support/WorkflowInbox.php:93-95` is the `Staffusers::where(...)->pluck('userId')`
 * read the sentence claims.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass239b-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$wi = explode("\n", (string) file_get_contents($root.'/app/Support/WorkflowInbox.php'));
$fails = 0;
foreach ([[87, 'public static function actors'], [93, 'Staffusers::where'], [95, "pluck('userId')"], [99, '}']] as [$no, $needle]) {
    $actual = trim((string) ($wi[$no - 1] ?? ''));
    if (! str_contains($actual, $needle)) {
        echo 'ASSERT FAILED WorkflowInbox.php:'.$no." must carry [$needle] — reads [$actual]\n";
        $fails++;
    }
}
if ($fails > 0) {
    exit(1);
}
if (! copy($src, $tmp)) {
    exit("REFUSED: no backup\n");
}

docxApply($src, $tmp, [
    [':93-95', 'app/Support/WorkflowInbox.php:93-95', 'answers the office’s staff userIds'],
], [], in_array('--apply', $argv, true));

echo in_array('--apply', $argv, true) ? "APPLIED\n" : "DRY RUN — nothing written\n";
