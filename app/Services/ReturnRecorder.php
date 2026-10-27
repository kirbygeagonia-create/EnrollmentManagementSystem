<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ReturnCategory;
use App\Enums\ReturnDestination;
use App\Enums\WorkflowStepStatus;
use App\Models\Admissions;
use App\Models\Enrollmentreturns;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Staffusers;
use App\Models\Workflowsteps;
use App\Policies\EnrollmentReturnPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one return mechanism development prompt §6.4 asks for (gap G-10, ruled by the owner 2026-10-26).
 *
 * Ruled shape: one right (`enrollment.return`) rather than one right per desk; the acting desk names
 * a destination and a category; the record keeps the signatures already given *before* that
 * destination and re-opens the destination and everything after it.
 *
 * Four things this class is the only place that says them.
 *
 * **What a return does to the workflow form.** Boxes at or after the destination step go back to
 * `pending` with their signer and date cleared; earlier boxes keep theirs. Clearing rather than
 * deleting is deliberate — the audit row and the status history keep who signed what, and the
 * signature itself records an act that did happen. A return to Admission re-opens every box, because
 * Admission holds no box on this record's own form: nothing on the form is *earlier* than it.
 *
 * **Why the destination re-opens the application as well as the record.** A return to Admission that
 * left the application reading `approved` would point at a desk with nothing to act on, so the
 * approved application goes back to `pending`. Re-approving it is then a correction rather than a
 * second issue: the seat guard in `AdmissionController` refuses a new enrollment for a student who
 * still holds one in that term, and a parked record still holds its seat.
 *
 * **Which destinations a screen may offer.** `optionsFor()` and `record()` both read `refusalFor()`,
 * so a desk is never shown an option the save then refuses — the standing rule that a dropdown is
 * filtered by the same predicate the policy asks. The four refusals are: a dropped record (terminal
 * by ruling 17); a destination the record already sits in, which would write a trail row for a
 * no-op; a Department Evaluation box that has not been signed yet, because sending a record "back"
 * to the desk that has not acted asks the same desk to act twice; and Admission when the student
 * holds no approved application for this course and term — real, because a continuing student's
 * record is issued by Department Evaluation, so the desk that owes that record's paper is not
 * Admission.
 *
 * **The inbox needs no call here.** `WorkflowInbox` reads the boxes still pending, so re-opening one
 * puts the record in that desk's queue during the same request that returned it.
 */
class ReturnRecorder
{
    public function __construct(private readonly EnrollmentStateMachine $stateMachine) {}

    /**
     * What a desk may be offered for this record, labelled, from the same predicate the save
     * applies — so a screen listing the four categories or both destinations by hand would be
     * offering work the server refuses.
     *
     * Static because offering needs no state machine: making three controllers inject one to ask a
     * read-only question would hide what the call actually is.
     *
     * @return array{destinations: list<array{value: string, label: string}>, categories: list<array{value: string, label: string}>}
     */
    public static function optionsFor(Enrollments $enrollment): array
    {
        $destinations = [];

        foreach (ReturnDestination::cases() as $destination) {
            if (self::refusalFor($enrollment, $destination) === null) {
                $destinations[] = ['value' => $destination->value, 'label' => $destination->label()];
            }
        }

        return [
            'destinations' => $destinations,
            'categories' => array_map(
                static fn (ReturnCategory $category): array => ['value' => $category->value, 'label' => $category->label()],
                ReturnCategory::cases(),
            ),
        ];
    }

