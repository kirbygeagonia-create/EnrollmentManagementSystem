<?php

// Lane 50 step 1: measure what the models declare for every date-shaped key the screens format.
// The wire spelling a `date` cast produces is what makes a calendar day shift, so this is the binding table
// the checker needs — taken from the source, not remembered.
$files = glob(dirname(__DIR__, 3).'/app/Models/*.php');

// A probe that reads no files prints the same clean numbers as one that reads all of them.
if (count($files) === 0) {
    fwrite(STDERR, "NO CONCLUSION: no model files found under ".dirname(__DIR__, 3)."/app/Models\n");
    exit(1);
}

$day = $instant = $other = $uncast = [];

foreach ($files as $file) {
    $source = file_get_contents($file);
    $name = basename($file);

    // These models use the Laravel 11+ method form, not the old property.
    if (! preg_match('/protected function casts\(\): array\s*\{\s*return \[(.*?)\n\s*\];/s', $source, $block)) {
        $uncast[] = $name;

        continue;
    }

    preg_match_all('/([\'"])(\w+)\1\s*=>\s*([\'"])([\w:\-]+)\3/', $block[1], $pairs, PREG_SET_ORDER);

    foreach ($pairs as $pair) {
        $type = strtolower($pair[4]);

        // "datetime" starts with "date", so the instant test has to run first.
        if ($type === 'datetime' || $type === 'timestamp' || str_starts_with($type, 'datetime')) {
            $instant[$pair[2]] = $name.':'.$type;
        } elseif ($type === 'date' || $type === 'immutable_date' || str_starts_with($type, 'date:')) {
            $day[$pair[2]] = $name.':'.$type;
        } else {
            $other[$pair[2]] = $name.':'.$type;
        }
    }

    // $dates is the older spelling of the same declaration.
    if (preg_match('/protected \$dates = \[(.*?)\];/s', $source, $d)) {
        preg_match_all('/([\'"])(\w+)\1/', $d[1], $names);

        foreach ($names[2] as $n) {
            $day[$n.'  ($dates)'] = $name;
        }
    }
}

ksort($day);
ksort($instant);

if (count($day) + count($instant) === 0) {
    fwrite(STDERR, "NO CONCLUSION: read ".count($files)." models and found no date-shaped cast — the parser matched nothing\n");
    exit(1);
}

printf("models read: %d;  with no casts() body: %d (%s)\n\n", count($files), count($uncast), implode(', ', $uncast));
printf("=== declared as a DATE (calendar day) — %d keys ===\n", count($day));
foreach ($day as $k => $v) {
    printf("  %-26s %s\n", $k, $v);
}
printf("\n=== declared as an INSTANT (datetime/timestamp) — %d keys ===\n", count($instant));
foreach ($instant as $k => $v) {
    printf("  %-26s %s\n", $k, $v);
}
printf("\n=== other casts that share a date-shaped name — %d ===\n", count($other));
foreach ($other as $k => $v) {
    if (preg_match('/date|At$|_at$|Year/i', $k)) {
        printf("  %-26s %s\n", $k, $v);
    }
}
