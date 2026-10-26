<?php

namespace App\Services;

use App\Enums\AcademicStanding;
use App\Enums\Semester;
use App\Models\Enrollments;
use App\Models\Gradescale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Derive a student's academic standing from the records already on file.
 *
 * Standing belongs to the Department Evaluation desk (it is an academic judgement
 * about what the student has actually passed) and is finalized by the Registrar.
 * Admission no longer asserts it, so this service is what turns the evidence —
 * prior terms' grades for this student — into a recommendation the evaluator
 * either accepts or overrides with their own call.
 *
 * The one decisive rule used here: a student carrying a FAILED subject is
 * irregular. "Failed" means a grade worse than the passing ceiling of the
 * configured grade scale (the Philippine scale is inverted, so higher is worse).
 *
 * Not decisive, and deliberately left to the human: the number of repeated
 * subjects, the gap between year level and semesters completed, and a study load
 * that deviates from the prescribed block. Those are surfaced as advisories
 * because no institutional policy in the documentation states a threshold for
 * them — needs registrar confirmation before any of them becomes automatic.
 */
class AcademicStandingService
{
    /**
     * @return array{
     *     derived: ?string,
     *     canDerive: bool,
     *     headline: string,
     *     passingCeiling: float,
     *     hasGradeScale: bool,
     *     gradedSubjectCount: int,
     *     failedSubjects: array<int, array<string, mixed>>,
     *     retakenSubjects: array<int, array<string, mixed>>,
     *     advisories: array<int, string>,
     * }
     */
    public function derive(Enrollments $enrollment): array
    {
        $ceiling = Gradescale::passingCeiling();
        $graded = $this->gradedPriorSubjects($enrollment);

        $failed = $graded
            ->filter(fn (array $row) => (float) $row['grade'] > $ceiling)
            ->values();

        $retakes = $graded
            ->filter(fn (array $row) => (int) $row['attempt_number'] > 1 || $row['original_enrolled_subject_id'] !== null)
            ->unique(fn (array $row) => $row['subjectCode'])
            ->values();

        $canDerive = $graded->isNotEmpty();
        $derived = $canDerive
            ? ($failed->isNotEmpty() ? AcademicStanding::Irregular : AcademicStanding::Regular)
            : null;

        return [
            'derived' => $derived?->value,
            'canDerive' => $canDerive,
            'headline' => $this->headline($graded, $ceiling),
            'passingCeiling' => $ceiling,
            'hasGradeScale' => Gradescale::isConfigured(),
            'gradedSubjectCount' => $graded->count(),
            'failedSubjects' => $failed->all(),
            'retakenSubjects' => $retakes->all(),
            'advisories' => $this->advisories($enrollment, $graded, $retakes),
        ];
    }

