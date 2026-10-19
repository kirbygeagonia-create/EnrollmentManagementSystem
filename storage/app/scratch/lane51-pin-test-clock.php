<?php

// Lane 51: three daily-report tests were clock-dependent — they write a receipt dated `now()` in UTC and then let
// the report answer "today" implicitly, which lane 51 moved to the school's calendar. Pin the clock to a moment
// where the two agree (06:00Z = 14:00 Manila) and clear it in tearDown so the pin cannot leak into the rest of
// the suite, which runs in the same process.

$files = [
    'tests/Feature/Accounting/PartPaymentAndRefundTest.php',
    'tests/Feature/Accounting/PaymentModeCheckTest.php',
    'tests/Feature/Accounting/PaymentVoidTest.php',
];

$pin = "        parent::setUp();\n\n"
    ."        // Lane 51: the daily report's default date is the school's today, so a fixture that writes\n"
    ."        // `now()->toDateString()` must not race the clock. 06:00Z is 14:00 in Manila — both calendars say\n"
    ."        // the 18th, and the test stops depending on when CI happened to run.\n"
    ."        Carbon::setTestNow('2026-10-18 06:00:00');\n";

$tearDown = "\n    protected function tearDown(): void\n    {\n"
    ."        Carbon::setTestNow();\n\n        parent::tearDown();\n    }\n";

$fails = 0;

foreach ($files as $rel) {
    $s = file_get_contents($rel);
    $before = $s;

    if (str_contains($s, 'Carbon::setTestNow')) {
        echo "  skip  $rel already pins the clock\n";

        continue;
    }

    if (substr_count($s, "        parent::setUp();\n") !== 1) {
        fwrite(STDERR, "REFUSE: $rel — parent::setUp() occurs ".substr_count($s, "        parent::setUp();\n")." times\n");
        $fails++;

        continue;
    }

    $s = str_replace("        parent::setUp();\n", $pin, $s);

    // The end of setUp() is the first `    }` after it.
    $start = strpos($s, 'protected function setUp(): void');
    $endOfSetUp = $start === false ? false : strpos($s, "\n    }\n", $start);

    if ($endOfSetUp === false) {
        fwrite(STDERR, "REFUSE: $rel — cannot find the end of setUp()\n");
        $fails++;

        continue;
    }

    $insertAt = $endOfSetUp + strlen("\n    }\n");
    $s = substr($s, 0, $insertAt).$tearDown.substr($s, $insertAt);

    if (! str_contains($s, 'use Illuminate\Support\Carbon;')) {
        $firstUse = strpos($s, "\nuse ");

        if ($firstUse === false) {
            fwrite(STDERR, "REFUSE: $rel has no import to anchor Carbon on\n");
            $fails++;

            continue;
        }

        $s = substr($s, 0, $firstUse)."\nuse Illuminate\\Support\\Carbon;".substr($s, $firstUse);
    }

    if ($s === $before) {
        fwrite(STDERR, "REFUSE: $rel unchanged\n");
        $fails++;

        continue;
    }

    file_put_contents($rel, $s);
    echo "  ok    $rel\n";
}

if ($fails > 0) {
    exit(1);
}

echo "done\n";
