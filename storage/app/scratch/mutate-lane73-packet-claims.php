<?php

/**
 * Prove that lane73-packet-claims.php can fail, and that each claim family it prints is load-bearing.
 *
 * The guard exists so that a department clearing a board flag, or a Registrar editing a requirement line, turns
 * the packet red instead of leaving an officer ticking against paper the build no longer agrees with. A guard that
 * cannot refuse is decoration, so every family gets its own planted defect on a COPY of the packet through
 * --packet=, and a leg whose needle matches nothing reports BAD rather than passing (lane 44's sleeping mutant).
 *
 *   php storage/app/scratch/mutate-lane73-packet-claims.php
 */
$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$php = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$tool = $scratch.'/lane73-packet-claims.php';
$packetSrc = $root.'/Documentation/Registrar-Window-Packet-2026-10-26.md';
$tmp = $scratch.'/tmp-lane73';
@mkdir($tmp);
$fails = 0;

$run = static function (string $args) use ($php, $tool, $root): string {
    return (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($tool).' '.$args.' 2>&1');
};
$check = static function (string $name, bool $ok, string $detail) use (&$fails): void {
    printf("  %s %s%s\n", $ok ? 'PASS' : 'BAD ', $name, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $fails++;
    }
};
$staleOf = static function (string $out, string $kind): int {
    return preg_match_all('/^\s+STALE\s+'.preg_quote($kind, '/').'/m', $out) ?: 0;
};
$claimCount = static function (string $out): ?int {
    return preg_match('/on all (\d+) claim/', $out, $m) === 1 ? (int) $m[1] : null;
};

$packet = (string) file_get_contents($packetSrc);
if ($packet === '') {
    exit("REFUSED: the packet is empty, so every leg below would report the guard's silence as a pass.\n");
}

// 1 — control.
$out = $run('');
$claims = $claimCount($out);
$check('control', $claims !== null && $claims >= 20 && ! str_contains($out, 'VERDICT: 1 claim'), 'claims bound: '.var_export($claims, true));
$control = $out;
if ($fails > 0) {
    echo "\nREFUSED: the control is not clean, so a red leg would prove nothing about its mutant.\n";
    exit(1);
}

$plants = [
    'COURSE-FLAG' => ['**16 courses; 5 carry `requiresCourseSpecificExam`', '**17 courses; 5 carry `requiresCourseSpecificExam`'],
    'BOARD-ROW' => ['| BSA | BS Agriculture | yes | no |', '| BSA | BS Agriculture | yes | yes |'],
    'OFFICE-LINE' => ['Medical and dental examination on file |', 'Medical examination on file only |'],
    'FEE' => ['Clearance Slip Replacement, ₱100.00, flat', 'Clearance Slip Replacement, ₱150.00, flat'],
    'COLUMN-LIST' => ['`feeTypeId, feeName, defaultAmount, unitBasis`', '`feeTypeId, feeName, amount, unitBasis`'],
];
foreach ($plants as $kind => [$needle, $replacement]) {
    $copies = substr_count($packet, $needle);
    if ($copies !== 1) {
        $check("plant-$kind needle is unique", false, "the needle occurs $copies time(s), so this leg cannot isolate the claim");

        continue;
    }
    file_put_contents($tmp.'/'.strtolower($kind).'.md', str_replace($needle, $replacement, $packet));
    $out = $run('--packet=storage/app/scratch/tmp-lane73/'.strtolower($kind).'.md');
    $check("plant-$kind raises STALE $kind", $staleOf($out, $kind) >= 1
        && str_contains($out, 're-issue the packet')
        && ($kind !== 'COLUMN-LIST' || str_contains($out, 'feetypes packet')),// the stale line must name the table the list belongs to
        'STALE/'.$kind.' '.$staleOf($out, $kind));
    // The other families must keep answering, or one planted defect would silence the whole run.
    $check("plant-$kind leaves the other claims standing", $staleOf($out, $kind) < 5, 'still judging the rest');
}

// the packet's own prose must not be able to fake a pass: strike every claim out.
$none = (string) preg_replace('/\d+\s+(?:courses|carry)/', 'zzz carry', $packet);
$none = (string) preg_replace('/^\|\s*\d+\s+\w[\w ]*\|\s*[^|]+\|\s*$/m', '', $none);
file_put_contents($tmp.'/none.md', $none);
$out = $run('--packet=storage/app/scratch/tmp-lane73/none.md');
$check('a packet the guard cannot read refuses instead of judging', str_contains($out, 'REFUSED'),
    trim(substr((string) strtok($out, "\n"), 0, 60)));

// byte-identical copy answers exactly like the control.
file_put_contents($tmp.'/copy.md', $packet);
$out = $run('--packet=storage/app/scratch/tmp-lane73/copy.md');
$check('byte-identical copy answers like the control', $out === $control, $out === $control ? '' : 'output differs');

// three refusals, told apart by their own messages.
$out = $run('--packet=storage/app/scratch/tmp-lane73/nope.md');
$check('missing packet refuses by name', str_contains($out, 'REFUSED: no packet at'), trim(substr((string) strtok($out, "\n"), 0, 60)));
$out = $run('--ci');
$check('an unknown flag refuses rather than doing nothing', str_contains($out, 'is not a flag this tool reads'),
    trim(substr((string) strtok($out, "\n"), 0, 60)));

printf("\n%s\n", $fails === 0 ? 'every claim family can fail, and the refusals are distinguishable'
    : "$fails leg(s) BAD");
foreach (glob($tmp.'/*.md') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmp);
exit($fails === 0 ? 0 : 1);
