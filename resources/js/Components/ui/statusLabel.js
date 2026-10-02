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

// A year level is a level, not a rank. Six pages each carried their own
// getYearSuffix and printed four vocabularies for one number — "1st Year", a
// bare "1st", "Year 1", and the raw digit — so the printed paper disagreed with
// the picker the evaluator used to record it. One formatter, matching that
// picker, and it names the empty case instead of rendering "th Year".
export function formatYearLevel(level) {
    return level ? `Year ${level}` : '—';
}

// The seven gates RegistrarController::checklist() enforces, in the order a
// record clears them. Key order is the signing order, and the queue and the
// desk both read it from here so neither can rename a gate the other still shows.
export const registrarGateLabels = {
    evaluation_signed: 'Dept. Evaluation Signed',
    documents_verified: 'Admission Documents Verified',
    prerequisites_met: 'Subject Prerequisites Met',
    assessment_completed: 'Assessment Computed',
    payment_completed: 'Cashier Payment Settled',
    clearance_verified: 'Campus Clearance Verified',
    registrarApprovalPending: 'Registrar Ready',
};

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
