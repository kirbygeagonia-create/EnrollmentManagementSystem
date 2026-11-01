<?php

/**
 * Harness for lane147-measure-notice-fields.php — re-cut the day the build landed, and proved in both directions.
 *
 * The first version of this harness asserted that title, action and link were ABSENT, because that is what the tree
 * said when it was written. §28.3 G-35 has since been built, so those legs became BAD for the right reason: the
 * instrument was reporting the new truth. A verifier whose expectation is a remembered reading is indistinguishable
 * from a broken tool, so the legs now go both ways — the census must report every part present, AND each part must
 * be able to go missing again when a copy of the screen is stripped. A census that can only ever print "yes" has
 * stopped checking.
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
    if ($new === $text) {
        fwrite(STDERR, "BAD: the plant of {$name} changed nothing.\n");

        exit(1);
    }
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
    printf("  %s  %-42s %s\n", $ok ? 'ok  ' : 'BAD ', $leg, $detail);
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

// 1 — control: every part is written and read, and the census says so in words.
[$code, $out] = $run('');
$report('control exits 0', $code === 0, "exit {$code}");
foreach (['title', 'action required', 'record reference', 'protected deep link'] as $part) {
    $report($part.' is written and read', str_starts_with($cell($out, $part), 'yes/yes'), $cell($out, $part));
}
$report('timestamp reads from the column', str_contains($cell($out, 'timestamp'), 'column/yes'), $cell($out, 'timestamp'));
$report('no gap is printed, not a count of parts', str_contains($out, 'no gap: every part is written'), '');
$report('the invented fallback is gone', ! str_contains($out, 'INVENTED'), '');

// 2 — put the substituted label back: the accusation must be able to fire again.
$copy = $plant($screen, 'lane147-screen-fallback.jsx', '{n.data?.message ?? ', "{n.data?.message || 'Notification' ?? ", 1);
[$code, $out] = $run('--screen='.$copy);
$report('a restored substitution is caught', str_contains($out, 'INVENTED'), '');

// 3 — remove the title paragraph: the writer/screen pair must split.
$copy = $plant($screen, 'lane147-screen-notitle.jsx', 'n.data?.title', 'n.data?.notATitle', 2);
[$code, $out] = $run('--screen='.$copy);
$report('a dropped title reads as SCREEN no', $cell($out, 'title') === 'yes/NO/n/a', $cell($out, 'title'));

// 4 — remove the deep link the same way.
$copy = $plant($screen, 'lane147-screen-nolink.jsx', 'n.data?.link', 'n.data?.noLink', 2);
[$code, $out] = $run('--screen='.$copy);
$report('a dropped link reads as SCREEN no', str_starts_with($cell($out, 'protected deep link'), 'yes/NO'),
    $cell($out, 'protected deep link'));

// 5 — the two bounds that must refuse rather than report absence.
$copy = $plant($screen, 'lane147-screen-nomarker.jsx', '<div key={n.id}', '<div key={noticeRow.id}', 1);
[$code, $out] = $run('--screen='.$copy);
$report('a missing row marker refuses', $code === 1 && str_contains($out, "'<div key={n.id}'"), "exit {$code}");

$copy = $plant($listeners, 'lane147-listener-nomessage.php', "'message' => \$message,", "'body' => \$message,", 1);
[$code, $out] = $run('--listeners='.$copy);
$report('a missing payload literal refuses', $code === 1 && str_contains($out, 'REFUSED'), "exit {$code}");

// 6 — the rows surface on a schema with no notices refuses; live `ems` answers with history, not a gap.
[$code, $out] = $run('--rows --schema=ems_l148_a');
$report('an empty notifications table refuses', $code === 1 && str_contains($out, 'holds no row'), "exit {$code}");
[$code, $out] = $run('--rows');
$report('stored rows are named as history', $code === 0 && str_contains($out, 'That is history, not a defect'), '');

// 7 — refusals told apart by name.
[$code, $out] = $run('--all');
$report('unknown flag refused by name', $code === 1 && str_contains($out, "'--all'"), "exit {$code}");
[$code, $out] = $run('--screen=resources/js/Pages/Nope.jsx');
$report('a missing screen refuses by name', $code === 1 && str_contains($out, 'Nope.jsx'), "exit {$code}");

// 8 — control again: every plant lived in a throwaway copy.
[$code, $out] = $run('');
$report('trailing control clean', $code === 0 && str_contains($out, 'no gap: every part is written'), "exit {$code}");

printf("\n%s: %d leg(s), %d bad\n", $fail === 0 ? 'ALL PROVABLE' : 'FAILED', $legs, $fail);

exit($fail === 0 ? 0 : 1);
