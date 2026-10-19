<?php

// Lane 51: read the boundary helper's actual answers out of the booted application, before any test trusts them.
// Run: php storage/app/scratch/lane51-probe-school-time.php

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\SchoolTime;

printf("app.timezone      = %s\n", config('app.timezone'));
printf("school.timezone   = %s\n", SchoolTime::zone());
printf("now()             = %s\n", now()->toDateTimeString());
printf("SchoolTime::today = %s\n", SchoolTime::today());

$opens = SchoolTime::startOfSchoolDay('2026-10-18');
$closes = SchoolTime::startOfNextSchoolDay('2026-10-18');

printf("\nschool day 2026-10-18 opens  %s %s\n", $opens->toDateString(), $opens->toTimeString());
printf("school day 2026-10-18 closes  %s %s (exclusive)\n", $closes->toDateString(), $closes->toTimeString());
printf("span length                   %d hours (must be 24)\n", ($closes->timestamp - $opens->timestamp) / 3600);

// A stamp the database holds as UTC that is already tomorrow at the school.
$evening = Illuminate\Support\Carbon::parse('2026-10-18 18:00:00');
printf("\ninstant %s UTC is the school's %s\n", $evening->toDateTimeString(), SchoolTime::civilDateOf($evening));

foreach (['2026-10-18', ' 2026-10-18 ', 'not-a-date', '', '2026/10/18', null] as $junk) {
    printf("dayOrNull(%-12s) = %s\n", var_export($junk, true), var_export(SchoolTime::dayOrNull($junk), true));
}

try {
    SchoolTime::startOfSchoolDay('2026-13-45');
    echo "REFUSE-TO-TEST FAILED: an impossible date was accepted\n";
} catch (InvalidArgumentException $e) {
    echo 'impossible date refused: ', $e->getMessage(), "\n";
}
