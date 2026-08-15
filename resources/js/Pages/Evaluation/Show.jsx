import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Badge, FormSection, Select, ConfirmDialog } from '@/Components/ui';
import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';

const studentTypeToneMap = {
    firstYear: 'info',
    continuing: 'success',
    transferee: 'warning',
    shifter: 'accent',
};

const enrollmentStatusToneMap = {
    pending: 'pending',
    evaluated: 'evaluated',
    assessed: 'assessed',
    paid: 'paid',
    enrolled: 'enrolled',
    dropped: 'dropped',
};

// Status banner styling per enrollment status
const statusBannerMap = {
    pending: {
        wrap: 'bg-warning-50 border-warning-200',
        chip: 'bg-warning-100 text-warning-800',
        title: 'Pending Evaluation',
        desc: 'This enrollment is awaiting subject proposal and evaluation signing.',
    },
    evaluated: {
        wrap: 'bg-info-50 border-info-200',
        chip: 'bg-info-100 text-info-800',
        title: 'Evaluated',
        desc: 'Evaluation is complete and ready for the enrollment workflow to be signed.',
    },
    assessed: {
        wrap: 'bg-accent-50 border-accent-200',
        chip: 'bg-accent-100 text-accent-800',
        title: 'Assessed',
        desc: 'This enrollment has been assessed. Proceed to accounting for payment.',
    },
    paid: {
        wrap: 'bg-success-50 border-success-200',
        chip: 'bg-success-100 text-success-800',
        title: 'Paid',
        desc: 'Fees have been settled. Proceed to enrollment finalization.',
    },
    enrolled: {
        wrap: 'bg-brand-50 border-brand-200',
        chip: 'bg-brand-700 text-white',
        title: 'Enrolled',
        desc: 'This student is fully enrolled for the term.',
    },
    dropped: {
        wrap: 'bg-danger-50 border-danger-200',
        chip: 'bg-danger-100 text-danger-800',
        title: 'Dropped',
        desc: 'This enrollment has been dropped.',
    },
};

