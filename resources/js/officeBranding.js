// Account-bound office branding (refinement pass item 1).
//
// Office identity comes from the signed-in account (user.office.officeId),
// not from per-page logos — every desk page renders a clean header and the
// office crest lives on the account avatar in the top bar.
//
// Only offices with a real crest asset are mapped; offices without one
// (Registrar, Accounting, Blocking, Admission, Academic, ID Office) fall
// back to the staff initials avatar.

export const officeLogoMap = {
    3: '/images/logos/scholarship.jpg', // Scholarship & Financial Aid
    4: '/images/logos/guidance-office.jpg', // Guidance Services & Testing Center
    11: '/images/logos/clinic.jpg', // School Clinic
};

export function officeLogoFor(officeId) {
    return officeLogoMap[officeId] || null;
}

// College crest per course unit. Keys are the official Academicunits IDs the
// DevReferenceDataSeeder seeds (unitType 'college'); the unit NAME is never
// mapped here — it is backend data (course.unit.unitName), so the UI can't
// drift from the seeded baseline the way the old per-page keyword heuristics
// did (Evaluation/Show and Students/Show disagreed, and both hardcoded names
// the seeder guards).
export const collegeLogoMap = {
    1: '/images/logos/college-of-agriculture-and-fisheries.jpg',
    2: '/images/logos/college-of-criminal-justice-education.jpg',
    3: '/images/logos/college-of-business-and-good-governance.jpg',
    4: '/images/logos/college-of-information-and-communication-technology.jpg',
    5: '/images/logos/department-of-civil-engineering.jpg',
    6: '/images/logos/college-of-teacher-education.jpg',
};

export function collegeLogoFor(unitId) {
    return collegeLogoMap[unitId] || '/images/logos/seait-logo.png';
}