    public function record(
        Enrollments $enrollment,
        Staffusers $actor,
        ReturnDestination $destination,
        ReturnCategory $category,
        string $note,
        string $expectedCorrection,
    ): Enrollmentreturns {
        if (($refusal = self::refusalFor($enrollment, $destination)) !== null) {
            throw ValidationException::withMessages(['destination' => $refusal]);
        }

        $previous = $enrollment->enrollmentStatus;
        $workflowId = $enrollment->enrollmentworkflow?->workflowId;
        $destinationStep = $destination->isWorkflowStep()
            ? self::stepFor($workflowId, $destination->officeId())
            : null;
        $admission = $destination === ReturnDestination::Admission
            ? self::approvedAdmission($enrollment)
            : null;

        // Read before the boxes are re-opened: the trail names the phase the record had actually
        // reached, not the last box left standing after the return.
        $originStepOrder = Workflowsteps::query()
            ->where('workflowId', $workflowId)
            ->where('stepStatus', WorkflowStepStatus::Completed->value)
            ->max('stepOrder');

        return DB::transaction(function () use (
            $enrollment,
            $actor,
            $destination,
            $category,
            $note,
            $expectedCorrection,
            $previous,
            $workflowId,
            $destinationStep,
            $admission,
            $originStepOrder,
        ): Enrollmentreturns {
            $this->stateMachine->transition(
                $enrollment,
                $destination->status(),
                $actor,
                "Returned to {$destination->label()} ({$category->label()}): {$note}",
            );

            // The stepper and the receiving desk's panel read the reason off the record, the way
            // ruling 14 left them; the trail row carries the same words plus everything §6.4 lists.
            $enrollment->update(['returnReason' => $note]);

            // A return to Admission re-opens every box, because nothing on this record's own form is
            // earlier than Admission; a return to the department re-opens its box and every later one.
            $reopenFrom = $destinationStep === null ? 1 : $destinationStep->stepOrder;

            Workflowsteps::query()
                ->where('workflowId', $workflowId)
                ->where('stepOrder', '>=', $reopenFrom)
                ->update([
                    'stepStatus' => WorkflowStepStatus::Pending->value,
                    'signedBy' => null,
                    'signedDate' => null,
                ]);

            $admission?->update(['admissionStatus' => AdmissionStatus::Pending->value]);

            return Enrollmentreturns::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'workflowId' => $workflowId,
                'originOfficeId' => $actor->officeId,
                'originStepOrder' => $originStepOrder,
                'destination' => $destination->value,
                'destinationOfficeId' => $destination->officeId(),
                'destinationStepOrder' => $destinationStep?->stepOrder,
                'returnCategory' => $category->value,
                'returnNote' => $note,
                'expectedCorrection' => $expectedCorrection,
                'previousStatus' => $previous->value,
                'newStatus' => $destination->status()->value,
                'returnedBy' => $actor->userId,
            ]);
        });
    }

    /**
     * Why this record may not be sent to this destination right now, or null when it may. The one
     * statement of the routing rules: the save and the screen both read it, so they cannot drift.
     */
    private static function refusalFor(Enrollments $enrollment, ReturnDestination $destination): ?string
    {
        if ($enrollment->enrollmentStatus === EnrollmentStatus::Dropped) {
            return 'A dropped record is terminal; the student comes back on a new enrollment, not this one.';
        }

        if ($enrollment->enrollmentStatus === $destination->status()) {
            return "This record is already returned to {$destination->label()}.";
        }

        $workflowId = $enrollment->enrollmentworkflow?->workflowId;

        if ($destination->isWorkflowStep()) {
            $step = self::stepFor($workflowId, $destination->officeId());

            if ($step === null) {
                return 'This record has no Department Evaluation box on its workflow form, so there is nothing to re-open there.';
            }

            if ($step->stepStatus === WorkflowStepStatus::Pending && ! self::hasAnySignature($workflowId)) {
                return 'Department Evaluation has not signed this record yet, so returning it there asks the same desk to act again.';
            }

            return null;
        }

        return self::approvedAdmission($enrollment) === null
            ? 'This record has no approved application to re-open, so Admission is not the desk that can correct it.'
            : null;
    }

    private static function stepFor(?int $workflowId, int $officeId): ?Workflowsteps
    {
        if ($workflowId === null) {
            return null;
        }

        return Workflowsteps::query()
            ->where('workflowId', $workflowId)
            ->where('officeId', $officeId)
            ->orderBy('stepOrder')
            ->first();
    }

    private static function hasAnySignature(?int $workflowId): bool
    {
        return $workflowId !== null && Workflowsteps::query()
            ->where('workflowId', $workflowId)
            ->where('stepStatus', WorkflowStepStatus::Completed->value)
            ->exists();
    }

    private static function approvedAdmission(Enrollments $enrollment): ?Admissions
    {
        return Admissions::query()
            ->where('studentId', $enrollment->studentId)
            ->where('courseId', $enrollment->courseId)
            ->where('termId', $enrollment->termId)
            ->where('admissionStatus', AdmissionStatus::Approved->value)
            ->first();
    }

    /**
     * The return this record is still answering, or null when it is not sitting under one.
     *
     * "Still answering" is the row whose `newStatus` is the status the record holds now: the moment
     * any desk moves the record the return has been acted on, so the notice goes away while the trail
     * row stays for the audit. Without this read the six facts §6.4 asks a return to carry would be
     * write-only — the desk that owes the correction would see only `enrollments.returnReason`, the
     * one sentence ruling 14 already stored, and `expectedCorrection` would name nothing on any screen.
     *
     * @return array{returnId: int, originOfficeName: ?string, destinationLabel: string, categoryLabel: string, note: string, expectedCorrection: string, returnedByName: ?string, returnedAt: \DateTimeInterface, previousStatus: string}|null
     */
    public static function openReturn(Enrollments $enrollment): ?array
    {
        $row = Enrollmentreturns::query()
            ->where('enrollmentId', $enrollment->enrollmentId)
            ->where('newStatus', $enrollment->enrollmentStatus->value)
            ->latest('returnedAt')
            ->latest('returnId')
            ->first();

        if ($row === null) {
            return null;
        }

        // The office that sent it, read from the reference table rather than from `OfficeId`, because
        // a return can come from a desk the enum does not name; null when the account held no office,
        // which the banner spells out instead of painting a desk that never signed.
        $originOfficeName = $row->originOfficeId === null
            ? null
            : Offices::query()->whereKey($row->originOfficeId)->value('officeName');

        return [
            'returnId' => $row->returnId,
            'originOfficeName' => $originOfficeName,
            'destinationLabel' => $row->destination->label(),
            'categoryLabel' => $row->returnCategory->label(),
            'note' => $row->returnNote,
            'expectedCorrection' => $row->expectedCorrection,
            'returnedByName' => $row->returnedByUser?->name,
            'returnedAt' => $row->returnedAt,
            'previousStatus' => $row->previousStatus,
        ];
    }

    /**
     * The record an application issued, for the Admission desk whose own page is about the
     * application rather than the enrollment (§6.4 asks that Admission be able to return too, and to
     * see what another desk sent back). Null only when the approval produced no live record, because
     * then there is genuinely nothing at this desk to read or to send.
     *
     * Two halves, deliberately decided apart. `openReturn` is what THIS desk owes — it is shown
     * whatever the account may do, because a record sent here by another desk is still the reason the
     * application sits back in the queue. `options` is the act, and it is null when the policy refuses
     * the return or the record's state offers no destination: a screen that offers an act the route
     * then refuses is the defect this file has retired three times over.
     *
     * The enrollment is resolved here rather than in the controller because the page binds an
     * application: nobody but this method knows which record the desk is looking at.
     *
     * @return array{enrollmentId: int, options: array|null, openReturn: array|null}|null
     */
    public static function admissionTarget(Admissions $admission, Staffusers $actor): ?array
    {
        $record = Enrollments::query()
            ->where('studentId', $admission->studentId)
            ->where('courseId', $admission->courseId)
            ->where('termId', $admission->termId)
            ->where('enrollmentStatus', '!=', EnrollmentStatus::Dropped->value)
            ->latest('enrollmentId')
            ->first();

        if ($record === null) {
            return null;
        }

        return [
            'enrollmentId' => $record->enrollmentId,
            'options' => app(EnrollmentReturnPolicy::class)->returnRecord($actor, $record)
                ? self::optionsFor($record)
                : null,
            'openReturn' => self::openReturn($record),
        ];
    }
}
