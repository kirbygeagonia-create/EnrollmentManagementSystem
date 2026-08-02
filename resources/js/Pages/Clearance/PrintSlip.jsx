import { PrintLayout, Card, Badge } from '@/Components/ui';

const approvalStatusToneMap = {
    pending: 'pending',
    approved: 'success',
    rejected: 'danger',
    waived: 'info',
};

export default function PrintSlip({ clearance }) {
    const student = clearance.student;
    const period = clearance.clearancePeriod;
    const approvals = clearance.approvals || [];

    const term = period?.term;
    const academicYear = term?.academicYear;

    return (
        <PrintLayout
            title="Clearance Slip"
            subtitle={student ? `${student.lastName}, ${student.firstName} ${student.middleName ? student.middleName.charAt(0) + '.' : ''}` : 'Student Clearance'}
            date={academicYear ? `${academicYear.yearStart}-${academicYear.yearEnd} ${term?.semester}` : undefined}
            headerContent={
                <div className="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <p className="font-medium">School ID:</p>
                        <p>{student?.schoolIdNumber || '—'}</p>
                    </div>
                    <div>
                        <p className="font-medium">Course:</p>
                        <p>{student?.enrollments?.[0]?.course?.name || '—'}</p>
                    </div>
                    <div>
                        <p className="font-medium">Year Level:</p>
                        <p>{student?.enrollments?.[0]?.yearLevel || '—'}</p>
                    </div>
                </div>
            }
            footerContent={
                <>
                    <p className="text-center font-medium mb-2">Clearance Status: <span className="font-normal capitalize">{clearance.overallStatus}</span></p>
                    <p className="text-center text-sm text-brand-500">This document is valid only with official signatures from all required offices.</p>
                </>
            }
        >
            {/* Requirements Checklist */}
            <Card title="Clearance Requirements" subtitle="Office approvals and signatures">
                <div className="space-y-3">
                    {approvals.length > 0 ? (
                        approvals.map((approval, index) => {
                            const req = approval.clearanceRequirement;
                            const office = req?.office;
                            return (
                                <div key={approval.clearanceApprovalId} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 border border-brand-200 rounded-lg">
                                    <div className="flex items-center gap-4 flex-1">
                                        <span className="font-mono text-sm text-brand-500 w-8 text-center">{index + 1}.</span>
                                        <div>
                                            <p className="font-medium text-brand-900">{office?.officeName || 'Unknown Office'}</p>
                                            <p className="text-sm text-brand-500">{req?.office?.officeName || 'Clearance Requirement'}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-4 sm:ml-auto">
                                        <Badge tone={approvalStatusToneMap[approval.status] || 'neutral'} className="whitespace-nowrap">
                                            {approval.status?.charAt(0).toUpperCase() + approval.status?.slice(1)}
                                        </Badge>
                                        <div className="w-32 border-t border-brand-300" />
                                        <span className="text-xs text-brand-500">Signature</span>
                                    </div>
                                    {approval.remarks && (
                                        <div className="sm:col-span-2 mt-2 text-sm text-brand-600">
                                            <span className="font-medium">Remarks:</span> {approval.remarks}
                                        </div>
                                    )}
                                </div>
                            );
                        })
                    ) : (
                        <p className="text-brand-500 text-center py-8">No clearance requirements configured for this period.</p>
                    )}
                </div>
            </Card>

            {/* Overall Status */}
            <Card title="Overall Clearance Status">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-lg font-medium text-brand-900">Status: <span className="capitalize">{clearance.overallStatus}</span></p>
                        {clearance.receivedDate && (
                            <p className="text-sm text-brand-500 mt-1">Received on: {new Date(clearance.receivedDate).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        )}
                        {clearance.receivedBy && (
                            <p className="text-sm text-brand-500">Received by: {clearance.receivedBy.name}</p>
                        )}
                    </div>
                    <Badge tone={approvalStatusToneMap[clearance.overallStatus] || 'neutral'} className="text-lg px-4 py-2">
                        {clearance.overallStatus?.charAt(0).toUpperCase() + clearance.overallStatus?.slice(1)}
                    </Badge>
                </div>
            </Card>
        </PrintLayout>
    );
}