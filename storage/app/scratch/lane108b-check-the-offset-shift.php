<?php

/**
 * Lane 108b: prove the read-back's offset formula against the bytes the apply actually wrote.
 *
 * The apply shifted each higher literal right by the cumulative delta of the lower edits in the same file, and the
 * read-back forgot that, so it FAILed three correct writes. This recomputes where every narrowed literal must now
 * sit from the census JSON plus the formula, and requires the file to say so — the formula is the thing under test,
 * and a formula checked only against a file that was written by the same misunderstanding proves nothing.
 *
 * It also checks the negative: the pre-apply offset must NOT hold the narrowed text for those three shifted rows,
 * which is what shows the shift is real rather than a story told to excuse a bad check.
 *
 * Usage: php storage/app/scratch/lane108b-check-the-offset-shift.php --census=<json>
 */
$root = dirname(__DIR__, 3);
$censusPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--census=')) {
        $censusPath = substr($arg, strlen('--census='));

        continue;
    }
    fwrite(STDERR, "REFUSED: unknown argument {$arg}; this check reads --census=.\n");

    exit(1);
}
if ($censusPath === null) {
    fwrite(STDERR, "REFUSED: --census=<json> is required.\n");

    exit(1);
}
$rows = json_decode((string) file_get_contents(str_starts_with($censusPath, '/') ? $censusPath : $root.'/'.$censusPath), true);
if (! is_array($rows)) {
    fwrite(STDERR, "REFUSED: the census did not parse.\n");

    exit(1);
}

$cols = 'studentId,schoolIdNumber,lastName,firstName,middleName';
$checked = 0;
$failures = [];
$shiftedSeen = 0;
foreach ($rows as $r) {
    if (($r['kind'] ?? '') !== 'LIST') {
        continue;
    }
    $bytes = (string) file_get_contents($root.'/'.ltrim($r['file'], '/'));
    // Group this site's literals with every other LIST site in the SAME file, because the shift comes from all of
    // them, not only from the ones this row happened to carry.
    $sameFile = [];
    foreach ($rows as $other) {
        if ($other['file'] === $r['file'] && ($other['kind'] ?? '') === 'LIST') {
            foreach ($other['literals'] as $l) {
                $sameFile[] = $l;
            }
        }
    }
    foreach ($r['literals'] as $lit) {
        $to = "'".$lit['path'].':'.$cols."'";
        $delta = strlen($to) - strlen("'".$lit['path']."'");
        $shift = 0;
        foreach ($sameFile as $o) {
            if ($o['at'] < $lit['at']) {
                $shift += (strlen("'".$o['path'].':'.$cols."'") - strlen("'".$o['path']."'"));
            }
        }
        $at = (int) $lit['at'] + $shift;
        $ok = substr($bytes, $at, strlen($to)) === $to;
        if (! $ok) {
            $failures[] = sprintf('%s %s() planned @%d + shift %d = @%d: expected %s, found %s',
                $r['file'], $r['action'], $lit['at'], $shift, $at, $to, var_export(substr($bytes, $at, strlen($to)), true));
        }
        // The negative control: for a row that really moved, the OLD offset must not hold the new text.
        if ($shift > 0) {
            $shiftedSeen++;
            if (substr($bytes, (int) $lit['at'], strlen($to)) === $to) {
                $failures[] = sprintf('%s @%d holds the narrowed text at the PLANNED offset although a lower edit'
                    .' should have shifted it by %d — the shift is being invented, not measured', $r['file'], $lit['at'], $shift);
            }
        }
        $checked++;
    }
}

if ($checked === 0) {
    fwrite(STDERR, "REFUSED: the census carried no LIST row, so there was nothing to check. After a successful"
        ." apply that is the expected state — this script must be run against the census that sized the write.\n");

    exit(1);
}
foreach ($failures as $f) {
    echo '  FAIL '.$f."\n";
}
printf("%d literal(s) checked, %d of them shifted by a lower edit in the same file, %d failure(s).\n",
    $checked, $shiftedSeen, count($failures));
if ($failures !== []) {
    exit(1);
}
echo "VERIFIED: every narrowed literal sits where the plan plus the measured shift says it must.\n";
