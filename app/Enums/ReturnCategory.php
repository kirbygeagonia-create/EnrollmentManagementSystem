<?php

namespace App\Enums;

/**
 * Why a record was sent back (development prompt §6.4, gap G-10).
 *
 * §6.4 requires that every return carries a reason *category* beside its explanatory note, because
 * "the system must know which phase caused the problem and route the record to that phase" — a free
 * text alone cannot be counted, filtered, or compared against the destination the desk chose. Four
 * values, ruled by the owner on 2026-10-26: the three that name a missing or wrong paper, a failed
 * or unadopted examination, and money or a grant that has not settled, plus the one that is not any
 * desk's fault at all — the load the record was built from does not match what the student must take.
 *
 * The category does not decide the destination. A desk may route a `missingDocuments` return to
 * Department Evaluation as well as to Admission; the pair is what makes the trail readable, which is
 * why both are stored rather than deriving one from the other.
 */
enum ReturnCategory: string
{
    case MissingDocuments = 'missingDocuments';
    case ExaminationFailure = 'examinationFailure';
    case AssessmentOrFeeHold = 'assessmentOrFeeHold';
    case CurriculumSectionMismatch = 'curriculumSectionMismatch';

    public function label(): string
    {
        return match ($this) {
            self::MissingDocuments => 'Missing documents',
            self::ExaminationFailure => 'Examination failure',
            self::AssessmentOrFeeHold => 'Assessment or fee hold',
            self::CurriculumSectionMismatch => 'Curriculum or section mismatch',
        };
    }

    /** Every value, in the order a desk lists them. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
