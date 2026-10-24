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

// A year level is spelled the way the school's own papers spell it: an ordinal, "2nd Year". Ruled
// 2026-10-24. Six pages once carried their own getYearSuffix and printed four vocabularies for one
// number, so the fix was one formatter — but the vocabulary it chose was "Year 2", which is not what the
// school writes: its own clearance slip reads "2nd Year", and App\Enums\Semester already spells the term
// "1st" / "2nd" on the same paper. This changes which vocabulary the single definition picks, not how
// many there are: App\Support\YearLevel carries the same rule on the server, and
// tests/Feature/YearLevelVocabularyTest.php pins the two against each other and refuses any surface that
// spells a level by hand, so a paper cannot disagree with the picker that recorded the level.
export function formatYearLevel(level) {
    if (!level) return '—';
    return `${ordinalSuffix(level)} Year`;
}

// The five levels a desk may record. Nothing else may spell a level, so the option a desk chooses is
// word-for-word what the paper prints back — five pick-lists used to carry their own `Year ${n}` labels
// while three sibling tables already read formatYearLevel, which is one number in two vocabularies on
// the same screen.
export const YEAR_LEVELS = [1, 2, 3, 4, 5];

export function yearLevelChoices(levels = YEAR_LEVELS) {
    return levels.map((level) => ({ value: level, label: formatYearLevel(level) }));
}

function ordinalSuffix(level) {
    // 11th–13th are the English exceptions; nothing else in a school's range reaches them.
    if (level % 100 >= 11 && level % 100 <= 13) return `${level}th`;

    return `${level}${({ 1: 'st', 2: 'nd', 3: 'rd' })[level % 10] ?? 'th'}`;
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

// The workflow statuses whose badge is simply named after them: .badge-pending, .badge-approved,
// .badge-rejected, .badge-waived, .badge-incomplete. Six desk files each carried their own copy of
// exactly this list, which is how one word can end up two colours after someone edits one copy.
// Anything outside it reads neutral, the same fallback those maps had.
const identityStatusTones = ['pending', 'approved', 'rejected', 'waived', 'incomplete'];

export const statusToneFor = (status) => (
    identityStatusTones.includes(status) ? status : 'neutral'
);
