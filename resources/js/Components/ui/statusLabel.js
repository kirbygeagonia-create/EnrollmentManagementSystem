// One source of truth for enrollment-status presentation. Before this, four
// pages each carried their own tone map (three missing returnedToEvaluation)
// and three different capitalisers, so the same status rendered as
// "returnedToEvaluation", "ReturnedToEvaluation", and "Returnedtoevaluation".
export function formatStatusLabel(status) {
    if (!status) return '—';
    return String(status)
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, (c) => c.toUpperCase());
}

// Keys match App\Enums\EnrollmentStatus values; values are Badge tones.
export const enrollmentStatusTone = {
    pending: 'pending',
    evaluated: 'evaluated',
    assessed: 'assessed',
    paid: 'paid',
    returnedToEvaluation: 'warning',
    enrolled: 'enrolled',
    dropped: 'dropped',
};

// Keys match App\Enums\StudentType values (enrollments.studentType) and
// Admissions.applicantType. These tones came from the Evaluation queue, which
// was the only desk already showing the type; the other queues now share it.
export const studentTypeTone = {
    firstYear: 'info',
    continuing: 'success',
    transferee: 'warning',
    shifter: 'accent',
};

// Keys match App\Enums\AcademicStanding values.
const academicStandingTone = {
    regular: 'success',
    irregular: 'warning',
};

// Standing is only recorded when the evaluating department proposes it, so most
// queues legitimately hold none yet. Render that as an explicit state instead of
// a blank cell, which reads as broken data.
export function academicStandingLabel(standing) {
    return standing ? formatStatusLabel(standing) : 'Not yet decided';
}

export function academicStandingToneFor(standing) {
    return standing ? (academicStandingTone[standing] || 'neutral') : 'pending';
}

// Keys match App\Enums\IdRequestReason values; labels match the ID desk's
// own vocabulary (IDController::show maps the same cases).
export const idRequestReasonLabel = {
    newStudent: 'New Student',
    shifted: 'Shifted Program',
    lost: 'Lost Replacement',
    replaced: 'Damaged Replacement',
    renewed: 'Annual Renewal',
};

// Keys match App\Enums\IdRequestStatus values; values are Badge tones.
// validated = accent (SEAIT orange) — the ID desk's own intent; 'seait' is
// not a Badge tone and fell back to neutral.
export const idRequestStatusTone = {
    pending: 'pending',
    validated: 'accent',
    cancelled: 'danger',
};
