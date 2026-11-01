<?php

/**
 * Harness for lane147-measure-notice-fields.php — the census must be able to see a field it does not see today.
 *
 * The instrument reports that three of prompt line 444's five parts are absent on every surface. A census that can
 * only ever print "NO" is indistinguishable from a census that reads nothing, so each leg plants the field the
 * register says is missing and requires the table to change, and two legs require the run to REFUSE rather than
 * report absence: a bell whose row marker has been renamed, and a payload literal that no longer exists.
 *
 * Usage: php storage/app/scratch/mutate-lane147-notice-fields.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane147-measure-notice-fields.php';
$listeners = 'app/Listeners/SendWorkflowNotification.php';
$screen = 'resources/js/Layouts/AuthenticatedLayout.jsx';

$copies = [];
register_shutdown_function(static function () use (&$copies, $root): void {
    foreach ($copies as $c) {
        if (file_exists($root.'/'.$c)) {
            unlink($root.'/'.$c);
        }
    }
});

$plant = static function (string $src, string $name, string $from, string $to, int $expect) use ($root, &$copies): string {
    $dst = 'storage/app/scratch/'.$name;
    $text = (string) file_get_contents($root.'/'.$src);
    $got = substr_count($text, $from);
    if ($got !== $expect) {
        fwrite(STDERR, sprintf("BAD: the plant needle occurs %d time(s) in %s, wanted %d.\n", $got, $src, $expect));

        exit(1);
    }
    $new = str_replace($from, $to, $text);
    if (@file_put_contents($root.'/'.$dst, $new) !== strlen($new)) {
        fwrite(STDERR, "BAD: the copy {$name} did not land.\n");

        exit(1);
    }
    $copies[] = $dst;

    return $dst;
};

$run = static function (string $args) use ($root, $php, $tool): array {
    $out = [];
    $code = 0;
    exec($php.' '.$root.'/'.$tool.' '.$args.' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$fail = 0;
$legs = 0;
$report = static function (string $leg, bool $ok, string $detail = '') use (&$fail, &$legs): void {
    $legs++;
    printf("  %s  %-38s %s\n", $ok ? 'ok  ' : 'BAD ', $leg, $detail);
    if (! $ok) {
        $fail++;
    }
};

$cell = static function (string $out, string $label): string {
    if (preg_match('/^'.preg_quote($label, '/').'\s+(\S+)\s+(\S+)\s+(\S+)$/m', $out, $m) === 1) {
        return $m[1].'/'.$m[2].'/'.$m[3];
    }

    return 'missing';
};

// 1 — control, read against the live schema's rows.
[$code, $out] = $run('--rows');
$report('control exits 0', $code === 0, "exit {$code}");
$report('title is absent on both surfaces', $cell($out, 'title') === 'NO/NO/no', $cell($out, 'title'));
$report('record reference is written but not painted', $cell($out, 'record reference') === 'yes/NO/yes',
    $cell($out, 'record reference'));
$report('the invented fallback is reported', str_contains($out, "INVENTED")
    && str_contains($out, "'Notification'"), '');

// 2 — plant a title into a copy of the listener: the census must see it.
$copy = $plant($listeners, 'lane147-listener-title.php', "'message' => \$message,",
    "'title' => 'A signature is waiting',\n                        'message' => \$message,", 1);
[$code, $out] = $run('--listeners='.$copy);
$report('a planted title reads as WRITER yes', $cell($out, 'title') === 'yes/NO/n/a'
    || str_starts_with($cell($out, 'title'), 'yes/'), $cell($out, 'title'));

// 3 — remove the bell's fallback: the INVENTED row must go away, not merely change words.
$copy = $plant($screen, 'lane147-screen-nofallback.jsx', "|| 'Notification'", '', 1);
[$code, $out] = $run('--screen='.$copy);
$report('deleting the fallback stops the accusation', ! str_contains($out, 'INVENTED'), '');

// 4 — rename the row marker: the screen surface must REFUSE rather than report every field unpainted.
$copy = $plant($screen, 'lane147-screen-nomarker.jsx', '<div key={n.id}', '<div key={noticeRow.id}', 1);
[$code, $out] = $run('--screen='.$copy);
$report('a missing row marker refuses', $code === 1 && str_contains($out, "'<div key={n.id}'"), "exit {$code}");

// 5 — rename the payload literal: the writer surface must REFUSE rather than call every field absent.
$copy = $plant($listeners, 'lane147-listener-nomessage.php', "'message' => \$message,", "'body' => \$message,", 1);
[$code, $out] = $run('--listeners='.$copy);
$report('a missing payload literal refuses', $code === 1 && str_contains($out, 'REFUSED'), "exit {$code}");

// 6 — the rows surface on a schema that holds no notification must refuse, not report 'no'.
[$code, $out] = $run('--rows --schema=ems_l148_a');
$report('an empty notifications table refuses', $code === 1 && str_contains($out, 'holds no row'), "exit {$code}");

// 7 — refusals told apart by name.
[$code, $out] = $run('--schema=n0t_a_real_schema');
$report('an unknown schema refuses', $code === 1, "exit {$code}");
[$code, $out] = $run('--all');
$report('unknown flag refused by name', $code === 1 && str_contains($out, "'--all'"), "exit {$code}");
[$code, $out] = $run('--screen=resources/js/Pages/Nope.jsx');
$report('a missing screen refuses by name', $code === 1 && str_contains($out, 'Nope.jsx'), "exit {$code}");

// 8 — control again: every plant lives in a throwaway copy, so the tree is untouched.
[$code, $out] = $run('--rows');
$report('trailing control', $code === 0 && str_contains($out, 'INVENTED'), "exit {$code}");

printf("\n%s: %d leg(s), %d bad\n", $fail === 0 ? 'ALL PROVABLE' : 'FAILED', $legs, $fail);

exit($fail === 0 ? 0 : 1);
