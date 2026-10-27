import { useForm } from '@inertiajs/react';
import { Modal, Select } from '@/Components/ui';

/**
 * The one return act (development prompt §6.4, gap G-10), shared by the three desks that hold
 * `enrollment.return` so a return looks and reads the same wherever it is made.
 *
 * `options` comes from the server, already narrowed to what this record can be sent to and already
 * labelled — the same predicate the save applies. Nothing here lists a destination or a category the
 * controller would refuse, which is why the button only ever lights up for an act the server accepts.
 */
export default function ReturnRecordModal({ enrollmentId, options, open, onClose }) {
    const form = useForm({
        destination: '',
        returnCategory: '',
        returnNote: '',
        expectedCorrection: '',
    });

    const close = () => {
        onClose();
    };

    const note = form.data.returnNote.trim();
    const correction = form.data.expectedCorrection.trim();
    const ready = form.data.destination !== ''
        && form.data.returnCategory !== ''
        && note.length >= 10
        && correction.length >= 5;

    const submit = () => {
        form.post(route('enrollment.return', { enrollment: enrollmentId }), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                close();
            },
        });
    };

    const destinationChoices = options?.destinations || [];
    const categoryChoices = options?.categories || [];

    return (
        <Modal
            show={open}
            onClose={() => {
                form.reset();
                close();
            }}
            title="Return this record"
            subtitle="Say where it goes, why, and what the receiving desk has to finish"
            size="sm"
            footer={
                <div className="flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        onClick={() => {
                            form.reset();
                            close();
                        }}
                        className="btn btn-ghost btn-sm"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={form.processing || !ready}
                        title={ready ? undefined : 'Both choices, a reason of at least 10 characters, and the correction owed are required'}
                        className="btn btn-primary btn-sm disabled:opacity-50"
                    >
                        {form.processing ? 'Returning…' : 'Confirm Return'}
                    </button>
                </div>
            }
        >
            <div className="space-y-4">
                <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Send it back to <span className="text-red-500">*</span>
                    </label>
                    <Select
                        value={form.data.destination}
                        onChange={(value) => form.setData('destination', value)}
                        options={destinationChoices.map((c) => ({ value: c.value, label: c.label }))}
                        placeholder="Choose the desk that must act next"
                    />
                    {form.errors.destination && <p className="form-error mt-1">{form.errors.destination}</p>}
                    {destinationChoices.length === 0 && (
                        <p className="text-xs text-slate-500 mt-1">
                            No desk can take this record back right now — it is either dropped, already
                            returned, or has nothing on file for the other desk to re-open.
                        </p>
                    )}
                </div>

                <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Reason category <span className="text-red-500">*</span>
                    </label>
                    <Select
                        value={form.data.returnCategory}
                        onChange={(value) => form.setData('returnCategory', value)}
                        options={categoryChoices.map((c) => ({ value: c.value, label: c.label }))}
                        placeholder="What kind of problem this is"
                    />
                    {form.errors.returnCategory && <p className="form-error mt-1">{form.errors.returnCategory}</p>}
                </div>

                <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Reason <span className="text-red-500">*</span>
                    </label>
                    <textarea
                        rows={3}
                        value={form.data.returnNote}
                        onChange={(e) => form.setData('returnNote', e.target.value)}
                        placeholder="What is wrong, in the words you would write on the paper"
                        className="w-full text-sm rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                    />
                    {form.errors.returnNote && <p className="form-error mt-1">{form.errors.returnNote}</p>}
                    {note.length > 0 && note.length < 10 && (
                        <p className="text-xs text-slate-500 mt-1">Minimum 10 characters — {10 - note.length} more needed.</p>
                    )}
                </div>

                <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Correction owed <span className="text-red-500">*</span>
                    </label>
                    <textarea
                        rows={2}
                        value={form.data.expectedCorrection}
                        onChange={(e) => form.setData('expectedCorrection', e.target.value)}
                        placeholder="What that desk must finish before this record moves again"
                        className="w-full text-sm rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                    />
                    {form.errors.expectedCorrection && (
                        <p className="form-error mt-1">{form.errors.expectedCorrection}</p>
                    )}
                </div>
            </div>
        </Modal>
    );
}