export default function Show({ enrollment, curriculumSubjects }) {
    const [showConfirmSign, setShowConfirmSign] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const student = enrollment.student;
    const addresses = student?.addresses || [];
    const guardians = student?.guardians || [];
    const enrolledSubjects = enrollment.enrolledSubjects || [];

    const profileColumns = useMemo(() => [
        { key: 'subjectCode', label: 'Code', render: (row) => row.subject?.subjectCode || '—' },
        { key: 'subjectName', label: 'Subject', render: (row) => row.subject?.subjectName || '—' },
        { key: 'units', label: 'Units', render: (row) => {
            const lec = row.subject?.lectureUnits || 0;
            const lab = row.subject?.labUnits || 0;
            return lec + lab > 0 ? `${lec}/${lab}` : '—';
        }},
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={row.status === 'proposed' ? 'pending' : row.status === 'confirmed' ? 'success' : 'danger'}>
                {row.status?.charAt(0).toUpperCase() + row.status?.slice(1)}
            </Badge>
        )},
    ], []);

    const curriculumColumns = useMemo(() => [
        { key: 'subjectCode', label: 'Code', render: (row) => row.subject?.subjectCode || '—' },
        { key: 'subjectName', label: 'Subject', render: (row) => row.subject?.subjectName || '—' },
        { key: 'units', label: 'Units', render: (row) => {
            const lec = row.subject?.lectureUnits || 0;
            const lab = row.subject?.labUnits || 0;
            return lec + lab > 0 ? `${lec}/${lab}` : '—';
        }},
        { key: 'yearLevel', label: 'Year', render: (row) => row.yearLevel || '—' },
        { key: 'semesterOffered', label: 'Semester', render: (row) => row.semesterOffered || '—' },
    ], []);

    const handleCaptureProfile = (e) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);
        const data = Object.fromEntries(formData.entries());
        
        // Convert arrays
        const addresses = [];
        const guardians = [];
        
        for (const [key, value] of formData.entries()) {
            if (key.startsWith('addresses[')) {
                const match = key.match(/addresses\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    const idx = parseInt(match[1]);
                    const field = match[2];
                    if (!addresses[idx]) addresses[idx] = {};
                    addresses[idx][field] = value;
                }
            }
            if (key.startsWith('guardians[')) {
                const match = key.match(/guardians\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    const idx = parseInt(match[1]);
                    const field = match[2];
                    if (!guardians[idx]) guardians[idx] = {};
                    guardians[idx][field] = value;
                }
            }
        }
        
        data.addresses = addresses.filter(Boolean);
        data.guardians = guardians.filter(Boolean);
        
        router.put(route('evaluation.profile.capture', { enrollment: enrollment.enrollmentId }), data, {
            onSuccess: () => setIsSubmitting(false),
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
    };

    const handleProposeSubjects = (e) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);
        const subjects = [];
        
        for (const [key, value] of formData.entries()) {
            if (key.startsWith('subjects[')) {
                const match = key.match(/subjects\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    const idx = parseInt(match[1]);
                    const field = match[2];
                    if (!subjects[idx]) subjects[idx] = {};
                    subjects[idx][field] = value;
                }
            }
        }
        
        router.post(route('evaluation.subjects.propose', { enrollment: enrollment.enrollmentId }), { subjects: subjects.filter(Boolean) }, {
            onSuccess: () => setIsSubmitting(false),
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
    };

    const handleProcessCredits = (e) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);
        const credits = [];
        
        for (const [key, value] of formData.entries()) {
            if (key.startsWith('credits[')) {
                const match = key.match(/credits\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    const idx = parseInt(match[1]);
                    const field = match[2];
                    if (!credits[idx]) credits[idx] = {};
                    credits[idx][field] = value;
                }
            }
        }
        
        router.post(route('evaluation.credits.process', { enrollment: enrollment.enrollmentId }), { credits: credits.filter(Boolean) }, {
            onSuccess: () => setIsSubmitting(false),
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
    };

    const handleSign = () => {
        router.post(route('evaluation.sign', { enrollment: enrollment.enrollmentId }), {}, {
            onSuccess: () => {
                setShowConfirmSign(false);
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
    };

    const getAddress = (type) => addresses.find(a => a.addressType === type);
    const homeAddress = getAddress('home');
    const currentAddress = getAddress('current');
    const permanentAddress = getAddress('permanent');

    const formatAddress = (addr) => {
        if (!addr) return '—';
        const parts = [addr.houseBuildingNo, addr.street, addr.sitioPurok, addr.barangay, addr.cityMunicipality, addr.province, addr.zipCode, addr.country].filter(Boolean);
        return parts.join(', ') || '—';
    };

    const banner = statusBannerMap[enrollment.enrollmentStatus] || statusBannerMap.pending;

    const collegeInfo = useMemo(() => {
        const code = (enrollment.course?.courseCode || enrollment.course?.code || '').toUpperCase();
        const name = (enrollment.course?.courseName || enrollment.course?.name || '').toUpperCase();

        if (code.includes('IT') || code.includes('CS') || name.includes('INFORMATION') || name.includes('COMPUTER')) {
            return {
                logo: '/images/logos/college-of-information-and-communication-technology.jpg',
                collegeName: 'College of Information & Communications Technology',
            };
        }
        if (code.includes('AGRI') || code.includes('BSA') || name.includes('AGRICULTURE') || name.includes('FISHERIES')) {
            return {
                logo: '/images/logos/college-of-agriculture-and-fisheries.jpg',
                collegeName: 'College of Agriculture & Fisheries',
            };
        }
        if (code.includes('CRIM') || name.includes('CRIMINOLOGY') || name.includes('JUSTICE')) {
            return {
                logo: '/images/logos/college-of-criminal-justice-education.jpg',
                collegeName: 'College of Criminal Justice Education',
            };
        }
        if (code.includes('BA') || code.includes('HM') || code.includes('TM') || name.includes('BUSINESS') || name.includes('TOURISM') || name.includes('HOSPITALITY')) {
            return {
                logo: '/images/logos/college-of-business-and-good-governance.jpg',
                collegeName: 'College of Business & Good Governance',
            };
        }
        if (code.includes('ED') || name.includes('TEACHER') || name.includes('EDUCATION')) {
            return {
                logo: '/images/logos/college-of-teacher-education.jpg',
                collegeName: 'College of Teacher Education',
            };
        }
        if (code.includes('CE') || name.includes('ENGINEERING') || name.includes('CIVIL')) {
            return {
                logo: '/images/logos/department-of-civil-engineering.jpg',
                collegeName: 'Department of Civil Engineering',
            };
        }
        return {
            logo: '/images/logos/seait-logo.png',
            collegeName: 'Academic Department Evaluation Desk',
        };
    }, [enrollment.course]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Academic Evaluation — ${collegeInfo.collegeName}`}
                    subtitle={`${student?.firstName} ${student?.lastName} — ${enrollment.course?.name || '—'} (${enrollment.term?.name || 'Current Term'})`}
                    logo={collegeInfo.logo}
                    logoAlt={collegeInfo.collegeName}
                    phaseBadge="Phase 2 · Department Evaluation"
                    officeBadge="Office 4 · Academic Evaluation"
                />
            }
        >
            <Head title="Evaluation Details" />

            {/* Status Banner */}
            <div className={`mb-6 rounded-card border p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 ${banner.wrap}`}>
                <div className="flex items-center gap-3">
                    <span className={`inline-flex items-center justify-center h-10 w-10 rounded-xl ${banner.chip}`}>
                        {enrollment.enrollmentStatus === 'evaluated' ? (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        ) : enrollment.enrollmentStatus === 'enrolled' ? (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" /></svg>
                        ) : enrollment.enrollmentStatus === 'dropped' ? (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                        ) : (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        )}
                    </span>
                    <div>
                        <p className="font-semibold text-brand-900">{banner.title}</p>
                        <p className="text-sm text-brand-600">{banner.desc}</p>
                    </div>
                </div>
                <Badge tone={enrollmentStatusToneMap[enrollment.enrollmentStatus] || 'neutral'}>
                    {enrollment.enrollmentStatus?.charAt(0).toUpperCase() + enrollment.enrollmentStatus?.slice(1)}
                </Badge>
            </div>

            {/* Student Profile Card */}
            <Card title="Student Profile" subtitle="Demographic and academic information" className="mb-6">
                <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                    <FormSection label="School ID">
                        <dd className="text-brand-900 font-mono">{student?.schoolIdNumber || '—'}</dd>
                    </FormSection>
                    <FormSection label="Full Name">
                        <dd className="text-brand-900">
                            {student?.lastName}, {student?.firstName} {student?.middleName ? student.middleName.charAt(0) + '.' : ''} {student?.suffix || ''}
                        </dd>
                    </FormSection>
                    <FormSection label="Student Type">
                        <dd>
                            <Badge tone={studentTypeToneMap[enrollment.studentType] || 'neutral'}>
                                {enrollment.studentType?.replace(/([A-Z])/g, ' $1') || '—'}
                            </Badge>
                        </dd>
                    </FormSection>
                    <FormSection label="Course">
                        <dd className="text-brand-900">{enrollment.course?.name || '—'}</dd>
                    </FormSection>
                    <FormSection label="Major">
                        <dd className="text-brand-900">{enrollment.major?.name || '—'}</dd>
                    </FormSection>
                    <FormSection label="Year Level">
                        <dd className="text-brand-900">Year {enrollment.yearLevel || '—'}</dd>
                    </FormSection>
                    <FormSection label="Term">
                        <dd className="text-brand-900">{enrollment.term?.name || '—'} {enrollment.term?.academicYear?.year || ''}</dd>
                    </FormSection>
                    <FormSection label="Enrollment Status">
                        <dd>
                            <Badge tone={enrollmentStatusToneMap[enrollment.enrollmentStatus] || 'neutral'}>
                                {enrollment.enrollmentStatus?.charAt(0).toUpperCase() + enrollment.enrollmentStatus?.slice(1)}
                            </Badge>
                        </dd>
                    </FormSection>
                    <FormSection label="Academic Standing">
                        <dd className="text-brand-900">{enrollment.academicStanding?.charAt(0).toUpperCase() + enrollment.academicStanding?.slice(1) || '—'}</dd>
                    </FormSection>
                    <FormSection label="Gender">
                        <dd className="text-brand-900">{student?.gender?.charAt(0).toUpperCase() + student?.gender?.slice(1) || '—'}</dd>
                    </FormSection>
                    <FormSection label="Birthdate">
                        <dd className="text-brand-900">{student?.birthdate ? new Date(student.birthdate).toLocaleDateString('en-PH') : '—'}</dd>
                    </FormSection>
                    <FormSection label="Birthplace">
                        <dd className="text-brand-900">{student?.birthplace || '—'}</dd>
                    </FormSection>
                    <FormSection label="Citizenship">
                        <dd className="text-brand-900">{student?.citizenship || '—'}</dd>
                    </FormSection>
                    <FormSection label="Civil Status">
                        <dd className="text-brand-900">{student?.civilStatus?.charAt(0).toUpperCase() + student?.civilStatus?.slice(1) || '—'}</dd>
                    </FormSection>
                    <FormSection label="Religion">
                        <dd className="text-brand-900">{student?.religion?.religionName || '—'}</dd>
                    </FormSection>
                    <FormSection label="Contact Number">
                        <dd className="text-brand-900">{student?.contactNumber || '—'}</dd>
                    </FormSection>
                    <FormSection label="Email">
                        <dd className="text-brand-900">{student?.email || '—'}</dd>
                    </FormSection>
                </dl>

                {/* Addresses */}
                <div className="mt-6 pt-6 border-t border-brand-100">
                    <h4 className="font-medium text-brand-900 mb-3">Addresses</h4>
                    <dl className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormSection label="Home Address">
                            <dd className="text-brand-600">{formatAddress(homeAddress)}</dd>
                        </FormSection>
                        <FormSection label="Current Address">
                            <dd className="text-brand-600">{formatAddress(currentAddress)}</dd>
                        </FormSection>
                        <FormSection label="Permanent Address">
                            <dd className="text-brand-600">{formatAddress(permanentAddress)}</dd>
                        </FormSection>
                    </dl>
                </div>

                {/* Guardians */}
                {guardians.length > 0 && (
                    <div className="mt-6 pt-6 border-t border-brand-100">
                        <h4 className="font-medium text-brand-900 mb-3">Guardians</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {guardians.map((guardian, idx) => (
                                <div key={idx} className="border border-brand-200 rounded-card p-4">
                                    <p className="font-medium text-brand-900">{guardian.fullName}</p>
                                    <p className="text-sm text-brand-600">{guardian.relationship?.charAt(0).toUpperCase() + guardian.relationship?.slice(1)}</p>
                                    <p className="text-sm text-brand-600">{guardian.contactNumber}</p>
                                    {guardian.email && <p className="text-sm text-brand-600">{guardian.email}</p>}
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {guardian.isEmergencyContact && <span className="badge badge-info">Emergency Contact</span>}
                                        {guardian.isAuthorizedToActOnBehalf && <span className="badge badge-warning">Authorized Representative</span>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </Card>

            {/* Proposed Subjects */}
            <Card title="Proposed Subject Load" subtitle="Subjects currently proposed for this enrollment" className="mb-6">
                {enrolledSubjects.length > 0 ? (
                    <DataTable
                        columns={profileColumns}
                        rows={enrolledSubjects}
                        emptyMessage="No proposed subjects"
                    />
                ) : (
                    <p className="text-brand-500 text-center py-8">No subjects proposed yet.</p>
                )}
            </Card>

            {/* Curriculum Subjects */}
            <Card title="Curriculum Subjects" subtitle="Available subjects from curriculum for this year level and semester" className="mb-6">
                {curriculumSubjects.length > 0 ? (
                    <DataTable
                        columns={curriculumColumns}
                        rows={curriculumSubjects}
                        emptyMessage="No curriculum subjects found"
                    />
                ) : (
                    <p className="text-brand-500 text-center py-8">No curriculum subjects available.</p>
                )}
            </Card>

            {/* Credit Evaluation (for transferees/shifters) */}
            {(enrollment.studentType === 'transferee' || enrollment.studentType === 'shifter') && (
                <Card title="Credit Evaluation" subtitle="Process transfer credits from previous institution" className="mb-6">
                    <form onSubmit={handleProcessCredits} className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FormSection label="Previous Subject Name" required>
                                <input type="text" name="credits[0][previousSubjectName]" className="form-input" required />
                            </FormSection>
                            <FormSection label="Credited To Subject" required>
                                <Select
                                    name="credits[0][creditedToSubjectId]"
                                    options={curriculumSubjects.map(cs => ({ value: cs.subject?.subjectId, label: `${cs.subject?.subjectCode} - ${cs.subject?.subjectName}` })).filter(o => o.value)}
                                    placeholder="Select subject"
                                    required
                                />
                            </FormSection>
                            <FormSection label="Credited Units" required>
                                <input type="number" name="credits[0][creditedUnits]" className="form-input" min="0" step="0.5" required />
                            </FormSection>
                            <FormSection label="Institution Name" required>
                                <input type="text" name="credits[0][institutionName]" className="form-input" required />
                            </FormSection>
                            <FormSection label="Institution Type" required>
                                <Select
                                    name="credits[0][institutionType]"
                                    options={[
                                        { value: 'elementary', label: 'Elementary' },
                                        { value: 'secondary', label: 'Secondary' },
                                        { value: 'seniorHigh', label: 'Senior High' },
                                        { value: 'college', label: 'College' },
                                        { value: 'graduate', label: 'Graduate' },
                                    ]}
                                    placeholder="Select type"
                                    required
                                />
                            </FormSection>
                            <FormSection label="Grade">
                                <input type="number" name="credits[0][grade]" className="form-input" step="0.01" />
                            </FormSection>
                            <FormSection label="Remarks">
                                <textarea name="credits[0][remarks]" className="form-input form-textarea" rows="2" />
                            </FormSection>
                        </div>
                        <div className="flex justify-end">
                            <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
                                {isSubmitting ? 'Processing...' : 'Process Credits'}
                            </button>
                        </div>
                    </form>
                </Card>
            )}

            {/* Actions */}
            <Card title="Actions" className="mb-6">
                <div className="flex flex-wrap gap-3">
                    {/* Capture Profile */}
                    <form onSubmit={handleCaptureProfile} className="inline">
                        <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
                            {isSubmitting ? 'Saving...' : 'Capture Profile'}
                        </button>
                    </form>

                    {/* Propose Subjects */}
                    <form onSubmit={handleProposeSubjects} className="inline">
                        <button type="submit" className="btn btn-secondary" disabled={isSubmitting || enrolledSubjects.length > 0}>
                            {isSubmitting ? 'Proposing...' : 'Propose Subjects'}
                        </button>
                    </form>

                    {/* Sign Evaluation */}
                    <button
                        type="button"
                        className="btn btn-accent"
                        onClick={() => setShowConfirmSign(true)}
                        disabled={isSubmitting || enrollment.enrollmentStatus !== 'evaluated'}
                    >
                        Sign Evaluation
                    </button>
                </div>

                {/* Confirm Dialog for Sign */}
                <ConfirmDialog
                    show={showConfirmSign}
                    onClose={() => setShowConfirmSign(false)}
                    onConfirm={handleSign}
                    title="Sign Evaluation"
                    message="This will sign the evaluation and create the enrollment workflow. Are you sure you want to proceed?"
                    confirmText="Sign"
                    variant="warning"
                    loading={isSubmitting}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
