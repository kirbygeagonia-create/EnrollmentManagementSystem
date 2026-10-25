<?php

/**
 * Lane 71: prove `lane71-model-decls.php` can fail.
 *
 * It just answered 54 OK / 0 problems, and a clean verdict from a checker that has never been seen to complain is the
 * exact shape this repository has been burned by: lane 46's first filter tool "cleared the tree" because it read the
 * wrong construct, lane 50 passed every real call because it matched only the shape it had just fixed, and lane 44's
 * mutant anchored on a generated line ordinal went to sleep the moment the document grew a paragraph. So the proof is
 * not the count — it is that the tool NAMES the defect when one is planted, and goes SILENT when it is repaired.
 *
 * Every leg mutates a COPY of the flat dump through `--dump=`, never the document or the models, and each refusal is
 * recognised by its own message: `judges-nothing`, `missing-dump` and a planted defect all leave the tool non-zero.
 * A needle that cannot land is reported BAD, never passed — a mutant whose target is gone reports the tool's clean
 * output as its own failure.
 *
 *   php storage/app/scratch/mutate-lane71-model-decls.php
 */
$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$tool = $root.'/storage/app/scratch/lane71-model-decls.php';
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$tmp = $root.'/storage/app/scratch/lane71-mutants';
if (! is_dir($tmp)) {
    mkdir($tmp, 0777, true);
}
$fails = 0;
$say = function (string $leg, bool $ok, string $note = '') use (&$fails): void {
    printf("  %-6s %-26s %s\n", $ok ? 'PASS' : 'BAD', $leg, $ok ? $note : ('— '.$note));
    if (! $ok) {
        $fails++;
    }
};
$run = static function (array $args) use ($php, $tool): array {
    $cmd = escapeshellarg($php).' '.escapeshellarg($tool);
    foreach ($args as $a) {
        $cmd .= ' '.escapeshellarg($a);
    }
    $out = [];
    exec($cmd.' 2>&1', $out, $rc);

    return [$rc, implode("\n", $out)];
};

$src = (string) file_get_contents($dump);
// The control first: 54 claims, none refuted, exit 0. Without this every later "it complained" is meaningless.
[$rc, $out] = $run([]);
preg_match('/(\d+) claim\(s\) read = (\d+) judged \(OK (\d+), WRONG-PK (\d+), WRONG-TIMESTAMPS (\d+)/', $out, $c);
$control = ['rc' => $rc, 'read' => (int) ($c[1] ?? -1), 'ok' => (int) ($c[3] ?? -1)];
$say('control', $rc === 0 && $control['read'] > 0 && $control['read'] === $control['ok'],
    sprintf('%d claims, all OK, exit 0', $control['read']));

$plant = static function (string $name, string $from, string $to, int $want) use ($src, $tmp, $run, $say, $control): void {
    $n = substr_count($src, $from);
    if ($n < 1) {
        $say($name, false, sprintf('needle occurs %d time(s) — the mutant cannot apply', $n));

        return;
    }
    $path = $tmp.'/'.$name.'.txt';
    file_put_contents($path, str_replace($from, $to, $src));
    [$rc, $out] = $run(['--dump='.$path]);
    preg_match('/(\d+) claim\(s\) read = (\d+) judged \(OK (\d+), WRONG-PK (\d+), WRONG-TIMESTAMPS (\d+)/', $out, $m);
    $read = (int) ($m[1] ?? -1);
    $ok = (int) ($m[3] ?? -1);
    $complaints = (int) ($m[4] ?? 0) + (int) ($m[5] ?? 0);
    $named = preg_match('/WRONG-(?:PK|TIMESTAMPS)\s+line/', $out) === 1;
    $say($name, $rc === 1 && $named && $complaints === $want && $read === $control['read'] && $ok === $control['ok'] - $want,
        sprintf('read %d (control %d), OK %d, %d complaint(s), exit %d', $read, $control['read'], $ok, $complaints, $rc));
};

// (1) a primary key the class does not declare; (2) a timestamps flag it does not declare. Both needles are chosen
// to hit ONE sentence: `$timestamps = true` occurs ten times in the dump, so a bare-needle plant reports ten
// complaints and proves nothing about the one claim it was aimed at.
$plant('wrong-primary-key', "\$primaryKey = 'studentId'", "\$primaryKey = 'studentNumber'", 1);
$plant('wrong-timestamps', "\$primaryKey = 'religionId', \$timestamps = false", "\$primaryKey = 'religionId', \$timestamps = true", 1);
// (3) the named file is not in the repository — the one verdict the tool can answer without reading a class.
$m = null;
foreach (explode("\n", $src) as $ln) {
    if (str_contains((string) $ln, 'app/Models/Students.php')) {
        $m = $ln;

        break;
    }
}
if ($m === null) {
    $say('missing-file', false, 'no §24 row cites app/Models/Students.php — the case has no target');
} else {
    $path = $tmp.'/missing-file.txt';
    file_put_contents($path, str_replace('app/Models/Students.php', 'app/Models/NotARealModel.php', $src));
    [, $out] = $run(['--dump='.$path]);
    $say('missing-file', str_contains((string) $out, 'NO-FILE') && preg_match('/NO-FILE 1/', (string) $out) === 1,
        'the tool says the path is not in the repository');
}
// (4) a byte-identical copy must move no verdict — this is what proves --dump= is read and not merely accepted.
$same = $tmp.'/identical.txt';
file_put_contents($same, $src);
[, $out] = $run(['--dump='.$same]);
preg_match('/(\d+) claim\(s\) read = \d+ judged \(OK (\d+)/', (string) $out, $d);
$say('byte-identical', (int) ($d[1] ?? -1) === $control['read'] && (int) ($d[2] ?? -1) === $control['ok'],
    sprintf('read %s, OK %s — same as the control', (string) ($d[1] ?? '?'), (string) ($d[2] ?? '?')));
// (5) a dump with the shape removed must refuse, not report a clean §24.
$stripped = (string) preg_replace('/Model App\\\\Models/u', 'Klass App\\Models', $src);
$gone = $tmp.'/no-shape.txt';
file_put_contents($gone, $stripped);
[$rc, $out] = $run(['--dump='.$gone]);
$say('judges-nothing', $rc === 1 && str_contains((string) $out, 'REFUSED: 0 model declarations judged'),
    'refused with its own message, exit '.$rc);
// (6) a missing dump must refuse too, told apart from (5) by its message.
[$rc, $out] = $run(['--dump='.$tmp.'/does-not-exist.txt']);
$say('missing-dump', $rc === 1 && str_contains((string) $out, 'no dump at'), 'refused with its own message');
// (7) an unknown flag must be refused, not accepted-and-ignored.
[, $out] = $run(['--wat']);
$say('unknown-flag', str_contains((string) $out, 'unknown argument'), 'it refuses rather than running narrower');

printf("\n%s\n", $fails === 0 ? 'every leg can fail, and the control is green' : $fails.' leg(s) BAD — the checker is not proven');
exit($fails === 0 ? 0 : 1);
