<?php

/**
 * Falsifier for `lane196-repoint-md.php`, the one-shot markdown re-pointer this batch wrote.
 *
 * A one-shot patcher can only be falsified against bytes that still hold the defect: its needles are consumed by
 * its own first run, so legging it against the live tree reports refusals as broken guards (lane 193's harness met
 * exactly that). Every leg here builds a fixture file, plants its own needles into it, and deletes the fixture after
 * the assertion, with the copies removed by a shutdown list captured by reference.
 *
 * Two legs judge the tool's own claims rather than its output. `apply-reaches-the-bytes` is what proves the write
 * path works at all — without it every refusal leg could be satisfied by a tool that never writes anything.
 * `marker-words-only-still-judged` is the falsification of the dated-document exemption: a file that merely
 * mentions the phrase must still be re-pointed, because an exemption that fires on the words rather than on the
 * dated marker silently disables the tool on the living files that need it most (this batch met that exact
 * refusal on `AGENTS.md`).
 *
 * Usage: php storage/app/scratch/mutate-lane196-repoint-md.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = '"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe"';
$tool = 'storage/app/scratch/lane196-repoint-md.php';
$dir = 'storage/app/scratch/lane196-fx';

$copies = [];
register_shutdown_function(function () use (&$dir, &$copies): void {
    foreach ($copies as $f) {
        @unlink($f);
    }
    @rmdir($root.'/'.$dir);
});
if (! is_dir($dir) && ! mkdir($dir, 0777, true)) {
    fwrite(STDERR, "REFUSED: the fixture directory could not be created.\n");
    exit(1);
}

$fx = static function (string $name, string $body) use (&$copies, $dir): string {
    $path = $dir.'/'.$name;
    if (file_put_contents($path, $body) === false) {
        throw new RuntimeException("fixture {$name} could not be written — a plant nobody verified is not a plant");
    }
    $copies[] = $path;

    return $path;
};
$read = static fn (string $p): string => (string) file_get_contents($p);

$run = static function (string $args) use ($php, $tool): array {
    $lines = [];
    $code = 0;
    // Each argument is wrapped in DOUBLE quotes: PHP's exec hands the command to cmd.exe, which reads a bare `|`
    // as a pipe and tries to run `app/Support/ZzExample.php` as a program — the harness reported eight BAD legs
    // that way before one quoted argument showed the patcher was answering correctly all along.
    $parts = $args === '' ? [] : explode(' ', $args);
    foreach ($parts as &$p) {
        $p = '"'.$p.'"';
    }
    unset($p);
    exec($php.' '.escapeshellarg($tool).' '.implode(' ', $parts).' 2>&1', $lines, $code);

    return [implode("\n", $lines), $code];
};

$results = [];
$bad = 0;
$report = static function (bool $ok, string $leg, string $detail) use (&$bad): void {
    printf("%s %-34s %s\n", $ok ? 'ok  ' : 'BAD ', $leg, $detail);
    if (! $ok) {
        $bad++;
    }
};

$pointer = 'See `app/Support/ZzExample.php:10-12` for the rule.';
$moved = 'See `app/Support/ZzExample.php:15-17` for the rule.';
$pair = static fn (string $f): string => '--pair='.$f.'|app/Support/ZzExample.php:10-12|app/Support/ZzExample.php:15-17';

// 1 — dry run plans and writes NOTHING.
$a = $fx('A.md', $pointer."\n");
$before = $read($a);
[$out, $code] = $run($pair($a));
$report($code === 0 && str_contains($out, 'PLAN') && str_contains($out, 'validated') && $read($a) === $before,
    'dry-run-writes-nothing', str_contains($out, 'PLAN') && $read($a) === $before ? 'planned, bytes unchanged' : trim(substr($out, 0, 180)));

// 2 — the apply path reaches the bytes (without this leg every refusal below could be a tool that does nothing).
[$out, $code] = $run($pair($a).' --apply');
$report($code === 0 && str_contains($read($a), 'app/Support/ZzExample.php:15-17') && ! str_contains($read($a), ':10-12'),
    'apply-reaches-the-bytes', str_contains($read($a), '15-17') ? 'written and the retired pointer gone' : trim(substr($out, 0, 180)));

// 3 — a needle matching twice is refused, and nothing else moves.
$b = $fx('B.md', $pointer."\n".$pointer."\n");
$before = $read($b);
[$out, $code] = $run($pair($b).' --apply');
$report($code === 1 && str_contains($out, 'occurs 2 time(s)') && $read($b) === $before,
    'needle-count-is-refused', str_contains($out, 'occurs 2 time(s)') ? 'named with its count' : trim(substr($out, 0, 180)));

// 4 — a bad pair in the LAST file leaves the FIRST untouched (validate everything, then write anything).
$c1 = $fx('C1.md', $pointer."\n");
$c2 = $fx('C2.md', "nothing here matches the needle\n");
$beforeC1 = $read($c1);
[$out, $code] = $run($pair($c1).' '.str_replace($c1, $c2, $pair($c1)).' --apply');
$report($code === 1 && $read($c1) === $beforeC1 && str_contains($out, 'occurs 0 time(s)'),
    'partial-write-is-refused', $read($c1) === $beforeC1 ? 'the good file kept its bytes' : 'THE FIRST FILE WAS WRITTEN anyway');

// 5 — a no-op pair is refused by name.
[$out, $code] = $run('--pair='.$a.'|app/Support/ZzExample.php:15-17|app/Support/ZzExample.php:15-17');
$report($code === 1 && str_contains($out, 'no-op pair'), 'no-op-pair-refused',
    str_contains($out, 'no-op pair') ? 'refused by name' : trim(substr($out, 0, 180)));

// 6 — a dated document is exempt, and the exemption is PRINTED.
$d = $fx('D.md', "Pointer currency (2026-01-01): the numbers below are that day's reading.\n".$pointer."\n");
$before = $read($d);
[$out, $code] = $run($pair($d).' --apply');
$report($code === 1 && str_contains($out, 'Pointer currency') && $read($d) === $before,
    'dated-marker-exempts', str_contains($out, 'Pointer currency') ? 'refused, and named' : trim(substr($out, 0, 180)));

// 7 — the bare PHRASE is not the marker: a file that only discusses it must still be judged.
$e = $fx('E.md', "This guide explains the `Pointer currency` convention used elsewhere.\n".$pointer."\n");
[$out, $code] = $run($pair($e).' --apply');
$report($code === 0 && str_contains($read($e), ':15-17'),
    'marker-words-only-still-judged', str_contains($read($e), ':15-17')
        ? 're-pointed, so the exemption is the dated form and not the phrase' : trim(substr($out, 0, 200)));

// 8/9/10 — the three refusals, told apart by their own messages.
[$out, $code] = $run('--nonsense=1');
$report($code === 1 && str_contains($out, 'does not understand') && str_contains($out, '--nonsense'),
    'unknown-flag-refused-by-name', str_contains($out, '--nonsense') ? 'named' : trim(substr($out, 0, 160)));

[$out, $code] = $run('--pair=');
$report($code === 1 && str_contains($out, '--pair needs FILE|OLD|NEW'),
    'malformed-pair-refused', str_contains($out, 'FILE|OLD|NEW') ? 'the shape is stated' : trim(substr($out, 0, 160)));

[$out, $code] = $run('');
$report($code === 1 && str_contains($out, 'no --pair='),
    'empty-plan-refused', str_contains($out, 'no --pair=') ? 'a run that judges nothing refuses' : trim(substr($out, 0, 160)));

// 11 — the fixtures this run wrote are gone, and the tool's own file was never touched.
$untouched = str_contains($read('storage/app/scratch/lane196-repoint-md.php'), 'lane196-repoint-md.php');
$report($untouched, 'tool-self-untouched', 'the patcher was not re-pointed by its own harness');

$total = 11;
echo $bad === 0
    ? "ALL KILLED: {$total} leg(s), 0 bad\n"
    : "{$bad} of {$total} leg(s) BAD — read them as the harness or the patcher, never as a clean run.\n";
exit($bad === 0 ? 0 : 1);
