import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link, usePage } from '@inertiajs/react';
import { PageHeader, Card, StatCard, DataTable, Badge, Modal, EmptyState, FormSection, Select, ConfirmDialog } from '@/Components/ui';
import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';

const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// SVG icon components (extracted to avoid parser issues with long strings in JSX)
const CapacityIcon = () => (
    <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
);
const EnrolledIcon = () => (
    <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
    </svg>
);
const AvailableIcon = () => (
    <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
);

// Modal icons
const ScheduleIcon = () => (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
);
const AssignIcon = () => (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
    </svg>
);

// Flash message display component
function FlashMessages({ flash }) {
    if (!flash) return null;
    return (
        <div className="space-y-3" role="status" aria-live="polite">
            {flash.success && (
                <div className="p-4 bg-success-50 border border-success-200 rounded-card text-success-800 flex items-center gap-3">
                    <svg className="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{flash.success}</span>
                </div>
            )}
            {flash.warning && (
                <div className="p-4 bg-warning-50 border border-warning-200 rounded-card text-warning-800 flex items-center gap-3">
                    <svg className="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{flash.warning}</span>
                </div>
            )}
            {flash.error && (
                <div className="p-4 bg-danger-50 border border-danger-200 rounded-card text-danger-800 flex items-center gap-3">
                    <svg className="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{flash.error}</span>
                </div>
            )}
        </div>
    );
}

