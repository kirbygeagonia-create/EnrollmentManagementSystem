<?php
/**
 * Re-read §23.8's module census and §23.4's delete-rule split from information_schema.
 *
 * §23.3's inventory just grew by the fourteen edges the rulings introduced, so every figure that
 * was derived from it moves too: Keys out and Keys in per module, the inbound counts printed next
 * to a table's name, and the "66 NO ACTION / 16 SET NULL / 4 CASCADE" sentence. This prints the
 * server's numbers beside the document's, and nothing is written.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

// The document's own grouping (§23.8's rows, §24's module order), spelled from its table lists.
$modules = [
    'Student Information' => ['students', 'guardians', 'addresses', 'religions', 'educationalinstitutions', 'studenteducationalbackgrounds'],
    'Academic Structure' => ['academicunits', 'courses', 'majors', 'curriculums', 'subjects', 'curriculumsubjects', 'gradescale', 'examresults'],
    'Admissions and Requirements' => ['admissions', 'admissionrequirements', 'studentrequirementsubmissions', 'documents'],
    'Enrollment' => ['academicyears', 'academicterms', 'enrollments', 'enrollmentstatushistory', 'enrolledsubjects', 'shiftingrequests'],
    'Transfer and Prior Academic Records' => ['transferacademicrecords', 'creditedsubjects'],
    'Scholarships and Fees' => ['scholarshiptypes', 'studentscholarships', 'feetypes', 'studentassessments', 'charges', 'payments'],
    'Clearance (Registrar sub-function)' => ['clearanceperiods', 'clearancerequirements', 'studentclearances', 'clearanceapprovals'],
    'Scheduling and Blocking' => ['blocks', 'schedules', 'schedulemeetings', 'rooms'],
    'Clinic' => ['clinicrecords'],
    'ID Management' => ['idrequests'],
    'Users, Offices, Roles and Security' => ['offices', 'staffusers', 'roles', 'permissions', 'staff_roles', 'role_permissions', 'model_has_permissions', 'auditlogs', 'settings'],
    'Reports and Document Tracking' => ['notifications', 'documentprintlog'],
    'Workflow Tracking' => ['enrollmentworkflow', 'workflowsteps'],
];
$moduleOf = [];
foreach ($modules as $name => $tables) {
    foreach ($tables as $t) {
        $moduleOf[strtolower($name.'|'.$t)] = $name;
    }
}

$edges = [];
foreach (DB::select("
    select k.table_name t, k.column_name c, k.referenced_table_name rt, r.delete_rule d, r.update_rule u
    from information_schema.key_column_usage k
    join information_schema.referential_constraints r
      on r.constraint_name = k.constraint_name and r.constraint_schema = k.table_schema
    where k.table_schema = 'ems' and k.referenced_table_name is not null
    order by k.table_name, k.column_name") as $r) {
    $edges[] = ['child' => strtolower($r->t), 'parent' => strtolower($r->rt), 'd' => $r->d, 'u' => $r->u];
}

printf("%d referential edges\n\n", count($edges));

$allTables = array_merge(...array_values($modules));
$keysOut = $keysIn = $inbound = $unknown = [];
foreach ($edges as $e) {
    $inbound[$e['parent']] = ($inbound[$e['parent']] ?? 0) + 1;
    if (! in_array($e['child'], $allTables, true)) {
        $unknown[$e['child']] = ($unknown[$e['child']] ?? 0) + 1;
    }
    foreach ($modules as $name => $tables) {
        if (in_array($e['child'], $tables, true)) {
            $keysOut[$name] = ($keysOut[$name] ?? 0) + 1;
        }
        if (in_array($e['parent'], $tables, true) && ! in_array($e['child'], $tables, true)) {
            $keysIn[$name] = ($keysIn[$name] ?? 0) + 1;
        }
    }
}

echo "--- module census, as the server reads it ---\n";
printf("%-38s %7s %9s %8s\n", 'Module', 'Tables', 'Keys out', 'Keys in');
foreach ($modules as $name => $tables) {
    printf("%-38s %7d %9d %8d\n", $name, count($tables), $keysOut[$name] ?? 0, $keysIn[$name] ?? 0);
}
printf("%-38s %7d %9d %8d\n", 'TOTAL (13 modules)', array_sum(array_map('count', $modules)), array_sum($keysOut), array_sum($keysIn));

echo "\n--- edges whose child table is in no documented module (framework tables) ---\n";
foreach ($unknown as $t => $n) {
    printf("  %-30s %d\n", $t, $n);
}

echo "\n--- inbound keys for the tables §23.7 and §23.8 name ---\n";
foreach (['enrollments', 'students', 'courses', 'subjects', 'academicterms', 'staffusers', 'admissions', 'offices', 'majors', 'schedules', 'clearanceperiods', 'enrollmentworkflow'] as $t) {
    printf("  inbound %-22s %d\n", $t, $inbound[$t] ?? 0);
}

echo "\n--- delete and update rule split over the whole inventory ---\n";
$ds = $us = [];
foreach ($edges as $e) {
    $ds[$e['d']] = ($ds[$e['d']] ?? 0) + 1;
    $us[$e['u']] = ($us[$e['u']] ?? 0) + 1;
}
foreach ($ds as $rule => $n) {
    printf("  on delete %-12s %d\n", $rule, $n);
}
foreach ($us as $rule => $n) {
    printf("  on update %-12s %d\n", $rule, $n);
}

echo "\n--- the shift docket's own edges, for the §23.8 Enrollment row ---\n";
foreach ($edges as $e) {
    if ($e['child'] === 'shiftingrequests') {
        printf("  → %-24s %s\n", $e['parent'], $e['d'].' / '.$e['u']);
    }
}
