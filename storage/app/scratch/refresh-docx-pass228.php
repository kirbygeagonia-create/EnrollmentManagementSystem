<?php

/**
 * Pass 228 — the twenty-fourth wrong target: a bare pointer whose nearest preceding name was the wrong file.
 *
 * §2's clearance paragraph reads "… a user whose officeId is Registrar ( ClearancePolicy.php:68-94 ). Ruling 12
 * retired the row in foreign-key order ( :62-104 : its staff, the approvals pointing at its requirement, the
 * requirement, then the office row )". The second number names no file, so every instrument that attributes by
 * distance hung it on `ClearancePolicy.php` — where :62 and :104 are both bare `}` lines. The retirement it
 * describes is `DevReferenceDataSeeder.php:62-104`, and §27's office-8 row cites that span correctly with the
 * file written out, so the document already contained the true owner twice.
 *
 * The fix is the one AGENTS.md prescribes for this shape: **name the file in the sentence** rather than teach the
 * checker to guess. The seeder's own lines are asserted first, including the two brace lines the bare pointer was
 * charged to, so the pass proves the mis-attribution as well as the repair.
 *
 *   php storage/app/scratch/refresh-docx-pass228.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass228.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$seeder = (array) file($root.'/database/seeders/DevReferenceDataSeeder.php');
$policy = (array) file($root.'/app/Policies/ClearancePolicy.php');
$fails = 0;
foreach ([
    [$seeder, 62, 'D-1, ruling 12: retire legacy office 8'],
    [$seeder, 80, "->where('officeId', \$legacyClearanceOffice)"],
    [$seeder, 101, '->delete();'],
    [$seeder, 104, "\$officeRemoved = DB::table('offices')->where('officeId', \$legacyClearanceOffice)->delete();"],
    // what the bare pointer was charged to, by the file named just before it
    [$policy, 62, '}'],
    [$policy, 104, '}'],
] as [$l, $n, $needle]) {
    $t = trim((string) ($l[$n - 1] ?? ''));
    $ok = str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 66));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "REFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needle = '( :62-104 :';
$n = substr_count($xml, $needle);
printf("\n  needle [%s] occurs %d time(s) in document.xml (want 1)\n", $needle, $n);
if ($n !== 1) {
    echo "REFUSED: the needle is not the one copy this pass measured.\n";
    exit(1);
}
if (substr_count($xml, 'DevReferenceDataSeeder.php:62-104') !== 1) {
    echo "BAD: the correct citation is not already in the document once, so the claim of a duplicate owner is wrong.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass228.docx',
    [[$needle, '( DevReferenceDataSeeder.php:62-104 :']], [], $apply);
