<?php
/**
 * Lane 107 probe: does a ListSort join leak the joined table's columns into the desk payload?
 *
 * ListSort's docblock promises "the select list stays the model's own (table.*), so an aliased join adds no
 * ambiguous column to the payload". The new assessment/accounting enum cases read `studentType` off the TOP level of
 * a fee-sheet row and pass, which is only possible if the sort's own join put that column there — a shape that
 * changes depending on which header the desk clicked. This probe asks the builder, read-only, and prints the SQL and
 * the keys back. It writes nothing.
 */

require __DIR__.'/../../../vendor/autoload.php';
use Illuminate\Contracts\Console\Kernel;

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Studentassessments;

$spec = ['column' => 'studentType', 'join' => 'enrollments', 'foreign' => 'enrollmentId', 'local' => 'studentassessments.enrollmentId'];

$plain = Studentassessments::query()->limit(1);
echo 'UNSORTED SQL: ', $plain->toSql(), PHP_EOL;

$joined = Studentassessments::query()
    ->join('enrollments as srt0', 'srt0.'.$spec['foreign'], '=', $spec['local'])
    ->orderBy('srt0.'.$spec['column'], 'asc')
    ->limit(1);
echo 'SORTED   SQL: ', $joined->toSql(), PHP_EOL;

$row = $joined->get()->first();
if ($row === null) {
    fwrite(STDERR, "REFUSED: this schema holds no fee sheet to inspect, so the key list would describe nothing.\n");
    exit(1);
}
$keys = array_keys($row->toArray());
sort($keys);
echo 'ROW KEYS AFTER THE SORT JOIN: ', implode(', ', $keys), PHP_EOL;
foreach (['studentType', 'academicStanding', 'enrollmentStatus', 'yearLevel'] as $probe) {
    echo str_pad($probe, 18), ': ', in_array($probe, $keys, true) ? 'PRESENT in the payload' : 'absent', PHP_EOL;
}

// Second question: the name sort joins `students` two hops away. Does a credential field ride in?
$twoHop = Studentassessments::query()
    ->join('enrollments as srt0', 'srt0.enrollmentId', '=', 'studentassessments.enrollmentId')
    ->join('students as srt1', 'srt1.studentId', '=', 'srt0.studentId')
    ->orderBy('srt1.lastName', 'asc')->limit(1);
echo PHP_EOL, 'NAME-SORT SQL: ', $twoHop->toSql(), PHP_EOL;
$keys2 = array_keys($twoHop->get()->first()->toArray());
sort($keys2);
foreach (['passwordHash', 'lastName', 'firstName', 'schoolIdNumber', 'birthdate', 'contactNumber'] as $probe) {
    printf("%-16s: %s\n", $probe, in_array($probe, $keys2, true) ? 'ARRIVES in the row payload' : 'absent');
}
printf("hidden on the Students model: [%s]\n", implode(', ', (new App\Models\Students)->getHidden()));
