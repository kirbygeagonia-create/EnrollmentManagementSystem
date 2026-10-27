<?php

namespace App\Enums;

/**
 * Where a returned record is sent (development prompt §6.4, gap G-10).
 *
 * The owner ruled two destinations, and they are not the same kind of place. Department Evaluation
 * is a box on the enrollment's own workflow form, so returning there re-opens a step the record has
 * already passed through. Admission is not a box at all — the enrollment exists only because an
 * admission was approved — so a return there re-opens the *application* and parks the enrollment in
 * `returnedToAdmission` until the Admission desk acts again.
 *
 * One place maps a destination to the office that owns it, so a return row and the screen that reads
 * it cannot disagree about which desk must act next (§6.4's last requirement).
 */
enum ReturnDestination: string
{
    case Admission = 'admission';
    case DepartmentEvaluation = 'departmentEvaluation';

    public function officeId(): int
    {
        return match ($this) {
            self::Admission => OfficeId::Admission->value,
            self::DepartmentEvaluation => OfficeId::Guidance->value,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Admission => 'Admission',
            self::DepartmentEvaluation => 'Department Evaluation',
        };
    }

    /**
     * The enrollment status a return to this destination puts the record into.
     *
     * Department Evaluation reuses the state ruling 14 already established, so the Evaluation desk's
     * queue and the stepper keep working; Admission needs its own, because a record sent to a desk
     * that holds no box must not read as though the department simply had not evaluated it yet.
     */
    public function status(): EnrollmentStatus
    {
        return match ($this) {
            self::Admission => EnrollmentStatus::ReturnedToAdmission,
            self::DepartmentEvaluation => EnrollmentStatus::ReturnedToEvaluation,
        };
    }

    /** True when the destination is a box on the workflow form, and so has a step to re-open. */
    public function isWorkflowStep(): bool
    {
        return $this === self::DepartmentEvaluation;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
