<?php

/**
 * Lane 73: the evidence behind the Registrar window packet, read from the build and from live ems.
 *
 * `Documentation/Registrar-Window-Packet-2026-10-26.md` asks the four open decision-sheet rows (items 1, 2, 4, 5),
 * the department confirmation C-5, the install-time check G-13, and the unticked rows of the 2026-10-10 fidelity
 * sheet. Every figure the packet quotes has to come from a command rather than from the previous sheet: the older
 * packet is the reason two of its cells went stale against the build (lane 69's finding), and a packet that
 * re-copies itself is how an answered question gets asked again.
 *
 * Read-only. Nothing here writes a row: the board flags C-5 asks about are desk-side data on purpose, so the
 * answer is a Registrar's or a department's click in Reference Data → Courses and its rollback is the same click
 * reversed. Column names are taken from `information_schema` rather than recalled (lane 60's rule).
 *
 *   php storage/app/scratch/lane73-packet-evidence.php
 */
// The imports sit ABOVE the bootstrap on purpose: `pint --test` passes this file either way, but a `use` filed
// below `$app->make(Kernel::class)` does not apply to that call, and the script fatals at runtime with
// "Target class [Kernel] does not exist" — the lane 65 trap, found here on 2026-10-26 by running the command this
// file's own gate bullet tells an operator to run. Run the script, never only the formatter.
use App\Models\Clearancerequirements;
use App\Models\Courses;
use App\Models\Feetypes;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fails = 0;
$cols = static function (string $table): array {
    return array_column(DB::select('select COLUMN_NAME c from information_schema.COLUMNS
        where TABLE_SCHEMA = DATABASE() and TABLE_NAME = ? order by ORDINAL_POSITION', [$table]), 'c');
};

// --- items 1 and 2: whose lines the slip prints today ------------------------------------------------------
$lines = Clearancerequirements::query()->get();
printf("== clearancerequirements: %d line(s), columns %s\n", $lines->count(), implode(', ', $cols('clearancerequirements')));
foreach ($lines as $l) {
    $a = $l->toArray();
    printf("   office %-4s  %s\n", $a['officeId'] ?? '-',
        $a['requirementName'] ?? ($a['requirement'] ?? json_encode($a)));
}
if ($lines->count() === 0) {
    echo "   BAD: the table answers nothing, so the packet could not state an office list.\n";
    $fails++;
}

// --- item 5: the fee the paid route reads ------------------------------------------------------------------
$fees = Feetypes::where('feeName', 'like', '%learance%')->get();
printf("\n== feetypes naming a clearance: %d   (columns %s)\n", $fees->count(), implode(', ', $cols('feetypes')));
foreach ($fees as $f) {
    $a = $f->toArray();
    printf("   %s\n", json_encode($a));
}
if ($fees->count() === 0) {
    echo "   The paid replacement route refuses outright with no such fee on file, so item 5's answer is a rule\n"
        ."        about a charge the catalogue does not currently hold.\n";
}

// --- C-5: the board set, measured rather than recalled ------------------------------------------------------
$flagged = Courses::where('requiresCourseSpecificExam', true)->orderBy('courseCode')->get();
$entrance = Courses::where('requiresEntranceExam', true)->orderBy('courseCode')->count();
$exempt = Courses::where('entranceExamExemptsTransferee', true)->count();
printf("\n== courses: %d total, %d carrying requiresCourseSpecificExam, %d carrying requiresEntranceExam, %d exempting a transferee\n",
    Courses::count(), $flagged->count(), $entrance, $exempt);
foreach ($flagged as $c) {
    printf("   %-12s %-58s entrance=%s\n", (string) $c->courseCode, (string) $c->courseName,
        $c->requiresEntranceExam ? 'yes' : 'NO');
}
echo "   SEAIT_Official_Information.md §2 marks exactly two programs as board courses: BSCrim (SCAT entrance and\n"
    ."   retention examinations) and BSSW (board program). A flagged course the record does not mark is the\n"
    ."   owner's instruction alone — that is the half C-5 still owes the owning departments.\n";

// --- G-13: what an install would have to name ----------------------------------------------------------------
$boot = (string) file_get_contents(dirname(__DIR__, 3).'/bootstrap/app.php');
printf("\n== G-13 proxy state on this build\n   config('trustedproxy.proxies') = %s   (that is TRUSTED_PROXIES, read by config/trustedproxy.php)\n"
    ."   bootstrap/app.php calls trustProxies(): %s\n"
    ."   so HSTS appears only when the app itself sees HTTPS. An install behind a TLS-terminating proxy names that\n"
    ."   proxy's address in TRUSTED_PROXIES — never `*`, and not a range keyword: login throttling and the audit log\n"
    ."   both read the client address. php artisan ems:preflight reports which state this install is in.\n",
    json_encode(config('trustedproxy.proxies')), str_contains($boot, 'trustProxies') ? 'yes' : 'no');

// --- the sheets this packet has to age -----------------------------------------------------------------------
// The verdict is read from where it is WRITTEN: the tick box, not the prose. A case-insensitive count of the word
// "answered" would charge every sentence that merely reports another row's ruling (lane 69's defect class), which is
// what this line did until 2026-10-26 — it looked for the sheet's own upper-case marker and answered 0 for a file
// whose rows were marked `Answered 2026-10-11 by the owner`.
foreach (['Print-Fidelity-Comparison-2026-10-10.md', 'Registrar-Print-Decision-Sheet-2026-10-11.md',
    'School-Decision-Sheet-2026-10-23.md'] as $s) {
    $path = dirname(__DIR__, 3).'/Documentation/'.$s;
    $src = (string) file_get_contents($path);
    printf("\n== %s\n   unticked boxes (☐): %d   checked boxes (☑): %d\n", $s,
        substr_count($src, '☐'), substr_count($src, '☑'));
}

printf("\nlive ems on %s: %d audit row(s), %d enrollment(s), %d course(s) — the papers this packet renders are dated\n"
    ."by the dataset they were read from, so re-run this command before quoting the packet.\n",
    (string) now()->toDateString(), (int) DB::table('auditlogs')->count(),
    (int) DB::table('enrollments')->count(), Courses::count());

printf("\n%s\n", $fails === 0 ? 'evidence read; the packet quotes this command rather than the previous sheet'
    : "$fails surface(s) answered nothing — fix the probe before writing the packet");
exit($fails === 0 ? 0 : 1);
