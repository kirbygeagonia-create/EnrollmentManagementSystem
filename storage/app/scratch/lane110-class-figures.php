<?php

/** Class totals straight out of the delivered junit — a figure beside a class name must be read from the run that produced it. */
$file = $argv[1] ?? 'storage/app/scratch/lane110-suite-junit.xml';
$x = simplexml_load_file($file);
if ($x === false) {
    fwrite(STDERR, "REFUSED: the junit did not parse.\n");

    exit(1);
}
$want = array_slice($argv, 2);
if ($want === []) {
    $want = ['DashboardQueueCountMatchesDeskTest'];
}
echo 'suite root: '.(string) $x['tests'].' tests / '.(string) $x['assertions']." assertions\n";
$found = 0;
foreach ($x->xpath('//testsuite') as $s) {
    $name = (string) $s['name'];
    foreach ($want as $w) {
        if ($name === $w || str_ends_with($name, '\\'.$w)) {
            printf("%-40s %s tests / %s assertions\n", $w, (string) $s['tests'], (string) $s['assertions']);
            $found++;
        }
    }
}
if ($found !== count($want)) {
    fwrite(STDERR, "REFUSED: asked for ".count($want)." class(es), found {$found} — a missing suite means the name or the run is wrong.\n");

    exit(1);
}
