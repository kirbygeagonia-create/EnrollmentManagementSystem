<?php
/**
 * Replace the demo tool's hardcoded reference ids with the lookups, asserting every count.
 *
 * Fourteen sites write `evaluatedBy => 5`, three write `processedBy => 3`, two write each fee type: all of
 * them mean "the seeded head of that desk", which the tool already resolves properly in its later sections
 * (`$deskSigners[OfficeId::Accounting->value]`). A hand-run find/replace across a 2,500-line tool is how a
 * `roomId` that means something else gets rewritten too, so each swap declares how many times it must match
 * and the file is only written when all of them do.
 *
 * Usage: php storage/app/scratch/derive-demo-reference-ids.php [--apply]
 */
$root = dirname(__DIR__, 3);
$path = $root.'/tools/seed_full_demo_pipeline.php';
$apply = in_array('--apply', $argv, true);
$bytes = file_get_contents($path);

$guidance = '$deskSigners[OfficeId::Guidance->value]';
$cashier = '$deskSigners[OfficeId::Accounting->value]';
$idOffice = '$deskSigners[OfficeId::IdOffice->value]';

$swaps = [
    ["'subjectId' => 5, // IT101", "'subjectId' => \$it101SubjectId, // by subject code, not by this database's counter", 1],
    ["'roomId' => 1,", "'roomId' => \$room101Id,", 1],
    ["'religionId' => 1,", "'religionId' => \$religionId,", 1],
    ['global $defaultPassword;', 'global $defaultPassword, $religionId;', 1],
    ["'validatedBy' => 11, // office22_head — the ID Office signer, see \$deskSigners", "'validatedBy' => {$idOffice}, // the ID Office signer, resolved with the other desks", 1],
    ["'processedBy' => 3,", "'processedBy' => {$cashier},", 3],
    ["'evaluatedBy' => 7, // Admission Head", "'evaluatedBy' => \$admissionHeadId, // Admission, by username", 1],
    ["'evaluatedBy' => 7,", "'evaluatedBy' => \$admissionHeadId,", 1],
    ["'evaluatedBy' => 5,", "'evaluatedBy' => {$guidance},", 14],
    ["'feeTypeId' => 1,", "'feeTypeId' => \$feeTuitionId,", 2],
    ["'feeTypeId' => 2,", "'feeTypeId' => \$feeMiscellaneousId,", 2],
    ["'feeTypeId' => 3,", "'feeTypeId' => \$feeLaboratoryId,", 2],
    ["'feeTypeId' => 4,", "'feeTypeId' => \$feeLibraryId,", 2],
];

$oldMap = <<<'BLOCK'
$deskSigners = [
    OfficeId::Guidance->value => 5,       // office4_head  — Department Evaluation
    OfficeId::Scholarship->value => 4,    // office3_head  — Assessment
    OfficeId::Accounting->value => 3,     // office2_head  — Cashier
    OfficeId::Registrar->value => 2,      // office1_head  — Registrar
    OfficeId::Blocking->value => 6,       // office5_head  — Blocking & Scheduling
    OfficeId::Clinic->value => 10,        // office11_head — Clinic
    OfficeId::IdOffice->value => 11,      // office22_head — ID Office
];
BLOCK;

$newMap = "// \$deskSigners now resolves with the other reference lookups above, where each desk is named by office\n"
    ."// rather than by the user id this machine happened to hand out.";

$fails = 0;

foreach ($swaps as [$from, $to, $expect]) {
    $n = substr_count($bytes, $from);
    if ($n !== $expect) {
        fwrite(STDERR, sprintf("REFUSE: %d matches for [%s], expected %d\n", $n, substr($from, 0, 46), $expect));
        $fails++;

        continue;
    }
    $bytes = str_replace($from, $to, $bytes);
    printf("swap ok  %s  (%dx)\n", substr($from, 0, 46), $expect);
}

$n = substr_count($bytes, $oldMap);
if ($n !== 1) {
    fwrite(STDERR, sprintf("REFUSE: the hand-kept desk map matched %d times, expected 1\n", $n));
    $fails++;
} else {
    $bytes = str_replace($oldMap, $newMap, $bytes);
    echo "swap ok  the hand-kept \$deskSigners literal map replaced\n";
}

$after = [
    'no evaluatedBy literal survives' => substr_count($bytes, "'evaluatedBy' => 5,") === 0
        && substr_count($bytes, "'evaluatedBy' => 7") === 0,
    'no processedBy literal survives' => substr_count($bytes, "'processedBy' => 3,") === 0,
    'no feeTypeId literal survives' => preg_match('/\'feeTypeId\' => \d,/', $bytes) === 0,
    'the room and subject literals are gone' => substr_count($bytes, "'roomId' => 1,") === 0
        && substr_count($bytes, "'subjectId' => 5") === 0
        && substr_count($bytes, "'religionId' => 1,") === 0,
    'the lookups are defined once' => substr_count($bytes, '$namedId = function') === 1
        && substr_count($bytes, '$deskSigners[$deskOffice->value] =') === 1,
    'the other two roomId sites were not touched' => substr_count($bytes, "'roomId' => \$roomId,") === 2,
];

foreach ($after as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

echo $fails === 0 ? "\nall swaps and guards ok\n" : "\n{$fails} problem(s)\n";
if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

file_put_contents($path, $bytes);
echo "WROTE {$path}\n";
