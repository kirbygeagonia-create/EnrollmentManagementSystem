import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, Modal, FormSection, Select, RadioCards, StatCard, formatStatusLabel, enrollmentStatusTone, studentTypeTone, academicStandingLabel, academicStandingToneFor, formatYearLevel } from '@/Components/ui';
import { useState, useMemo } from 'react';

export default function Index({ enrollments, filters = {}, canIssueEnrollment = false, returningStudents = [], terms = [], courses = [] }) {
    const [search, setSearch] = useState(filters.search || '');
    const [issueOpen, setIssueOpen] = useState(false);

    // Ruling 2 (G-1): the returning student's cycle starts at this desk, so the desk
    // issues the enrollment. The list is only ever students who repeat a term — a
    // student with no earlier term belongs at Admission.
    const issueForm = useForm({
        studentId: '',
        termId: '',
        courseId: '',
        majorId: '',
        yearLevel: '',
        studentType: 'continuing',
    });

    const studentOptions = useMemo(() => returningStudents.map((s) => {
        const last = s.enrollments?.[0];
        return {
            value: s.studentId,
            label: `${s.lastName}, ${s.firstName} ${s.schoolIdNumber}${last?.course ? ` · ${last.course.courseCode}` : ''}`,
        };
    }), [returningStudents]);

    const termOptions = useMemo(() => terms.map((t) => ({
        value: t.termId,
        label: `${t.academicYear?.yearLabel || ''} ${t.semester}`,
    })), [terms]);

    const courseOptions = useMemo(() => courses.map((c) => ({
        value: c.courseId,
        label: `${c.courseCode} — ${c.courseName}`,
    })), [courses]);

    // Choosing the student carries forward what the school already knows: their last
    // program, and the year level their record implies (G-2 — completed years, counted by
    // the server). The desk still edits the level before issuing, because placement is the
    // department's call; what it no longer has to do is remember the number.
    const onStudentPick = (studentId) => {
        issueForm.setData('studentId', studentId);
        const student = returningStudents.find((s) => s.studentId === studentId);
        const last = student?.enrollments?.[0];

        if (last) {
            issueForm.setData((data) => ({
                ...data,
                courseId: last.courseId || data.courseId,
                majorId: last.majorId || '',
                yearLevel: student.derivedYearLevel || last.yearLevel || data.yearLevel,
            }));
        }
    };

    const submitIssue = (e) => {
        e.preventDefault();
        issueForm.post(route('evaluation.store'), {
            preserveScroll: true,
            onSuccess: () => setIssueOpen(false),
        });
    };

    // Derive quick stats from the full paginator payload (if available)
    const stats = useMemo(() => {
        const rows = enrollments?.data || [];
        return {
            total: enrollments?.total ?? rows.length,
            pending: rows.filter((r) => r.enrollmentStatus === 'pending').length,
            evaluated: rows.filter((r) => r.enrollmentStatus === 'evaluated').length,
            transferees: rows.filter((r) => r.studentType === 'transferee' || r.studentType === 'shifter').length,
        };
    }, [enrollments]);

    const columns = useMemo(() => [
        { key: 'student.schoolIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name', render: (row) => (
            row.student ? `${row.student.lastName}, ${row.student.firstName}` : '—'
        )},
        { key: 'course', label: 'Course', render: (row) => row.course?.courseName || '—' },
        { key: 'yearLevel', label: 'Year Level', render: (row) => formatYearLevel(row.yearLevel) },
        { key: 'studentType', label: 'Student Type', render: (row) => (
            <Badge tone={studentTypeTone[row.studentType] || 'neutral'}>
                {formatStatusLabel(row.studentType)}
            </Badge>
        )},
        { key: 'academicStanding', label: 'Proposed Standing', render: (row) => (
            <Badge tone={academicStandingToneFor(row.academicStanding)}>
                {academicStandingLabel(row.academicStanding)}
            </Badge>
        )},
        { key: 'enrollmentStatus', label: 'Status', render: (row) => (
            <Badge tone={enrollmentStatusTone[row.enrollmentStatus] || 'neutral'}>
                {formatStatusLabel(row.enrollmentStatus)}
            </Badge>
        )},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('evaluation.index'), {
            search: search || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('evaluation.show', { enrollment: row.enrollmentId })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span className="hidden sm:inline">View</span>
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Academic Department Evaluation Desk"
                    subtitle="Capture student demographic profiles (BR32), evaluate transfer credits, and propose curriculum subject loads"
                    phaseBadge="Phase 2 · Department Evaluation"
                    officeBadge="Office 4 · Academic Evaluation Desk"
                    actions={canIssueEnrollment ? (
                        <button type="button" className="btn btn-primary" onClick={() => setIssueOpen(true)}>
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Issue Enrollment Form
                        </button>
                    ) : null}
                />
            }
        >
            <Head title="Evaluation Queue" />

            {/* Quick Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <div className="animate-slide-up" style={{ animationDelay: '0ms' }}>
                    <StatCard
                        compact
                        label="In Queue"
                        value={stats.total}
                        iconBg="seait"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                    />
                </div>
                <div className="animate-slide-up" style={{ animationDelay: '60ms' }}>
                    <StatCard
                        compact
                        label="Pending Evaluation"
                        value={stats.pending}
                        iconBg="warning"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                    />
                </div>
                <div className="animate-slide-up" style={{ animationDelay: '120ms' }}>
                    <StatCard
                        compact
                        label="Evaluated"
                        value={stats.evaluated}
                        iconBg="success"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                            </svg>
                        }
                    />
                </div>
                <div className="animate-slide-up" style={{ animationDelay: '180ms' }}>
                    <StatCard
                        compact
                        label="Transferees / Shifters"
                        value={stats.transferees}
                        iconBg="info"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                            </svg>
                        }
                    />
                </div>
            </div>

            {/* Filter Bar */}
            <div className="animate-slide-up" style={{ animationDelay: '120ms' }}>
                <FilterBar onSubmit={handleFilter}>
                    <FilterBarField label="Search">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name, ID number..."
                            className="form-input"
                        />
                    </FilterBarField>
                </FilterBar>
            </div>

            {/* Data Table */}
            <div className="animate-slide-up" style={{ animationDelay: '180ms' }}>
                <Card>
                    {enrollments?.data?.length > 0 ? (
                        <>
                            <DataTable
                                columns={columns}
                                rows={enrollments.data}
                                children={renderActions}
                                emptyMessage="No enrollments pending evaluation"
                            />
                            <div className="mt-4">
                                <Pagination paginator={enrollments} />
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title="No enrollments found"
                            message={search ? 'Try adjusting your search to find matching records.' : 'No enrollments are currently pending evaluation.'}
                        />
                    )}
                </Card>
            </div>
            {/* Issue Enrollment Form — ruling 2 (G-1). The returning student's term starts
                here; they do not pass Admission, the entrance examination or the admission
                decision again. */}
            <Modal
                show={issueOpen}
                onClose={() => setIssueOpen(false)}
                title="Issue Enrollment Form"
                subtitle="For a student who has already completed a term at SEAIT"
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" className="btn btn-secondary" onClick={() => setIssueOpen(false)} disabled={issueForm.processing}>
                            Cancel
                        </button>
                        <button type="button" className="btn btn-primary" onClick={submitIssue} disabled={issueForm.processing}>
                            {issueForm.processing ? 'Issuing…' : 'Issue and Open the Form'}
                        </button>
                    </div>
                }
            >
                <form onSubmit={submitIssue} className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormSection label="Returning student" required error={issueForm.errors.studentId} className="sm:col-span-2">
                        <Select
                            value={issueForm.data.studentId}
                            onChange={onStudentPick}
                            options={studentOptions}
                            placeholder="Select a student with an earlier term"
                            className={`form-input ${issueForm.errors.studentId ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="Term" required error={issueForm.errors.termId}>
                        <Select
                            value={issueForm.data.termId}
                            onChange={(v) => issueForm.setData('termId', v)}
                            options={termOptions}
                            placeholder="Select term"
                            className={`form-input ${issueForm.errors.termId ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="Program" required error={issueForm.errors.courseId}>
                        <Select
                            value={issueForm.data.courseId}
                            onChange={(v) => issueForm.setData('courseId', v)}
                            options={courseOptions}
                            placeholder="Select program"
                            className={`form-input ${issueForm.errors.courseId ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection
                        label="Year level"
                        required
                        error={issueForm.errors.yearLevel}
                        hint="Carried from the last enrollment — the department decides the level; nothing promotes it on its own."
                    >
                        <Select
                            value={issueForm.data.yearLevel}
                            onChange={(v) => issueForm.setData('yearLevel', v)}
                            options={[1, 2, 3, 4, 5].map((n) => ({ value: n, label: `Year ${n}` }))}
                            placeholder="Year level"
                            className={`form-input ${issueForm.errors.yearLevel ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="Student type" required error={issueForm.errors.studentType} className="sm:col-span-2">
                        <RadioCards
                            name="issuingStudentType"
                            label="Student type"
                            value={issueForm.data.studentType}
                            onChange={(v) => issueForm.setData('studentType', v)}
                            options={[
                                { value: 'continuing', label: 'Continuing', tone: 'success' },
                                { value: 'shifter', label: 'Shifting program', tone: 'accent' },
                            ]}
                        />
                    </FormSection>
                    <p className="sm:col-span-2 text-xs text-slate-500">
                        One active enrollment per student per term. A student the Registrar dropped can be issued again in the same term — the drop released the seat.
                    </p>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
