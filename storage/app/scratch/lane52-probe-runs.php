<?php

// Lane 52 step 1: the run inventory of §25.6's five paragraphs. The flat dump joins runs with spaces, which is
// why a whole-sentence needle matches the dump and matches nothing in word/document.xml — every code span is
// its own run. This prints the runs exactly as the XML holds them so each edit can be addressed inside one run.
//
//   php storage/app/scratch/lane52-probe-runs.php

$root = dirname(__DIR__, 3);
$z = new ZipArchive();

if ($z->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "REFUSE: cannot open the docx\n");

    exit(1);
}

$xml = $z->getFromName('word/document.xml');
$z->close();

$at = strpos($xml, '25.6 The clearance slip');

if ($at === false) {
    fwrite(STDERR, "REFUSE: the §25.6 heading is not in the document\n");

    exit(1);
}

// Walk forward from the heading, taking whole paragraphs.
$body = substr($xml, $at);
$paras = [];
$scan = 0;

while (count($paras) < 8) {
    $end = strpos($body, '</w:p>', $scan);

    if ($end === false) {
        break;
    }

    $chunk = substr($body, $scan, $end - $scan);
    $paras[] = $chunk;
    $scan = $end + 6;
}

foreach ($paras as $pi => $chunk) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $chunk, $m);

    if ($m[1] === []) {
        echo "\n=== para $pi: (no text runs)\n";

        continue;
    }

    $flat = implode('', $m[1]);
    echo "\n=== para $pi — ".count($m[1])." run(s), ".strlen($flat)." bytes\n";

    foreach ($m[1] as $ri => $run) {
        printf("  [%2d] %s%s\n", $ri, str_contains($m[0][$ri], 'Consolas') ? 'C ' : '  ', mb_substr($run, 0, 150));
    }
}
