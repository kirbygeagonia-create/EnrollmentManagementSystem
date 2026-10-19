<?php

// Is `dayOrNull` stateful, or was the probe's list not what it looked like? Test with literals and show bytes.

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\SchoolTime;

$cases = [
    'plain' => '2026-10-18',
    'padded' => ' 2026-10-18 ',
    'junk-word' => 'not-a-date',
    'empty' => '',
    'slashes' => '2026/10/18',
    'impossible-month' => '2026-13-45',
    'impossible-day' => '2026-02-30',
    'null' => null,
];

foreach ($cases as $label => $value) {
    printf("%-18s in=%-14s hex=%-24s out=%s\n",
        $label,
        var_export($value, true),
        bin2hex((string) $value),
        var_export(SchoolTime::dayOrNull($value), true));
}

foreach (['2026-13-45', '2026-02-30', '2026-02-29'] as $date) {
    try {
        $opened = SchoolTime::startOfSchoolDay($date);
        printf("startOfSchoolDay(%s) = %s   <- %s\n", $date, $opened->toDateTimeString(),
            $date === '2026-02-29' ? 'legitimate (2026 is not a leap year? it is not: 2026 %% 4 != 0)' : 'ROLLED OVER — must be refused');
    } catch (InvalidArgumentException $e) {
        printf("startOfSchoolDay(%s) refused: %s\n", $date, $e->getMessage());
    }
}

printf("\ncheckdate(2,29,2026) = %s   checkdate(13,45,2026) = %s\n",
    var_export(checkdate(2, 29, 2026), true), var_export(checkdate(13, 45, 2026), true));
