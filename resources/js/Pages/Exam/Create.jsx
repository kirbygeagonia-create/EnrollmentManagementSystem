/* eslint-disable react-hooks/set-state-in-effect */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, FormSection, Select } from '@/Components/ui';
import { useForm, router } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import useFormKeyboardNav from '@/Hooks/useFormKeyboardNav';

const resultOptions = [
    { value: 'pass', label: 'Pass' },
    { value: 'fail', label: 'Fail' },
];

export default function Create({ courses, terms, selectedCourse, selectedTerm, stage, type }) {
    const [students, setStudents] = useState([]);
    const [loadingStudents, setLoadingStudents] = useState(false);
    const { formProps } = useFormKeyboardNav();

    const form = useForm({
        studentId: '',
        courseId: selectedCourse?.courseId || '',
        termId: selectedTerm?.termId || '',
        examResult: '',
        examDate: new Date().toISOString().split('T')[0],
    });

    const fetchStudents = useCallback(() => {
        if (form.data.courseId && form.data.termId) {
            setLoadingStudents(true);
            router.get(route('exam.students', { courseId: form.data.courseId, termId: form.data.termId }), {
                only: ['students'],
                onSuccess: (page) => {
                    setStudents(page.props.students || []);
                    setLoadingStudents(false);
                },
                onError: () => setLoadingStudents(false),
            });
        } else {
            setStudents([]);
        }
    }, [form.data.courseId, form.data.termId]);

    // Fetch students when course and term change
    useEffect(() => {
        fetchStudents();
    }, [fetchStudents]);

    const handleSubmit = (e) => {
        e.preventDefault();

        let submitRoute;
        if (stage === 'entrance' && type === 'general') {
            submitRoute = route('exam.general.record');
        } else if (stage === 'entrance' && type === 'courseSpecific') {
            submitRoute = route('exam.course-specific.record');
        } else if (stage === 'retention') {
            submitRoute = route('exam.retention.record');
        } else {
            submitRoute = route('exam.general.record');
        }

        router.post(submitRoute, form.data(), {
            onSuccess: () => form.reset('examResult'),
        });
    };

    const getStageLabel = () => {
        if (stage === 'entrance' && type === 'general') return 'General Entrance Exam';
        if (stage === 'entrance' && type === 'courseSpecific') return 'Course-Specific Entrance Exam';
        if (stage === 'retention') return 'Retention Exam';
        return 'Exam';
    };

    const getStageDescription = () => {
        if (stage === 'entrance' && type === 'general') return 'Record general entrance exam results (Guidance Office)';
        if (stage === 'entrance' && type === 'courseSpecific') return 'Record course-specific entrance exam results (Department)';
        if (stage === 'retention') return 'Record retention exam results (Board course continuing students)';
        return 'Record exam results';
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={getStageLabel()}
                    subtitle={getStageDescription()}
                    logo="/images/logos/guidance-office.jpg"
                    logoAlt="Guidance Office"
                />
            }
        >
            <Head title={getStageLabel()} />

            <form onSubmit={handleSubmit} {...formProps} className="space-y-5">
                {/* Rapid Entry Banner */}
                <div className="flex items-center justify-between px-4 py-2.5 bg-amber-500/10 border border-amber-500/30 rounded-xl text-xs text-amber-900 font-medium">
                    <div className="flex items-center gap-2">
                        <span className="h-2 w-2 rounded-full bg-amber-500 animate-pulse" />
                        <span><strong>Rapid Data Entry:</strong> Press <kbd className="px-1.5 py-0.5 bg-white border border-amber-300 rounded shadow-xs font-mono font-bold">Enter ↵</kbd> to jump to next field · <kbd className="px-1.5 py-0.5 bg-white border border-amber-300 rounded shadow-xs font-mono font-bold">Ctrl+Enter</kbd> to record exam</span>
                    </div>
                    <span className="hidden sm:inline text-amber-700 font-mono text-[11px] font-bold">AUTO-ADVANCE READY</span>
                </div>

                {/* 2-Column High-Efficiency Form Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* Left Column (7 cols): Academic Target & Student */}
                    <div className="lg:col-span-7 space-y-4">
                        <Card title="Target Academic Cohort & Student" subtitle="Select program, term, and candidate applicant">
                            <div className="space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <FormSection label="Course / Program" required>
                                        <Select
                                            value={form.data.courseId}
                                            onChange={(value) => form.setData('courseId', value)}
                                            options={courses.map(c => ({ value: c.courseId, label: `${c.courseCode} - ${c.courseName}` }))}
                                            placeholder="Select course"
                                            required
                                        />
                                        {form.errors.courseId && <p className="form-error">{form.errors.courseId}</p>}
                                    </FormSection>

                                    <FormSection label="Academic Term" required>
                                        <Select
                                            value={form.data.termId}
                                            onChange={(value) => form.setData('termId', value)}
                                            options={terms.map(t => ({ value: t.termId, label: `${t.semester} ${t.academicYear?.year || ''}` }))}
                                            placeholder="Select term"
                                            required
                                        />
                                        {form.errors.termId && <p className="form-error">{form.errors.termId}</p>}
                                    </FormSection>
                                </div>

                                <FormSection label="Candidate Student" required>
                                    {loadingStudents ? (
                                        <div className="flex items-center gap-3 p-3 rounded-xl bg-brand-50 border border-brand-100">
                                            <svg className="animate-spin h-5 w-5 text-seait-600" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            <span className="text-brand-700 text-xs font-semibold">Loading student roster for selected course...</span>
                                        </div>
                                    ) : students.length > 0 ? (
                                        <Select
                                            value={form.data.studentId}
                                            onChange={(value) => form.setData('studentId', value)}
                                            options={students.map(s => ({ value: s.studentId, label: `${s.schoolIdNumber} — ${s.lastName}, ${s.firstName} ${s.middleName ? s.middleName.charAt(0) + '.' : ''}` }))}
                                            placeholder="Search & select candidate student..."
                                            required
                                        />
                                    ) : (
                                        <div className="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
                                            <svg className="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                            </svg>
                                            <span className="text-slate-500 text-xs font-medium">
                                                {form.data.courseId && form.data.termId ? 'No examinees found awaiting score entry for this cohort.' : 'Please select Course and Term above to populate students.'}
                                            </span>
                                        </div>
                                    )}
                                    {form.errors.studentId && <p className="form-error">{form.errors.studentId}</p>}
                                </FormSection>
                            </div>
                        </Card>
                    </div>

                    {/* Right Column (5 cols): Exam Score & Action */}
                    <div className="lg:col-span-5 space-y-4">
                        <Card title="Exam Assessment & Scoring" subtitle="Official score result and date of evaluation">
                            <div className="space-y-4">
                                <FormSection label="Evaluation Result" required>
                                    <Select
                                        value={form.data.examResult}
                                        onChange={(value) => form.setData('examResult', value)}
                                        options={resultOptions}
                                        placeholder="Select result (Pass / Fail)"
                                        required
                                    />
                                    {form.errors.examResult && <p className="form-error">{form.errors.examResult}</p>}
                                </FormSection>

                                <FormSection label="Date of Examination" required>
                                    <input
                                        type="date"
                                        value={form.data.examDate}
                                        onChange={(e) => form.setData('examDate', e.target.value)}
                                        className="form-input text-sm font-mono"
                                        required
                                        max={new Date().toISOString().split('T')[0]}
                                    />
                                    {form.errors.examDate && <p className="form-error">{form.errors.examDate}</p>}
                                </FormSection>

                                <div className="pt-2 border-t border-slate-100 flex items-center justify-between gap-3">
                                    <button
                                        type="button"
                                        onClick={() => router.get(route('exam.index'))}
                                        className="btn btn-secondary text-xs"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing || !form.data.studentId}
                                        className="btn btn-primary text-xs shadow-md"
                                    >
                                        {form.processing ? (
                                            <>
                                                <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                </svg>
                                                Recording...
                                            </>
                                        ) : (
                                            <>
                                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Record Exam Result
                                            </>
                                        )}
                                    </button>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
