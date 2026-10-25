<?php

/**
 * Prove lane 71's morph-target probe can fail.
 *
 * The probe reads one value from three surfaces — what the document asserts, what the code passes, what the rows
 * hold — and refuses rather than answering when any surface is unreadable. Each leg here breaks one surface in a
 * throwaway copy and requires the named complaint, and every leg that must refuse is told apart by its own message,
 * because all the refusals exit 1.
 *
 *   php storage/app/scratch/mutate-lane71-morph-targets.php
 */
$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$php = escapeshellarg(PHP_BINARY);
$tool = $scratch.'/lane71-morph-targets.php';
$dumpSrc = $scratch.'/docx-flat.txt';
$tmp = $scratch.'/tmp-lane71-morph';
@mkdir($tmp);
$fails = 0;

$run = static function (string $args) use ($php, $tool, $root): string {
    // An exit code cannot be read back through cmd.exe (`$?` is not its variable and a chained `%ERRORLEVEL%`
    // expands before the command runs), and the tool exits 1 for several different reasons anyway. Every leg below
    // therefore asserts on the message, which is the only thing that separates the refusals.
    return (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($tool).$args.' 2>&1');
};

$check = static function (string $name, bool $ok, string $detail) use (&$fails): void {
    printf("  %s %s%s\n", $ok ? 'PASS' : 'BAD ', $name, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $fails++;
    }
};

$dump = (string) file_get_contents($dumpSrc);
if (substr_count($dump, 'Staffusers::class') < 3) {
    exit("REFUSED: the dump carries fewer than three `Staffusers::class` claims, so nothing here can be planted.\n");
}

// 1 — the control, on the real dump.
$out = $run('');
$check('control', str_contains($out, 'AGREE') && str_contains($out, 'the document, the code and the rows answer the same question'), '');
$agreeLine = $out;

// 2 — one document claim re-spelled onto a class no code writes and no row holds.
$planted = preg_replace('/notifiable_type = Staffusers::class/u', 'notifiable_type = Students::class', $dump, 1);
if ($planted === $dump) {
    exit("REFUSED: the planted needle matched nothing, so this leg would report the control's answer as a pass.\n");
}
file_put_contents($tmp.'/planted.txt', $planted);
$out = $run(' --dump='.escapeshellarg($tmp.'/planted.txt'));
$check('planted-document-class', str_contains($out, 'DISAGREE')
    && str_contains($out, 'App\\Models\\Students') && str_contains($out, 'named by no code')
    && str_contains($out, 'a surface contradicts the others'),
    'DISAGREE '.substr_count($out, 'DISAGREE'));
$check('planted-document-class leaves the honest claim standing', str_contains($out, 'AGREE      App\\Models\\Staffusers'),
    'seven of the eight sentences still name the right class');

// 3 — a dump with nothing to judge must refuse, not report agreement.
$none = preg_replace('/notifiable_type[^\n]{0,60}/u', 'the morph column', $dump);
file_put_contents($tmp.'/none.txt', $none);
$out = $run(' --dump='.escapeshellarg($tmp.'/none.txt'));
$check('judges-nothing refuses', str_contains($out, 'judged nothing') && ! str_contains($out, 'VERDICT: the document, the code'), 'exit not readable; the message is the fact');

// 4 — a truncated or missing dump must refuse.
file_put_contents($tmp.'/short.txt', "ROW 1: nothing\n");
$out = $run(' --dump='.escapeshellarg($tmp.'/short.txt'));
$check('missing-dump refuses', str_contains($out, 'REFUSED: the dump'), '');

// 5 — a schema the probe was not told to read is a refusal, and the message names both.
$out = $run(' --allow-schema=ems_no_such');
$check('unknown-schema refuses', str_contains($out, 'REFUSED') && str_contains($out, 'ems_no_such'), '');

// 6 — a byte-identical copy must move no verdict: this is what proves --dump reaches the walk rather than being
// accepted and ignored.
file_put_contents($tmp.'/same.txt', $dump);
$out = $run(' --dump='.escapeshellarg($tmp.'/same.txt'));
$check('byte-identical copy answers like the control', preg_replace('/\r/', '', $out) === preg_replace('/\r/', '', $agreeLine), '');

// 7 — the two refusals above share exit 1, so a leg that accepts either passes on a broken instrument.
$out = $run(' --dump='.escapeshellarg($tmp.'/none.txt'));
$check('judges-nothing is told from missing-dump by its message',
    str_contains($out, 'judged nothing') && ! str_contains($out, 'REFUSED: the dump'), '');

// 8 — `--ci` must CHANGE what is judged: it reads no database, so it names the third surface instead of asking it.
// A flag that is accepted and inert is the defect this repository has been bitten by three times (lane 46's page
// scope, lane 44's `--dir=`, lane 70's `--all`), so the leg asserts the shape of the --ci answer, not merely that it
// exits 0 — and leg 9 then proves the flag did not turn the gate into a run that judges nothing.
$out = $run(' --ci');
$check('ci names the rows surface instead of reading it',
    str_contains($out, 'rows surface: NOT JUDGED') && str_contains($out, 'rows not read (--ci)')
    && str_contains($out, 'the document and the code answer the same question'),
    str_contains($out, 'NOT JUDGED') ? 'two surfaces judged' : 'no --ci verdict line at all');
$check('ci quotes no schema it never opened', ! str_contains($out, 'morph group(s) in'), '');

// 9 — the planted wrong class must still be refused under --ci, which is what makes the CI step a gate rather than a
// banner: the document surface is still weighed when the rows are not.
$out = $run(' --ci --dump='.escapeshellarg($tmp.'/planted.txt'));
$check('ci still refuses a document claim the code disproves',
    str_contains($out, 'DISAGREE') && str_contains($out, 'named by no code')
    && str_contains($out, 'a surface contradicts the others'),
    'DISAGREE '.substr_count($out, 'DISAGREE'));

// 10 — and the flag pair that cannot be honoured refuses for its own reason, told from the unknown-schema refusal
// even though both exit 1.
$out = $run(' --ci --allow-schema=ems_test');
$check('ci refuses an --allow-schema it has no database to apply',
    str_contains($out, 'accepted and inert') && ! str_contains($out, 'the environment answers'), '');

// 13 — an unknown flag must refuse rather than run. `--selftest` sat in the tool promising to plant a wrong class in a
// copy of the dump while doing nothing but printing a note about it, so a caller that believed it had measured a
// planted defect had measured the untouched document — the accepted-and-inert flag this lane keeps removing.
$out = $run(' --selftest');
$check('an unknown flag refuses by name and answers nothing',
    str_contains($out, 'is not a flag this tool reads') && str_contains($out, '--selftest')
    && ! str_contains($out, 'VERDICT:'), substr(str_replace("\n", ' ', $out), 0, 60));

array_map('unlink', glob($tmp.'/*'));
@rmdir($tmp);
printf("\n%d leg(s) failed.\n", $fails);
exit($fails > 0 ? 1 : 0);
