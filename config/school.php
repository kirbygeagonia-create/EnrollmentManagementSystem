<?php

/*
|--------------------------------------------------------------------------
| The school's own civil time
|--------------------------------------------------------------------------
|
| `app.timezone` stays UTC and `database.connections.mysql` still forces `+00:00`, because every instant in
| this system — a signature, a receipt, an audit row — is written and compared as UTC and that agreement is
| what `ems:preflight`'s "Clocks agree" row checks. This file answers a different question: which calendar day
| a SEAIT desk is standing in. The institution is in Manila (UTC+8) while its application has been cutting days
| at UTC midnight, and 1,459 of the live audit log's 3,426 rows land on a different date depending on which of
| the two you ask.
|
| Ruled 2026-10-18 (lane 51): store and compare instants in UTC, draw every desk-facing day and month boundary
| and every institutional time shown to a person in this zone. `App\Support\SchoolTime` is the only place that
| reads it, so a second spelling of "today" cannot drift.
*/

return [
    'timezone' => env('SCHOOL_TIMEZONE', 'Asia/Manila'),
];
