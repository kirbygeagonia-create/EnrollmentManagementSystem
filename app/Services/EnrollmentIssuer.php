<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Curriculums;
use App\Models\Enrollments;
use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

/**
 * The one way a desk issues an enrollment it is responsible for creating.
 *
 * Ruling 3 (G-4) says one active enrollment per student per term, enforced at *every*
 * creation point — and there are now two desks that create: Department Evaluation, which
 * issues the returning student's form (ruling 2), and the Guidance Councillor's grant of a
 * shift request (ruling 11), which issues the receiving program's enrollment. A guard
 * written twice is a guard that drifts the first time one of them is edited, so both the
 * seat question and the creation itself live here.
 *
 * The workflow form is built on creation, not left to signing: the six or seven boxes are
 * the record's shape from its first moment (§6.5), and every desk behind this one reads
 * the form the queue prints.
 */
class EnrollmentIssuer
{
    public function __construct(private WorkflowService $workflowService) {}

    /**
     * The enrollment currently holding this student's seat in this term, or null.
     *
     * `dropped` is excluded on purpose: a drop releases the seat, which is what lets the
     * student the Registrar dropped (ruling 17) be enrolled in the same term again.
     */
    public static function seatHolder(int $studentId, int $termId): ?Enrollments
    {
        return Enrollments::where('studentId', $studentId)
            ->where('termId', $termId)
            ->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
            ->first();
    }

    /**
     * Issue the enrollment and its workflow form.
     *
     * The curriculum version is stamped here rather than left to be resolved later, so the
     * subjects, the band the load is judged against and the fee sheet built from it all
     * remain readable years after the catalog is amended (item 7). A caller that names a
     * version itself is respected — the pin records what the desk used, not what this
     * helper would have guessed.
     *
     * @param  array<string, mixed>  $attributes  the desk's own fields (student, program, term, level, type)
     */
    public function issue(array $attributes, Staffusers $issuer): Enrollments
    {
        return DB::transaction(function () use ($attributes, $issuer): Enrollments {
            $enrollment = [
                ...$attributes,
                'enrollmentType' => $attributes['enrollmentType'] ?? EnrollmentType::Old,
                'enrollmentStatus' => EnrollmentStatus::Pending,
                'evaluatedBy' => $issuer->userId,
                'formIssuedDate' => now()->toDateString(),
            ];

            if (! array_key_exists('curriculumId', $enrollment)) {
                $enrollment['curriculumId'] = Curriculums::currentFor(
                    (int) ($enrollment['courseId'] ?? 0),
                    isset($enrollment['majorId']) ? (int) $enrollment['majorId'] : null,
                )?->curriculumId;
            }

            $created = Enrollments::create($enrollment);

            $this->workflowService->createWorkflow($created);

            return $created;
        });
    }
}
