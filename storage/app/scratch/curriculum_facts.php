<?php

// The imports sit above the bootstrap: PHP resolves an unqualified name at compile time in statement order,
// so `DB::purge()` written before its import reads global \DB and dies. Found on 2026-10-26 by
// storage/app/scratch/lane73b-probe-import-order.php — this file had been broken since the day it was written.
use App\Models\Curriculums;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.host' => '127.0.0.1',
    'database.connections.mysql.port' => '3306',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

foreach ([3, 5, 1] as $courseId) {
    $cur = Curriculums::currentFor($courseId);
    echo "=== course {$courseId} -> curriculum ".($cur?->curriculumId ?? 'null').' (effectiveYear '.($cur?->effectiveYear ?? '-').")\n";
    if (! $cur) {
        continue;
    }

    $rows = DB::table('curriculumsubjects')
        ->where('curriculumId', $cur->curriculumId)
        ->orderBy('yearLevel')
        ->orderBy('semesterOffered')
        ->get(['curriculumSubjectId', 'subjectId', 'yearLevel', 'semesterOffered', 'is_elective', 'elective_group', 'elective_min_choices', 'elective_max_choices', 'prerequisiteSubjectId']);

    $grouped = [];
    foreach ($rows as $r) {
        $grouped["{$r->yearLevel}-{$r->semesterOffered}"][] = $r;
    }
    foreach ($grouped as $key => $set) {
        $mand = array_values(array_filter($set, fn ($s) => ! $s->is_elective));
        $elec = array_values(array_filter($set, fn ($s) => (bool) $s->is_elective));
        echo "  y{$key}: mandatory(".count($mand).')=['.implode(',', array_column($mand, 'subjectId')).']';
        if ($elec !== []) {
            echo ' elective=['.implode(',', array_map(fn ($s) => $s->subjectId.':'.$s->elective_group.'(min '.$s->elective_min_choices.')', $elec)).']';
        }
        $prereq = array_values(array_filter($set, fn ($s) => $s->prerequisiteSubjectId !== null));
        if ($prereq !== []) {
            echo ' prereq=['.implode(',', array_map(fn ($s) => $s->subjectId.'<-'.$s->prerequisiteSubjectId, $prereq)).']';
        }
        echo "\n";
    }

    $allPrereq = $rows->where('prerequisiteSubjectId', '!=', null);
    foreach ($allPrereq as $p) {
        $who = DB::table('curriculumsubjects')
            ->where('curriculumId', $cur->curriculumId)
            ->where('subjectId', $p->prerequisiteSubjectId)
            ->first(['subjectId', 'yearLevel', 'semesterOffered']);
        if ($who) {
            echo "  prereq subject {$p->prerequisiteSubjectId} is offered at y{$who->yearLevel}-{$who->semesterOffered}\n";
        } else {
            echo "  prereq subject {$p->prerequisiteSubjectId} NOT in this curriculum\n";
        }
    }
}

echo "\n=== terms\n";
foreach (DB::table('academicterms')->orderBy('termId')->get() as $t) {
    echo "  term {$t->termId}: {$t->semester} year {$t->academicYearId} ({$t->startDate} .. {$t->endDate})\n";
}
echo "=== clearance periods\n";
foreach (DB::table('clearanceperiods')->orderBy('clearancePeriodId')->get() as $p) {
    echo "  #{$p->clearancePeriodId}: term {$p->termId} status {$p->periodStatus} ({$p->clearanceStartDate} .. {$p->clearanceEndDate})\n";
}
