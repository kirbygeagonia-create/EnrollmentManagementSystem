import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState } from '@/Components/ui';
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
                />
            }
        >
            <Head title="Exam Results" />

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
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}