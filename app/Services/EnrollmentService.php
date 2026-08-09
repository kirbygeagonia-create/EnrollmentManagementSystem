<?php

namespace App\Services;

use App\Models\Enrolledsubjects;
use App\Models\Enrollments;

class EnrollmentService
{
    /**
     * Determine the attempt number for a subject in a new enrollment.
     *
     * @param  int  $enrollmentId  The new enrollment ID
     * @param  int  $subjectId  The subject ID being proposed
     * @return array ['attempt' => int, 'originalId' => int|null]
     */
    public static function determineAttemptNumber(int $enrollmentId, int $subjectId): array
    {
        // Get the new enrollment to find studentId and academicTermId
        $enrollment = Enrollments::findOrFail($enrollmentId);
        $studentId = $enrollment->studentId;

        // Find all prior enrollments for this student with this subject (not dropped)
        // Join with enrollments to get the studentId, order by attempt_number desc
        $priorAttempt = Enrolledsubjects::query()
            ->join('enrollments', 'enrolledsubjects.enrollmentId', '=', 'enrollments.enrollmentId')
            ->where('enrollments.studentId', $studentId)
            ->where('enrolledsubjects.subjectId', $subjectId)
            ->where('enrolledsubjects.status', '!=', 'dropped')
            ->orderByDesc('enrolledsubjects.attempt_number')
            ->select('enrolledsubjects.*')
            ->first();

        if (! $priorAttempt) {
            return ['attempt' => 1, 'originalId' => null];
        }

        $nextAttempt = $priorAttempt->attempt_number + 1;

        // Find the FIRST attempt (attempt_number = 1) for this student/subject
        $firstAttempt = Enrolledsubjects::query()
            ->join('enrollments', 'enrolledsubjects.enrollmentId', '=', 'enrollments.enrollmentId')
            ->where('enrollments.studentId', $studentId)
            ->where('enrolledsubjects.subjectId', $subjectId)
            ->where('enrolledsubjects.status', '!=', 'dropped')
            ->where('enrolledsubjects.attempt_number', 1)
            ->select('enrolledsubjects.enrolledSubjectId')
            ->first();

        $originalId = $firstAttempt->enrolledSubjectId ?? $priorAttempt->enrolledSubjectId;

        return ['attempt' => $nextAttempt, 'originalId' => $originalId];
    }
}
