<?php

// Lane 51: where did the moved lines land the document's pointers? For each citation into a file this batch
// changed, accept only a shift under which the cited line is byte-identical in the working copy — the same rule
// lane46-probe-exam-cites.php uses, applied to the four PHP files lane 51 edited.
//
//   php storage/app/scratch/lane51-probe-cite-shift.php

$root = dirname(__DIR__, 3);
$dump = $root.'/storage/app/scratch/docx-flat.txt';

// Every file this batch touched, from git. The HEAD copies live in lane50-head/ with '__' for '/'.
$listFile = $root.'/storage/app/scratch/lane51-changed-files.txt';
$files = array_values(array_filter(explode("\n", trim((string) (is_file($listFile) ? file_get_contents($listFile) : ''))))));

if (! is_file($dump)) {
    fwrite(STDERR, "REFUSE: no flat dump\n");
    exit(1);
}

$text = file_get_contents($dump);
$total = 0;

foreach ($files as $rel) {
    $headPath = $root.'/storage/app/scratch/lane50-head/'.str_replace('/', '__', $rel);

    if (! is_file($headPath)) {
        fwrite(STDERR, "REFUSE: no exported HEAD copy for $rel\n");
        exit(1);
    }

    $head = explode("\n", str_replace("\r\n", "\n", file_get_contents($headPath)));
    $work = explode("\n", str_replace("\r\n", "\n", file_get_contents($root.'/'.$rel)));
    $base = basename($rel);

    preg_match_all('/'.preg_quote($base, '/').'\s*:\s*(\d+)(?:\s*-\s*(\d+))?/', $text, $cites, PREG_SET_ORDER);

    $seen = [];
    $shifts = [];

    foreach ($cites as $c) {
        $from = (int) $c[1];
        $to = isset($c[2]) && $c[2] !== '' ? (int) $c[2] : $from;

        if (isset($seen[$from.'-'.$to])) {
            continue;
        }

        $seen[$from.'-'.$to] = true;
        $found = null;

        // A citation is right at shift k when every line it spans is the same text at N+k.
        foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $k) {
            $all = true;

            for ($n = $from; $n <= $to; $n++) {
                if (($head[$n - 1] ?? "\0") !== ($work[$n - 1 + $k] ?? "\1")) {
                    $all = false;
                    break;
                }
            }

            if ($all) {
                $found = $k;
                break;
            }
        }

        $shifts[$from.'-'.$to] = $found;
        $total++;

        printf("%-34s %-12s %s\n", $base, $from.'-'.$to, $found === null
            ? 'NO UNIFORM SHIFT — read it'
            : ($found === 0 ? 'unchanged' : 'moves to '.($from + $found).'-'.($to + $found)." (+".$found.')'));
    }
}

printf("\n%d citation range(s) measured.\n", $total);
