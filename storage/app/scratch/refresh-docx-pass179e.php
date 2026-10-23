<?php

/**
 * Pass 179e — two living sentences that say "today", found by grepping the document for the word
 * rather than by any checker (lane 58, 59, 60 and 61 all bind figures to readings; none of them reads
 * a deictic adverb).
 *
 * Both were written on 2026-10-21 and are read on 2026-10-23:
 *  - §30.6's last sentence: "the two blank letterhead rows this sentence named until today". The rows
 *    were supplied on 2026-10-21, so the sentence's own day has to be the day it stopped naming them.
 *  - §19's X-4 note: "Measured on live ems today the role holds 43 permissions across fifteen
 *    name-spaces". Re-measured before rewriting, because the honest fix is a date only if the figure
 *    still answers: OfficeHead holds 43 permissions over 15 name-spaces (admission 6, clearance 5,
 *    evaluation 5, block 4, clinic 4, print 4), the catalogue answers 93 permissions and 298 grants.
 *    So the number stays and the word goes.
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

require_once $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$head = Role::findByName('OfficeHead');
$spaces = [];
foreach ($head->permissions as $p) {
    $s = explode('.', $p->name)[0];
    $spaces[$s] = ($spaces[$s] ?? 0) + 1;
}
$guard($head->permissions->count() === 43, 'OfficeHead still answers 43 permissions (read '.$head->permissions->count().')');
$guard(count($spaces) === 15, 'across fifteen name-spaces (read '.count($spaces).')');
foreach (['admission' => 6, 'clearance' => 5, 'evaluation' => 5, 'block' => 4, 'clinic' => 4, 'print' => 4] as $n => $want) {
    $guard(($spaces[$n] ?? null) === $want, 'name-space '.$n.' answers '.$want.' (read '.($spaces[$n] ?? 'nothing').')');
}
$guard(Permission::count() === 93, 'the live catalogue holds 93 permissions (read '.Permission::count().')');

$textEdits = [
    [
        'Measured on live ems today the role holds 43 permissions',
        'Measured on live ems on 2026-10-23 the role holds 43 permissions',
    ],
    [
        'this sentence named until today are not owed after all',
        'this sentence named until 2026-10-21 are not owed after all',
    ],
];

docxApply($src, $root.'/storage/app/scratch/pass179e-backup.docx', $textEdits, [], $apply);
