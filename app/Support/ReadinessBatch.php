<?php

namespace App\Support;

use App\Models\Creditedsubjects;
use App\Models\Curriculumsubjects;
use App\Models\Enrollments;
use Illuminate\Support\Facades\DB;

/**
 * The three sets the readiness gate reads per record, taken once for a whole page.
 *
 * `EnrollmentReadiness::prerequisitesMet()` asks the same three questions of every enrollment it is handed:
 * which subjects this **student** has passed, which this **enrollment** has been credited for, and which
 * prerequisite rows the curriculum declares for this course and year level. On a 20-row queue that is 60
 * statements for work whose answer set is the page — the second half of §P09, after the passing line and the
 * curriculum catalogue were already resolved once per request.
 *
 * This is a *cache of reads*, not a second definition of the rule: the predicates still decide, and the batch
 * only answers their lookups. So each accessor returns exactly what the fresh query would have returned for
 * that record, and `PrerequisiteGateTest` asserts the equality in both directions — a batch that disagrees
 * with the read it replaced is the second-definition defect this repository refuses in every other shape.
 *
 * Built from the page's own rows, after `paginate()`, so it never widens what a desk is allowed to see: the
 * student ids and enrollment ids it queries are the ones already on screen.
 */
final class ReadinessBatch
{
    /**
     * @param  array<int, list<int>>  $passedByStudent  studentId => subject ids passed at or below the ceiling
     * @param  array<int, list<int>>  $creditedByEnrollment  enrollmentId => subject ids credited by the department
     * @param  array<string, list<int>>  $prerequisites  "curriculumId:yearLevel" => the prerequisite rows it declares
     * @param  array<string, array<int, list<int>>>  $prereqBySubject  the same rows, keyed by the subject that needs them
     */
    private function __construct(
        private array $passedByStudent,
        private array $creditedByEnrollment,
        private array $prerequisites,
        private array $prereqBySubject,
        private float $passingCeiling,
    ) {}

    /**
     * @param  iterable<Enrollments>  $enrollments
     */
    public static function forEnrollments(iterable $enrollments, float $passingCeiling): self
    {
        $studentIds = [];
        $enrollmentIds = [];
        foreach ($enrollments as $enrollment) {
            $studentIds[(int) $enrollment->studentId] = (int) $enrollment->studentId;
            $enrollmentIds[(int) $enrollment->enrollmentId] = (int) $enrollment->enrollmentId;
        }

        if ($studentIds === []) {
            return new self([], [], [], [], $passingCeiling);
        }

        // One grouped read over the page's students answers "what has this person passed". `MIN(grade)` is the
        // PH convention the fresh path already uses — lower is better — and the ceiling filter stays here so the
        // two paths can never diverge on which passing line counts.
        // Every person on the page gets a key, even one with no graded row yet: an absent key means "this batch
        // was not built for you" and falls back to the fresh read, so without the seed a student with nothing
        // graded would re-query per record and the batch would quietly keep its N-plus-one where it matters most.
        // A plain query builder, not an Eloquent one: the grouped read carries two aliases and an aggregate that
        // are not columns of `Enrolledsubjects`, and PHPStan is right that no such property exists. Asking the
        // base builder for rows answers them as they are — a scalar per column — and emits the same SQL.
        $passedByStudent = [];
        foreach ($studentIds as $studentId) {
            $passedByStudent[$studentId] = [];
        }
        $passed = DB::table('enrolledsubjects')
            ->join('enrollments as e2', 'e2.enrollmentId', '=', 'enrolledsubjects.enrollmentId')
            ->whereIn('e2.studentId', array_values($studentIds))
            ->whereNotNull('enrolledsubjects.grade')
            ->groupBy('e2.studentId', 'enrolledsubjects.subjectId')
            ->selectRaw('e2.studentId as batchStudentId, enrolledsubjects.subjectId as batchSubjectId, MIN(enrolledsubjects.grade) as best_grade')
            ->get();
        foreach ($passed as $row) {
            if ((float) $row->best_grade > $passingCeiling) {
                continue;
            }
            $passedByStudent[(int) $row->batchStudentId][] = (int) $row->batchSubjectId;
        }

        // Approved credits only — the same `approved()` scope the fresh reader draws, so a declined equivalency
        // cannot satisfy anything through this path either (G-3's one definition of "counts").
        $creditedByEnrollment = [];
        $credited = Creditedsubjects::approved()
            ->whereIn('enrollmentId', array_values($enrollmentIds))
            ->get(['enrollmentId', 'creditedToSubjectId']);
        foreach ($credited as $row) {
            $creditedByEnrollment[(int) $row->enrollmentId][] = (int) $row->creditedToSubjectId;
        }

        // The declared prerequisites: the whole set is one read, and the table is the curriculum catalogue
        // rather than a history, so this is a smaller constant than the rows it replaces per record.
        $prerequisites = [];
        $prereqBySubject = [];
        $rows = Curriculumsubjects::whereNotNull('prerequisiteSubjectId')
            ->get(['curriculumId', 'yearLevel', 'subjectId', 'prerequisiteSubjectId']);
        foreach ($rows as $row) {
            $key = (int) $row->curriculumId.':'.(int) $row->yearLevel;
            $prerequisites[$key][] = (int) $row->prerequisiteSubjectId;
            $prereqBySubject[$key][(int) $row->subjectId][] = (int) $row->prerequisiteSubjectId;
        }

        return new self($passedByStudent, $creditedByEnrollment, $prerequisites, $prereqBySubject, $passingCeiling);
    }

    /**
     * Subjects the student has passed or been credited for, in the same merged set the fresh read returns.
     *
     * @return list<int>|null null when this batch was not built for the record, so the caller reads fresh
     */
    public function satisfiedFor(Enrollments $enrollment): ?array
    {
        $studentId = (int) $enrollment->studentId;
        if (! array_key_exists($studentId, $this->passedByStudent)) {
            // Not a "no subjects" answer — the batch was built for a different set of people. Returning the
            // empty map here would call an unmet prerequisite satisfied, which is the wrong direction to err.
            return null;
        }

        // Every person on the page got a key above, so no coalesce belongs here — and PHPStan names the
        // redundancy rather than letting it read as a null-able path.
        return array_values(array_unique(array_merge(
            $this->passedByStudent[$studentId],
            $this->creditedByEnrollment[(int) $enrollment->enrollmentId] ?? []
        )));
    }

    /**
     * The prerequisite rows for this curriculum version and year level, restricted to the subjects asked for —
     * the shape the fresh `whereIn('subjectId', …)` query answers with.
     *
     * @param  list<int>  $subjectIds
     * @return list<int> the prerequisite subject ids declared for those subjects
     */
    public function prerequisitesFor(int $curriculumId, int $yearLevel, array $subjectIds): array
    {
        $key = $curriculumId.':'.$yearLevel;
        if (! isset($this->prerequisites[$key])) {
            // The curriculum version declares no prerequisite at all; the fresh query would answer an empty
            // set, and an empty set means no unmet prerequisite. That agreement is asserted, not assumed.
            return [];
        }

        $found = [];
        foreach ($subjectIds as $subjectId) {
            foreach ($this->prereqBySubject[$key][$subjectId] ?? [] as $prerequisiteId) {
                $found[$prerequisiteId] = $prerequisiteId;
            }
        }

        return array_values($found);
    }

    public function passingCeiling(): float
    {
        return $this->passingCeiling;
    }
}
