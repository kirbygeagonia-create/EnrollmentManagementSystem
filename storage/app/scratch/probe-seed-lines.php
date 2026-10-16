<?php
// Where each seeder fact actually lives, so §21.5 and §22's line citations can be re-pointed
// rather than remembered. Prints, for every fact the document cites, the matching source lines.
$files = [
    'RbacSeeder' => 'database/seeders/RbacSeeder.php',
    'DevReferenceDataSeeder' => 'database/seeders/DevReferenceDataSeeder.php',
    'StarterReferenceDataSeeder' => 'database/seeders/StarterReferenceDataSeeder.php',
];
$marks = [
    'RbacSeeder' => ['CREATE ALL PERMISSIONS', 'Permission::firstOrCreate', 'Role::updateOrCreate', 'syncPermissions(', 'syncRoles(', 'staffusers.role', 'DEFAULT ROLE'],
    'DevReferenceDataSeeder' => ['offices = [', "firstOrCreate(['officeId'", 'legacyClearanceOffice', 'Clearance Slip Replacement', 'ProvisionalClearanceRequirements::NAMES', 'dean_academic', "_head", 'Room 101', 'Academicyears', 'religions'],
    'StarterReferenceDataSeeder' => ['religions', 'CLEARANCE_SLIP_REPLACEMENT', 'Scholarshiptypes'],
];
foreach ($files as $name => $path) {
    $lines = file($path);
    echo '##### ', $name, '.php (', count($lines), " lines)\n";
    foreach ($marks[$name] as $needle) {
        foreach ($lines as $n => $line) {
            if (str_contains($line, $needle)) {
                printf("  %5d| %s\n", $n + 1, trim(mb_substr($line, 0, 72)));
            }
        }
    }
}
