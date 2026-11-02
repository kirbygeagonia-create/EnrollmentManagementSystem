<?php

/**
 * Proves `lane193-repoint-md.php` reads its own guards, on a fixture tree it builds and destroys.
 *
 * Why a fixture rather than the real documents: the apply script is a one-shot whose needles its own successful
 * run consumed. The first version of this harness legged against the live tree and reported three BADs for that
 * reason alone — the pointers it expected (`EnrollmentReadiness.php:61-63` twice, `RegistrarController.php:211-223`
 * once) had already been re-pointed, so the tool refused a correct no-op and the legs read a working guard as a
 * broken one. That is the "a leg that cannot fail is the instrument's age" finding arriving as "a leg that can
 * only fail is the instrument's age too": a one-shot mutation tool can only be falsified against bytes that still
 * hold the defect.
 *
 * Nothing outside storage/app/scratch/lane193-fixture is written, and the directory is removed at the end.
 *
 * Usage: php storage/app/scratch/mutate-lane193-repoint-md.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane193-repoint-md.php';
$fx = 'storage/app/scratch/lane193-fixture';
if (! is_file($php) || ! is_file($tool)) {
    fwrite(STDERR, "REFUSED: the interpreter or the apply script is missing.\n");
    exit(1);
}

$bad = 0;
$report = static function (bool $ok, string $leg, string $detail) use (&$bad): void {
    printf("%s %-32s %s\n", $ok ? 'ok  ' : 'BAD ', $leg, $detail);
    if (! $ok) {
        $bad++;
    }
};

/** The fixture documents, each carrying the needles the tool expects at exactly the counts it expects. */
$build = static function () use ($fx): void {
    $files = [
        $fx.'/AGENTS.md' => "gate cites EnrollmentReadiness.php:61-63 once and\nEnrollmentReadiness.php:61-63 again; plus RegistrarController.php:211-223.\n",
        $fx.'/Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md' => "the approve act sits at RegistrarController.php:321 today\n",
        $fx.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md' => "refusal text at RegistrarController.php:304-326\n",
        $fx.'/Documentation/REQUIREMENTS-TRACEABILITY-ADS.md' => "rows at RegistrarController.php:97-106, the write at RegistrarController.php:321,"
            ." the drop at RegistrarController.php:368, clearance at EnrollmentReadiness.php:65 and the pin at EnrollmentReadiness.php:133\n",
        $fx.'/Documentation/TESTING-AND-VERIFICATION-ADS.md' => "drop covered at RegistrarController.php:368\n",
    ];
    foreach ($files as $path => $text) {
        @mkdir(dirname($path), 0777, true);
        if (file_put_contents($path, $text) === false) {
            throw new RuntimeException("the fixture {$path} could not be written");
        }
    }
};

$run = static function (string $script, string $args = '') use ($php, $fx): array {
    $out = (string) shell_exec(escapeshellarg($php).' '.escapeshellarg($script).' '.$args.' --fixture='.$fx.' 2>&1');

    return ['text' => $out, 'refused' => str_contains($out, 'REFUSED'), 'dry' => str_contains($out, 'DRY RUN')];
};

$real = (string) file_get_contents($tool);
$copies = [];
$mutate = static function (string $name, string $from, string $to) use ($real, $tool, &$copies): string {
    if ($from === $to || ! str_contains($real, $from)) {
        throw new RuntimeException("leg {$name}: its anchor is missing from the tool, so the leg cannot fire");
    }
    $path = dirname($tool).'/'.$name;
    if (file_put_contents($path, str_replace($from, $to, $real)) === false) {
        throw new RuntimeException("leg {$name}: the copy could not be written");
    }
    $copies[] = $path;

    return $path;
};

$build();

// 1 — the clean dry run must pass validation and write nothing (a population check: an unread fixture would let
// every later refusal look like the tool working).
$r = $run($tool);
$untouched = str_contains((string) file_get_contents($fx.'/AGENTS.md'), 'EnrollmentReadiness.php:61-63');
$report($r['dry'] && ! $r['refused'] && $untouched, 'dry-run-validates-and-writes', $r['dry'] && ! $r['refused'] && $untouched ? 'planned the ten edits, changed no bytes' : trim(substr($r['text'], 0, 180)));

// 2 — the apply path works, so every refusal leg below is a guard and not a tool that never functions.
$r = $run($tool, '--apply');
$applied = str_contains((string) file_get_contents($fx.'/AGENTS.md'), 'EnrollmentReadiness.php:62-64')
    && substr_count((string) file_get_contents($fx.'/Documentation/REQUIREMENTS-TRACEABILITY-ADS.md'), 'RegistrarController.php:106-115') === 1;
