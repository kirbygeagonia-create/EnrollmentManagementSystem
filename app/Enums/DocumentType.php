<?php

namespace App\Enums;

enum DocumentType: string
{
    case SubjectLoad = 'subjectLoad';
    case ClassCard = 'classCard';
    case Certificate = 'certificate';
    case ClearanceSlip = 'clearanceSlip';
    case BlockSchedule = 'blockSchedule';
    case EnrollmentForm = 'enrollmentForm';
}
