<?php
/**
 * Lane 69: prove lane62-print-paper-probe.php's new blindness guard fires.
 *
 * The probe used to build the level token by hand (`'Year '.$enrollment->yearLevel`), so the day the
 * papers became ordinals it stopped matching anything and printed a column of NOs across all eight
 * documents — a table that reads exactly like a finding. The probe now refuses when a needle it searched
 * for appears in no document. This harness plants the old spelling in a COPY and requires that refusal,
 * then proves the untouched probe is green, because a guard nobody has watched fire is a guard assumed.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = $root.'/storage/app/scratch/lane62-print-paper-probe.php';
$copy = $root.'/storage/app/scratch/lane69-probe-blind-copy.php';

$live = (string) file_get_contents($src);
$needle = "\$level = \$enrollment === null ? '' : (string) App\\Support\\YearLevel::format((int) \$enrollment->yearLevel);";
$defect = "\$level = \$enrollment === null ? '' : 'Year '.\$enrollment->yearLevel;";

if (substr_count($live, $needle) !== 1) {
    printf("REFUSED: the token line matches %d times in the probe — read it before planting.\n", substr_count($live, $needle));
    exit(1);
}

$fails = 0;
try {
    file_put_contents($copy, str_replace($needle, $defect, $live));
    echo "control (the probe as it stands):\n";
    $out = (string) shell_exec('"'.$php.'" '.escapeshellarg($src).' 2>&1');
    $ok = ! str_contains($out, 'REFUSED') && str_contains($out, 'year level');
    echo $ok ? "  ok  green, and reads the level where the paper prints it\n" : "  BAD control did not read clean\n";
    $fails += $ok ? 0 : 1;

    echo "planted (the hand-built token back in a copy):\n";
    $out2 = (string) shell_exec('"'.$php.'" '.escapeshellarg($copy).' 2>&1');
    $refused = str_contains($out2, 'REFUSED') && str_contains($out2, 'year level');
    echo $refused ? "  ok  refused — a blind column can no longer be read as a finding\n"
        : "  BAD the blind probe still printed a table:\n".substr($out2, -400)."\n";
    $fails += $refused ? 0 : 1;

    // The same shape a second time: the program name, which the probe also builds from the record.
    echo "planted (a program name no paper can carry):\n";
    $copy2 = $root.'/storage/app/scratch/lane69-probe-blind-copy2.php';
    file_put_contents($copy2, str_replace(
        "\$program = (string) (optional(\$enrollment?->course)->courseName ?? '');",
        "\$program = 'Bachelor of Nowhere';",
        (string) file_get_contents($copy)
    ));
    $v3 = (string) shell_exec('"'.$php.'" '.escapeshellarg($copy2).' 2>&1');
    @unlink($copy2);
    $refused3 = str_contains($v3, 'REFUSED') && str_contains($v3, 'program');
    echo $refused3 ? "  ok  refused on the program column too\n" : "  BAD it printed a table anyway:\n".substr($v3, -400)."\n";
    $fails += $refused3 ? 0 : 1;
} finally {
    @unlink($copy);
    echo "\ncopy removed; the real probe was never written to.\n";
}

if ($fails > 0) {
    echo "RESULT: ".$fails." leg(s) failed to prove the guard.\n";
    exit(1);
}
echo "RESULT: the blindness guard fires, and the untouched probe is green.\n";
