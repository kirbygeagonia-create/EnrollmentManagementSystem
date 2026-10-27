<?php

/**
 * Docx pass 253 — the permission-catalogue figures pass 252c left behind.
 *
 * G-4 added one permission (`exam.verify`), so the live catalogue answers 94 permissions and 301
 * grants over 17 roles, measured on `ems` the day this pass was written. Pass 252c moved the two
 * sentences that spell the figure out ("93 permissions across 14 modules" → 94, twice) and the
 * module table's own count cell, and `lane60-ratio-claims.php` agreed with all of it — because that
 * checker binds a figure only where the sentence cites its instrument. Two present-tense claims in
 * tables carry the figure with no instrument named, so no gate could see them:
 *
 *   §22.3's SysAdmin row, which states what the role holds TODAY — "Every one of the 93 permissions,
 *   and additionally all abilities, by passing every policy, through Gate::before".
 *   §13's paragraph on the retired `exam.verify.general`, which closes "The live catalogue IS 93
 *   permissions and 298 grants after the reseed, with no orphan grant left behind".
 *
 * A third mention was read and deliberately LEFT: §23.7's `role_permissions` row says "Measured
 * 2026-10-11 on live ems: 298 grant rows, 93 permissions, 17 roles", and a row that carries its own
 * date records the day it was weighed — re-aging it would state a figure that was not true then.
 *
 * Both needles were probed first and each occurs exactly once in `word/document.xml`.
 * Usage: php storage/app/scratch/refresh-docx-pass253.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass253-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

// The live figures this pass writes, re-measured here rather than trusted from the header.
$counts = [];
foreach (['permissions' => 94, 'role_permissions' => 301, 'roles' => 17] as $table => $expected) {
    $out = shell_exec('C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -u root ems -N -e "SELECT COUNT(*) FROM '.$table.';" 2>&1');
    $got = (int) trim((string) $out);
    if ($got !== $expected) {
        $counts[] = "{$table} answers {$got}, this pass only writes while it is {$expected}";
    }
}

// shell_exec cannot reach a Git-Bash path on this box: a refusal here must be loud, never a silent zero.
if ($counts !== []) {
    fwrite(STDERR, "REFUSED: the live catalogue does not answer the figures this pass would write.\n");
    foreach ($counts as $c) {
        fwrite(STDERR, '  '.$c."\n");
    }
    exit(1);
}

docxApply(
    $docx,
    $backup,
    [
        ['Every one of the 93 permissions', 'Every one of the 94 permissions', null, 1],
        ['The live catalogue is 93 permissions and 298 grants', 'The live catalogue is 94 permissions and 301 grants', null, 1],
    ],
    [],
    $apply
);
