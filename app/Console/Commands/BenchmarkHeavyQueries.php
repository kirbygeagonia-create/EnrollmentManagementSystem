<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Models\Blocks;
use App\Models\Enrollments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Stage 4 load-test scaffold: times the heavy screens (Registrar approval
 * queue, Blocking roster, Blocking eligibility) against the live database
 * and reports row counts + whether the composite indexes from migration
 * 0052 are being used (EXPLAIN QUERY PLAN). Read-only: never mutates data.
 */
class BenchmarkHeavyQueries extends Command
{
    protected $signature = 'ems:benchmark {--iterations=5 : Times to run each query for a stable average}';

    protected $description = 'Benchmark the heavy Stage 4 screens (Registrar queue, Blocking rosters)';

    public function handle(): int
    {
        $iterations = max(1, (int) $this->option('iterations'));

        $this->info("Benchmarking heavy queries ({$iterations} iterations each)...");
        $this->newLine();

        $queries = [
            'Registrar approval queue (Assessed/Paid + 6 eager loads)' => function () {
                return Enrollments::with([
                    'student', 'course', 'major', 'term',
                    'studentassessments', 'enrollmentworkflow',
                    'enrolledSubjects.subject',
                ])
                    ->whereIn('enrollmentStatus', [EnrollmentStatus::Assessed, EnrollmentStatus::Paid])
                    ->orderByDesc('enrollmentId')
                    ->paginate(20);
            },
            'Blocking roster (Blocks + schedules + enrolledSubjects)' => function () {
                return Blocks::with([
                    'course', 'term.academicYear', 'schedules.subject',
                    'schedules.room', 'schedules.instructor', 'enrolledSubjects',
                ])->paginate(20);
            },
            'Blocking eligible enrollments (enrolled, unassigned to block)' => function () {
                return Enrollments::with(['student', 'enrolledSubjects.subject'])
                    ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
                    ->whereDoesntHave('enrolledSubjects', function ($q) {
                        $q->whereNotNull('blockId');
                    })
                    ->orderByDesc('enrollmentId')
                    ->paginate(20);
            },
        ];

        $driver = DB::connection()->getDriverName();
        $results = [];

        foreach ($queries as $label => $callback) {
            // Warm up (first run loads caches/page cache)
            $callback();

            $times = [];
            $rowCount = 0;
            for ($i = 0; $i < $iterations; $i++) {
                $start = hrtime(true);
                $page = $callback();
                $times[] = (hrtime(true) - $start) / 1e6; // ms
                $rowCount = $page->total();
            }

            $avg = array_sum($times) / count($times);
            $min = min($times);
            $max = max($times);
            $results[$label] = compact('avg', 'min', 'max', 'rowCount');

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Matching rows', number_format($rowCount)],
                    ['Avg (ms)', number_format($avg, 2)],
                    ['Min (ms)', number_format($min, 2)],
                    ['Max (ms)', number_format($max, 2)],
                ]
            );
            $this->newLine();
        }

        // Index-usage check for the two heavy where-clauses (0052 composite indexes)
        $this->info('Index usage check (EXPLAIN QUERY PLAN):');

        $checks = [
            'enrollments(termId, enrollmentStatus)' => "select * from `enrollments` where `enrollmentStatus` in ('assessed', 'paid') order by `enrollmentId` desc limit 20",
            'enrolledsubjects(enrollmentId, status)' => 'select * from `enrolledsubjects` where `enrollmentId` = 1 and `status` in (\'proposed\', \'confirmed\')',
        ];

        foreach ($checks as $label => $sql) {
            if ($driver === 'sqlite') {
                $plan = DB::select("explain query plan {$sql}");
                $detail = $plan[0]->detail ?? json_encode($plan);
                $this->line("  {$label}: {$detail}");
            } else {
                $plan = DB::select("explain {$sql}");
                $keys = $plan[0]->key ?? $plan[0]->possible_keys ?? 'none';
                $rows = $plan[0]->rows ?? '?';
                $this->line("  {$label}: possible_keys={$keys} rows={$rows}");
            }
        }

        $this->newLine();
        $this->info('Done. Compare avg times against a baseline run after data growth.');

        return self::SUCCESS;
    }
}
