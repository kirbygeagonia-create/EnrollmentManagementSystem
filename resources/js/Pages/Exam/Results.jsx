import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState, StatCard, formatStatusLabel } from '@/Components/ui';
import { useState, useMemo } from 'react';

const resultOptions = [
    { value: '', label: 'All Results' },
    { value: 'pass', label: 'Pass' },
    { value: 'fail', label: 'Fail' },
];

const typeToneMap = {
    general: 'neutral',
    courseSpecific: 'accent',
};

const resultToneMap = {
    pass: 'success',
    fail: 'danger',
};

const typeLabels = {
    general: 'School Entrance',
    courseSpecific: 'Course-Specific',
};

export default function Results({ exams, filters = {}, can = {} }) {
    const [result, setResult] = useState(filters.result || '');

    // Item 4: the page frames itself by the viewer's desk, matching the
    // visibility the backend enforces on the results query.
    const canGeneral = can.recordGeneral ?? false;
    const canCourseSpecific = can.recordCourseSpecific ?? false;
    const isGuidance = canGeneral && !canCourseSpecific;
    const isDepartment = canCourseSpecific && !canGeneral;

    const rows = useMemo(() => exams?.data || [], [exams]);

    // Summary tiles derived from the current page
    const stats = useMemo(() => {
        let pass = 0;
        let fail = 0;
        rows.forEach((r) => {
            if (r.examResult === 'pass') pass += 1;
            else if (r.examResult === 'fail') fail += 1;
        });
        const passRate = rows.length > 0 ? Math.round((pass / rows.length) * 100) : 0;
        return { pass, fail, passRate, total: rows.length };
    }, [rows]);

    const columns = useMemo(() => [
        { key: 'studentIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name' },
        { key: 'course', label: 'Course', render: (row) => row.course?.courseName || '—' },
        { key: 'examType', label: 'Exam', render: (row) => (
            <Badge tone={typeToneMap[row.examType] || 'neutral'}>
                {typeLabels[row.examType] || row.examType}
            </Badge>
        )},
        { key: 'examResult', label: 'Result', render: (row) => (
            <Badge tone={resultToneMap[row.examResult] || 'neutral'}>
                {formatStatusLabel(row.examResult)}
            </Badge>
        )},
        { key: 'examDate', label: 'Date', render: (row) => row.examDate ? new Date(row.examDate).toLocaleDateString('en-PH') : '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('exam.results'), {
            result: result || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const subtitle = isGuidance
        ? 'Every School Entrance Examination result — pass and failed — with passer transfer to the academic departments'
        : isDepartment
            ? 'Your department\'s course-specific exam results, plus the transferred School Entrance passers (names and results only)'
            : canGeneral
                ? 'Pass/fail lists across the School Entrance and course-specific entrance examinations'
                : 'Transferred School Entrance Examination passers — names and results only';

    const hasFilters = Boolean(result);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Exam Results & Verification Matrix"
                    subtitle={subtitle}
                    phaseBadge="Phase 0.5 · BR9"
                    officeBadge={isGuidance ? 'Guidance & Testing Center' : 'Academic Departments'}
                />
            }
        >
            <Head title="Exam Results" />

            {/* Passer transfer note — what crosses the office boundary (BR9) */}
            {(isDepartment || (!canGeneral && !canCourseSpecific)) && (
                <div className="mb-5 flex items-start gap-3 rounded-lg border border-info-200 bg-info-50 px-4 py-3 text-sm text-info-900 dark:border-info-800 dark:bg-info-950/40 dark:text-info-100">
                    <svg className="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        {isDepartment
                            ? 'Rows marked “School Entrance · Pass” are passers transferred from the Guidance & Testing Center — names and results only. Failed School Entrance results stay with Guidance.'
                            : 'This roster lists the School Entrance Examination passers transferred from the Guidance & Testing Center. Failed results remain with Guidance.'}
                    </span>
                </div>
            )}

            {/* Summary tiles */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
                <StatCard
                    compact
                    label="Passed"
                    value={stats.pass}
                    iconBg="success"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Failed"
                    value={stats.fail}
                    iconBg="danger"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Pass Rate"
                    value={`${stats.passRate}%`}
                    iconBg="info"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    }
                />
            </div>

            {/* Filter Bar */}
            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Result">
                    <Select
                        value={result}
                        onChange={setResult}
                        options={resultOptions}
                        placeholder="All Results"
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            {/* Data Table */}
            <Card>
                {exams?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={exams.data}
                            emptyMessage="No exam results found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={exams} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No exam results found"
                        message={hasFilters ? 'Try adjusting your filters to find matching records.' : 'No exam results have been recorded yet.'}
                        icon={
                            <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        }
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
