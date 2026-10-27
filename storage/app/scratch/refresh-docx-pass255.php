<?php

/**
 * Docx pass 255 — the figures and the status vocabulary the one return act (gap G-10) moved.
 *
 * G-10 added `enrollment.return` to the catalogue and `returnedToAdmission` to the enrollment status, so
 * every present-tense sentence that spells either set is now false. This pass carries only the members
 * whose needle is contiguous inside one `<w:t>` run and whose replacement completes the sentence — seven
 * of them, each probed with `lane70-probe-needles.php` before it was written and each given the count
 * the probe measured, so a drift is a refusal rather than a half-applied register.
 *
 * Two families are deliberately NOT here, because a citation lives in a later run of the same cell and a
 * partial rewrite would leave the document's prose naming the new mechanism beside the old method's
 * line numbers — the shape this file has refused by taking the whole cell instead:
 *   - §13's hold paragraph and §16.4's notification row, whose sentences carry
 *     `RegistrarController.php:304-326` / `-328` (nine and two copies document-wide);
 *   - §7.1's transition table rows and §28.5's E-3, whose cells cite that deleted action.
 * Those go through the cell and para kinds in pass 255b. The §24 dictionary row for the new
 * `enrollmentreturns` table, §28.3's G-32 register row and §1.1's record row are row clones, which the
 * engine refuses at `<w:tr>`, and belong to 255c.
 *
 * The permission and grant figures are re-measured from live `ems` inside this pass. Nothing is written
 * unless the server answers what the sentences are about to say, because a docx figure typed from a
 * note is how §22.2's Total row came to read 93 while its own module rows summed to 94.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass255.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass255-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$expected = ['permissions' => 95, 'role_permissions' => 305, 'roles' => 17];
foreach ($expected as $table => $want) {
    // shell_exec cannot reach a Git-Bash path on this box, so an unread count must refuse loudly rather
    // than arrive as zero and be compared against zero.
    $out = (int) trim((string) shell_exec(
        'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -u root ems -N -e "SELECT COUNT(*) FROM '.$table.';" 2>&1'
    ));
    if ($out !== $want) {
        fwrite(STDERR, "REFUSED: live ems answers {$out} for {$table}; this pass writes only while it is {$want}.\n");
        exit(1);
    }
}

// The status set is read off the enum the document describes, not off this script's own memory of it.
$enumSource = (string) file_get_contents($root.'/app/Enums/EnrollmentStatus.php');
preg_match_all('/^\s*case ([A-Z]\w+) = \'(\w+)\';$/m', $enumSource, $cases, PREG_SET_ORDER);
$values = array_column($cases, 2);
if (count($values) !== 8 || ! in_array('returnedToAdmission', $values, true)) {
    fwrite(STDERR, 'REFUSED: EnrollmentStatus answers '.count($values).' cases and the new state is not among them.'."\n");
    exit(1);
}

$dotList = implode(' · ', $values);
$commaList = implode(', ', $values);
$enumList = 'enum('.implode('|', $values).')';

docxApply(
    $docx,
    $backup,
    [
        // §22.3's SysAdmin row and §13's retired-right paragraph: the catalogue grew by one.
        ['Every one of the 94 permissions', 'Every one of the 95 permissions', null, 1],
        ['The live catalogue is 94 permissions and 301 grants', 'The live catalogue is 95 permissions and 305 grants', null, 1],
        // "94 permissions across 14 modules" is written in two places; both are the same claim about the
        // same table, so both move together (expect=2, never a guess at a third).
        ['94 permissions across 14 modules', '95 permissions across 14 modules', null, 2],

        // The enrollment status, spelled three ways in three sections: §1's vocabulary table, §24's
        // column inventory, and §24's enum-by-model row. Every one is now eight values.
        ['pending · evaluated · assessed · paid · returnedToEvaluation · enrolled · dropped', $dotList, null, 1],
        ['enum(pending|evaluated|assessed|paid|returnedToEvaluation|enrolled|dropped)', $enumList, null, 1],
        ['pending, evaluated, assessed, paid, returnedToEvaluation, enrolled, dropped', $commaList, null, 1],

        // §19.4's notification-sentence table said the return was the Registrar's alone. The right is
        // one held by three desks, and the sentence's own "(§16.4)" pointer still resolves.
        ['in practice only types with a paid enrollment can be returned by the Registrar',
            'in practice any of the three desks holding enrollment.return may return a record from any state but dropped', null, 1],
    ],
    [],
    $apply
);
