import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { PageHeader, Card, Badge, Modal, FormSection, Select, RadioCards, StatCard, EmptyState, FilterBar, FilterBarField, formatStatusLabel } from '@/Components/ui';
import { useState } from 'react';

const statusTone = {
    pending: 'warning',
    endorsed: 'info',
    granted: 'success',
    rejected: 'danger',
};

const fmt = (d) => (d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }) : '—');

const name = (u) => (u ? `${u.firstName} ${u.lastName}` : '—');

export default function ShiftRequests({ requests, filters = {}, students = [], courses = [], terms = [], can = {}, stats = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [filing, setFiling] = useState(false);
    const [endorsing, setEndorsing] = useState(null);
    const [deciding, setDeciding] = useState(null);

    const fileForm = useForm({ studentId: '', targetCourseId: '', willStatement: '' });
    const endorseForm = useForm({ termId: '', yearLevel: '' });
    const decideForm = useForm({ decision: 'grant', remarks: '' });

    const studentOptions = students.map((s) => {
        const current = s.enrollments?.[0];
        return {
            value: s.studentId,
            label: `${s.lastName}, ${s.firstName} ${s.schoolIdNumber}${current?.course ? ` · ${current.course.courseCode}` : ''}`,
        };
    });

    const courseOptions = courses.map((c) => ({ value: c.courseId, label: `${c.courseCode} — ${c.courseName}` }));
    const termOptions = terms.map((t) => ({ value: t.termId, label: `${t.academicYear?.yearLabel || ''} ${t.semester}` }));

    const submitFile = (e) => {
        e.preventDefault();
        fileForm.post(route('shift.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setFiling(false);
                fileForm.reset();
            },
        });
    };

    const openEndorse = (request) => {
        setEndorsing(request);
        endorseForm.setData({ termId: '', yearLevel: '' });
    };

    const submitEndorse = () => {
        endorseForm.post(route('shift.endorse', { shiftRequest: endorsing.shiftingRequestId }), {
            preserveScroll: true,
            onSuccess: () => setEndorsing(null),
        });
    };

    const submitDecide = () => {
        decideForm.post(route('shift.decide', { shiftRequest: deciding.shiftingRequestId }), {
            preserveScroll: true,
            onSuccess: () => setDeciding(null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Program Change (Shift) Requests"
                    subtitle="Filed at Academic Department Evaluation, endorsed by the dean or program head, decided by the Guidance Councillor — whose signature is the final call"
                    phaseBadge="Phase 2 · Department Evaluation"
                    officeBadge="Office 4 · Academic Evaluation Desk"
                    actions={can.file ? (
                        <button type="button" className="btn btn-primary" onClick={() => setFiling(true)}>
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            File Shift Request
                        </button>
                    ) : null}
                />
            }
        >
            <Head title="Shift Requests" />

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <StatCard compact label="Waiting on the department" value={stats.pending ?? 0} iconBg="warning" />
                <StatCard compact label="Waiting on Guidance" value={stats.endorsed ?? 0} iconBg="info" />
                <StatCard compact label="Granted" value={stats.granted ?? 0} iconBg="success" />
                <StatCard compact label="Refused" value={stats.rejected ?? 0} iconBg="danger" />
            </div>

            <FilterBar
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(route('shift.index'), { search: search || undefined, status: status || undefined }, { preserveState: true, preserveScroll: true });
                }}
                onClear={() => {
                    setSearch('');
                    setStatus('');
                    router.get(route('shift.index'), {}, { preserveState: true });
                }}
            >
                <FilterBarField label="Student">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Name or school ID"
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Waiting on">
                    <Select
                        value={status}
                        onChange={(v) => setStatus(v)}
                        options={[
                            { value: '', label: 'Any status' },
                            { value: 'pending', label: 'Filed — awaiting endorsement' },
                            { value: 'endorsed', label: 'Endorsed — awaiting Guidance' },
                            { value: 'granted', label: 'Granted' },
                            { value: 'rejected', label: 'Refused' },
                        ]}
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            <Card title="The docket" subtitle="Each row is one paper: the student's stated will, and the three signatures behind it">
                {requests.data?.length ? (
                    <div className="space-y-4">
                        {requests.data.map((r) => (
                            <div key={r.shiftingRequestId} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-heading font-bold text-slate-900 text-sm">
                                            #{r.shiftingRequestId} · {r.student?.lastName}, {r.student?.firstName}
                                            <span className="ml-2 font-mono text-xs text-slate-500">{r.student?.schoolIdNumber}</span>
                                        </p>
                                        <p className="text-xs text-slate-600 mt-1">
                                            {r.currentCourse?.courseCode} → <span className="font-semibold">{r.targetCourse?.courseCode}</span>
                                            {r.term ? ` · entering ${r.term.academicYear?.yearLabel} ${r.term.semester}` : ''}
                                            {r.yearLevel ? ` · Year ${r.yearLevel}` : ''}
                                        </p>
                                    </div>
                                    <Badge tone={statusTone[r.requestStatus] || 'neutral'}>{formatStatusLabel(r.requestStatus)}</Badge>
                                </div>

                                <p className="mt-3 text-xs text-slate-700 italic border-l-2 border-slate-200 pl-3">
                                    “{r.willStatement}”
                                </p>

                                <dl className="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <dt className="text-slate-400 uppercase tracking-wide text-[10px] font-bold">Filed</dt>
                                        <dd className="text-slate-700">{name(r.requestedByUser)} · {fmt(r.requestedAt)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-slate-400 uppercase tracking-wide text-[10px] font-bold">Endorsed</dt>
                                        <dd className="text-slate-700">{r.departmentSignedAt ? `${name(r.departmentSignedByUser)} · ${fmt(r.departmentSignedAt)}` : '— not signed'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-slate-400 uppercase tracking-wide text-[10px] font-bold">Guidance decision</dt>
                                        <dd className="text-slate-700">
                                            {r.decidedAt ? `${name(r.decisionByUser)} · ${fmt(r.decidedAt)}` : `— waiting on ${r.requestStatus === 'pending' ? 'the department head' : 'Guidance'}`}
                                        </dd>
                                    </div>
                                </dl>

                                {r.decisionRemarks && (
                                    <p className="mt-2 text-xs text-slate-600">
                                        <span className="font-bold">Decision remarks:</span> {r.decisionRemarks}
                                    </p>
                                )}

                                {r.grantedEnrollment && (
                                    <p className="mt-2 text-xs text-emerald-700">
                                        Receiving enrollment #{r.grantedEnrollment.enrollmentId} issued as a shifter; the seat in {r.currentCourse?.courseCode} was retired.
                                    </p>
                                )}

                                <div className="mt-3 pt-3 border-t border-slate-100 flex flex-wrap gap-2">
                                    {can.endorse && r.requestStatus === 'pending' && (
                                        <button type="button" className="btn btn-secondary btn-sm" onClick={() => openEndorse(r)}>
                                            Endorse with term and level
                                        </button>
                                    )}
                                    {can.decide && r.requestStatus === 'endorsed' && (
                                        <button
                                            type="button"
                                            className="btn btn-primary btn-sm"
                                            onClick={() => {
                                                setDeciding(r);
                                                decideForm.setData({ decision: 'grant', remarks: '' });
                                            }}
                                        >
                                            Decide — final call
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        title="No shift requests on file"
                        message="A shift paper is filed here when a currently enrolled student declares they are changing program."
                    />
                )}
            </Card>

            {/* File the student's declared will */}
            <Modal
                show={filing}
                onClose={() => setFiling(false)}
                title="File a Shift Request"
                subtitle="The student's own declared will to change program"
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" className="btn btn-secondary" onClick={() => setFiling(false)} disabled={fileForm.processing}>Cancel</button>
                        <button type="button" className="btn btn-primary" onClick={submitFile} disabled={fileForm.processing}>
                            {fileForm.processing ? 'Filing…' : 'File the Request'}
                        </button>
                    </div>
                }
            >
                <form onSubmit={submitFile} className="space-y-4">
                    <FormSection label="Student" required error={fileForm.errors.studentId}>
                        <Select
                            value={fileForm.data.studentId}
                            onChange={(v) => fileForm.setData('studentId', v)}
                            options={studentOptions}
                            placeholder="Select an enrolled student"
                            className={`form-input ${fileForm.errors.studentId ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="Receiving program" required error={fileForm.errors.targetCourseId} hint="The program being left is read from the student's current enrollment.">
                        <Select
                            value={fileForm.data.targetCourseId}
                            onChange={(v) => fileForm.setData('targetCourseId', v)}
                            options={courseOptions}
                            placeholder="Select the program they are asking to enter"
                            className={`form-input ${fileForm.errors.targetCourseId ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="The student's stated will to shift" required error={fileForm.errors.willStatement} hint="Written as the student states it — this is the paper the dean and Guidance sign against.">
                        <textarea
                            rows={4}
                            value={fileForm.data.willStatement}
                            onChange={(e) => fileForm.setData('willStatement', e.target.value)}
                            placeholder="e.g., I am requesting to shift from BSIT to BSBA effective the second semester, as recorded in my counselling session."
                            className={`form-input ${fileForm.errors.willStatement ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                </form>
            </Modal>

            {/* Dean / program head endorsement */}
            <Modal
                show={!!endorsing}
                onClose={() => setEndorsing(null)}
                title="Endorse the Shift"
                subtitle={endorsing ? `Request #${endorsing.shiftingRequestId}` : ''}
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" className="btn btn-secondary" onClick={() => setEndorsing(null)} disabled={endorseForm.processing}>Cancel</button>
                        <button type="button" className="btn btn-primary" onClick={submitEndorse} disabled={endorseForm.processing}>
                            {endorseForm.processing ? 'Signing…' : 'Sign Endorsement'}
                        </button>
                    </div>
                }
            >
                <div className="space-y-4">
                    <p className="text-xs text-slate-500">
                        Endorsing states where the student lands: the term they enter and the year level the receiving department sets. Guidance cannot change them — it grants or refuses.
                    </p>
                    <FormSection label="Entering term" required error={endorseForm.errors.termId}>
                        <Select
                            value={endorseForm.data.termId}
                            onChange={(v) => endorseForm.setData('termId', v)}
                            options={termOptions}
                            placeholder="Select term"
                            className={`form-input ${endorseForm.errors.termId ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="Year level" required error={endorseForm.errors.yearLevel} hint="§5.1: the level is the department's academic call, never a value the student type supplies.">
                        <Select
                            value={endorseForm.data.yearLevel}
                            onChange={(v) => endorseForm.setData('yearLevel', v)}
                            options={[1, 2, 3, 4, 5].map((n) => ({ value: n, label: `Year ${n}` }))}
                            placeholder="Year level"
                            className={`form-input ${endorseForm.errors.yearLevel ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                </div>
            </Modal>

            {/* Guidance Councillor's final call */}
            <Modal
                show={!!deciding}
                onClose={() => setDeciding(null)}
                title="Guidance Decision — the Final Call"
                subtitle={deciding ? `Request #${deciding.shiftingRequestId}` : ''}
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" className="btn btn-secondary" onClick={() => setDeciding(null)} disabled={decideForm.processing}>Cancel</button>
                        <button type="button" className="btn btn-primary" onClick={submitDecide} disabled={decideForm.processing}>
                            {decideForm.processing ? 'Recording…' : 'Record Decision'}
                        </button>
                    </div>
                }
            >
                <div className="space-y-4">
                    <RadioCards
                        name="shiftDecision"
                        label="Decision"
                        value={decideForm.data.decision}
                        onChange={(v) => decideForm.setData('decision', v)}
                        options={[
                            { value: 'grant', label: 'Grant the shift', tone: 'success' },
                            { value: 'reject', label: 'Refuse — student stays in program' },
                        ]}
                    />
                    {decideForm.data.decision === 'reject' && (
                        <FormSection label="Reason for refusing" required error={decideForm.errors.remarks} hint="A refusal a panel cannot explain is not a decision.">
                            <textarea
                                rows={3}
                                value={decideForm.data.remarks}
                                onChange={(e) => decideForm.setData('remarks', e.target.value)}
                                className={`form-input ${decideForm.errors.remarks ? 'form-input-error' : ''}`}
                            />
                        </FormSection>
                    )}
                    <p className="text-xs text-slate-500">
                        Granting issues the receiving enrollment as a shifter and retires the seat held in the program being left. Proof of readiness is this paper plus the credit
                        evaluation the receiving department performs — no retention or course examination is sat for a shift.
                    </p>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
