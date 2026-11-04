<?php

namespace App\Support;

use App\Enums\OfficeId;
use App\Models\Enrollments;
use App\Services\WorkflowService;

/**
 * The four parts prompt §8 asks a notice to carry, assembled in one place.
 *
 * `Documentation/EMS Complete Documentation.docx` §28.3 G-35 recorded that the bell renders a sentence and a
 * moment: prompt §8 asks for "an intelligible title, action required, record reference, timestamp, and protected
 * deep link". Measured across three surfaces (the listeners' payload literal, the bell's own row block, and the
 * rows live `ems` holds), the title, the action and the link were absent everywhere, and the record identifier was
 * present in the payload but never painted — and an internal `enrollmentId` is not a reference a person reads. The
 * timestamp already exists as `notifications.created_at`, which the screen formats through the school's clock, so
 * this class deliberately does not restate it: two answers to "when" is the defect this repository refuses
 * everywhere else.
 *
 * A null in any slot means the fact is not known, and the screen dashes it. Nothing here invents a title, a
 * destination, or a student number.
 */
class NoticeFacts
{
    /**
     * @return array{title: string, actionRequired: ?string, recordReference: ?string, link: array{name: string, record: array{name: string, id: int}|null}|null}
     */
    public static function forEnrollment(Enrollments $enrollment, string $title, ?int $waitingOnOfficeId = null): array
    {
        $reference = self::recordReference($enrollment);

        return [
            'title' => $title,
            'actionRequired' => self::actionRequired($waitingOnOfficeId),
            'recordReference' => $reference,
            'link' => self::link($waitingOnOfficeId, $enrollment),
        ];
    }

    /**
     * The number a window reads, not the key a table joins on: the school id, the program code, and the year level
     * spelled by the shared vocabulary helper rather than by hand — a level written out in the pre-ruling spelling
     * here would restart
     * the four-vocabulary defect §28.3 records.
     */
    public static function recordReference(Enrollments $enrollment): ?string
    {
        $student = $enrollment->student;
        if ($student === null) {
            return null;
        }

        $parts = array_values(array_filter([
            $student->schoolIdNumber,
            $enrollment->course?->courseCode,
            // No null branch here: `enrollments.yearLevel` is NOT NULL, and the model casts it to int, so asking
            // whether it is null is a guard that can never fire. `YearLevel::format()` still accepts a missing
            // level, so a record without one cannot produce a hand-spelled label.
            YearLevel::format($enrollment->yearLevel),
        ], static fn (?string $part): bool => $part !== null && $part !== ''));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Where the record is waiting, in the office's own words — read from `WorkflowService::stepLabels()`, the map
     * the stepper and the bell already share, rather than a second list of office names. Null when no office holds
     * the next box or the map does not name it: "nothing" is the honest sentence rather than a placeholder.
     */
    public static function actionRequired(?int $waitingOnOfficeId): ?string
    {
        if ($waitingOnOfficeId === null) {
            return null;
        }

        $labels = WorkflowService::stepLabels();
        $label = $labels[$waitingOnOfficeId] ?? null;

        return $label === null ? null : "Waiting on {$label}";
    }

    /**
     * Where the notice takes the desk: the record itself when this office has a page keyed on that enrollment, and
     * the office's queue as the destination that always exists.
     *
     * Both halves are route NAMES plus the enrollment's own key — never a path taken from the request, so a notice
     * cannot be pointed at another office's page. The queue stays parameterless, which is why it needs no id; a
     * record page is reached by the one key it asks for, and where the desk's page is keyed on something a notice
     * about an enrollment does not hold (a fee sheet, a timetable block, an application, a shift request) the
     * record half is null and the bell offers the queue. That is the honest answer rather than a redirect invented
     * through a join, because what makes a deep link protected is the policy behind the destination, not the link.
     *
     * @return array{name: string, record: array{name: string, id: int}|null}|null
     */
    public static function link(?int $waitingOnOfficeId, Enrollments $enrollment): ?array
    {
        $office = $waitingOnOfficeId === null ? null : OfficeId::tryFrom($waitingOnOfficeId);
        $name = $office?->deskRouteName();

        if ($name === null) {
            return null;
        }

        $record = $office->recordRouteName();

        return [
            'name' => $name,
            'record' => $record === null ? null : ['name' => $record, 'id' => (int) $enrollment->enrollmentId],
        ];
    }
}
