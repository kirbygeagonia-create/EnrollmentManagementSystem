<?php

namespace App\Services;

use App\Enums\AcademicStanding;
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
