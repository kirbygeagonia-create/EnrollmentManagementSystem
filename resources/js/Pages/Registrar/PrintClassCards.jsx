import { Head } from '@inertiajs/react';
import PrintLayout from '@/Components/ui/PrintLayout';
import { Badge } from '@/Components/ui';

export default function PrintClassCards({ enrollment }) {
    const studentName = enrollment.student
        ? `${enrollment.student.lastName}, ${enrollment.student.firstName}${enrollment.student.middleName ? ` ${enrollment.student.middleName.charAt(0)}.` : ''}${enrollment.student.suffix ? ` ${enrollment.student.suffix}` : ''}`
        : '—';

    const termLabel = enrollment.term
        ? `${enrollment.term.semester?.value || enrollment.term.semester} ${enrollment.term.academicYear?.yearLabel || ''}`.trim()
        : '—';

    const confirmedSubjects = enrollment.enrolledSubjects?.filter(
        (es) => (es.status?.value || es.status) === 'confirmed'
    ) || [];

    return (
        <PrintLayout
            title="Class Cards"
            subtitle={`${enrollment.course?.courseName} — ${termLabel}`}
            headerContent={
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p className="font-medium text-brand-900">Student:</p>
                        <p>{studentName}</p>
                    </div>
                    <div>
                        <p className="font-medium text-brand-900">School ID:</p>
                        <p className="font-mono">{enrollment.student?.schoolIdNumber || '—'}</p>
                    </div>
                    <div>
                        <p className="font-medium text-brand-900">Course:</p>
                        <p>{enrollment.course?.courseName || '—'}</p>
                    </div>
                    <div>
                        <p className="font-medium text-brand-900">Year Level:</p>
                        <p>{enrollment.yearLevel ? `${enrollment.yearLevel}${getYearSuffix(enrollment.yearLevel)} Year` : '—'}</p>
                    </div>
                </div>
            }
        >
            <Head title={`Class Cards — ${studentName}`} />

            {confirmedSubjects.length > 0 ? (
                <div className="space-y-8">
                    {confirmedSubjects.map((es, index) => (
                        <div key={index} className="border border-brand-200 rounded-lg p-6 page-break-inside-avoid">
                            <div className="flex items-start justify-between mb-4">
                                <div>
                                    <p className="text-sm text-brand-500 mb-1">Subject Code</p>
                                    <p className="font-mono font-medium text-lg text-brand-900">{es.subject?.subjectCode || '—'}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm text-brand-500 mb-1">Units</p>
                                    <p className="font-medium text-brand-900">{getTotalUnits(es.subject)}</p>
                                </div>
                            </div>

                            <div className="mb-4">
                                <p className="text-sm text-brand-500 mb-1">Subject Title</p>
                                <p className="font-medium text-brand-900 text-lg">{es.subject?.subjectName || '—'}</p>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p className="text-sm text-brand-500 mb-1">Schedule</p>
                                    <p className="font-medium text-brand-900">{formatSchedule(es.schedule)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-brand-500 mb-1">Room</p>
                                    <p className="font-medium text-brand-900">{formatRoom(es.schedule?.room)}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p className="text-sm text-brand-500 mb-1">Instructor</p>
                                    <p className="font-medium text-brand-900">{formatInstructor(es.schedule?.instructor)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-brand-500 mb-1">Status</p>
                                    <Badge tone={getSubjectStatusTone(es.status?.value || es.status)}>
                                        {formatStatus(es.status?.value || es.status)}
                                    </Badge>
                                </div>
                            </div>

                            <div className="pt-4 border-t border-brand-200 flex justify-between">
                                <div className="text-center w-1/3">
                                    <div className="border-t border-brand-400 mb-1 h-8"></div>
                                    <p className="text-xs text-brand-600">Student Signature</p>
                                </div>
                                <div className="text-center w-1/3">
                                    <div className="border-t border-brand-400 mb-1 h-8"></div>
                                    <p className="text-xs text-brand-600">Instructor Signature</p>
                                </div>
                                <div className="text-center w-1/3">
                                    <div className="border-t border-brand-400 mb-1 h-8"></div>
                                    <p className="text-xs text-brand-600">Date</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="empty-state">
                    <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p className="empty-state-message">No confirmed subjects found for class cards.</p>
                </div>
            )}
        </PrintLayout>
    );
}

function getYearSuffix(year) {
    if (year === 1) return 'st';
    if (year === 2) return 'nd';
    if (year === 3) return 'rd';
    return 'th';
}

function formatStatus(status) {
    if (!status) return '—';
    return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
}

function getSubjectStatusTone(status) {
    const toneMap = {
        proposed: 'pending',
        confirmed: 'enrolled',
        dropped: 'dropped',
    };
    return toneMap[status] || 'neutral';
}

function getTotalUnits(subject) {
    if (!subject) return '—';
    const lecture = parseFloat(subject.lectureUnits) || 0;
    const lab = parseFloat(subject.labUnits) || 0;
    const total = lecture + lab;
    return total > 0 ? `${total} (${lecture} lec / ${lab} lab)` : '—';
}

function formatSchedule(schedule) {
    if (!schedule?.meetings || schedule.meetings.length === 0) return '—';
    return schedule.meetings.map((meeting) => {
        const day = meeting.dayOfWeek?.value || meeting.dayOfWeek || '';
        const start = formatTime(meeting.startTime);
        const end = formatTime(meeting.endTime);
        return `${day} ${start}–${end}`;
    }).join(', ');
}

function formatRoom(room) {
    if (!room) return '—';
    return `${room.roomName || '—'}${room.building ? ` (${room.building})` : ''}`;
}

function formatInstructor(instructor) {
    if (!instructor) return '—';
    return `${instructor.firstName || ''} ${instructor.lastName || ''}`.trim() || '—';
}

function formatTime(time) {
    if (!time) return '';
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}