    /**
     * Every subject this student has ever been loaded for, with the grade on it
     * and the term that recorded it, filterable by academic year and semester.
     *
     * Standing deliberately reads PRIOR terms only (see gradedPriorSubjects), because a
     * standing is decided before the current term's grades exist. The Registrar's record
     * review needs the opposite view: the whole trail, the enrollment being signed
     * included, and a row nobody has graded yet. A missing grade comes back as null and
     * the desk dashes it — an ungraded row is never a pass.
     *
     * The load status travels with every row so a subject still PROPOSED cannot be read
     * as an official recorded grade.
     *
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     academicYears: array<int, array{value: int, label: string}>,
     *     semesters: array<int, array{value: string, label: string}>,
     *     passingCeiling: float,
     *     hasGradeScale: bool,
     *     applied: array{academicYearId: ?int, semester: ?string},
     *     counts: array{terms: int, subjects: int, graded: int, ungraded: int, passed: int, failed: int, units: float},
     * }
     */
    public function gradeHistory(Enrollments $enrollment, ?int $academicYearId = null, ?string $semester = null): array
    {
        $ceiling = Gradescale::passingCeiling();

        $rows = DB::table('enrolledsubjects as es')
            ->join('enrollments as e', 'e.enrollmentId', '=', 'es.enrollmentId')
            ->join('subjects as s', 's.subjectId', '=', 'es.subjectId')
            ->join('academicterms as t', 't.termId', '=', 'e.termId')
            ->leftJoin('academicyears as y', 'y.academicYearId', '=', 't.academicYearId')
            ->where('e.studentId', $enrollment->studentId)
            ->orderByDesc('t.endDate')
            ->orderBy('s.subjectCode')
            ->get([
                'es.enrolledSubjectId',
                'es.grade',
                'es.status',
                'es.attempt_number',
                'es.enrollmentId',
                's.subjectCode',
                's.subjectName',
                's.lectureUnits',
                's.labUnits',
                't.termId',
                't.semester',
                't.academicYearId',
                't.startDate',
                't.endDate',
                'y.yearLabel',
                'e.yearLevel',
            ]);

        // The choices offered are the ones this student's own records can answer, so a
        // filter can never be set to a value that has no rows — the failure lane 46
        // calls FICTION.
        $academicYears = $rows
            ->filter(fn ($row) => $row->academicYearId !== null)
            ->groupBy('academicYearId')
            // The label is the year's own yearLabel. A bare id is the fallback and can only appear if
            // academicyears.yearLabel were null — this repository's vocabulary guard refuses any surface
            // that spells a year level by hand, and it caught this line the first time it was written.
            ->map(fn ($group) => [
                'value' => (int) $group->first()->academicYearId,
                'label' => (string) ($group->first()->yearLabel ?? $group->first()->academicYearId),
            ])
            ->values()
            ->all();

        // Semesters are offered in the institution's own order (1st, 2nd, Summer), not the
        // order the rows arrived in — a filter list is vocabulary, and vocabulary has one
        // spelling per enum.
        $presentSemesters = $rows->pluck('semester')->filter()->unique()->all();
        $semesters = array_values(array_map(
            fn (Semester $case) => ['value' => $case->value, 'label' => $case->value],
            array_filter(
                Semester::cases(),
                fn (Semester $case) => in_array($case->value, $presentSemesters, true)
            ),
        ));

        $visible = $rows->filter(function ($row) use ($academicYearId, $semester) {
            if ($academicYearId !== null && (int) $row->academicYearId !== $academicYearId) {
                return false;
            }

            return $semester === null || (string) $row->semester === $semester;
        });

        $mapped = $visible->map(function ($row) use ($ceiling, $enrollment): array {
            $isGraded = $row->grade !== null;

            return [
                'enrolledSubjectId' => (int) $row->enrolledSubjectId,
                'subjectCode' => (string) $row->subjectCode,
                'subjectName' => (string) $row->subjectName,
                'lectureUnits' => (float) $row->lectureUnits,
                'labUnits' => (float) $row->labUnits,
                'totalUnits' => (float) $row->lectureUnits + (float) $row->labUnits,
                'grade' => $isGraded ? (float) $row->grade : null,
                'result' => $isGraded ? ((float) $row->grade <= $ceiling ? 'pass' : 'fail') : null,
                'loadStatus' => (string) $row->status,
                'attemptNumber' => (int) ($row->attempt_number ?? 1),
                'academicYearId' => $row->academicYearId !== null ? (int) $row->academicYearId : null,
                'yearLabel' => (string) ($row->yearLabel ?? '—'),
                'semester' => (string) $row->semester,
                'termLabel' => trim($row->semester.' '.($row->yearLabel ?? '')),
                'termStart' => $row->startDate,
                'termEnd' => $row->endDate,
                'yearLevel' => (int) $row->yearLevel,
                'isTermUnderReview' => (int) $row->enrollmentId === (int) $enrollment->enrollmentId,
            ];
        })->values()->all();

        $graded = array_filter($mapped, fn (array $r) => $r['grade'] !== null);

        return [
            'rows' => $mapped,
            'academicYears' => $academicYears,
            'semesters' => $semesters,
            'passingCeiling' => $ceiling,
            'hasGradeScale' => Gradescale::isConfigured(),
            'applied' => ['academicYearId' => $academicYearId, 'semester' => $semester],
            'counts' => [
                'terms' => count(array_unique(array_column($mapped, 'termLabel'))),
                'subjects' => count($mapped),
                'graded' => count($graded),
                'ungraded' => count($mapped) - count($graded),
                'passed' => count(array_filter($graded, fn (array $r) => $r['result'] === 'pass')),
                'failed' => count(array_filter($graded, fn (array $r) => $r['result'] === 'fail')),
                'units' => array_sum(array_column($mapped, 'totalUnits')),
            ],
        ];
    }

