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
// validated = accent (SEAIT orange) — distinct from released's green, the
// ID desk's own intent; 'seait' is not a Badge tone and fell back to neutral.
export const idRequestStatusTone = {
    pending: 'pending',
    validated: 'accent',
    released: 'enrolled',
    cancelled: 'danger',
};
