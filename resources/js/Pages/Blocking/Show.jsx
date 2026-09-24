import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage, router } from '@inertiajs/react';
import { PageHeader, Card, StatCard, DataTable, Badge, Modal, EmptyState, FormSection, Select, CauseEffectModal, formatStatusLabel } from '@/Components/ui';
import { useState, useMemo } from 'react';

const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Timetable grid: 7:00-21:00 in 30-min slots (item 9) = 28 rows.
const START_HOUR = 7;
const END_HOUR = 21;
const SLOTS = (END_HOUR - START_HOUR) * 2;

const slotMinutes = (t) => {
    const [h, m] = String(t).split(':').map(Number);
    return h * 60 + m;
};

/** [startSlot, spanSlots] for a meeting, clamped to the 7:00-21:00 grid. */
const meetingSpan = (meeting) => {
    const start = Math.min(Math.max(Math.floor((slotMinutes(meeting.startTime) - START_HOUR * 60) / 30), 0), SLOTS - 1);
    const end = Math.min(Math.max(Math.ceil((slotMinutes(meeting.endTime) - START_HOUR * 60) / 30), start + 1), SLOTS);
    return [start, end - start];
};

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
            {flash.info && (
                <div className="p-4 bg-info-50 border border-info-200 rounded-card text-info-800 flex items-center gap-3">
                    <svg className="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{flash.info}</span>
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
    const [showFinalizeModal, setShowFinalizeModal] = useState(false);
    const [submittingFinalize, setSubmittingFinalize] = useState(false);
    const [showEditBlockModal, setShowEditBlockModal] = useState(false);
    const [blockForm, setBlockForm] = useState({ blockName: block.blockName, maxStudents: block.maxStudents });
    const [blockErrors, setBlockErrors] = useState({});
    const [submittingBlock, setSubmittingBlock] = useState(false);

    const isFinalized = block.scheduleStatus === 'final';

    const handleFinalize = () => {
        setSubmittingFinalize(true);
        router.patch(route('blocking.finalize', { block: block.blockId }), {}, {
            onSuccess: () => {
                setShowFinalizeModal(false);
                setSubmittingFinalize(false);
            },
            onError: () => setSubmittingFinalize(false),
        });
    };

    const handleBlockSubmit = (e) => {
        e.preventDefault();
        setBlockErrors({});
        setSubmittingBlock(true);
        router.patch(route('blocking.update', { block: block.blockId }), blockForm, {
            onSuccess: () => {
                setShowEditBlockModal(false);
                setSubmittingBlock(false);
            },
            onError: (errors) => {
                setBlockErrors(errors);
                setSubmittingBlock(false);
            },
        });
    };

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
                {formatStatusLabel(row.status)}
            </Badge>
        )},
    ], []);

    const renderScheduleActions = (row) => (
        <div className="flex items-center gap-2">
            <button
                onClick={() => handleEditSchedule(row)}
                disabled={isFinalized}
                title={isFinalized ? 'Block schedule is finalized — unfinalize to edit' : 'Edit schedule'}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900 disabled:opacity-50"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => handleDeleteSchedule(row.scheduleId)}
                disabled={isFinalized}
                title={isFinalized ? 'Block schedule is finalized — unfinalize to delete' : 'Delete schedule'}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900 disabled:opacity-50"
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
        router.post(route('blocking.unassign', { block: block.blockId }), { enrollmentIds: [confirmUnassign.enrollmentId] }, {
            onSuccess: () => {
                setConfirmUnassign({ open: false, enrollmentId: null });
                setSubmittingUnassign(false);
            },
            onError: (errors) => {
                if (errors.enrollmentIds) {
                    alert(errors.enrollmentIds[0]);
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

    // Combined timetable: maps each day+slot to its meeting so the grid can
    // render one class block per meeting. First meeting wins on overlap —
    // the conflict detector only blocks instructor/room overlaps, so two
    // legal schedules can still share a slot; overlapped starts get an
    // overflow badge rather than silently disappearing.
    const timetable = useMemo(() => {
        const occupied = {};
        const overflows = {};
        (block.schedules || []).forEach((schedule) => {
            (schedule.meetings || []).forEach((m) => {
                const d = dayOrder.indexOf(m.dayOfWeek?.value || m.dayOfWeek);
                if (d < 0 || !m.startTime || !m.endTime) return;
                const [start, span] = meetingSpan(m);
                for (let s = start; s < start + span; s++) {
                    if (!occupied[`${d}:${s}`]) {
                        occupied[`${d}:${s}`] = { meeting: m, schedule, isStart: s === start, span };
                    } else if (s === start) {
                        overflows[`${d}:${s}`] = (overflows[`${d}:${s}`] || 0) + 1;
                    }
                }
            });
        });
        return { occupied, overflows };
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
                <Card title="Block Information" subtitle="Course, term, and section details for this block" actions={
                    <button onClick={() => setShowEditBlockModal(true)} className="btn btn-secondary btn-sm">
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Block
                    </button>
                }>
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
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <StatCard
                        compact
                        label="Capacity"
                        value={capacity}
                        icon={<CapacityIcon />}
                        iconBg="brand"
                    />
                    <StatCard
                        compact
                        label="Enrolled"
                        value={enrolled}
                        icon={<EnrolledIcon />}
                        iconBg="info"
                    />
                    <StatCard
                        compact
                        label="Available Slots"
                        value={available}
                        icon={<AvailableIcon />}
                        iconBg={available > 0 ? 'success' : 'danger'}
                    />
                    <StatCard
                        compact
                        label="Schedules"
                        value={sortedSchedules.length}
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        }
                        iconBg="seait"
                    />
                </div>

                {/* Schedule Section */}
                <Card title="Block Schedule" subtitle="Subject meeting times, rooms, and instructors" actions={
                    <div className="flex items-center gap-2">
                        {isFinalized ? (
                            <Badge tone="approved">Finalized — timetable locked</Badge>
                        ) : (
                            <button
                                onClick={() => setShowFinalizeModal(true)}
                                className="btn btn-secondary btn-sm"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Finalize Schedule
                            </button>
                        )}
                        <button
                            onClick={() => setShowScheduleModal(true)}
                            disabled={isFinalized}
                            title={isFinalized ? 'Timetable is finalized — schedule slots can no longer be added' : undefined}
                            className="btn btn-primary btn-sm"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Schedule
                        </button>
                    </div>
                }>
                    {sortedSchedules.length > 0 && (
                        <div className="mb-6">
                            <p className="text-sm text-brand-500 mb-2">Weekly Timetable (7:00 AM – 9:00 PM, 30-minute slots)</p>
                            <div className="overflow-x-auto rounded-btn border border-brand-100">
                                <div
                                    className="grid min-w-[760px]"
                                    style={{
                                        gridTemplateColumns: '64px repeat(7, minmax(96px, 1fr))',
                                        gridTemplateRows: `32px repeat(${SLOTS}, 20px)`,
                                    }}
                                >
                                    {/* Header row */}
                                    <div />
                                    {dayOrder.map((day) => (
                                        <div key={day} className="flex items-center justify-center text-xs font-medium text-brand-700 border-b border-brand-100 bg-brand-50">
                                            {day.slice(0, 3)}
                                        </div>
                                    ))}
                                    {/* Slot rows */}
                                    {Array.from({ length: SLOTS }, (_, s) => {
                                        const isHourMark = s % 2 === 0;
                                        return (
                                            <div key={`label-${s}`} className="text-[10px] text-brand-400 pr-2 text-right" style={{ gridRow: `${s + 2} / span ${isHourMark ? 2 : 1}`, gridColumn: 1 }}>
                                                {isHourMark ? `${(START_HOUR + s / 2) % 12 || 12}:00 ${(START_HOUR + s / 2) >= 12 ? 'PM' : 'AM'}` : ''}
                                            </div>
                                        );
                                    })}
                                    {dayOrder.map((day, d) => (
                                        Array.from({ length: SLOTS }, (_, s) => {
                                            const cell = timetable.occupied[`${d}:${s}`];
                                            if (cell && cell.isStart) {
                                                const { meeting, schedule } = cell;
                                                const extra = timetable.overflows[`${d}:${s}`] || 0;
                                                const title = `${meeting.dayOfWeek?.value || meeting.dayOfWeek} ${formatTime(meeting.startTime)}-${formatTime(meeting.endTime)} · ${schedule.subject?.subjectName || ''} · ${schedule.room?.roomName || 'No room'} · ${schedule.instructor ? `${schedule.instructor.firstName} ${schedule.instructor.lastName}` : 'No instructor'}${extra > 0 ? ` · +${extra} more meeting(s) in this slot — see the table below` : ''}`;
                                                return (
                                                    <div
                                                        key={`${day}-${s}`}
                                                        title={title}
                                                        className={`bg-brand-100 border rounded-sm px-1.5 py-0.5 overflow-hidden ${extra > 0 ? 'border-amber-400' : 'border-brand-300'}`}
                                                        style={{ gridRow: `${s + 2} / span ${cell.span}`, gridColumn: d + 2 }}
                                                    >
                                                        <p className="text-[11px] font-medium text-brand-900 leading-tight truncate">{schedule.subject?.subjectCode}</p>
                                                        <p className="text-[10px] text-brand-600 leading-tight truncate">{schedule.room?.roomName || '—'}</p>
                                                        {extra > 0 && (
                                                            <p className="text-[10px] font-bold text-amber-700 leading-tight">+{extra} more</p>
                                                        )}
                                                    </div>
                                                );
                                            }
                                            if (cell) {
                                                return <div key={`${day}-${s}`} style={{ gridRow: s + 2, gridColumn: d + 2 }} />;
                                            }
                                            return <div key={`${day}-${s}`} className="border-b border-r border-brand-50" style={{ gridRow: s + 2, gridColumn: d + 2 }} />;
                                        })
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
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
                            actionLabel={isFinalized ? undefined : 'Add Schedule'}
                            onAction={isFinalized ? undefined : () => setShowScheduleModal(true)}
                        />
                    )}
                </Card>

                {/* Assigned Students Section */}
                <Card title="Assigned Students" subtitle={`${sortedStudents.length} student(s) assigned to this block`} actions={
                    <button
                        onClick={() => setShowAssignModal(true)}
                        disabled={sortedSchedules.length === 0}
                        title={sortedSchedules.length === 0 ? 'Create a schedule first, then assign students to it' : undefined}
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
                    {/* Item 9: the finalized-guard error key is 'schedule' — render it
                        instead of letting a blocked submit die silently. */}
                    {scheduleErrors.schedule && <p className="form-error">{scheduleErrors.schedule}</p>}
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
                                    <div className="flex-1 w-full">
                                        <input
                                            type="time"
                                            value={meeting.startTime}
                                            onChange={(e) => updateMeeting(index, 'startTime', e.target.value)}
                                            className="form-input w-full"
                                        />
                                        {scheduleErrors.meetings?.[index]?.startTime && (
                                            <p className="form-error mt-1">{scheduleErrors.meetings[index].startTime}</p>
                                        )}
                                    </div>
                                    <div className="flex-1 w-full">
                                        <input
                                            type="time"
                                            value={meeting.endTime}
                                            onChange={(e) => updateMeeting(index, 'endTime', e.target.value)}
                                            className="form-input w-full"
                                        />
                                        {scheduleErrors.meetings?.[index]?.endTime && (
                                            <p className="form-error mt-1">{scheduleErrors.meetings[index].endTime}</p>
                                        )}
                                    </div>
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
                            title={
                                submittingAssign ? undefined
                                    : assignForm.enrollmentIds.length > available ? `Selection exceeds available seats (${available} available)`
                                        : !assignForm.scheduleId ? 'Select a schedule first'
                                            : assignForm.enrollmentIds.length === 0 ? 'Select at least one student'
                                                : undefined
                            }
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

            {/* Unassign Student Cause & Effect Modal */}
            <CauseEffectModal
                show={confirmUnassign.open}
                onClose={() => setConfirmUnassign({ open: false, enrollmentId: null })}
                onConfirm={confirmUnassignStudent}
                title="Unassign Student from Block Section"
                subtitle="Section Capacity & Timetable Roster Modification"
                tone="danger"
                entityContext={{
                    label: 'Target Block Section',
                    value: block.blockCode || 'BLOCK SECTION',
                    badge: `${block.enrolledCount || 0}/${block.maxCapacity || 0} SEATS`,
                }}
                cause="Unassigning the student removes them from this section's cohort masterlist and releases their reserved seat."
                effects={[
                    'Releases 1 reserved seat in this block, increasing available section capacity.',
                    'Vacates the student from assigned timetable schedule slots and room seatings.',
                    'Student must be re-assigned to another block before class cards can be printed.',
                ]}
                requiresAcknowledgement={true}
                acknowledgementText="I confirm that this student should be removed from this section's timetable."
                confirmText="Yes, Unassign Student"
                cancelText="Keep Student in Block"
                loading={submittingUnassign}
            />

            {/* Delete Schedule Cause & Effect Modal */}
            <CauseEffectModal
                show={confirmDeleteSchedule.open}
                onClose={() => setConfirmDeleteSchedule({ open: false, scheduleId: null })}
                onConfirm={confirmDeleteScheduleAction}
                title="Delete Timetable Schedule Slot"
                subtitle="Room & Faculty Schedule Deletion"
                tone="danger"
                entityContext={{
                    label: 'Block Section',
                    value: block.blockCode || 'BLOCK SECTION',
                }}
                cause="Deleting this timetable slot permanently removes the room and time allocation for this subject."
                effects={[
                    'Removes room reservation and faculty teaching assignment for this period.',
                    'Enrolled students in this block will have an unassigned schedule slot for this subject.',
                ]}
                requiresAcknowledgement={true}
                acknowledgementText="I understand that deleting this schedule will remove the assigned room and class time."
                confirmText="Yes, Permanently Delete Slot"
                cancelText="Keep Schedule"
                loading={submittingDeleteSchedule}
            />

            {/* Finalize Schedule Cause & Effect Modal */}
            <CauseEffectModal
                show={showFinalizeModal}
                onClose={() => setShowFinalizeModal(false)}
                onConfirm={handleFinalize}
                title="Finalize Block Schedule"
                subtitle="Section Timetable Lock"
                tone="info"
                entityContext={{
                    label: 'Block Section',
                    value: block.blockName,
                    badge: `${sortedSchedules.length} SCHEDULE SLOT${sortedSchedules.length === 1 ? '' : 'S'}`,
                }}
                cause="Finalizing locks this block's weekly timetable as the official class schedule for the term."
                effects={[
                    'Schedule slots can no longer be added, edited, or deleted for this block.',
                    'Students can still be assigned to the block while capacity allows.',
                    'Class card and schedule printing can proceed from the finalized timetable.',
                ]}
                requiresAcknowledgement={true}
                acknowledgementText="I confirm that this timetable is final and ready for student assignment."
                confirmText="Yes, Finalize Schedule"
                cancelText="Keep Editing"
                loading={submittingFinalize}
            />

            {/* Edit Block Modal (capacity stays editable after finalization) */}
            <Modal
                show={showEditBlockModal}
                onClose={() => { setShowEditBlockModal(false); setBlockErrors({}); }}
                title="Edit Block"
                subtitle="Update the section name and seat capacity."
                icon={<CapacityIcon />}
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => setShowEditBlockModal(false)} className="btn btn-secondary" disabled={submittingBlock}>
                            Cancel
                        </button>
                        <button type="submit" form="edit-block-form" className="btn btn-primary" disabled={submittingBlock}>
                            {submittingBlock ? 'Saving...' : 'Update'}
                        </button>
                    </div>
                }
            >
                <form id="edit-block-form" onSubmit={handleBlockSubmit} className="space-y-4">
                    <FormSection label="Section Name" error={blockErrors.blockName} required>
                        <input
                            type="text"
                            value={blockForm.blockName}
                            onChange={(e) => setBlockForm({ ...blockForm, blockName: e.target.value })}
                            className={`form-input ${blockErrors.blockName ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="Max Students" error={blockErrors.maxStudents} required>
                        <input
                            type="number"
                            min="1"
                            value={blockForm.maxStudents}
                            onChange={(e) => setBlockForm({ ...blockForm, maxStudents: e.target.value })}
                            className={`form-input ${blockErrors.maxStudents ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    {isFinalized && (
                        <p className="text-sm text-info-700 bg-info-50 border border-info-200 rounded-btn p-3">
                            This block's timetable is finalized — the schedule is locked, but seat capacity stays adjustable.
                        </p>
                    )}
                </form>
            </Modal>
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