    /**
     * Every grade this student earned in a DIFFERENT term than the enrollment
     * being evaluated. The current term's own rows are excluded: standing is
     * decided before this term's grades exist, so they cannot be evidence.
     *
     * Read through the query builder rather than Eloquent because the rows are
     * joined columns (subject code, subject name, grade, attempt, term, year
     * level), not models.
     */
    private function gradedPriorSubjects(Enrollments $enrollment): Collection
    {
        $rows = DB::table('enrolledsubjects')
            ->join('enrollments as e', 'e.enrollmentId', '=', 'enrolledsubjects.enrollmentId')
            ->join('subjects as s', 's.subjectId', '=', 'enrolledsubjects.subjectId')
            ->where('e.studentId', $enrollment->studentId)
            ->where('enrolledsubjects.enrollmentId', '!=', $enrollment->enrollmentId)
            ->where('e.termId', '!=', $enrollment->termId)
            ->whereNotNull('enrolledsubjects.grade')
            ->orderBy('e.termId')
            ->orderBy('s.subjectCode')
            ->get([
                'enrolledsubjects.grade',
                'enrolledsubjects.attempt_number',
                'enrolledsubjects.original_enrolled_subject_id',
                's.subjectCode',
                's.subjectName',
                'e.enrollmentId',
                'e.termId',
                'e.yearLevel',
            ]);

        $termLabels = $this->termLabels(
            $rows->pluck('termId')->map(fn ($termId) => (int) $termId)->unique()->values()->all()
        );

        return $rows->map(fn ($row) => [
            'subjectCode' => (string) $row->subjectCode,
            'subjectName' => (string) $row->subjectName,
            'grade' => (float) $row->grade,
            'attempt_number' => (int) ($row->attempt_number ?? 1),
            'original_enrolled_subject_id' => $row->original_enrolled_subject_id !== null
                ? (int) $row->original_enrolled_subject_id
                : null,
            'enrollmentId' => (int) $row->enrollmentId,
            'termLabel' => $termLabels[(int) $row->termId] ?? 'Term '.$row->termId,
            'yearLevel' => (int) $row->yearLevel,
        ])->values();
    }

    /**
     * @param  int[]  $termIds
     * @return array<int, string>
     */
    private function termLabels(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        return DB::table('academicterms as t')
            ->leftJoin('academicyears as y', 'y.academicYearId', '=', 't.academicYearId')
            ->whereIn('t.termId', $termIds)
            ->get(['t.termId', 't.semester', 'y.yearLabel'])
            ->mapWithKeys(fn ($row) => [
                (int) $row->termId => trim(($row->semester ?? '').' '.($row->yearLabel ?? ''))
                    ?: 'Term '.$row->termId,
            ])
            ->all();
    }

    private function headline(Collection $graded, float $ceiling): string
    {
        if ($graded->isEmpty()) {
            return 'No grades from a previous term are on file for this student, so the records cannot decide the standing.';
        }

        $passLine = number_format($ceiling, 2);
        $count = $graded->count();
        $failed = $graded->filter(fn (array $row) => $row['grade'] > $ceiling)->count();

        if ($failed > 0) {
            return "Derived IRREGULAR: {$failed} of {$count} subject(s) from previous terms is graded worse than the passing ceiling of {$passLine}.";
        }

        return "Derived REGULAR: all {$count} subject(s) from previous terms are graded at or better than the passing ceiling of {$passLine}.";
    }

    /**
     * Signals the evaluator should see but that do not decide anything on their
     * own — no documented threshold makes them automatic.
     *
     * @return array<int, string>
     */
    private function advisories(Enrollments $enrollment, Collection $graded, Collection $retakes): array
    {
        $notes = [];

        if (! Gradescale::isConfigured()) {
            $notes[] = 'No official grade scale is on file, so the passing line was assumed to be 3.00 (the lowest passing grade of the 1.00–5.00 scale). Confirm the institutional scale.';
        }

        if ($graded->isNotEmpty() && $enrollment->student) {
            $terms = $graded->pluck('termLabel')->unique()->count();
            $semestersCompleted = (int) ($enrollment->student->semestersCompleted ?? 0);
            if ($semestersCompleted > 0 && $semestersCompleted !== $terms) {
                $notes[] = "The student's profile records {$semestersCompleted} semester(s) completed while graded records cover {$terms} term(s) — worth checking before deciding.";
            }
        }

        if ($retakes->isNotEmpty()) {
            $notes[] = 'A repeated subject on record is shown as evidence but does not decide the standing here: no documented rule sets how many repeats make a student irregular.';
        }

        return $notes;
    }
}
