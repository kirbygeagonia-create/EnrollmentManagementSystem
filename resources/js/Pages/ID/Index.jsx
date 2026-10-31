import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, StatCard, nextSortDirection, sortParams } from '@/Components/ui';
import { formatStatusLabel, idRequestStatusTone, studentTypeTone, academicStandingLabel, academicStandingToneFor } from '@/Components/ui/statusLabel';
import { useState, useMemo } from 'react';

export default function Index({ enrollments, stats = {}, filters = {}, tab = 'queue', tabs = {}, sort = null }) {
    const [search, setSearch] = useState(filters.search || '');
    const [sortBy, setSortBy] = useState(sort?.column || 'enrollmentId');
    const [sortDir, setSortDir] = useState(sort?.direction || 'desc');

    const columns = useMemo(() => [
        { key: 'student.schoolIdNumber', label: 'School ID', sortKey: 'schoolId', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name', sortKey: 'name', render: (row) => (
            row.student ? `${row.student.lastName}, ${row.student.firstName}` : '—'
        )},
        { key: 'course', label: 'Course', sortKey: 'course', render: (row) => row.course?.courseName || '—' },
        // Student Type and Standing are sortable even though both are ENUM columns: on the school's server an ENUM
        // sorts in the order the values were declared, which is the order the school wrote down.
        { key: 'studentType', label: 'Student Type', sortKey: 'studentType', render: (row) => (
            <Badge tone={studentTypeTone[row.studentType] || 'neutral'}>
                {formatStatusLabel(row.studentType)}
            </Badge>
        )},
        { key: 'academicStanding', label: 'Standing', sortKey: 'academicStanding', render: (row) => (
            <Badge tone={academicStandingToneFor(row.academicStanding)}>
                {academicStandingLabel(row.academicStanding)}
            </Badge>
        )},
        // Not sortable: this is the status of the row's FIRST request, and ordering by it would mean deciding what an
        // enrollment with two requests amounts to — a question the school has not answered.
        { key: 'idStatus', label: 'ID Status', render: (row) => {
            const status = row.idrequests?.[0]?.status;

            if (!status) {
                return <Badge tone="neutral">None</Badge>;
            }

            return (
                <Badge tone={idRequestStatusTone[status] || 'neutral'}>
                    {formatStatusLabel(status)}
                </Badge>
            );
        }},
    ], []);

    const visit = (nextSearch, nextTab, nextSort, nextDir) => {
        router.get(route('id.index'), {
            search: nextSearch || undefined,
            tab: nextTab === 'issued' ? 'issued' : undefined,
            // The desk's own newest-first default travels as nothing at all, so a plain link stays a short URL.
            ...sortParams('enrollmentId', nextSort, nextDir),
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleFilter = (e) => {
        e.preventDefault();
        visit(search, tab, sortBy, sortDir);
    };

    const handleSort = (key) => {
        const direction = nextSortDirection(key === sortBy, sortDir);
        setSortBy(key);
        setSortDir(direction);
        visit(search, tab, key, direction);
    };

    const openTab = (next) => visit(search, next, sortBy, sortDir);

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('id.show', { enrollment: row.enrollmentId, ...(tab === 'issued' ? { tab: 'issued' } : {}) })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span className="hidden sm:inline">View</span>
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Student ID Validation"
                    subtitle="Capture the face photo, validate the ID request, and sign the ID Validation workflow step"
                    phaseBadge="Phase 8 · ID Office"
                    officeBadge="ID Validation Desk"
                />
            }
        >
            <Head title="ID Requests" />

            {/* Queue = work still open; Issued = boxes this desk has signed. Validating removes a record from
                the queue by design, so without the second tab the desk cannot reopen its own finished work. */}
            <div className="flex flex-wrap gap-2 mb-4">
                {Object.entries({ queue: tabs.queue, issued: tabs.issued }).map(([key, meta]) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => openTab(key)}
                        className={`px-4 py-2 rounded-xl font-heading font-semibold text-xs transition-all flex items-center gap-2 ${
                            tab === key
                                ? 'bg-seait-600 text-white shadow-md shadow-seait-600/20'
                                : 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-200'
                        }`}
                    >
                        <span>{meta?.label ?? (key === 'issued' ? 'Issued' : 'Awaiting validation')}</span>
                        <span className={`px-1.5 py-0.5 rounded-full text-3xs font-mono font-bold ${tab === key ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'}`}>
                            {meta?.count ?? 0}
                        </span>
                    </button>
                ))}
            </div>

            {/* Summary tiles */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                <StatCard
                    compact
                    label="Pending Validation"
                    value={stats.pending}
                    iconBg="warning"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Validated, on the Issued tab"
                    value={stats.validated}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
            </div>

            {/* Filter Bar */}
            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name, ID number..."
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            {/* Data Table */}
            <Card>
                {enrollments?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={enrollments.data}
                            children={renderActions}
                            sort={sort}
                            onSort={handleSort}
                            emptyMessage="No ID requests found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={enrollments} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title={tab === 'issued' ? 'No ID boxes signed yet' : 'No ID requests found'}
                        message={search
                            ? 'Try adjusting your search to find matching records.'
                            : (tab === 'issued'
                                ? 'Nothing has been validated on this desk yet, so there is no finished work to reopen.'
                                : 'No students are currently pending ID processing.')}
                        icon={
                            <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2M10 6l1.5-1.5a2 2 0 011.414-.586H16a2 2 0 012 2v2.586a2 2 0 01-.586 1.414L16 12M10 6V4a2 2 0 012-2h2a2 2 0 012 2v2" />
                            </svg>
                        }
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
