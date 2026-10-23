<?php

/**
 * Pass 179d — three sentences pass 179 wrote that now describe a column the schema no longer holds
 * as though a reader could still go and look at it.
 *
 * Found by reading the rows back, not by a checker: lane 60 bound every figure, lane 61 found no
 * status word out of line, and all three sentences still passed. What is wrong in them is tense.
 *  - §27.5's "Where it is declared" cell pointed at `app/Models/Studentclearances.php`, which is the
 *    file that stopped declaring the column on 2026-10-23. The house style for a removed value is the
 *    migration that allowed it and the one that removed it — the precedent is the `idrequests.status`
 *    row above, which cites `…align_idrequests_with_validation_only_id_flow.php:49`.
 *  - §27.2 E-8's evidence cell states "Live ems held 4 rows and COUNT(extendedDeadline) answered 0".
 *    That query cannot run any more; the reading is true of the dump taken minutes before
 *    `artisan migrate`, so the cell says so.
 *  - The living §1.1 row says "NULL on every row live ems holds" — present tense about a column that
 *    is gone. It is now attributed to the pre-drop dump, which is where the pass itself reads it.
 *
 * DRY RUN unless --apply.
 */

require_once __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$create = (string) file_get_contents($root.'/database/migrations/2026_08_02_1000000021_create_studentclearances_table.php');
$lines = explode("\n", $create);
$guard(str_contains($lines[15] ?? '', "\$table->date('extendedDeadline')->nullable()"),
    'the create migration still carries the column at line 16, so the citation the pass writes is live');
$guard(! str_contains((string) file_get_contents($root.'/app/Models/Studentclearances.php'), 'extendedDeadline'),
    'and the model the old cell pointed at no longer names it');
$dump = 'C:/Users/ADMIN/ems-backups/ems-pre-drop-extendeddeadline-2026-10-23.sql';
$guard(is_file($dump), 'the dump the new sentences attribute the reading to exists');

$textEdits = [
    [
        'NULL on every row live ems holds',
        'NULL on every one of the four clearance rows in the dump taken minutes before the migration ran',
    ],
];

$cellEdits = [
    ['studentclearances.extendedDeadline — REMOVED from the column', 'was a nullable date',
        'allowed by 2026_08_02_1000000021_create_studentclearances_table.php:16 and dropped by '
            .'2026_10_23_1000000001_drop_extended_deadline_from_studentclearances_table.php; the model carried it in $fillable '
            .'and casts() until that day, and carries nothing of it now'],
    ['>E-8<', 'a scan of app/',
        'a scan of app/, resources/js/, tests/, tools/ and database/seeders/ for extendedDeadline named only the model’s $fillable '
            .'and casts() before the drop and names nothing after it; two files under database/migrations/ still carry the name, '
            .'the create and this drop. The mysqldump taken minutes before artisan migrate holds 4 studentclearances rows and NULL '
            .'in that column for every one of them, so the drop erased no data. ClearancePeriodStatus::Extended has exactly one '
            .'writer, extendPeriod, and is read through the accepting() scope rather than a literal comparison.'],
];

docxApply($src, $root.'/storage/app/scratch/pass179d-backup.docx', $textEdits, $cellEdits, $apply);
