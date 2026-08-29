import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader, Badge, CauseEffectModal, StatCard } from '@/Components/ui';
import { useState } from 'react';

const checklistSteps = [
    { key: 'evaluation_signed', label: 'Dept. Evaluation Signed' },
    { key: 'assessment_completed', label: 'Assessment Computed' },
    { key: 'payment_completed', label: 'Cashier Payment Settled' },
    { key: 'clearance_verified', label: 'Campus Clearance Verified' },
    { key: 'registrarApprovalPending', label: 'Registrar Ready' },
];

export default function Show({ enrollment, checklist, allValid }) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    const form = useForm({
        _method: 'post',
    });

    const handleApprove = () => {
        setConfirmOpen(true);
    };

    const confirmApprove = () => {
        form.post(route('registrar.approve', { enrollment: enrollment.enrollmentId }), {
            onSuccess: () => setConfirmOpen(false),
            onError: () => setConfirmOpen(false),
        });
    };

    const termLabel = enrollment.term
        ? `${enrollment.term.semester?.value || enrollment.term.semester} ${enrollment.term.academicYear?.yearLabel || ''}`.trim()
        : '—';

    const studentName = enrollment.student
        ? `${enrollment.student.lastName}, ${enrollment.student.firstName}${enrollment.student.middleName ? ` ${enrollment.student.middleName.charAt(0)}.` : ''}${enrollment.student.suffix ? ` ${enrollment.student.suffix}` : ''}`
        : '—';

    const isEnrolled = enrollment.enrollmentStatus === 'enrolled' || enrollment.enrollmentStatus?.value === 'enrolled';

    const enrolledSubjects = enrollment.enrolledSubjects || [];
    const totalUnits = enrolledSubjects.reduce((sum, es) => {
        const lec = Number(es.subject?.lectureUnits || 0);
        const lab = Number(es.subject?.labUnits || 0);
        return sum + lec + lab;
    }, 0);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Registrar Official Enrollment & Certificate Studio"
                    subtitle={`${studentName} • ${enrollment.course?.courseName || '—'}`}
                    logo="/images/logos/seait-logo.png"
                    logoAlt="Office of the Registrar Seal"
                    phaseBadge="Phase 5 · Official Enrollment"
                    officeBadge="Office 1 · Office of the Registrar"
                    actions={
                        <Link href={route('registrar.index')} className="btn btn-secondary btn-sm">
                            Back to Queue
                        </Link>
                    }
                />
            }
        >
            <Head title={`Registrar — ${studentName}`} />

            {/* Quick Metrics */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard
                    label="Prerequisite Validation"
                    value={allValid ? 'PASSED (100%)' : 'PENDING GATES'}
                    iconBg={allValid ? 'success' : 'warning'}
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Enrollment Type"
                    value={enrollment.enrollmentType ? enrollment.enrollmentType.toUpperCase() : 'PENDING'}
                    iconBg="seait"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    }
                />
                <StatCard
                    label="Confirmed Academic Load"
                    value={`${enrolledSubjects.length} Subjects (${totalUnits} Units)`}
                    iconBg="info"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    }
                />
                <StatCard
                    label="Enrollment Status"
                    value={isEnrolled ? 'OFFICIALLY ENROLLED' : 'READY FOR APPROVAL'}
                    iconBg={isEnrolled ? 'success' : 'accent'}
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                    }
                />
            </div>

            {/* Split Screen Registrar Suite */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                {/* Left: Validation Gate & Approval Controls */}
                <div className="lg:col-span-6 space-y-6">
                    {/* Validation Gates Checklist */}
                    <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <h3 className="font-heading font-bold text-slate-900 text-sm flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-seait-500" />
                                Pre-Enrollment Compliance Gate
                            </h3>
                            <Badge tone={allValid ? 'success' : 'warning'}>
                                {allValid ? 'All Gates Cleared' : 'Gates Incomplete'}
                            </Badge>
                        </div>

                        <div className="space-y-3">
                            {checklistSteps.map((item) => {
                                const isPassed = checklist[item.key] === true;
                                return (
                                    <div
                                        key={item.key}
                                        className={`flex items-center justify-between p-3.5 rounded-xl border transition-all ${
                                            isPassed
                                                ? 'bg-emerald-50/60 border-emerald-200 text-emerald-900'
                                                : 'bg-slate-50 border-slate-200 text-slate-500'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div
                                                className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                                                    isPassed
                                                        ? 'bg-emerald-600 text-white'
                                                        : 'bg-slate-300 text-slate-600'
                                                }`}
                                            >
                                                {isPassed ? '✓' : '—'}
                                            </div>
                                            <span className="text-xs font-bold">{item.label}</span>
                                        </div>
                                        <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded-full border ${
                                            isPassed
                                                ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                                : 'bg-slate-200 text-slate-600 border-slate-300'
                                        }`}>
                                            {isPassed ? 'Verified' : 'Pending'}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Approval Button */}
                        <div className="pt-5 border-t border-slate-100 mt-5">
                            {!isEnrolled ? (
                                <button
                                    type="button"
                                    onClick={handleApprove}
                                    disabled={!allValid || form.processing}
                                    className="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-seait-600 to-amber-700 hover:from-seait-500 hover:to-amber-600 text-white font-heading font-bold text-xs shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {form.processing ? 'Finalizing Enrollment...' : 'Official Registrar Approval & Certification'}
                                </button>
                            ) : (
                                <div className="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-center font-bold text-xs flex items-center justify-center gap-2">
                                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                    Officially Enrolled by Registrar on {enrollment.enrolledDate ? new Date(enrollment.enrolledDate).toLocaleDateString('en-PH') : 'Recent'}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Official Document Print Suite */}
                    <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
                        <h3 className="font-heading font-bold text-slate-900 text-sm mb-3">Registrar Print Engine</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <Link
                                href={route('registrar.print-certificate', { enrollment: enrollment.enrollmentId })}
                                target="_blank"
                                className="p-3 rounded-xl bg-slate-50 hover:bg-seait-50 border border-slate-200 hover:border-seait-300 text-slate-800 hover:text-seait-700 transition-all text-center flex flex-col items-center justify-center gap-1.5 text-xs font-bold"
                            >
                                <svg className="w-5 h-5 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Certificate of Enrollment</span>
                            </Link>

                            <Link
                                href={route('registrar.print-class-cards', { enrollment: enrollment.enrollmentId })}
                                target="_blank"
                                className="p-3 rounded-xl bg-slate-50 hover:bg-seait-50 border border-slate-200 hover:border-seait-300 text-slate-800 hover:text-seait-700 transition-all text-center flex flex-col items-center justify-center gap-1.5 text-xs font-bold"
                            >
                                <svg className="w-5 h-5 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <span>Class Cards</span>
                            </Link>

                            <Link
                                href={route('registrar.print-subject-load', { enrollment: enrollment.enrollmentId })}
                                target="_blank"
                                className="p-3 rounded-xl bg-slate-50 hover:bg-seait-50 border border-slate-200 hover:border-seait-300 text-slate-800 hover:text-seait-700 transition-all text-center flex flex-col items-center justify-center gap-1.5 text-xs font-bold"
                            >
                                <svg className="w-5 h-5 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                <span>Subject Load</span>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Right: Live Interactive Certificate of Enrollment Preview */}
                <div className="lg:col-span-6 space-y-4">
                    <div className="bg-white rounded-2xl p-6 border-2 border-slate-300 shadow-md text-slate-900 text-xs relative overflow-hidden font-sans">
                        {/* ENROLLED Stamp Watermark */}
                        {isEnrolled && (
                            <div className="absolute right-6 top-24 border-4 border-emerald-600/40 text-emerald-600/40 font-heading font-black text-4xl uppercase px-4 py-2 rounded-xl rotate-[-15deg] select-none pointer-events-none tracking-widest">
                                SEAIT ENROLLED
                            </div>
                        )}

                        {/* Certificate Header */}
                        <div className="text-center border-b-2 border-slate-800 pb-3 mb-4">
                            <div className="flex items-center justify-center gap-2 mb-1">
                                <img src="/images/logos/seait-logo.png" alt="Seal" className="h-9 w-9 object-contain" />
                                <div className="leading-tight">
                                    <p className="font-heading font-extrabold text-sm text-slate-900">SOUTH EAST ASIAN INSTITUTE OF TECHNOLOGY, INC.</p>
                                    <p className="text-[10px] text-slate-500 font-semibold">National Highway, Crossing Rubber, Tupi, South Cotabato</p>
                                </div>
                            </div>
                            <p className="font-heading font-bold text-xs text-seait-700 tracking-wider mt-1 uppercase">CERTIFICATE OF ENROLLMENT</p>
                            <p className="text-[10px] text-slate-500 font-mono">{termLabel}</p>
                        </div>

                        {/* Student Details */}
                        <div className="grid grid-cols-2 gap-2 text-[11px] mb-4 bg-slate-50 p-3 rounded-xl border border-slate-200">
                            <div>
                                <span className="text-slate-400 font-semibold block">Student Name:</span>
                                <span className="font-bold text-slate-900">{studentName}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 font-semibold block">School ID Number:</span>
                                <span className="font-mono font-bold text-slate-900">{enrollment.student?.schoolIdNumber || '—'}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 font-semibold block">Course / Program:</span>
                                <span className="font-bold text-slate-900">{enrollment.course?.courseName}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 font-semibold block">Year Level & Standing:</span>
                                <span className="font-bold text-slate-900">Year {enrollment.yearLevel} ({enrollment.academicStanding || 'Regular'})</span>
                            </div>
                        </div>

                        {/* Subject Load Table */}
                        <div className="mb-4">
                            <table className="w-full text-[11px]">
                                <thead>
                                    <tr className="border-b border-slate-300 text-slate-500 text-left">
                                        <th className="py-1">Code</th>
                                        <th className="py-1">Descriptive Title</th>
                                        <th className="py-1 text-right">Units</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {enrolledSubjects.map((es, idx) => (
                                        <tr key={idx} className="py-1">
                                            <td className="font-mono font-bold text-slate-800">{es.subject?.subjectCode}</td>
                                            <td className="text-slate-600 truncate max-w-[180px]">{es.subject?.subjectName}</td>
                                            <td className="text-right font-bold text-slate-800">
                                                {Number(es.subject?.lectureUnits || 0) + Number(es.subject?.labUnits || 0)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t-2 border-slate-800 font-bold">
                                        <td colSpan={2} className="py-1.5 text-right">TOTAL ACADEMIC UNITS:</td>
                                        <td className="py-1.5 text-right text-seait-700 font-mono text-xs">{totalUnits} Units</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {/* Signatures */}
                        <div className="grid grid-cols-2 gap-6 border-t border-slate-200 pt-4 text-center text-[10px]">
                            <div>
                                <div className="border-b border-slate-400 pb-1 mb-1 font-bold text-slate-800">
                                    {enrollment.evaluatedByUser?.name || 'Academic Dean / Chair'}
                                </div>
                                <span className="text-slate-400">Department Evaluator</span>
                            </div>
                            <div>
                                <div className="border-b border-slate-400 pb-1 mb-1 font-bold text-slate-800">
                                    {enrollment.registrarProcessedByUser?.name || 'Office of the Registrar'}
                                </div>
                                <span className="text-slate-400">College Registrar</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Official Registrar Approval Cause & Effect Modal */}
            <CauseEffectModal
                show={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={confirmApprove}
                title="Execute Official Institutional Enrollment Approval"
                subtitle="Office of the College Registrar — Final Enrollment Gate (Phase 5)"
                tone="success"
                entityContext={{
                    label: 'Enrolling Student',
                    value: studentName,
                    badge: enrollment.course?.courseCode || 'PROGRAM',
                }}
                cause={`Approving this record officially certifies ${studentName} as a bona fide matriculated student for ${termLabel}.`}
                effects={[
                    'Updates enrollment status to ENROLLED and applies the institutional watermark seal to the official Certificate of Enrollment.',
                    'Unlocks student class cards and assigns permanent seats in the official section masterlist.',
                    'Commits student units to CHED Institutional Annual Reports and active student directory files.',
                    'Sends automated clearance to the Student ID Processing Center (Phase 8) and School Health Clinic (Phase 7).',
                ]}
                requiresAcknowledgement={false}
                confirmText="Yes, Issue Official Enrollment"
                cancelText="Return to Verification"
                loading={form.processing}
            />
        </AuthenticatedLayout>
    );
}
