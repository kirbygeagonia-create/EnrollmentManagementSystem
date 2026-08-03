import { Head } from '@inertiajs/react';
import PrintLayout from '@/Components/ui/PrintLayout';
import { Badge } from '@/Components/ui';

export default function PrintCertificate({ enrollment }) {
    const studentName = enrollment.student
        ? `${enrollment.student.lastName}, ${enrollment.student.firstName}${enrollment.student.middleName ? ` ${enrollment.student.middleName.charAt(0)}.` : ''}${enrollment.student.suffix ? ` ${enrollment.student.suffix}` : ''}`
        : '—';

    const termLabel = enrollment.term
        ? `${enrollment.term.semester?.value || enrollment.term.semester} ${enrollment.term.academicYear?.yearLabel || ''}`.trim()
        : '—';

    const enrolledDate = enrollment.enrolledDate
        ? new Date(enrollment.enrolledDate).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
        : '—';

    const processedBy = enrollment.registrarProcessedBy
        ? `${enrollment.registrarProcessedBy.firstName} ${enrollment.registrarProcessedBy.lastName}`
        : '—';

    return (
        <PrintLayout
            title="Enrollment Certificate"
            subtitle={`Certificate of Enrollment for ${termLabel}`}
            headerContent={
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
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
                        <p className="font-medium text-brand-900">Major:</p>
                        <p>{enrollment.major?.majorName || '—'}</p>
                    </div>
                    <div className="sm:col-span-2">
                        <p className="font-medium text-brand-900">Term:</p>
                        <p>{termLabel}</p>
                    </div>
                </div>
            }
        >
            <Head title={`Enrollment Certificate — ${studentName}`} />

            <div className="space-y-6">
                <div className="text-center py-8">
                    <p className="text-lg text-brand-700 mb-4">
                        This is to certify that
                    </p>
                    <p className="text-2xl font-heading font-bold text-brand-900 mb-2">
                        {studentName}
                    </p>
                    <p className="text-brand-600 mb-4">
                        with School ID <span className="font-mono font-medium">{enrollment.student?.schoolIdNumber || '—'}</span>
                    </p>
                    <p className="text-lg text-brand-700 mb-4">
                        is officially enrolled at
                    </p>
                    <p className="text-xl font-heading font-semibold text-brand-900 mb-2">
                        Southeast Asian Institute of Technology
                    </p>
                    <p className="text-brand-600 mb-4">
                        in the program
                    </p>
                    <p className="text-xl font-heading font-semibold text-brand-900 mb-4">
                        {enrollment.course?.courseName || '—'}
                        {enrollment.major?.majorName ? ` — ${enrollment.major?.majorName}` : ''}
                    </p>
                    <p className="text-brand-600 mb-4">
                        for the <strong>{termLabel}</strong> term.
                    </p>
                    <p className="text-brand-700">
                        Enrollment Date: <strong>{enrolledDate}</strong>
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-6 border-t border-brand-200">
                    <div className="text-center">
                        <p className="text-sm text-brand-500 mb-1">Enrollment Status</p>
                        <Badge tone={getStatusTone(enrollment.enrollmentStatus?.value || enrollment.enrollmentStatus)} className="text-base px-3 py-1">
                            {formatStatus(enrollment.enrollmentStatus?.value || enrollment.enrollmentStatus)}
                        </Badge>
                    </div>
                    <div className="text-center">
                        <p className="text-sm text-brand-500 mb-1">Enrollment Type</p>
                        <p className="font-medium text-brand-900 capitalize">{enrollment.enrollmentType?.value || enrollment.enrollmentType || '—'}</p>
                    </div>
                    <div className="text-center">
                        <p className="text-sm text-brand-500 mb-1">Processed By</p>
                        <p className="font-medium text-brand-900">{processedBy}</p>
                    </div>
                </div>

                <div className="pt-8 border-t border-brand-200">
                    <p className="text-sm text-brand-500 text-center mb-4">
                        This certificate is valid only with the official school seal and registrar's signature.
                    </p>
                    <div className="flex justify-between">
                        <div className="text-center w-1/3">
                            <div className="border-t border-brand-400 mb-1"></div>
                            <p className="text-sm text-brand-600">Registrar</p>
                        </div>
                        <div className="text-center w-1/3">
                            <div className="border-t border-brand-400 mb-1"></div>
                            <p className="text-sm text-brand-600">Date Issued</p>
                        </div>
                        <div className="text-center w-1/3">
                            <div className="border-t border-brand-400 mb-1"></div>
                            <p className="text-sm text-brand-600">Document No.</p>
                        </div>
                    </div>
                </div>
            </div>
        </PrintLayout>
    );
}

function formatStatus(status) {
    if (!status) return '—';
    return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
}

function getStatusTone(status) {
    const toneMap = {
        pending: 'pending',
        evaluated: 'evaluated',
        assessed: 'assessed',
        paid: 'paid',
        enrolled: 'enrolled',
        dropped: 'dropped',
    };
    return toneMap[status] || 'neutral';
}