<?php
/**
 * Facts §21.3 and §28.5 assert, read from the build rather than from the paragraph.
 * Each line prints the claim the document makes and what the code or live ems says now.
 */
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\OfficeId;

function line(string $label, string $value): void
{
    echo str_pad($label, 46).' '.$value.PHP_EOL;
}

// --- refdata.* permissions and who holds them -----------------------------------------
$refdata = DB::table('permissions')->where('module', 'refdata')->orderBy('name')->pluck('name')->all();
$manage = array_values(array_filter($refdata, fn ($n) => str_ends_with($n, '.manage')));
line('refdata.* permissions', count($refdata).' ('.implode(', ', $refdata).')');
line('refdata.*.manage count', (string) count($manage));
foreach ($manage as $m) {
    $roles = DB::table('role_permissions')
        ->join('roles', 'roles.id', '=', 'role_permissions.roleId')
        ->join('permissions', 'permissions.id', '=', 'role_permissions.permissionId')
        ->where('permissions.name', $m)->pluck('roles.name')->implode(', ');
    line('  holder of '.$m, $roles === '' ? 'NOBODY' : $roles);
}

// --- offices: rows, enum cases, deletion guard ----------------------------------------
line('offices rows', (string) DB::table('offices')->count());
line('OfficeId cases', (string) count(OfficeId::cases()));
$ctrl = file_get_contents($root.'/app/Http/Controllers/Admin/ReferenceDataController.php');
line('refuses office deletion', str_contains($ctrl, 'staff') && str_contains($ctrl, 'orphan') || str_contains($ctrl, 'cannot be deleted') ? 'yes (text found)' : 'CHECK BY HAND');
preg_match_all('/cannot be deleted[^"]{0,80}/i', $ctrl, $why);
foreach (array_slice($why[0], 0, 3) as $w) {
    line('  refusal sentence', $w);
}

// --- clearancerequirements columns ----------------------------------------------------
$cols = DB::select("select COLUMN_NAME from information_schema.COLUMNS where TABLE_SCHEMA=database() and TABLE_NAME='clearancerequirements'");
line('clearancerequirements columns', implode(', ', array_map(fn ($c) => $c->COLUMN_NAME, $cols)));

// --- elective columns on the screen ---------------------------------------------------
preg_match_all("/(is_elective|elective_group|elective_min_choices|elective_max_choices)/", $ctrl, $el);
line('elective fields in ReferenceDataController', (string) count(array_unique($el[1])).'/4 named');
line('normalizeElective exists', str_contains($ctrl, 'normalizeElective') ? 'yes' : 'NO');

// --- curriculumsubjects actually flagged elective -------------------------------------
line('curriculumsubjects rows flagged elective', (string) DB::table('curriculumsubjects')->where('is_elective', 1)->count());

// --- payments statuses and who can settle ---------------------------------------------
line('payments.paymentStatus enum', (string) DB::select("select COLUMN_TYPE t from information_schema.COLUMNS where TABLE_SCHEMA=database() and TABLE_NAME='payments' and COLUMN_NAME='paymentStatus'")[0]->t);
line('settleNoBalance route exists', str_contains(file_get_contents($root.'/routes/web.php'), 'settle') ? 'yes' : 'NO');
