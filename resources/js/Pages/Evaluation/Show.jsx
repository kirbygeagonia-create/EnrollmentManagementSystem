import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { PageHeader, Badge, CauseEffectModal, formatStatusLabel, enrollmentStatusTone } from '@/Components/ui';
import { useState, useMemo } from 'react';
import { collegeLogoFor } from '@/officeBranding';

const studentTypeToneMap = {
    firstYear: 'info',
    continuing: 'success',
    transferee: 'warning',
    shifter: 'accent',
};

export default function Show({ enrollment, curriculumSubjects, curriculum, unmetPrerequisiteSubjectIds = [], retentionExam = null, can = {} }) {
    const [showConfirmSign, setShowConfirmSign] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Item 4: the retention exam lives in the Academic Evaluation area (BR10)
    // — handled and viewed only by the owning academic department.
    const canRecordRetention = can.recordRetention ?? false;
    const requiresRetentionExam = Boolean(enrollment.course?.requiresRetentionExam);

    // Item 6: credit-transfer intake for transferee/shifter students.
    const creditForm = useForm({
        previousSubjectName: '',
        creditedToSubjectId: '',
        creditedUnits: 3,
        institutionName: '',
        institutionType: 'college',
        grade: '',
        remarks: '',
    });

    const handleRecordCredit = () => {
        // The controller validates a nested credits array (one entry per credit
        // row); the intake form is a single row, so wrap it (item 6). Error keys
        // then come back as credits.0.* — matching the panel's error reads.
        creditForm.transform((data) => ({ credits: [data] })).post(
            route('evaluation.credits.process', { enrollment: enrollment.enrollmentId }),
            { onSuccess: () => creditForm.reset() },
        );
    };

    // Item 4: retention exam recording — re-recording corrects the existing
    // result rather than creating a duplicate (firstOrNew on the backend).
    const retentionForm = useForm({
        examResult: retentionExam?.examResult || '',
        examDate: retentionExam?.examDate
            ? new Date(retentionExam.examDate).toISOString().split('T')[0]
            : new Date().toISOString().split('T')[0],
    });

    const handleRecordRetention = (e) => {
        e.preventDefault();
        retentionForm.post(route('evaluation.retention.record', { enrollment: enrollment.enrollmentId }), {
            onSuccess: () => retentionForm.reset('examResult'),
        });
    };

    // Selected subject IDs for the interactive curriculum load builder — the
    // default for a fresh selection excludes prerequisite-locked subjects
    // (item 7), so the unit counter never counts a subject that can't be proposed.
    const initialSelectedIds = (enrollment.enrolledSubjects || []).map((es) => es.subjectId);
    const defaultSelectedIds = (curriculumSubjects || [])
        .filter((cs) => !unmetPrerequisiteSubjectIds.includes(cs.subjectId))
        .map((cs) => cs.subjectId);
    const [selectedSubjectIds, setSelectedSubjectIds] = useState(initialSelectedIds.length > 0 ? initialSelectedIds : defaultSelectedIds);

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

    // College identity is backend data (course.unit) keyed to the official
    // seeded units — not a per-page keyword heuristic, which used to disagree
    // with Students/Show for the same course.
    const collegeName = enrollment.course?.unit?.unitName || 'Academic Department Evaluation';
    const collegeLogo = useMemo(() => collegeLogoFor(enrollment.course?.unitId), [enrollment.course?.unitId]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Academic Department Evaluation — ${collegeName}`}
                    subtitle={`${student?.lastName}, ${student?.firstName} • ${enrollment.course?.courseName || '—'} (${enrollment.term?.semester?.value || enrollment.term?.semester || 'Current Term'})`}
                    logo={collegeLogo}
                    logoAlt={collegeName}
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
                            <Badge tone={enrollmentStatusTone[enrollment.enrollmentStatus] || 'neutral'}>
                                {formatStatusLabel(enrollment.enrollmentStatus)}
                            </Badge>
                        </div>
                        <p className="text-xs text-slate-500 mt-0.5">
                            School ID: <span className="font-mono font-bold text-slate-700">{student?.schoolIdNumber || '—'}</span> • Program: <span className="font-semibold text-slate-800">{enrollment.course?.courseCode}</span> • Year Level: <span className="font-semibold text-slate-800">Year {enrollment.yearLevel}</span>
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3 w-full md:w-auto justify-end">
                    <button
                        type="button"
                        onClick={() => setShowConfirmSign(true)}
                        disabled={isSubmitting || enrollment.enrollmentStatus !== 'evaluated' || !!enrollment.formSignedDate}
                        title={enrollment.formSignedDate || enrollment.enrollmentStatus === 'evaluated' ? undefined : 'Signable once the evaluation status reaches Evaluated'}
                        className="px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-500 hover:to-indigo-600 text-white font-heading font-bold text-xs shadow-md transition-all flex items-center gap-2 disabled:opacity-50"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {enrollment.formSignedDate ? 'Signed & Finalized' : 'Sign & Finalize Evaluation'}
                    </button>

                    {enrollment.enrollmentStatus === 'evaluated' && (
                        <button
                            type="button"
                            onClick={() => router.post(route('assessment.compute', { enrollment: enrollment.enrollmentId }))}
                            disabled={isSubmitting}
                            className="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white font-heading font-bold text-xs shadow-md transition-all flex items-center gap-2"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Compute & Proceed to Assessment
                        </button>
                    )}
                    {enrollment.enrollmentStatus !== 'evaluated' && !enrollment.formSignedDate && (
                        <p className="text-xs text-slate-500 w-full text-right">
                            Signing unlocks once the evaluation is complete (status: Evaluated).
                        </p>
                    )}
                </div>
            </div>

            {/* Registrar Return Notice (Item 8) */}
            {(enrollment.enrollmentStatus === 'returnedToEvaluation' || enrollment.enrollmentStatus?.value === 'returnedToEvaluation') && enrollment.returnReason && (
                <div className="mb-6 bg-amber-50 border border-amber-300 rounded-2xl p-5 flex items-start gap-3 shadow-xs">
                    <div className="h-9 w-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="font-heading font-bold text-amber-900 text-sm">Returned by the Registrar for Re-Evaluation</h3>
                        <p className="text-xs text-amber-800 mt-1 leading-relaxed">
                            <span className="font-bold">Reason:</span> {enrollment.returnReason}
                        </p>
                        <p className="text-[11px] text-amber-700 mt-1.5">
                            Adjust the subject load below and sign again — the record re-enters Assessment automatically after re-evaluation.
                        </p>
                    </div>
                </div>
            )}

            {/* Student-Type Flow Banner (Item 6) */}
            <div className="mb-6">
                {(enrollment.studentType === 'transferee' || enrollment.studentType === 'shifter') ? (
                    <div className="bg-indigo-50 border border-indigo-200 rounded-2xl p-4 flex items-start gap-3 shadow-xs">
                        <div className="h-8 w-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center flex-shrink-0">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                        <div>
                            <h3 className="font-heading font-bold text-indigo-900 text-sm">
                                {enrollment.studentType === 'transferee' ? 'Transferee Flow' : 'Shifter Flow'} — Credit Transfer First
                            </h3>
                            <p className="text-xs text-indigo-800 mt-0.5 leading-relaxed">
                                Record academic credits from the previous institution below before finalizing the study load — credited subjects are exempt from the mandatory-block requirement and prerequisite checks.
                            </p>
                        </div>
                    </div>
                ) : enrollment.studentType === 'continuing' ? (
                    <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-start gap-3 shadow-xs">
                        <div className="h-8 w-8 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center flex-shrink-0">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 className="font-heading font-bold text-slate-900 text-sm">Continuing Student Flow</h3>
                            <p className="text-xs text-slate-600 mt-0.5 leading-relaxed">
                                Campus clearance must be settled with the owning departments before the Registrar can approve — the clearance gate is enforced at Phase 5, not here.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-3 shadow-xs">
                        <div className="h-8 w-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center flex-shrink-0">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253" />
                            </svg>
                        </div>
                        <div>
                            <h3 className="font-heading font-bold text-slate-900 text-sm">First-Year Flow</h3>
                            <p className="text-xs text-slate-600 mt-0.5 leading-relaxed">
                                Propose the prescribed curriculum load below, capture the demographic profile, then sign — the record advances to Assessment automatically.
                            </p>
                        </div>
                    </div>
                )}
            </div>

            {/* Retention Examination — board-course gate, department-owned (Item 4 / BR10) */}
            {requiresRetentionExam && (
                <div className="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs mb-8">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <h3 className="font-heading font-bold text-slate-900 text-sm flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-purple-600" />
                                Retention Examination
                            </h3>
                            <p className="text-xs text-slate-400 mt-0.5">
                                Given in this Academic Evaluation area to students eligible to proceed to the next academic year — handled and viewed only by the owning department (BR10)
                            </p>
                        </div>
                        {retentionExam ? (
                            <div className="flex items-center gap-2">
                                <Badge tone={retentionExam.examResult === 'pass' ? 'success' : retentionExam.examResult === 'fail' ? 'danger' : 'neutral'}>
                                    {formatStatusLabel(retentionExam.examResult)}
                                </Badge>
                                <span className="text-xs font-mono text-slate-500">
                                    {retentionExam.examDate ? new Date(retentionExam.examDate).toLocaleDateString('en-PH') : '—'}
                                </span>
                            </div>
                        ) : (
                            <span className="text-xs font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">
                                Not yet recorded
                            </span>
                        )}
                    </div>

                    {canRecordRetention ? (
                        <form onSubmit={handleRecordRetention} className="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                            <div>
                                <label className="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Exam Result *</label>
                                {/* Pass / Fail as a radio pair, not a dropdown (item 13) */}
                                <div className="grid grid-cols-2 gap-3" role="radiogroup" aria-label="Retention exam result">
                                    {['pass', 'fail'].map((value) => {
                                        const selected = retentionForm.data.examResult === value;
                                        const isPass = value === 'pass';
                                        return (
                                            <label
                                                key={value}
                                                className={`flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold transition ${
                                                    selected
                                                        ? (isPass
                                                            ? 'border-success-500 bg-success-50 text-success-700 ring-2 ring-success-500/20'
                                                            : 'border-danger-500 bg-danger-50 text-danger-700 ring-2 ring-danger-500/20')
                                                        : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="retentionExamResult"
                                                    value={value}
                                                    checked={selected}
                                                    onChange={() => retentionForm.setData('examResult', value)}
                                                    className="sr-only"
                                                />
                                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    {isPass
                                                        ? <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                                        : <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" />}
                                                </svg>
                                                {isPass ? 'Pass' : 'Fail'}
                                            </label>
                                        );
                                    })}
                                </div>
                                {retentionExam && (
                                    <p className="mt-1.5 text-xs text-slate-400">
                                        A result is already on record — re-recording corrects it rather than adding a duplicate.
                                    </p>
                                )}
                                {retentionForm.errors.examResult && <p className="form-error mt-1.5">{retentionForm.errors.examResult}</p>}
                            </div>
                            <div>
                                <label className="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Date of Examination *</label>
                                <input
                                    type="date"
                                    value={retentionForm.data.examDate}
                                    onChange={(e) => retentionForm.setData('examDate', e.target.value)}
                                    className="w-full text-sm rounded-xl border-slate-300 focus:border-purple-500 focus:ring-purple-500 font-mono"
                                    required
                                    max={new Date().toISOString().split('T')[0]}
                                />
                                {retentionForm.errors.examDate && <p className="form-error mt-1.5">{retentionForm.errors.examDate}</p>}
                                <button
                                    type="submit"
                                    disabled={retentionForm.processing || !retentionForm.data.examResult}
                                    className="mt-3 w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-purple-600 to-fuchsia-700 hover:from-purple-500 hover:to-fuchsia-600 text-white font-heading font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                                    title={!retentionForm.data.examResult ? 'Select Pass or Fail first' : undefined}
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {retentionForm.processing ? 'Recording…' : (retentionExam ? 'Update Retention Result' : 'Record Retention Result')}
                                </button>
                            </div>
                        </form>
                    ) : (
                        <p className="text-xs text-slate-500">
                            {retentionExam
                                ? 'Recorded by the owning academic department — shown here for reference only.'
                                : 'The retention exam is recorded by the owning academic department.'}
                        </p>
                    )}
                </div>
            )}

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
                            <div className="flex items-center gap-2">
                                {curriculum && (
                                    <span
                                        className="text-[10px] font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200"
                                        title="This enrollment stays pinned to the curriculum version the student was admitted under"
                                    >
                                        {curriculum.curriculumName || `Curriculum #${curriculum.curriculumId}`}
                                        {curriculum.effectiveYear ? ` · AY ${new Date(curriculum.effectiveYear).getFullYear()}` : ''}
                                    </span>
                                )}
                                <span className="text-xs font-bold text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-200">
                                    {curriculumSubjects?.length || 0} Available
                                </span>
                            </div>
                        </div>

                        <div className="space-y-2.5 max-h-[500px] overflow-y-auto pr-1">
                            {(curriculumSubjects || []).map((cs) => {
                                const isSelected = selectedSubjectIds.includes(cs.subjectId);
                                const isLocked = unmetPrerequisiteSubjectIds.includes(cs.subjectId);
                                const subj = cs.subject;
                                return (
                                    <div
                                        key={cs.curriculumSubjectId}
                                        onClick={() => !isLocked && toggleSubject(cs.subjectId)}
                                        title={isLocked ? `Locked: pass ${cs.prerequisiteSubject?.subjectCode || 'the prerequisite'} first` : undefined}
                                        className={`p-3.5 rounded-xl border transition-all flex items-center justify-between gap-3 ${
                                            isLocked
                                                ? 'bg-slate-50 border-slate-200 opacity-60 cursor-not-allowed'
                                                : isSelected
                                                    ? 'bg-blue-50/70 border-blue-300 shadow-xs cursor-pointer'
                                                    : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/50 cursor-pointer'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3 min-w-0">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                disabled={isLocked}
                                                onChange={() => {}}
                                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 pointer-events-none disabled:opacity-50"
                                            />
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-xs font-bold text-slate-900">{subj?.subjectCode}</span>
                                                    {cs.prerequisiteSubject && (
                                                        <span className={`text-[10px] font-medium px-1.5 py-0.2 rounded border ${
                                                            isLocked
                                                                ? 'text-red-700 bg-red-50 border-red-200'
                                                                : 'text-amber-700 bg-amber-50 border-amber-200'
                                                        }`}>
                                                            {isLocked ? '🔒 ' : ''}Prereq: {cs.prerequisiteSubject.subjectCode}
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
                            title={selectedSubjectIds.length === 0 ? 'Select at least one subject to propose' : undefined}
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

            {/* Credit Transfer Panel — transferee/shifter only (Item 6) */}
            {(enrollment.studentType === 'transferee' || enrollment.studentType === 'shifter') && (
                <div className="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs mb-8">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <h3 className="font-heading font-bold text-slate-900 text-sm flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-indigo-600" />
                                Credit Transfer from Previous Institution
                            </h3>
                            <p className="text-xs text-slate-400 mt-0.5">Credited subjects are exempt from the mandatory block and prerequisite checks</p>
                        </div>
                        <span className="text-xs font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200">
                            {(enrollment.creditedsubjects || []).length} Credited
                        </span>
                    </div>

                    {(enrollment.creditedsubjects || []).length > 0 && (
                        <div className="space-y-2 mb-4">
                            {enrollment.creditedsubjects.map((cs) => (
                                <div key={cs.creditedId} className="flex items-center justify-between p-3 rounded-xl bg-indigo-50/60 border border-indigo-200">
                                    <div className="min-w-0">
                                        <span className="font-bold text-xs text-slate-900">{cs.previousSubjectName}</span>
                                        <span className="text-[11px] text-slate-500 block">→ credited to {cs.creditedToSubject?.subjectCode || cs.creditedToSubjectId}</span>
                                    </div>
                                    <div className="text-right text-xs">
                                        <span className="font-mono font-bold text-indigo-700">{Number(cs.creditedUnits || 0)} units</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label className="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Previous Subject Name *</label>
                            <input
                                type="text"
                                value={creditForm.data.previousSubjectName}
                                onChange={(e) => creditForm.setData('previousSubjectName', e.target.value)}
                                placeholder="e.g., Discrete Structures (STI College)"
                                className="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {creditForm.errors['credits.0.previousSubjectName'] && <p className="text-xs text-red-600 mt-1">{creditForm.errors['credits.0.previousSubjectName']}</p>}
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Credited To Subject *</label>
                            <select
                                value={creditForm.data.creditedToSubjectId}
                                onChange={(e) => creditForm.setData('creditedToSubjectId', e.target.value)}
                                className="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Select subject…</option>
                                {(curriculumSubjects || []).map((cs) => (
                                    <option key={cs.curriculumSubjectId} value={cs.subjectId}>
                                        {cs.subject?.subjectCode} — {cs.subject?.subjectName}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Credited Units *</label>
                            <input
                                type="number"
                                min="0"
                                step="0.5"
                                value={creditForm.data.creditedUnits}
                                onChange={(e) => creditForm.setData('creditedUnits', e.target.value)}
                                className="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Institution Name *</label>
                            <input
                                type="text"
                                value={creditForm.data.institutionName}
                                onChange={(e) => creditForm.setData('institutionName', e.target.value)}
                                placeholder="e.g., STI College"
                                className="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Institution Type *</label>
                            <select
                                value={creditForm.data.institutionType}
                                onChange={(e) => creditForm.setData('institutionType', e.target.value)}
                                className="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="elementary">Elementary</option>
                                <option value="secondary">Secondary</option>
                                <option value="seniorHigh">Senior High</option>
                                <option value="college">College</option>
                                <option value="graduate">Graduate</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">Grade (optional)</label>
                            <input
                                type="number"
                                min="1"
                                max="5"
                                step="0.01"
                                value={creditForm.data.grade}
                                onChange={(e) => creditForm.setData('grade', e.target.value)}
                                placeholder="1.00–5.00"
                                className="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={handleRecordCredit}
                        disabled={creditForm.processing || !creditForm.data.previousSubjectName || !creditForm.data.creditedToSubjectId || !creditForm.data.institutionName}
                        className="w-full mt-4 py-3 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white font-heading font-bold text-xs shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                        title={!creditForm.data.previousSubjectName || !creditForm.data.creditedToSubjectId || !creditForm.data.institutionName ? 'Complete the required fields first' : undefined}
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {creditForm.processing ? 'Recording Credit…' : 'Record Credit Transfer'}
                    </button>
                </div>
            )}

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
