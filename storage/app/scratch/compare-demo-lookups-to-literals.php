<?php
/**
 * Does each lookup return what the demo tool's literals used to say?
 *
 * On the developer's machine the two must agree, or the change quietly rewrites which desk signs what and
 * which fee a charge belongs to. On any other install they are expected to disagree — that disagreement is
 * the defect being fixed — so the verdict printed here is per database, not global.
 */
$database = $argv[1] ?? 'ems';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');

$byName = fn (string $table, string $key, string $matcher, string $value) => $pdo
    ->query("SELECT {$key} FROM {$table} WHERE {$matcher} = ".$pdo->quote($value).' LIMIT 1')->fetchColumn();

$checks = [
    ['religions/religionName Roman Catholic', 'religions', 'religionId', 'religionName', 'Roman Catholic', 1],
    ['subjects/subjectCode IT101', 'subjects', 'subjectId', 'subjectCode', 'IT101', 5],
    ['rooms/roomName Room 101', 'rooms', 'roomId', 'roomName', 'Room 101', 1],
    ['feetypes Tuition Fee (per unit)', 'feetypes', 'feeTypeId', 'feeName', 'Tuition Fee (per unit)', 1],
    ['feetypes Miscellaneous Fee', 'feetypes', 'feeTypeId', 'feeName', 'Miscellaneous Fee', 2],
    ['feetypes Laboratory Fee', 'feetypes', 'feeTypeId', 'feeName', 'Laboratory Fee', 3],
    ['feetypes Library Fee', 'feetypes', 'feeTypeId', 'feeName', 'Library Fee', 4],
    ['staffusers office4_head (Guidance)', 'staffusers', 'userId', 'username', 'office4_head', 5],
    ['staffusers office3_head (Scholarship)', 'staffusers', 'userId', 'username', 'office3_head', 4],
    ['staffusers office2_head (Accounting)', 'staffusers', 'userId', 'username', 'office2_head', 3],
    ['staffusers office1_head (Registrar)', 'staffusers', 'userId', 'username', 'office1_head', 2],
    ['staffusers office5_head (Blocking)', 'staffusers', 'userId', 'username', 'office5_head', 6],
    ['staffusers office11_head (Clinic)', 'staffusers', 'userId', 'username', 'office11_head', 10],
    ['staffusers office22_head (ID Office)', 'staffusers', 'userId', 'username', 'office22_head', 11],
    ['staffusers office6_head (Admission)', 'staffusers', 'userId', 'username', 'office6_head', 7],
];

$same = 0;
$different = 0;
$absent = 0;

echo "database: {$database}\n\n";
foreach ($checks as [$label, $table, $key, $matcher, $value, $literal]) {
    $id = $byName($table, $key, $matcher, $value);

    if ($id === false) {
        $absent++;
        printf("  ABSENT     %-40s the literal said %d — the old code would have written %d anyway\n", $label, $literal, $literal);

        continue;
    }

    if ((int) $id === $literal) {
        $same++;
        printf("  same       %-40s %d\n", $label, $id);
    } else {
        $different++;
        printf("  DIFFERENT  %-40s lookup=%d  literal=%d  ← the old row would have been wrong here\n", $label, $id, $literal);
    }
}

printf("\n%d agree, %d differ, %d absent\n", $same, $different, $absent);
echo $database === 'ems' && $different === 0 && $absent === 0
    ? "VERDICT: on the machine the tool was written on, nothing changes.\n"
    : ($different + $absent > 0
        ? "VERDICT: this database does not match the literals — which is the defect the literals caused.\n"
        : "VERDICT: identical to the literals.\n");
