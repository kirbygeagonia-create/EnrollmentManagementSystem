/* eslint-disable react-hooks/set-state-in-effect */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, FormSection, Select } from '@/Components/ui';
import { useForm, router } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';

const resultOptions = [
    { value: 'pass', label: 'Pass' },
    { value: 'fail', label: 'Fail' },
];

export default function Create({ courses, terms, selectedCourse, selectedTerm, stage, type }) {
    const [students, setStudents] = useState([]);
    const [loadingStudents, setLoadingStudents] = useState(false);

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

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Course & Term Selection */}
                <Card title="Course & Term" subtitle="Select the course and term for this exam">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <FormSection label="Course" required>
                            <Select
                                value={form.data.courseId}
                                onChange={(value) => form.setData('courseId', value)}
                                options={courses.map(c => ({ value: c.courseId, label: `${c.courseCode} - ${c.courseName}` }))}
                                placeholder="Select course"
                                required
                            />
                            {form.errors.courseId && <p className="form-error">{form.errors.courseId}</p>}
                        </FormSection>

                        <FormSection label="Term" required>
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
                </Card>

                {/* Student Selection */}
                <Card title="Student" subtitle="Select the student to record exam for">
                    <FormSection label="Student" required>
                        {loadingStudents ? (
                            <div className="flex items-center gap-3 p-3 rounded-lg bg-brand-50">
                                <svg className="animate-spin h-5 w-5 text-seait-600" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span className="text-brand-600 text-sm">Loading students...</span>
                            </div>
                        ) : students.length > 0 ? (
                            <Select
                                value={form.data.studentId}
                                onChange={(value) => form.setData('studentId', value)}
                                options={students.map(s => ({ value: s.studentId, label: `${s.schoolIdNumber} - ${s.lastName}, ${s.firstName} ${s.middleName ? s.middleName.charAt(0) + '.' : ''}` }))}
                                placeholder="Select student"
                                required
                            />
                        ) : (
                            <div className="flex items-center gap-3 p-3 rounded-lg bg-brand-50">
                                <svg className="h-5 w-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <span className="text-brand-500 text-sm">
                                    {form.data.courseId && form.data.termId ? 'No students found for this course and term.' : 'Select a course and term to load students.'}
                                </span>
                            </div>
                        )}
                        {form.errors.studentId && <p className="form-error">{form.errors.studentId}</p>}
                    </FormSection>
                </Card>

                {/* Exam Details */}
                <Card title="Exam Details" subtitle="Record the exam result and date">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <FormSection label="Result" required>
                            <Select
                                value={form.data.examResult}
                                onChange={(value) => form.setData('examResult', value)}
                                options={resultOptions}
                                placeholder="Select result"
                                required
                            />
                            {form.errors.examResult && <p className="form-error">{form.errors.examResult}</p>}
                        </FormSection>

                        <FormSection label="Exam Date" required>
                            <input
                                type="date"
                                value={form.data.examDate}
                                onChange={(e) => form.setData('examDate', e.target.value)}
                                className="form-input"
                                required
                                max={new Date().toISOString().split('T')[0]}
                            />
                            {form.errors.examDate && <p className="form-error">{form.errors.examDate}</p>}
                        </FormSection>
                    </div>
                </Card>

                {/* Actions */}
                <div className="flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={() => router.get(route('exam.index'))}
                        className="btn btn-secondary"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing || !form.data.studentId}
                        className="btn btn-primary"
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
                                Record Exam
                            </>
                        )}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
