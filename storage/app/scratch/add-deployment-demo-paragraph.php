<?php
/**
 * Add the demo-seeder paragraph to DEPLOYMENT.md §7 — what this batch fixed, and what it honestly did not.
 *
 * CRLF file, so the write goes through a script that asserts its anchor and reports the line endings it
 * produced. The "not fixed" sentence is the point of the paragraph: a tool that prints an exception and
 * exits 0 will read as a pass to anything that only watches the exit code.
 */
$path = 'DEPLOYMENT.md';
$crlf = "\r\n";
$bytes = file_get_contents($path);

$anchor = 'insert lands where the code says regardless of the counter.';

$addition = $crlf.$crlf.
    '**The same trap sat in the demo seeder, and it was quieter.** `tools/seed_full_demo_pipeline.php` wrote'.PHP_EOL.
    '`feeTypeId => 1..4`, `subjectId => 5` ("IT101") and `roomId => 1`, plus a hand-kept office to user map,'.PHP_EOL.
    'on a machine where those numbers happen to be right. On a freshly migrated and seeded install fee type 1'.PHP_EOL.
    'is the **Clearance Slip Replacement**, so every demo charge attaches to the wrong paper and the foreign'.PHP_EOL.
    'key accepts it; subject 5 is RH1, not IT101, and the run aborts on the schedule insert. Both are fixed by'.PHP_EOL.
    'resolving names — `subjectCode`, `roomName`, `feeName`, and the seeded `officeN_head` accounts — which'.PHP_EOL.
    'changes nothing on the developer\'s database: all fifteen lookups were checked to return the ids the'.PHP_EOL.
    'literals used. **Not fixed:** the tool still assumes its named demo students exist, aborting on'.PHP_EOL.
    '`DEMO-2026-004` when they do not, and it prints that failure while exiting 0, so a gate that reads only'.PHP_EOL.
    'the exit code calls it a pass. Nothing in CI covers `tools/` — PHPStan analyses `app`, and `pint.json`'.PHP_EOL.
    'excludes `tools`.';

$hits = substr_count($bytes, $anchor);
if ($hits !== 1) {
    fwrite(STDERR, "ABORT: anchor matched {$hits} times, expected exactly 1.\n");
    exit(1);
}

$addition = str_replace("\n", $crlf, $addition);
file_put_contents($path, str_replace($anchor, $anchor.$addition, $bytes));

$lines = explode($crlf, file_get_contents($path));
echo 'paragraph added'.PHP_EOL;
foreach ($lines as $n => $line) {
    if (strlen($line) > 120) {
        echo '  long line '.($n + 1).': '.strlen($line)." chars\n";
    }
}
echo 'CRLF lines: '.count($lines).', bare LF: '.(substr_count(implode('', $lines), "\n"))."\n";
