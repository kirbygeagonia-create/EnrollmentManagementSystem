<?php
/**
 * Plant, then remove, one mutant in the walkthrough's religion lookup.
 *
 * The point being proven is not the fix but the *message*: with `$this->withoutExceptionHandling()` a desk
 * that dies on a foreign key must report the constraint, whereas before it reported Laravel's own
 * response-context helper (`Call to a member function all() on array`). `plant` writes the mutant, `revert`
 * restores the lookup from the byte-exact copy, and each asserts the match count it expected.
 */
$mode = $argv[1] ?? 'plant';
$file = 'tests/Feature/E2E/EnrollmentWalkthroughTest.php';
$copy = 'storage/app/scratch/E2E-noexh.php';

$real = "\$this->religionId = (int) DB::connection('mysql')->table('religions')->min('religionId');";
$mutant = '$this->religionId = 999999; // MUTANT: a religion that no database holds';

if ($mode === 'plant') {
    $s = file_get_contents($file);
    if (substr_count($s, $real) !== 1) {
        fwrite(STDERR, "ABORT: the lookup is not found exactly once.\n");
        exit(1);
    }
    file_put_contents($file, str_replace($real, $mutant, $s));
    echo "mutant planted\n";
    exit(0);
}

if ($mode === 'revert') {
    $s = file_get_contents($file);
    if (substr_count($s, $mutant) !== 1) {
        fwrite(STDERR, "ABORT: the mutant is not found exactly once — refusing to guess the file's state.\n");
        exit(1);
    }
    file_put_contents($file, str_replace($mutant, $real, $s));
    $same = file_get_contents($file) === file_get_contents($copy);
    echo $same ? "reverted, byte-identical to the pre-mutant copy\n" : "reverted, but NOT identical to the copy — inspect\n";
    exit($same ? 0 : 1);
}

fwrite(STDERR, "usage: php mutate-walkthrough-religion.php plant|revert\n");
exit(1);
