import { Head } from '@inertiajs/react';
import PrintLayout from '@/Components/ui/PrintLayout';

export default function PrintSchedule({ block }) {
    const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    const sortedSchedules = block.schedules?.sort((a, b) => {
        const aDay = a.meetings?.[0]?.dayOfWeek?.value || a.meetings?.[0]?.dayOfWeek || '';
        const bDay = b.meetings?.[0]?.dayOfWeek?.value || b.meetings?.[0]?.dayOfWeek || '';
        const aIndex = dayOrder.indexOf(aDay);
        const bIndex = dayOrder.indexOf(bDay);
        if (aIndex !== bIndex) return aIndex - bIndex;
        const aTime = a.meetings?.[0]?.startTime || '';
        const bTime = b.meetings?.[0]?.startTime || '';
        return aTime.localeCompare(bTime);
    }) || [];

    const termLabel = block.term
        ? `${block.term.semester?.value || block.term.semester} ${block.term.academicYear?.yearLabel || ''}`.trim()
        : '—';

    return (
        <PrintLayout
            title="Block Schedule"
            subtitle={`${block.course?.courseName} - ${block.blockName}`}
            date={termLabel}
            headerContent={
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p className="font-medium text-brand-900">Course:</p>
                        <p>{block.course?.courseName}</p>
                    </div>
                    <div>
                        <p className="font-medium text-brand-900">Year Level:</p>
                        <p>{block.yearLevel}{getYearSuffix(block.yearLevel)} Year</p>
                    </div>
                    <div>
                        <p className="font-medium text-brand-900">Section:</p>
                        <p>{block.blockName}</p>
                    </div>
                    <div>
                        <p className="font-medium text-brand-900">Term:</p>
                        <p>{termLabel}</p>
                    </div>
                </div>
            }
        >
            <Head title={`Block Schedule - ${block.blockName}`} />

            {sortedSchedules.length > 0 ? (
                <div className="overflow-x-auto">
                    <table className="data-table data-table-striped print-table">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Room</th>
                                <th>Instructor</th>
                                <th>Schedule</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sortedSchedules.map((schedule, index) => (
                                <tr key={index}>
                                    <td>{schedule.subject?.subjectCode || '—'}</td>
                                    <td>{schedule.subject?.subjectName || '—'}</td>
                                    <td>{schedule.room?.roomName || '—'} {schedule.room?.building ? `(${schedule.room.building})` : ''}</td>
                                    <td>
                                        {schedule.instructor
                                            ? `${schedule.instructor.firstName} ${schedule.instructor.lastName}`
                                            : '—'}
                                    </td>
                                    <td>
                                        {schedule.meetings?.map((meeting, mi) => (
                                            <div key={mi}>
                                                {meeting.dayOfWeek?.value || meeting.dayOfWeek} {formatTime(meeting.startTime)}-{formatTime(meeting.endTime)}
                                            </div>
                                        ))}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="empty-state">
                    <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p className="empty-state-message">No schedules defined for this block.</p>
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

function formatTime(time) {
    if (!time) return '';
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}