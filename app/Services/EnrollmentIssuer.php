<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Curriculums;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Support\AuditAct;
use App\Support\SchoolTime;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    public function issue(array $attributes, Staffusers $issuer, ?string $requestKey = null): Enrollments
    {
        // P17: the act's own identity, presented again after a dropped request. Answering the enrollment that
        // already exists is the whole recovery story — a second record would be the defect, and a refusal that
        // says only "duplicate" leaves the desk unable to tell whether its money and its student are on file.
        if ($requestKey !== null) {
            $already = Enrollments::where('requestKey', $requestKey)->first();
            if ($already !== null) {
                // A key identifies ONE act. If the same key arrives carrying a different student or a different
                // term, honouring it would answer a new action with somebody else's record — which is a wrong
                // enrollment delivered as a success. The screen's own retry after a real interruption always
                // resends the same act, so refusing here costs that desk nothing and costs a forged key everything.
                if ((int) $already->studentId !== (int) ($attributes['studentId'] ?? 0)
                    || (int) $already->termId !== (int) ($attributes['termId'] ?? 0)) {
                    throw ValidationException::withMessages([
                        'requestKey' => 'This request key already belongs to a different act: enrollment '
                            ."#{$already->enrollmentId} was issued for student #{$already->studentId} in term "
                            ."#{$already->termId}, and this request names student "
                            ."#{$attributes['studentId']} in term #{$attributes['termId']}. Mint a new key for a "
                            .'different action — nothing was written.',
                    ]);
                }

                return $already;
            }
        }

        try {
            // The audit row for an issued enrollment says `enrollment.issue`, not `created`, so §9's family is
            // answerable as one query. The label is bracketed: the replay path below returns without writing, and
            // nothing after this call may inherit the name.
            return AuditAct::run(AuditAct::ISSUE, fn () => $this->create($attributes, $issuer, $requestKey));
        } catch (UniqueConstraintViolationException $e) {
            // Two requests carrying one key can both pass the read above; the unique index is what refuses, and a
            // refusal here is a success with a different spelling. The pre-check is the fast path, never the guard.
            if ($requestKey !== null && preg_match('/uq_enrollments_request_key|1062|UNIQUE constraint failed/', $e->getMessage())) {
                $winner = Enrollments::where('requestKey', $requestKey)->first();
                if ($winner !== null) {
                    return $winner;
                }
            }

            throw $e;
        }
    }

    /**
     * The write itself, in one transaction: the enrollment and its workflow form arrive together or not at all,
     * which is what makes a replay answerable at all — a record with no form would be half a pipeline.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function create(array $attributes, Staffusers $issuer, ?string $requestKey): Enrollments
    {
        return DB::transaction(function () use ($attributes, $issuer, $requestKey): Enrollments {
            $enrollment = [
                ...$attributes,
                'enrollmentType' => $attributes['enrollmentType'] ?? EnrollmentType::Old,
                'enrollmentStatus' => EnrollmentStatus::Pending,
                'evaluatedBy' => $issuer->userId,
                // The school's civil day, not the machine's: between 16:00Z and midnight UTC the
                // Philippines is already on the next date, and this stamp reaches the printed form.
                'formIssuedDate' => SchoolTime::today(),
            ];

            if ($requestKey !== null) {
                $enrollment['requestKey'] = $requestKey;
            }

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