// Conflict alert component for modals
function ConflictAlert({ conflicts, title = 'Conflicts detected' }) {
    if (!conflicts || conflicts.length === 0) return null;
    return (
        <div className="p-4 bg-danger-50 border border-danger-200 rounded-card text-danger-800 mb-4" role="alert">
            <div className="flex items-start gap-3">
                <svg className="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <p className="font-medium">{title}</p>
                    <ul className="mt-2 list-disc list-inside space-y-1 text-sm">
                        {conflicts.map((conflict, index) => (
                            <li key={index}>{conflict}</li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}

export default function Show({ block, capacity, enrolled, available, subjects, rooms, instructors, days, eligibleEnrollments }) {
    const { flash } = usePage().props;
    const [showScheduleModal, setShowScheduleModal] = useState(false);
    const [showAssignModal, setShowAssignModal] = useState(false);
    const [editingScheduleId, setEditingScheduleId] = useState(null);
    const [scheduleForm, setScheduleForm] = useState({
        subjectId: '',
        instructorId: '',
        roomId: '',
        meetings: [{ dayOfWeek: '', startTime: '', endTime: '' }],
    });
    const [assignForm, setAssignForm] = useState({
        enrollmentIds: [],
        scheduleId: '',
    });
    const [scheduleErrors, setScheduleErrors] = useState({});
    const [assignErrors, setAssignErrors] = useState({});
    const [submittingSchedule, setSubmittingSchedule] = useState(false);
    const [submittingAssign, setSubmittingAssign] = useState(false);
    const [confirmUnassign, setConfirmUnassign] = useState({ open: false, enrollmentId: null });
    const [confirmDeleteSchedule, setConfirmDeleteSchedule] = useState({ open: false, scheduleId: null });
    const [submittingUnassign, setSubmittingUnassign] = useState(false);
    const [submittingDeleteSchedule, setSubmittingDeleteSchedule] = useState(false);

    const scheduleColumns = useMemo(() => [
        { key: 'subject', label: 'Subject', render: (row) => row.subject?.subjectCode || '—' },
        { key: 'subjectName', label: 'Subject Name', render: (row) => row.subject?.subjectName || '—' },
        { key: 'room', label: 'Room', render: (row) => row.room?.roomName || '—' },
        { key: 'instructor', label: 'Instructor', render: (row) => {
            if (!row.instructor) return '—';
            return `${row.instructor.firstName} ${row.instructor.lastName}`;
        }},
        { key: 'schedule', label: 'Schedule', render: (row) => {
            if (!row.meetings || row.meetings.length === 0) return '—';
            return row.meetings.map(m => `${m.dayOfWeek?.value || m.dayOfWeek} ${formatTime(m.startTime)}-${formatTime(m.endTime)}`).join(', ');
        }},
    ], []);

    const studentColumns = useMemo(() => [
        { key: 'studentIdNumber', label: 'School ID', render: (row) => row.enrollment?.student?.schoolIdNumber || '—' },
        { key: 'studentName', label: 'Student Name', render: (row) => {
            const s = row.enrollment?.student;
            if (!s) return '—';
            return `${s.lastName}, ${s.firstName} ${s.middleName ? s.middleName.charAt(0) + '.' : ''} ${s.suffix || ''}`.trim();
        }},
        { key: 'subject', label: 'Subject', render: (row) => row.subject?.subjectCode || '—' },
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={getStatusTone(row.status)}>
                {row.status?.charAt(0).toUpperCase() + row.status?.slice(1)}
            </Badge>
        )},
    ], []);

    const renderScheduleActions = (row) => (
        <div className="flex items-center gap-2">
            <button
                onClick={() => handleEditSchedule(row)}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                title="Edit schedule"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => handleDeleteSchedule(row.scheduleId)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                title="Delete schedule"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    );

    const renderStudentActions = (row) => (
        <div className="flex items-center gap-2">
            <button
                onClick={() => handleUnassign(row.enrollment?.enrollmentId)}
                disabled={!row.enrollment?.enrollmentId}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                title="Unassign student"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            </button>
        </div>
    );

    const handleCloseScheduleModal = () => {
        setShowScheduleModal(false);
        setScheduleForm({ subjectId: '', instructorId: '', roomId: '', meetings: [{ dayOfWeek: '', startTime: '', endTime: '' }] });
        setScheduleErrors({});
        setEditingScheduleId(null);
    };

    const handleCloseAssignModal = () => {
        setShowAssignModal(false);
        setAssignForm({ enrollmentIds: [], scheduleId: '' });
        setAssignErrors({});
    };

    const addMeeting = () => {
        setScheduleForm(prev => ({
            ...prev,
            meetings: [...prev.meetings, { dayOfWeek: '', startTime: '', endTime: '' }],
        }));
    };

    const removeMeeting = (index) => {
        setScheduleForm(prev => ({
            ...prev,
            meetings: prev.meetings.filter((_, i) => i !== index),
        }));
    };

    const updateMeeting = (index, field, value) => {
        setScheduleForm(prev => ({
            ...prev,
            meetings: prev.meetings.map((m, i) => i === index ? { ...m, [field]: value } : m),
        }));
    };

    const handleScheduleSubmit = (e) => {
        e.preventDefault();
        setScheduleErrors({});
        setSubmittingSchedule(true);

        const url = editingScheduleId
            ? route('blocking.schedules.update', { schedule: editingScheduleId })
            : route('blocking.schedules.store', { block: block.blockId });
        const method = editingScheduleId ? 'patch' : 'post';

        router[method](url, scheduleForm, {
            onSuccess: () => {
                handleCloseScheduleModal();
                setSubmittingSchedule(false);
            },
            onError: (errors) => {
                setScheduleErrors(errors);
                setSubmittingSchedule(false);
            },
        });
    };

    const handleEditSchedule = (schedule) => {
        setEditingScheduleId(schedule.scheduleId);
        setScheduleForm({
            subjectId: schedule.subjectId,
            instructorId: schedule.instructorId,
            roomId: schedule.roomId,
            meetings: schedule.meetings?.map(m => ({
                dayOfWeek: m.dayOfWeek?.value || m.dayOfWeek,
                startTime: m.startTime,
                endTime: m.endTime,
            })) || [{ dayOfWeek: '', startTime: '', endTime: '' }],
        });
        setShowScheduleModal(true);
    };

    const handleDeleteSchedule = (scheduleId) => {
        setConfirmDeleteSchedule({ open: true, scheduleId });
    };

    const confirmDeleteScheduleAction = () => {
        setSubmittingDeleteSchedule(true);
        router.delete(route('blocking.schedules.destroy', { schedule: confirmDeleteSchedule.scheduleId }), {
            onSuccess: () => {
                setConfirmDeleteSchedule({ open: false, scheduleId: null });
                setSubmittingDeleteSchedule(false);
            },
            onError: (errors) => {
                if (errors.schedule) {
                    alert(errors.schedule);
                }
                setSubmittingDeleteSchedule(false);
            },
        });
    };

    const handleAssignSubmit = (e) => {
        e.preventDefault();
        setAssignErrors({});
        setSubmittingAssign(true);

        router.post(route('blocking.assign', { block: block.blockId }), assignForm, {
            onSuccess: () => {
                handleCloseAssignModal();
                setSubmittingAssign(false);
            },
            onError: (errors) => {
                setAssignErrors(errors);
                setSubmittingAssign(false);
            },
        });
    };

    const handleUnassign = (enrollmentId) => {
        setConfirmUnassign({ open: true, enrollmentId });
    };

    const confirmUnassignStudent = () => {
        setSubmittingUnassign(true);
        router.post(route('blocking.unassign', { block: block.blockId }), { enrollmentId: confirmUnassign.enrollmentId }, {
            onSuccess: () => {
                setConfirmUnassign({ open: false, enrollmentId: null });
                setSubmittingUnassign(false);
            },
            onError: (errors) => {
                if (errors.enrollmentId) {
                    alert(errors.enrollmentId);
                }
                setSubmittingUnassign(false);
            },
        });
    };

    const sortedSchedules = useMemo(() => {
        if (!block.schedules) return [];
        return [...block.schedules].sort((a, b) => {
            const aDay = a.meetings?.[0]?.dayOfWeek?.value || a.meetings?.[0]?.dayOfWeek || '';
            const bDay = b.meetings?.[0]?.dayOfWeek?.value || b.meetings?.[0]?.dayOfWeek || '';
            const aIndex = dayOrder.indexOf(aDay);
            const bIndex = dayOrder.indexOf(bDay);
            if (aIndex !== bIndex) return aIndex - bIndex;
            const aTime = a.meetings?.[0]?.startTime || '';
            const bTime = b.meetings?.[0]?.startTime || '';
            return aTime.localeCompare(bTime);
        });
    }, [block.schedules]);

    const sortedStudents = useMemo(() => {
        if (!block.enrolledSubjects) return [];
        return [...block.enrolledSubjects].sort((a, b) => {
            const aName = a.enrollment?.student?.lastName || '';
            const bName = b.enrollment?.student?.lastName || '';
            return aName.localeCompare(bName);
        });
    }, [block.enrolledSubjects]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Block Section: ${block.blockName}`}
                    subtitle={`${block.course?.courseName} - ${block.term?.semester?.value || block.term?.semester} ${block.term?.academicYear?.yearLabel || ''} ${block.yearLevel}${getYearSuffix(block.yearLevel)}`}
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Scheduling Office"
                    phaseBadge="Phase 6 · Section Scheduling"
                    officeBadge="Office 5 · Scheduling Desk"
                    actions={
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('blocking.index')}
                                className="btn btn-secondary btn-sm"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back
                            </Link>
                            <Link
                                href={route('blocking.print-schedule', { block: block.blockId })}
                                target="_blank"
                                className="btn btn-secondary btn-sm"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Print Schedule
                            </Link>
                        </div>
                    }
                />
            }
        >
            <Head title={`Block: ${block.blockName}`} />

            <div className="space-y-6">
                {/* Flash Messages */}
                <FlashMessages flash={flash} />

                {/* Block Info Card */}
                <Card title="Block Information" subtitle="Course, term, and section details for this block">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <p className="text-sm text-brand-500">Course</p>
                            <p className="font-medium">{block.course?.courseName}</p>
                        </div>
                        <div>
                            <p className="text-sm text-brand-500">Term</p>
                            <p className="font-medium">
                                {block.term?.semester?.value || block.term?.semester} {block.term?.academicYear?.yearLabel || ''}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-brand-500">Year Level</p>
                            <p className="font-medium">{block.yearLevel}{getYearSuffix(block.yearLevel)} Year</p>
                        </div>
                        <div>
                            <p className="text-sm text-brand-500">Section</p>
                            <p className="font-medium">{block.blockName}</p>
                        </div>
                    </div>
                </Card>

                {/* Capacity Stat Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        label="Capacity"
                        value={capacity}
                        icon={<CapacityIcon />}
                        iconBg="brand"
                    />
                    <StatCard
                        label="Enrolled"
                        value={enrolled}
                        icon={<EnrolledIcon />}
                        iconBg="info"
                    />
                    <StatCard
                        label="Available Slots"
                        value={available}
                        icon={<AvailableIcon />}
                        iconBg={available > 0 ? 'success' : 'danger'}
                    />
                    <StatCard
                        label="Schedules"
                        value={sortedSchedules.length}
                        icon={
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        }
                        iconBg="seait"
                    />
                </div>

                {/* Schedule Section */}
                <Card title="Block Schedule" subtitle="Subject meeting times, rooms, and instructors" actions={
                    <button
                        onClick={() => setShowScheduleModal(true)}
                        className="btn btn-primary btn-sm"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Schedule
                    </button>
                }>
                    {sortedSchedules.length > 0 ? (
                        <DataTable
                            columns={scheduleColumns}
                            rows={sortedSchedules}
                            children={renderScheduleActions}
                            emptyMessage="No schedules added yet"
                        />
                    ) : (
                        <EmptyState
                            title="No schedules yet"
                            message="Add a schedule to define when and where subjects meet."
                            actionLabel="Add Schedule"
                            onAction={() => setShowScheduleModal(true)}
                        />
                    )}
                </Card>

                {/* Assigned Students Section */}
                <Card title="Assigned Students" subtitle={`${sortedStudents.length} student(s) assigned to this block`} actions={
                    <button
                        onClick={() => setShowAssignModal(true)}
                        disabled={sortedSchedules.length === 0}
                        className="btn btn-primary btn-sm"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Assign Students
                    </button>
                }>
                    {sortedStudents.length > 0 ? (
                        <DataTable
                            columns={studentColumns}
                            rows={sortedStudents}
                            children={renderStudentActions}
                            emptyMessage="No students assigned yet"
                        />
                    ) : (
                        <EmptyState
                            title="No students assigned"
                            message={sortedSchedules.length === 0
                                ? 'Create a schedule first, then assign students to it.'
                                : 'Assign students to this block using the button above.'}
                            actionLabel={sortedSchedules.length > 0 ? 'Assign Students' : undefined}
                            onAction={sortedSchedules.length > 0 ? () => setShowAssignModal(true) : undefined}
                        />
                    )}
                </Card>
            </div>

            {/* Add/Edit Schedule Modal */}
            <Modal
                show={showScheduleModal}
                onClose={handleCloseScheduleModal}
                title={editingScheduleId ? 'Edit Schedule' : 'Add Schedule'}
                subtitle={editingScheduleId ? 'Update subject meeting times, room, or instructor' : 'Define subject meeting times, room, and instructor'}
                icon={<ScheduleIcon />}
                size="xl"
                footer={
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={handleCloseScheduleModal}
                            className="btn btn-secondary"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="schedule-form"
                            disabled={submittingSchedule}
                            className="btn btn-primary"
                        >
                            {submittingSchedule ? (editingScheduleId ? 'Updating...' : 'Adding...') : (editingScheduleId ? 'Update Schedule' : 'Add Schedule')}
                        </button>
                    </div>
                }
            >
                <form id="schedule-form" onSubmit={handleScheduleSubmit} className="space-y-4">
                    <ConflictAlert conflicts={scheduleErrors.conflicts} title="Schedule conflicts detected" />
                    <FormSection label="Subject">
                        <Select
                            value={scheduleForm.subjectId}
                            onChange={(e) => setScheduleForm({ ...scheduleForm, subjectId: e.target.value })}
                            options={subjects.map(s => ({ value: s.subjectId, label: `${s.subjectCode} - ${s.subjectName}` }))}
                            placeholder="Select subject"
                            className="form-input"
                            error={scheduleErrors.subjectId}
                            disabled={editingScheduleId}
                        />
                    </FormSection>

                    <FormSection label="Instructor">
                        <Select
                            value={scheduleForm.instructorId}
                            onChange={(e) => setScheduleForm({ ...scheduleForm, instructorId: e.target.value })}
                            options={instructors.map(i => ({ value: i.userId, label: `${i.firstName} ${i.lastName}` }))}
                            placeholder="Select instructor"
                            className="form-input"
                            error={scheduleErrors.instructorId}
                        />
                    </FormSection>

                    <FormSection label="Room">
                        <Select
                            value={scheduleForm.roomId}
                            onChange={(e) => setScheduleForm({ ...scheduleForm, roomId: e.target.value })}
                            options={rooms.map(r => ({ value: r.roomId, label: `${r.roomName} (${r.building}) - Cap: ${r.capacity}` }))}
                            placeholder="Select room"
                            className="form-input"
                            error={scheduleErrors.roomId}
                        />
                    </FormSection>

                    <FormSection label="Meetings">
                        <div className="space-y-3">
                            {scheduleForm.meetings.map((meeting, index) => (
                                <div key={index} className="flex flex-col sm:flex-row gap-3 items-start">
                                    <Select
                                        value={meeting.dayOfWeek}
                                        onChange={(e) => updateMeeting(index, 'dayOfWeek', e.target.value)}
                                        options={days.map(d => ({ value: d.value, label: d.value }))}
                                        placeholder="Day"
                                        className="form-input flex-1"
                                        error={scheduleErrors.meetings?.[index]?.dayOfWeek}
                                    />
                                    <input
                                        type="time"
                                        value={meeting.startTime}
                                        onChange={(e) => updateMeeting(index, 'startTime', e.target.value)}
                                        placeholder="Start Time"
                                        className="form-input flex-1"
                                        error={scheduleErrors.meetings?.[index]?.startTime}
                                    />
                                    <input
                                        type="time"
                                        value={meeting.endTime}
                                        onChange={(e) => updateMeeting(index, 'endTime', e.target.value)}
                                        placeholder="End Time"
                                        className="form-input flex-1"
                                        error={scheduleErrors.meetings?.[index]?.endTime}
                                    />
                                    {scheduleForm.meetings.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeMeeting(index)}
                                            className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900 self-end mb-2"
                                        >
                                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    )}
                                </div>
                            ))}
                            {scheduleForm.meetings.length < 5 && (
                                <button
                                    type="button"
                                    onClick={addMeeting}
                                    className="btn btn-secondary btn-sm"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Another Meeting
                                </button>
                            )}
                        </div>
                    </FormSection>
                </form>
            </Modal>

            {/* Assign Students Modal */}
            <Modal
                show={showAssignModal}
                onClose={handleCloseAssignModal}
                title="Assign Students to Block"
                subtitle={`Select a schedule and eligible students to assign (${available} slots available)`}
                icon={<AssignIcon />}
                size="xl"
                footer={
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setShowAssignModal(false)}
                            className="btn btn-secondary"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="assign-form"
                            disabled={submittingAssign || assignForm.enrollmentIds.length === 0 || !assignForm.scheduleId || assignForm.enrollmentIds.length > available}
                            className="btn btn-primary"
                        >
                            {submittingAssign ? 'Assigning...' : 'Assign Students'}
                        </button>
                    </div>
                }
            >
                <form id="assign-form" onSubmit={handleAssignSubmit} className="space-y-4">
                    <ConflictAlert conflicts={assignErrors.conflicts} title="Assignment conflicts" />
                    {assignErrors.capacity && (
                        <div className="p-4 bg-danger-50 border border-danger-200 rounded-card text-danger-800 mb-4" role="alert">
                            {assignErrors.capacity}
                        </div>
                    )}
                    {assignErrors.room_capacity && (
                        <div className="p-4 bg-danger-50 border border-danger-200 rounded-card text-danger-800 mb-4" role="alert">
                            {assignErrors.room_capacity}
                        </div>
                    )}
                    <FormSection label="Schedule">
                        <Select
                            value={assignForm.scheduleId}
                            onChange={(e) => setAssignForm({ ...assignForm, scheduleId: e.target.value })}
                            options={sortedSchedules.map(s => ({
                                value: s.scheduleId,
                                label: `${s.subject?.subjectCode} - ${s.room?.roomName} - ${s.meetings?.[0]?.dayOfWeek?.value || s.meetings?.[0]?.dayOfWeek} ${formatTime(s.meetings?.[0]?.startTime)}-${formatTime(s.meetings?.[0]?.endTime)}`
                            }))}
                            placeholder="Select schedule"
                            className="form-input"
                            error={assignErrors.scheduleId}
                        />
                    </FormSection>

                    <FormSection label={`Students to Assign (${available} slots available)`}>
                        <div className="max-h-96 overflow-y-auto border border-brand-200 rounded-btn p-4">
                            {(eligibleEnrollments?.length || 0) > 0 ? (
                                eligibleEnrollments.map((enrollment) => {
                                    const isSelected = assignForm.enrollmentIds.includes(enrollment.enrollmentId);
                                    const selectedCount = assignForm.enrollmentIds.length;
                                    const wouldExceed = !isSelected && selectedCount >= available;
                                    return (
                                        <label key={enrollment.enrollmentId} className="flex items-center gap-3 p-2 hover:bg-brand-50 rounded-btn cursor-pointer">
                                            <input
                                                type="checkbox"
                                                value={enrollment.enrollmentId}
                                                checked={isSelected}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setAssignForm(prev => ({ ...prev, enrollmentIds: [...prev.enrollmentIds, enrollment.enrollmentId] }));
                                                    } else {
                                                        setAssignForm(prev => ({ ...prev, enrollmentIds: prev.enrollmentIds.filter(id => id !== enrollment.enrollmentId) }));
                                                    }
                                                }}
                                                disabled={wouldExceed}
                                                className="form-checkbox"
                                            />
                                            <div className={wouldExceed ? 'opacity-50' : ''}>
                                                <p className="font-medium">
                                                    {enrollment.student.lastName}, {enrollment.student.firstName} {enrollment.student.middleName ? enrollment.student.middleName.charAt(0) + '.' : ''}
                                                </p>
                                                <p className="text-sm text-brand-500">{enrollment.student.schoolIdNumber} - {enrollment.subjects.map(s => s.subjectCode).join(', ')}</p>
                                            </div>
                                            {wouldExceed && (
                                                <span className="text-xs text-warning-600 ml-auto">Capacity reached</span>
                                            )}
                                        </label>
                                    );
                                })
                            ) : (
                                <div className="text-center py-8">
                                    <p className="text-brand-500">No eligible students available for assignment.</p>
                                    <p className="text-xs text-brand-400 mt-1">Students must be enrolled and at the Blocking workflow step.</p>
                                </div>
                            )}
                        </div>
                        <p className="mt-2 text-sm text-brand-500">
                            {assignForm.enrollmentIds.length} of {available} slots selected
                        </p>
                    </FormSection>
                </form>
            </Modal>

            {/* Unassign Student Confirm */}
            <ConfirmDialog
                show={confirmUnassign.open}
                onClose={() => setConfirmUnassign({ open: false, enrollmentId: null })}
                onConfirm={confirmUnassignStudent}
                title="Unassign Student"
                message="Remove this student from the block? Their enrolled subjects will be unassigned."
                confirmText="Unassign"
                variant="danger"
                loading={submittingUnassign}
            />

            {/* Delete Schedule Confirm */}
            <ConfirmDialog
                show={confirmDeleteSchedule.open}
                onClose={() => setConfirmDeleteSchedule({ open: false, scheduleId: null })}
                onConfirm={confirmDeleteScheduleAction}
                title="Delete Schedule"
                message="Delete this schedule? This action cannot be undone."
                confirmText="Delete"
                variant="danger"
                loading={submittingDeleteSchedule}
            />
        </AuthenticatedLayout>
    );
}

function getYearSuffix(year) {
    if (year === 1) return 'st';
    if (year === 2) return 'nd';
    if (year === 3) return 'rd';
    return 'th';
}

function formatTime(time) {
    if (!time) return '';
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function getStatusTone(status) {
    const tones = {
        proposed: 'pending',
        confirmed: 'approved',
        dropped: 'rejected',
    };
    return tones[status] || 'neutral';
}
