import { Head } from '@inertiajs/react';
import PrintLayout from '@/Components/ui/PrintLayout';
import { Badge } from '@/Components/ui';

export default function PrintSubjectLoad({ enrollment }) {
    const studentName = enrollment.student
        ? `${enrollment.student.lastName}, ${enrollment.student.firstName}${enrollment.student.middleName ? ` ${enrollment.student.middleName.charAt(0)}.` : ''}${enrollment.student.suffix ? ` ${enrollment.student.suffix}` : ''}`
        : '—';

    const termLabel = enrollment.term
        ? `${enrollment.term.semester?.value || enrollment.term.semester} ${enrollment.term.academicYear?.yearLabel || ''}`.trim()
        : '—';

    const confirmedSubjects = enrollment.enrolledSubjects?.filter(
        (es) => (es.status?.value || es.status) === 'confirmed'
    ) || [];

    const totalUnits = confirmedSubjects.reduce((sum, es) => {
        const lecture = parseFloat(es.subject?.lectureUnits) || 0;
        const lab = parseFloat(es.subject?.labUnits) || 0;
        return sum + lecture + lab;
    }, 0);

    const totalLecUnits = confirmedSubjects.reduce((sum, es) => sum + (parseFloat(es.subject?.lectureUnits) || 0), 0);
    const totalLabUnits = confirmedSubjects.reduce((sum, es) => sum + (parseFloat(es.subject?.labUnits) || 0), 0);

    return (
        <PrintLayout
            title="Subject Load"
            subtitle={`${enrollment.course?.courseName} — ${termLabel}`}
            headerContent={
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm">
                    <div className="sm:col-span-2">
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
                        <p className="font-medium text-brand-900">Major:</p>
                        <p>{enrollment.major?.majorName || '—'}</p>
                    </div>
                    <div className="sm:col-span-2">
                        <p className="font-medium text-brand-900">Year Level:</p>
                        <p>{enrollment.yearLevel ? `${enrollment.yearLevel}${getYearSuffix(enrollment.yearLevel)} Year` : '—'}</p>
                    </div>
                    <div>
                        <p className="font-medium text-brand-900">Term:</p>
                        <p>{termLabel}</p>
                    </div>
                    <div>
                        <p className="font-medium text-brand-900">Total Units:</p>
                        <p className="font-medium text-brand-900">{totalUnits} ({totalLecUnits} lec / {totalLabUnits} lab)</p>
                    </div>
                </div>
            }
        >
            <Head title={`Subject Load — ${studentName}`} />

            {confirmedSubjects.length > 0 ? (
                <div className="overflow-x-auto">
                    <table className="data-table data-table-striped print-table">
                        <thead>
                            <tr>
                                <th className="w-8">#</th>
                                <th>Subject Code</th>
                                <th>Subject Title</th>
                                <th>Lec</th>
                                <th>Lab</th>
                                <th>Total</th>
                                <th>Schedule</th>
                                <th>Room</th>
                                <th>Instructor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {confirmedSubjects.map((es, index) => (
                                <tr key={index}>
                                    <td className="text-center font-mono text-sm">{index + 1}</td>
                                    <td className="font-mono text-sm">{es.subject?.subjectCode || '—'}</td>
                                    <td>{es.subject?.subjectName || '—'}</td>
                                    <td className="text-center">{es.subject?.lectureUnits || 0}</td>
                                    <td className="text-center">{es.subject?.labUnits || 0}</td>
                                    <td className="text-center font-medium">
                                        {(parseFloat(es.subject?.lectureUnits) || 0) + (parseFloat(es.subject?.labUnits) || 0)}
                                    </td>
                                    <td>{formatSchedule(es.schedule)}</td>
                                    <td>{formatRoom(es.schedule?.room)}</td>
                                    <td>{formatInstructor(es.schedule?.instructor)}</td>
                                    <td className="text-center">
                                        <Badge tone={getSubjectStatusTone(es.status?.value || es.status)}>
                                            {formatStatus(es.status?.value || es.status)}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="font-medium bg-brand-50">
                                <td colSpan={3} className="text-right">TOTAL</td>
                                <td className="text-center">{totalLecUnits}</td>
                                <td className="text-center">{totalLabUnits}</td>
                                <td className="text-center">{totalUnits}</td>
                                <td colSpan={4}></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            ) : (
                <div className="empty-state">
                    <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p className="empty-state-message">No confirmed subjects found for subject load.</p>
                </div>
            )}

            <div className="mt-8 pt-6 border-t border-brand-200 grid grid-cols-1 sm:grid-cols-3 gap-8">
                <div className="text-center">
                    <div className="border-t border-brand-400 mb-1 h-10"></div>
                    <p className="text-sm text-brand-600">Student Signature</p>
                </div>
                <div className="text-center">
                    <div className="border-t border-brand-400 mb-1 h-10"></div>
                    <p className="text-sm text-brand-600">Adviser Signature</p>
                </div>
                <div className="text-center">
                    <div className="border-t border-brand-400 mb-1 h-10"></div>
                    <p className="text-sm text-brand-600">Registrar Signature</p>
                </div>
            </div>
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