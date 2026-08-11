import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';

const stageOptions = [
    { value: '', label: 'All Stages' },
    { value: 'entrance', label: 'Entrance' },
    { value: 'retention', label: 'Retention' },
];

const resultOptions = [
    { value: '', label: 'All Results' },
    { value: 'pass', label: 'Pass' },
    { value: 'fail', label: 'Fail' },
];

const stageToneMap = {
    entrance: 'info',
    retention: 'warning',
};

const typeToneMap = {
    general: 'neutral',
    courseSpecific: 'accent',
};

const resultToneMap = {
    pass: 'success',
    fail: 'danger',
};

export default function Results({ exams, filters = {} }) {
    const [stage, setStage] = useState(filters.stage || '');
    const [result, setResult] = useState(filters.result || '');

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
        { key: 'examStage', label: 'Stage', render: (row) => (
            <Badge tone={stageToneMap[row.examStage] || 'neutral'}>
                {row.examStage?.charAt(0).toUpperCase() + row.examStage?.slice(1)}
            </Badge>
        )},
        { key: 'examType', label: 'Type', render: (row) => (
            <Badge tone={typeToneMap[row.examType] || 'neutral'}>
                {row.examType === 'courseSpecific' ? 'Course Specific' : row.examType?.charAt(0).toUpperCase() + row.examType?.slice(1)}
            </Badge>
        )},
        { key: 'examResult', label: 'Result', render: (row) => (
            <Badge tone={resultToneMap[row.examResult] || 'neutral'}>
                {row.examResult?.charAt(0).toUpperCase() + row.examResult?.slice(1)}
            </Badge>
        )},
        { key: 'examDate', label: 'Date', render: (row) => row.examDate ? new Date(row.examDate).toLocaleDateString('en-PH') : '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (stage) params.set('stage', stage);
        if (result) params.set('result', result);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Exam Results"
                    subtitle="Pass/fail lists for entrance and retention exams"
                    logo="/images/logos/guidance-office.jpg"
                    logoAlt="Guidance Office"
                />
            }
        >
            <Head title="Exam Results" />

            {/* Summary tiles */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <StatCard
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
                <FilterBarField label="Stage">
                    <Select
                        value={stage}
                        onChange={setStage}
                        options={stageOptions}
                        placeholder="All Stages"
                        className="form-input"
                    />
                </FilterBarField>
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
                        message={stage || result ? 'Try adjusting your filters to find matching records.' : 'No exam results have been recorded yet.'}
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
