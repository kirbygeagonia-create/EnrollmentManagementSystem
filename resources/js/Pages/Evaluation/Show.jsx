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

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Evaluation Details"
                    subtitle={`${student?.firstName} ${student?.lastName} — ${enrollment.course?.name || '—'}`}
                />
            }
        >
            <Head title="Evaluation Details" />

            {/* Student Profile Card */}
            <Card title="Student Profile" subtitle="Demographic and academic information" className="mb-6">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <FormSection label="School ID">
                        <p className="text-brand-900 font-mono">{student?.schoolIdNumber || '—'}</p>
                    </FormSection>
                    <FormSection label="Full Name">
                        <p className="text-brand-900">
                            {student?.lastName}, {student?.firstName} {student?.middleName ? student.middleName.charAt(0) + '.' : ''} {student?.suffix || ''}
                        </p>
                    </FormSection>
                    <FormSection label="Student Type">
                        <Badge tone={studentTypeToneMap[enrollment.studentType] || 'neutral'}>
                            {enrollment.studentType?.replace(/([A-Z])/g, ' $1') || '—'}
                        </Badge>
                    </FormSection>
                    <FormSection label="Course">
                        <p className="text-brand-900">{enrollment.course?.name || '—'}</p>
                    </FormSection>
                    <FormSection label="Major">
                        <p className="text-brand-900">{enrollment.major?.name || '—'}</p>
                    </FormSection>
                    <FormSection label="Year Level">
                        <p className="text-brand-900">Year {enrollment.yearLevel || '—'}</p>
                    </FormSection>
                    <FormSection label="Term">
                        <p className="text-brand-900">{enrollment.term?.name || '—'} {enrollment.term?.academicYear?.year || ''}</p>
                    </FormSection>
                    <FormSection label="Enrollment Status">
                        <Badge tone={enrollmentStatusToneMap[enrollment.enrollmentStatus] || 'neutral'}>
                            {enrollment.enrollmentStatus?.charAt(0).toUpperCase() + enrollment.enrollmentStatus?.slice(1)}
                        </Badge>
                    </FormSection>
                    <FormSection label="Academic Standing">
                        <p className="text-brand-900">{enrollment.academicStanding?.charAt(0).toUpperCase() + enrollment.academicStanding?.slice(1) || '—'}</p>
                    </FormSection>
                    <FormSection label="Gender">
                        <p className="text-brand-900">{student?.gender?.charAt(0).toUpperCase() + student?.gender?.slice(1) || '—'}</p>
                    </FormSection>
                    <FormSection label="Birthdate">
                        <p className="text-brand-900">{student?.birthdate ? new Date(student.birthdate).toLocaleDateString('en-PH') : '—'}</p>
                    </FormSection>
                    <FormSection label="Birthplace">
                        <p className="text-brand-900">{student?.birthplace || '—'}</p>
                    </FormSection>
                    <FormSection label="Citizenship">
                        <p className="text-brand-900">{student?.citizenship || '—'}</p>
                    </FormSection>
                    <FormSection label="Civil Status">
                        <p className="text-brand-900">{student?.civilStatus?.charAt(0).toUpperCase() + student?.civilStatus?.slice(1) || '—'}</p>
                    </FormSection>
                    <FormSection label="Religion">
                        <p className="text-brand-900">{student?.religion?.religionName || '—'}</p>
                    </FormSection>
                    <FormSection label="Contact Number">
                        <p className="text-brand-900">{student?.contactNumber || '—'}</p>
                    </FormSection>
                    <FormSection label="Email">
                        <p className="text-brand-900">{student?.email || '—'}</p>
                    </FormSection>
                </div>

                {/* Addresses */}
                <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <FormSection label="Home Address">
                        <p className="text-brand-600">{formatAddress(homeAddress)}</p>
                    </FormSection>
                    <FormSection label="Current Address">
                        <p className="text-brand-600">{formatAddress(currentAddress)}</p>
                    </FormSection>
                    <FormSection label="Permanent Address">
                        <p className="text-brand-600">{formatAddress(permanentAddress)}</p>
                    </FormSection>
                </div>

                {/* Guardians */}
                {guardians.length > 0 && (
                    <div className="mt-6">
                        <h4 className="font-medium text-brand-900 mb-3">Guardians</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {guardians.map((guardian, idx) => (
                                <div key={idx} className="border border-brand-200 rounded-card p-4">
                                    <p className="font-medium text-brand-900">{guardian.fullName}</p>
                                    <p className="text-sm text-brand-600">{guardian.relationship?.charAt(0).toUpperCase() + guardian.relationship?.slice(1)}</p>
                                    <p className="text-sm text-brand-600">{guardian.contactNumber}</p>
                                    {guardian.email && <p className="text-sm text-brand-600">{guardian.email}</p>}
                                    {guardian.isEmergencyContact && <span className="badge badge-info mt-2 inline-block">Emergency Contact</span>}
                                    {guardian.isAuthorizedToActOnBehalf && <span className="badge badge-warning mt-2 inline-block ml-2">Authorized Representative</span>}
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
                    variant="accent"
                    loading={isSubmitting}
                />
            </Card>
        </AuthenticatedLayout>
    );
}