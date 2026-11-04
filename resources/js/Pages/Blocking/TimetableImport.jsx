import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Select, EmptyState, Badge } from '@/Components/ui';

/**
 * The Blocking desk's timetable import.
 *
 * A department keeps its week in a spreadsheet; this screen takes that file, shows what the tool would write and
 * what it would refuse, and only then asks for it. The verdicts are not computed here: the same
 * `ems:import:timetable` a person at a terminal runs answers both surfaces, and the payload arrives already
 * decided, so a row cannot be accepted by one surface and refused by the other.
 */
export default function TimetableImport({ terms = [], columns = '', preview = null }) {
    const sheet = useForm({
        sheet: null,
        termId: terms[0]?.termId ?? '',
    });

    const publish = useForm({
        key: preview?.key ?? '',
        termId: preview?.termId ?? sheet.data.termId ?? '',
    });

    const rows = (preview?.rows ?? []).map((row, i) => ({
        line: row.row ?? i + 1,
        block: row.block,
        subject: row.subject,
        room: row.room,
        instructor: row.instructor,
        when: row.when,
        verdict: row.verdict ?? '',
    }));

    // One line per refused row, in the row's own words: a table cell that carries three sentences wrapped is
    // three sentences nobody reads, which is why the command prints them beside the table rather than inside it.
    const reasons = Object.entries(preview?.reasons ?? {}).flatMap(([line, why]) =>
        (why ?? []).map((reason) => ({ line, reason })),
    );

    const published = preview?.outcome === 'published';
    const canPublish = preview
        && !published
        && preview.key
        && (preview.read ?? 0) > 0
        && (preview.schedules ?? 0) > 0;

    return (
        <AuthenticatedLayout>
            <Head title="Import Timetable" />

            <div className="py-6 space-y-6">
                <PageHeader
                    title="Import a timetable sheet"
                    subtitle="Preview the rows against the Blocking rules, then publish the gaps. Nothing is written by a preview."
                />

                <Card title="1. Choose the term and the file">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            sheet.transform((data) => ({
                                termId: data.termId,
                                sheet: data.sheet,
                            })).post(route('blocking.import.preview'), {
                                forceFormData: true,
                                preserveScroll: true,
                            });
                        }}
                        className="space-y-4"
                    >
                        <div className="max-w-sm">
                            <Select
                                label="Term"
                                value={sheet.data.termId}
                                onChange={(value) => sheet.setData('termId', value)}
                                options={terms.map((t) => ({ value: t.termId, label: t.label }))}
                                placeholder="Choose the term this sheet belongs to"
                            />
                            <p className="text-xs text-slate-500 mt-1">
                                A sheet is read inside one term: the block carries the term, and a meeting from
                                another term is not a collision for the week being built.
                            </p>
                        </div>

                        <div>
                            <label htmlFor="timetable-sheet" className="block text-sm font-medium mb-1">
                                Sheet (CSV or plain text)
                            </label>
                            <input
                                id="timetable-sheet"
                                type="file"
                                accept=".csv,text/csv,text/plain"
                                onChange={(e) => sheet.setData('sheet', e.target.files?.[0] ?? null)}
                                className="block w-full text-sm"
                            />
                            {columns && (
                                <p className="text-xs text-slate-500 mt-1">
                                    Header row to copy: <code>{columns}</code>
                                </p>
                            )}
                            {sheet.errors.sheet && <p className="text-xs text-rose-600 mt-1">{sheet.errors.sheet}</p>}
                            {sheet.errors.termId && <p className="text-xs text-rose-600 mt-1">{sheet.errors.termId}</p>}
                        </div>

                        <div className="flex items-center gap-3">
                            <button type="submit" className="btn btn-primary" disabled={sheet.processing || !sheet.data.sheet}>
                                {sheet.processing ? 'Reading…' : 'Preview the sheet'}
                            </button>
                            <span className="text-xs text-slate-500">
                                The file is kept only until it is published or replaced, and never on a served folder.
                            </span>
                        </div>
                    </form>
                </Card>

                {preview && (
                    <Card title="2. What the sheet would do">
                        <div className="flex flex-wrap items-center gap-2 mb-3">
                            <Badge tone="neutral">{`${preview.read ?? 0} row(s) read`}</Badge>
                            <Badge tone={published ? 'success' : 'neutral'}>{`${preview.schedules ?? 0} schedule(s)`}</Badge>
                            <Badge tone="neutral">{`${preview.meetings ?? 0} meeting line(s)`}</Badge>
                            {(preview.refused ?? 0) > 0 && (
                                <Badge tone="warning">{`${preview.refused} refused row(s)`}</Badge>
                            )}
                            {(preview.skippedForCollision?.length ?? 0) > 0 && (
                                <Badge tone="warning">{`${preview.skippedForCollision.length} skipped as already booked`}</Badge>
                            )}
                        </div>

                        {preview.message && <p className="text-sm mb-3">{preview.message}</p>}

                        {rows.length > 0 ? (
                            <DataTable
                                columns={[
                                    { key: 'line', label: 'Row', className: 'w-12' },
                                    { key: 'block', label: 'Block' },
                                    { key: 'subject', label: 'Subject' },
                                    { key: 'room', label: 'Room' },
                                    { key: 'instructor', label: 'Instructor' },
                                    { key: 'when', label: 'When' },
                                    { key: 'verdict', label: 'Verdict' },
                                ]}
                                rows={rows}
                            />
                        ) : (
                            <EmptyState message="The sheet produced no row verdicts, so nothing was decided about it." />
                        )}

                        {reasons.length > 0 && (
                            <ul className="mt-3 space-y-1 text-xs">
                                {reasons.map((r) => (
                                    <li key={`${r.line}-${r.reason}`}>
                                        row {r.line}: {r.reason}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {(preview.advisories ?? []).length > 0 && (
                            <div className="mt-3">
                                <p className="text-xs font-medium">Warnings that do not stop the sheet:</p>
                                <ul className="text-xs list-disc pl-5">
                                    {preview.advisories.map((a) => <li key={a}>{a}</li>)}
                                </ul>
                            </div>
                        )}

                        <div className="mt-4 flex items-center gap-3">
                            <button
                                type="button"
                                className="btn btn-primary"
                                disabled={!canPublish || publish.processing}
                                onClick={() => publish.transform((data) => ({ key: data.key, termId: data.termId }))
                                    .post(route('blocking.import.publish'), { preserveScroll: true })}
                            >
                                {publish.processing ? 'Publishing…' : 'Publish these rows'}
                            </button>
                            {!canPublish && !published && (
                                <span className="text-xs text-slate-500">
                                    Nothing to publish: the sheet was refused, or every row already collides with a
                                    booked hour.
                                </span>
                            )}
                            {published && (
                                <span className="text-xs text-emerald-700">
                                    Written. Upload the next sheet when you have one.
                                </span>
                            )}
                        </div>
                        {publish.errors.key && <p className="text-xs text-rose-600 mt-2">{publish.errors.key}</p>}
                    </Card>
                )}

                {!preview && (
                    <Card>
                        <EmptyState message="No sheet has been read yet. Choose a term, then pick the file to see what would be written." />
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