$report(str_contains($r['text'], 'written.') && $applied, 'apply-reaches-the-bytes', $applied ? 'all five fixture files moved as planned' : trim(substr($r['text'], 0, 180)));

// 3 — a wrong expected count refuses, and NOTHING is written: the bad item lives in the last file, so a tool that
// validated per-file while writing would have left the first four moved. That is the half-applied register defect.
$build();
$c = $mutate('lane193-copy-count.php', "['RegistrarController.php:368', 'RegistrarController.php:377', 1],\n        ['EnrollmentReadiness.php:65'", "['RegistrarController.php:368', 'RegistrarController.php:377', 4],\n        ['EnrollmentReadiness.php:65'");
$r = $run($c);
$firstUntouched = str_contains((string) file_get_contents($fx.'/AGENTS.md'), 'EnrollmentReadiness.php:61-63');
$report($r['refused'] && str_contains($r['text'], 'occurs 1 time(s), expected 4') && $firstUntouched, 'count-mismatch-writes-nothing',
    $r['refused'] && $firstUntouched ? 'refused and left the earlier files alone' : trim(substr($r['text'], 0, 180)));

// 4 — an absent needle refuses by name.
$build();
$c = $mutate('lane193-copy-absent.php', "['RegistrarController.php:321', 'RegistrarController.php:330', 1],\n    ],\n    'Documentation/Prompt", "['RegistrarController.php:812-900', 'RegistrarController.php:821-909', 1],\n    ],\n    'Documentation/Prompt");
$r = $run($c);
$report($r['refused'] && str_contains($r['text'], 'occurs 0 time(s), expected 1'), 'absent-needle-refuses', $r['refused'] ? 'named the needle and its real count' : trim(substr($r['text'], 0, 180)));

// 5 — a needle whose replacement is identical is refused, not reported as written. Both count guards pass by
// construction on such a pair, which is exactly why the identity check exists.
$build();
$c = $mutate('lane193-copy-noop.php', "['RegistrarController.php:321', 'RegistrarController.php:330', 1],\n    ],\n    'Documentation/Prompt", "['RegistrarController.php:321', 'RegistrarController.php:321', 1],\n    ],\n    'Documentation/Prompt");
$r = $run($c);
$noopSlipped = str_contains($r['text'], 'written.') && ! $r['refused'];
$report(! $noopSlipped && str_contains($r['text'], 'a no-op is not a re-point'), 'no-op-pair-refused', $noopSlipped ? 'GAP: accepted and reported as written' : 'refused by name');

// 6 — a flag the tool does not read is refused by name (an accepted-and-inert option makes a scoped run print the
// verdict a whole one does).
$r = (function () use ($php, $tool) {
    $out = (string) shell_exec(escapeshellarg($php).' '.escapeshellarg($tool).' '.escapeshellarg('--no-such-flag').' 2>&1');

    return ['text' => $out, 'refused' => str_contains($out, 'does not understand')];
})();
$report($r['refused'], 'unknown-flag-refused', $r['refused'] ? 'refused by name' : trim(substr($r['text'], 0, 180)));

// Teardown: copies and the fixture tree both have to be gone, or a plant outlives the run that made it.
foreach ($copies as $path) {
    @unlink($path);
}
$leftCopies = glob($root.'/storage/app/scratch/lane193-copy-*.php') ?: [];
$report($leftCopies === [], 'copies-retired', $leftCopies === [] ? 'none left' : implode(', ', array_map('basename', $leftCopies)));

$walk = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root.'/'.$fx, FilesystemIterator::SKIP_DOTS),
    fn ($f) => true
));
foreach (iterator_to_array($walk, false) as $item) {
    if ($item->isFile()) {
        @unlink((string) $item);
    }
}
@rmdir($root.'/'.$fx.'/Documentation');
@rmdir($root.'/'.$fx);
$report(! is_dir($root.'/'.$fx), 'fixture-retired', is_dir($root.'/'.$fx) ? 'the fixture tree survived' : 'the fixture tree is gone');

printf("\n%s\n", $bad === 0 ? 'ALL KILLED: validate-then-write, count, absence, no-op and flag guards all bite, and the apply path still works.'
    : $bad.' leg(s) BAD — read them as the harness or the tool, never as a clean run.');
exit($bad === 0 ? 0 : 1);
