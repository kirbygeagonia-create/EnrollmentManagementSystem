import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    PageHeader,
    Card,
    DataTable,
    Pagination,
    FilterBar,
    FilterBarField,
    Select,
    Badge,
    StatCard,
    EmptyState,
    formatStatusLabel,
    enrollmentStatusTone,
    studentTypeTone,
} from '@/Components/ui';
import { formatSchoolMoment } from '@/Components/ui/calendarDay';
import { useState } from 'react';

// The three words a box can be in, spelled out so a row does not lean on a colour to say which.
// `skipped` is not the same fact as `pending`: one is a desk the rules excused, the other a desk
// that still owes an act, and §6.2 asks that the two stay distinguishable.
const stepWord = (status) => (status === 'completed' ? 'signed' : status === 'skipped' ? 'skipped' : 'open');

// Development prompt §6.1's fourth place: the pipeline read as one list, whose turn each
// record is on, and why a record that has stopped is stopped. Every desk detail page
// already carries a stepper; nothing in the build answered "where is the whole year".
//
// Read-only by design. §6.3 also asks for a configurable version, and the school has not
// ruled which phases are mandatory (matrix G-6), so this page states the definition a
// record is walking rather than pretending to edit one.
export default function Index({
    enrollments,
    stats = {},
    filters = {},
    officeChoices = [],
    statusChoices = [],
    studentTypeChoices = [],
    termChoices = [],
    definition = {},
}) {
    const { errors } = usePage().props;
    const [draft, setDraft] = useState({
        office: filters.office || '',
        status: filters.status || '',
        studentType: filters.studentType || '',
        term: filters.term || '',
        search: filters.search || '',
    });

    const set = (key) => (value) => setDraft((current) => ({ ...current, [key]: value }));

    const apply = (e) => {
        e.preventDefault();
        router.get(
            route('admin.workflow-monitor'),
            {
                office: draft.office || undefined,
                status: draft.status || undefined,
                studentType: draft.studentType || undefined,
                term: draft.term || undefined,
                search: draft.search || undefined,
            },
            { preserveState: false, preserveScroll: true },
        );
    };

    const filterError = Object.entries(errors || {}).find(([, message]) => message)?.[1];

    const rows = enrollments?.data || [];

    const columns = [
        {
            key: 'studentName',
            label: 'Student',
            render: (row) => (
                <div className="min-w-[10rem]">
                    <p className="text-sm font-semibold text-slate-900">
                        {row.studentLastName ? `${row.studentLastName}, ${row.studentFirstName || ''}` : '—'}
                    </p>
                    <p className="font-mono text-xs text-slate-500">{row.schoolIdNumber || '—'}</p>
                </div>
            ),
        },
        {
            key: 'courseCode',
            label: 'Program / Term',
            render: (row) => (
                <div className="text-sm">
                    <p className="font-semibold text-slate-800">{row.courseCode || '—'}</p>
                    <p className="text-xs text-slate-500">{row.termLabel || 'No term on the record'}</p>
                </div>
            ),
        },
        {
            key: 'studentType',
            label: 'Type',
            render: (row) => (
                <Badge tone={studentTypeTone[row.studentType] || 'neutral'}>
                    {formatStatusLabel(row.studentType)}
                </Badge>
            ),
        },
        {
            key: 'enrollmentStatus',
            label: 'Record status',
            render: (row) => (
                <Badge tone={enrollmentStatusTone[row.enrollmentStatus] || 'neutral'}>
                    {formatStatusLabel(row.enrollmentStatus)}
                </Badge>
            ),
        },
        {
            // §6.1's "progress indicator plus a readable step list". The count is the indicator;
            // the chain underneath names every box in the record's own order with a word for its
            // state, so the row reads the same without colour and without a percentage.
            key: 'progress',
            label: 'Phases signed',
            render: (row) => (
                row.totalBoxes > 0 ? (
                    <div className="min-w-[12rem]">
                        <p className="text-sm font-semibold text-slate-900">
                            {row.signedBoxes} of {row.totalBoxes}
                        </p>
                        <p className="text-xs text-slate-500">{row.openBoxes} still open</p>
                        <p className="mt-1 text-[11px] leading-snug text-slate-600">
                            {(row.steps || []).map((step) => `${step.label || 'Desk'} ${stepWord(step.status)}`).join(', ')}
                        </p>
                    </div>
                ) : (
                    <Badge tone="warning">No workflow form</Badge>
                )
            ),
        },
        {
            key: 'nextPhaseLabel',
            label: 'Desk to act next',
            render: (row) => (
                row.nextPhaseLabel ? (
                    <div className="min-w-[9rem]">
                        <p className="text-sm font-semibold text-slate-900">{row.nextPhaseLabel}</p>
                        <p className="text-xs text-slate-500">
                            {row.nextOfficeName || 'An account with no office of its own'}
                            {row.isLastBox ? ' · last box' : ''}
                        </p>
                    </div>
                ) : (
                    <p className="text-sm text-slate-500">Nothing open — signed through</p>
                )
            ),
        },
        {
            // An instant, so the viewer's own clock is the right one to read it by.
            key: 'lastActedAt',
            label: 'Last desk action',
            render: (row) => (
                <p className="whitespace-nowrap text-sm text-slate-600">
                    {row.lastActedAt ? formatSchoolMoment(row.lastActedAt) : 'No box signed yet'}
                </p>
            ),
        },
        {
            // §6.4's reason, read off the return row the receiving desk also reads.
            key: 'returnCategoryLabel',
            label: 'Returned for',
            render: (row) => (
                row.returnCategoryLabel ? (
                    <div className="min-w-[9rem]">
                        <Badge tone="danger">{row.returnCategoryLabel}</Badge>
                        <p className="mt-1 text-xs text-slate-500">
                            from {row.returnOriginOfficeName || 'a desk with no office of its own'}
                            {' · '}
                            to {row.returnDestinationLabel || '—'}
                        </p>
                        <p className="text-xs italic text-slate-600" title={row.returnNote}>
                            {row.returnNote ? `${row.returnNote.slice(0, 70)}${row.returnNote.length > 70 ? '…' : ''}` : ''}
                        </p>
                    </div>
                ) : (
                    <span className="text-xs text-slate-400">—</span>
                )
            ),
        },
        {
            key: 'actions',
            label: 'Open',
            render: (row) => (
                row.studentId
                    ? <Link href={route('students.show', row.studentId)} className="text-sm font-semibold text-seait-700 hover:underline">Record</Link>
                    : <span className="text-xs text-slate-400">—</span>
            ),
        },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Workflow Monitor" />

            <div className="space-y-5">
                <PageHeader
                    title="Workflow Monitor"
                    subtitle="Every record still in the pipeline, whose turn it is, and what any desk sent back"
                    phaseBadge="Admin · Institution-wide"
                />

                {/* The five tiles are the handler's counts over the filtered set — never a
                    tally of the rows on this page — and each is a number the server gave. */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <StatCard label="Records in view" value={stats.inView} compact />
                    <StatCard label="Awaiting a desk" value={stats.awaiting} compact iconBg="info" />
                    <StatCard label="Signed through" value={stats.signedThrough} compact iconBg="success" />
                    <StatCard label="Returned for correction" value={stats.returned} compact iconBg="danger" />
                    <StatCard label="No workflow form" value={stats.withoutForm} compact iconBg="warning" />
                </div>

                <Card>
                    <FilterBar onSubmit={apply}>
                        <FilterBarField label="Desk to act next">
                            <Select
                                value={draft.office}
                                onChange={set('office')}
                                placeholder="Any desk"
                                options={officeChoices.map((b) => ({
                                    value: b.officeId,
                                    label: `${b.label}${b.officeName ? ` (${b.officeName})` : ''}`,
                                }))}
                            />
                        </FilterBarField>
                        <FilterBarField label="Record status">
                            <Select
                                value={draft.status}
                                onChange={set('status')}
                                placeholder="Any status"
                                options={statusChoices.map((v) => ({ value: v, label: formatStatusLabel(v) }))}
                            />
                        </FilterBarField>
                        <FilterBarField label="Student type">
                            <Select
                                value={draft.studentType}
                                onChange={set('studentType')}
                                placeholder="Any type"
                                options={studentTypeChoices.map((v) => ({ value: v, label: formatStatusLabel(v) }))}
                            />
                        </FilterBarField>
                        <FilterBarField label="Term">
                            <Select
                                value={draft.term}
                                onChange={set('term')}
                                placeholder="Any term"
                                options={termChoices.map((t) => ({ value: t.termId, label: t.label }))}
                            />
                        </FilterBarField>
                        <FilterBarField label="Name, ID or program">
                            <input
                                className="filter-input w-full min-w-[10rem] rounded-md border border-slate-300 px-3 py-2 text-sm"
                                value={draft.search}
                                onChange={(e) => set('search')(e.target.value)}
                                placeholder="Dela Cruz, 2026-0001, BSIT"
                            />
                        </FilterBarField>
                    </FilterBar>

                    {/* A refused filter value is shown, not swallowed: the server validated it
                        and the list would otherwise read as an empty queue. */}
                    {filterError && (
                        <p className="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700">
                            {filterError}
                        </p>
                    )}

                    {/* §6.3 asked for the effective definition beside the progress. The order is
                        still one code list, so the page names that rather than inventing a version. */}
                    <p className="mt-3 text-xs text-slate-500">
                        Definition in force: {definition.boxCount ?? '—'} boxes declared by{' '}
                        <code className="font-mono">{definition.source || 'the workflow service'}</code>
                        {definition.versioned
                            ? ', from the active version.'
                            : ' — not yet versioned by academic year, semester or student type, because which phases are mandatory is a school ruling (matrix G-6). Each row below is read from that record’s own steps, so a signed box cannot be rewritten by a later change.'}
                    </p>
                </Card>

                <Card>
                    {rows.length === 0 ? (
                        <EmptyState
                            title="No records match these filters"
                            description="Clear a filter to widen the view. A record left the pipeline entirely when it is dropped, and dropped records are not listed here."
                        />
                    ) : (
                        <>
                            <DataTable columns={columns} rows={rows} />
                            <Pagination paginator={enrollments} />
                        </>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
