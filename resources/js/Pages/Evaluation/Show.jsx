import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageHeader, Badge, CauseEffectModal } from '@/Components/ui';
import { useState, useMemo } from 'react';

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

    // Selected subject IDs for the interactive curriculum load builder
    const initialSelectedIds = (enrollment.enrolledSubjects || []).map((es) => es.subjectId);
    const [selectedSubjectIds, setSelectedSubjectIds] = useState(initialSelectedIds.length > 0 ? initialSelectedIds : (curriculumSubjects || []).map((cs) => cs.subjectId));

    const student = enrollment.student;

    // Toggle subject selection
    const toggleSubject = (subjectId) => {
        setSelectedSubjectIds((prev) =>
            prev.includes(subjectId) ? prev.filter((id) => id !== subjectId) : [...prev, subjectId]
        );
    };

    // Calculate live unit counters
    const selectedCurriculumSubjects = useMemo(() => {
        return (curriculumSubjects || []).filter((cs) => selectedSubjectIds.includes(cs.subjectId));
    }, [curriculumSubjects, selectedSubjectIds]);

    const totalLectureUnits = selectedCurriculumSubjects.reduce((sum, cs) => sum + Number(cs.subject?.lectureUnits || 0), 0);
    const totalLabUnits = selectedCurriculumSubjects.reduce((sum, cs) => sum + Number(cs.subject?.labUnits || 0), 0);
    const totalAcademicUnits = totalLectureUnits + totalLabUnits;

    const handleProposeSelectedSubjects = (e) => {
        e.preventDefault();
        if (selectedSubjectIds.length === 0) {
            alert('Please select at least one subject to propose.');
            return;
        }

        setIsSubmitting(true);
        const payload = selectedSubjectIds.map((id) => ({
            subjectId: id,
        }));

        router.post(
            route('evaluation.subjects.propose', { enrollment: enrollment.enrollmentId }),
            { subjects: payload },
            {
                onSuccess: () => setIsSubmitting(false),
                onError: () => setIsSubmitting(false),
            }
        );
    };

    const handleSign = () => {
        setIsSubmitting(true);
        router.post(
            route('evaluation.sign', { enrollment: enrollment.enrollmentId }),
            {},
            {
                onSuccess: () => {
                    setShowConfirmSign(false);
                    setIsSubmitting(false);
                },
                onError: () => setIsSubmitting(false),
            }
        );
    };

    const collegeInfo = useMemo(() => {
        const code = (enrollment.course?.courseCode || '').toUpperCase();
        const name = (enrollment.course?.courseName || '').toUpperCase();

        if (code.includes('IT') || code.includes('CS') || name.includes('INFORMATION') || name.includes('COMPUTER')) {
            return {
                logo: '/images/logos/college-of-information-and-communication-technology.jpg',
                collegeName: 'College of Information & Communications Technology',
            };
        }
        if (code.includes('AGRI') || code.includes('BSA') || name.includes('AGRICULTURE') || name.includes('FISHERIES')) {
            return {
                logo: '/images/logos/college-of-agriculture-and-fisheries.jpg',
                collegeName: 'College of Agriculture & Fisheries',
            };
        }
        if (code.includes('CRIM') || name.includes('CRIMINOLOGY')) {
            return {
                logo: '/images/logos/college-of-criminal-justice-education.jpg',
                collegeName: 'College of Criminal Justice Education',
            };
        }
        if (code.includes('BA') || code.includes('HM') || code.includes('TM') || name.includes('BUSINESS')) {
            return {
                logo: '/images/logos/college-of-business-and-good-governance.jpg',
                collegeName: 'College of Business & Good Governance',
            };
        }
        if (code.includes('ED') || name.includes('TEACHER') || name.includes('EDUCATION')) {
            return {
                logo: '/images/logos/college-of-teacher-education.jpg',
                collegeName: 'College of Teacher Education',
            };
        }
        return {
            logo: '/images/logos/seait-logo.png',
            collegeName: 'Academic Department Evaluation Desk',
        };
    }, [enrollment.course]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Academic Department Evaluation — ${collegeInfo.collegeName}`}
                    subtitle={`${student?.lastName}, ${student?.firstName} • ${enrollment.course?.courseName || '—'} (${enrollment.term?.semester?.value || enrollment.term?.semester || 'Current Term'})`}
                    logo={collegeInfo.logo}
                    logoAlt={collegeInfo.collegeName}
                    phaseBadge="Phase 2 · Department Evaluation"
                    officeBadge="Office 4 · Academic Evaluation"
                />
            }
        >
            <Head title="Department Evaluation" />

            {/* Status & Student Header Strip */}
            <div className="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div className="flex items-center gap-4">
                    <div className="h-12 w-12 rounded-xl bg-blue-600 text-white font-bold text-base flex items-center justify-center shadow-xs flex-shrink-0">
                        {student?.lastName?.slice(0, 2).toUpperCase() || 'ST'}
                    </div>
                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <h2 className="font-heading font-extrabold text-slate-900 text-lg">
                                {student?.lastName}, {student?.firstName} {student?.middleName ? `${student.middleName[0]}.` : ''}
                            </h2>
                            <Badge tone={studentTypeToneMap[enrollment.studentType] || 'neutral'}>
                                {enrollment.studentType}
                            </Badge>
                            <Badge tone={enrollmentStatusToneMap[enrollment.enrollmentStatus] || 'neutral'}>
                                {enrollment.enrollmentStatus}
                            </Badge>
                        </div>
                        <p className="text-xs text-slate-500 mt-0.5">
                            School ID: <span className="font-mono font-bold text-slate-700">{student?.schoolIdNumber || '—'}</span> • Program: <span className="font-semibold text-slate-800">{enrollment.course?.courseCode}</span> • Year Level: <span className="font-semibold text-slate-800">Year {enrollment.yearLevel}</span>
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-3 w-full md:w-auto justify-end">
                    <button
                        type="button"
                        onClick={() => setShowConfirmSign(true)}
                        disabled={isSubmitting || enrollment.enrollmentStatus !== 'evaluated'}
                        className="px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-500 hover:to-indigo-600 text-white font-heading font-bold text-xs shadow-md transition-all flex items-center gap-2 disabled:opacity-50"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Sign & Finalize Evaluation
                    </button>
                </div>
            </div>

            {/* Split Screen Curriculum Load Builder */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                {/* Left: Available Curriculum Offerings */}
                <div className="lg:col-span-7 space-y-4">
                    <div className="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <div>
                                <h3 className="font-heading font-bold text-slate-900 text-sm flex items-center gap-2">
                                    <span className="h-2.5 w-2.5 rounded-full bg-blue-600" />
                                    Prescribed Curriculum Offerings
                                </h3>
                                <p className="text-xs text-slate-400 mt-0.5">Click to toggle subjects in the student's study load</p>
                            </div>
                            <span className="text-xs font-bold text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-200">
                                {curriculumSubjects?.length || 0} Available
                            </span>
                        </div>

                        <div className="space-y-2.5 max-h-[500px] overflow-y-auto pr-1">
                            {(curriculumSubjects || []).map((cs) => {
                                const isSelected = selectedSubjectIds.includes(cs.subjectId);
                                const subj = cs.subject;
                                return (
                                    <div
                                        key={cs.curriculumSubjectId}
                                        onClick={() => toggleSubject(cs.subjectId)}
                                        className={`p-3.5 rounded-xl border transition-all cursor-pointer flex items-center justify-between gap-3 ${
                                            isSelected
                                                ? 'bg-blue-50/70 border-blue-300 shadow-xs'
                                                : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3 min-w-0">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onChange={() => {}}
                                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 pointer-events-none"
                                            />
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-xs font-bold text-slate-900">{subj?.subjectCode}</span>
                                                    {cs.prerequisiteSubject && (
                                                        <span className="text-[10px] font-medium text-amber-700 bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200">
                                                            Prereq: {cs.prerequisiteSubject.subjectCode}
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-xs text-slate-600 truncate mt-0.5">{subj?.subjectName}</p>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-3 flex-shrink-0 text-xs">
                                            <div className="text-right">
                                                <span className="font-bold text-slate-800">
                                                    {Number(subj?.lectureUnits || 0) + Number(subj?.labUnits || 0)} Units
                                                </span>
                                                <span className="text-[10px] text-slate-400 block">
                                                    Lec: {subj?.lectureUnits || 0} • Lab: {subj?.labUnits || 0}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Right: Proposed Subject Load Summary & Unit Counter */}
                <div className="lg:col-span-5 space-y-4">
                    <div className="bg-gradient-to-br from-slate-900 to-navy-950 text-white rounded-2xl p-6 border border-slate-800 shadow-xl">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                            <h3 className="font-heading font-bold text-white text-sm flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-seait-400" />
                                Proposed Subject Load
                            </h3>
                            <span className="text-xs font-mono font-bold text-seait-400 bg-seait-500/20 px-2 py-0.5 rounded border border-seait-500/30">
                                {selectedSubjectIds.length} Subjects Selected
                            </span>
                        </div>

                        {/* Live Units Breakdown Cards */}
                        <div className="grid grid-cols-3 gap-2.5 mb-5 text-center">
                            <div className="bg-slate-800/80 rounded-xl p-2.5 border border-slate-700">
                                <span className="text-[10px] uppercase font-bold text-slate-400 block">Lecture</span>
                                <span className="text-lg font-mono font-bold text-white">{totalLectureUnits}</span>
                            </div>
                            <div className="bg-slate-800/80 rounded-xl p-2.5 border border-slate-700">
                                <span className="text-[10px] uppercase font-bold text-slate-400 block">Laboratory</span>
                                <span className="text-lg font-mono font-bold text-white">{totalLabUnits}</span>
                            </div>
                            <div className="bg-seait-500/20 rounded-xl p-2.5 border border-seait-500/40">
                                <span className="text-[10px] uppercase font-bold text-seait-300 block">Total Units</span>
                                <span className="text-lg font-mono font-bold text-seait-400">{totalAcademicUnits}</span>
                            </div>
                        </div>

                        {/* Selected Subjects List */}
                        <div className="space-y-2 max-h-60 overflow-y-auto divide-y divide-slate-800/60 mb-5 text-xs">
                            {selectedCurriculumSubjects.length === 0 ? (
                                <p className="text-slate-400 text-center py-6 text-xs">No subjects selected yet.</p>
                            ) : (
                                selectedCurriculumSubjects.map((cs) => (
                                    <div key={cs.curriculumSubjectId} className="pt-2 flex items-center justify-between">
                                        <div className="min-w-0 pr-2">
                                            <span className="font-mono font-bold text-white">{cs.subject?.subjectCode}</span>
                                            <p className="text-[11px] text-slate-400 truncate">{cs.subject?.subjectName}</p>
                                        </div>
                                        <span className="font-mono font-bold text-slate-200 flex-shrink-0">
                                            {Number(cs.subject?.lectureUnits || 0) + Number(cs.subject?.labUnits || 0)}u
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>

                        {/* Save & Propose Button */}
                        <button
                            type="button"
                            onClick={handleProposeSelectedSubjects}
                            disabled={isSubmitting || selectedSubjectIds.length === 0}
                            className="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-seait-500 to-seait-600 hover:from-seait-400 hover:to-seait-500 text-white font-heading font-bold text-xs shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            {isSubmitting ? 'Proposing Subject Load...' : 'Save & Propose Subject Load'}
                        </button>
                    </div>
                </div>
            </div>

            {/* Sign Evaluation Cause & Effect Confirmation Modal */}
            <CauseEffectModal
                show={showConfirmSign}
                onClose={() => setShowConfirmSign(false)}
                onConfirm={handleSign}
                title="Digitally Sign & Approve Academic Study Load"
                subtitle="Official Dean / Program Head Academic Endorsement"
                tone="info"
                entityContext={{
                    label: 'Student Study Load',
                    value: `${student?.lastName}, ${student?.firstName} (${student?.schoolIdNumber || '—'})`,
                    badge: `${totalAcademicUnits} Total Units`,
                }}
                cause={`Signing this evaluation certifies that the ${selectedCurriculumSubjects.length} prescribed subjects comply with curriculum prerequisite standards.`}
                effects={[
                    `Locks the approved ${totalAcademicUnits}.0 academic units (${totalLectureUnits} Lec / ${totalLabUnits} Lab) into the student's active enrollment record.`,
                    'Immediately advances the student to Phase 3 (Scholarship & Assessment Desk) for automated tuition billing calculation.',
                    'Any subsequent subject additions, drops, or timetable swaps will require an official Institutional Add/Drop petition.',
                    'Logs your digital signature stamp with timestamp in the student academic history.',
                ]}
                requiresAcknowledgement={false}
                confirmText="Yes, Sign & Lock Evaluation"
                cancelText="Review Load Again"
                loading={isSubmitting}
            />
        </AuthenticatedLayout>
    );
}
