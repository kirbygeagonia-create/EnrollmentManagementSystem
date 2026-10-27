import { formatSchoolDay, formatSchoolMoment } from '@/Components/ui/calendarDay';

/**
 * What a returned record says back to the desk that owes the correction.
 *
 * Development prompt §6.4 (gap G-10) asks every return to carry six facts, and the trail row stores
 * them — a record would otherwise be sent back with only `enrollments.returnReason` visible, which is
 * the one sentence ruling 14 already kept, and the `expectedCorrection` the sending desk typed would
 * reach nobody. This component is the read half of that mechanism, shared by the three record pages
 * so the words are the same whichever desk reads them.
 *
 * It prints nothing when the record is not sitting under a return. A record returned before this
 * table existed (`fallbackReason`, from ruling 14's own hold) still shows its reason, because retiring
 * the older notice would leave a desk holding a parked record with no explanation at all.
 */
export default function ReturnNotice({ openReturn, fallbackReason = null, nextStep = null, tone = 'amber' }) {
    if (!openReturn && !fallbackReason) return null;

    const origin = openReturn
        ? (openReturn.originOfficeName || 'an account with no office of its own')
        : 'the Registrar';

    return (
        <div className={`mb-5 rounded-xl border px-4 py-3 text-sm ${tone === 'amber' ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-amber-200 bg-amber-50/60 text-amber-900'}`}>
            <p className="font-bold">
                Returned by {origin}
                {openReturn ? ` to ${openReturn.destinationLabel}` : ''}.
            </p>

            {openReturn && (
                <p className="mt-1 leading-relaxed">
                    <span className="font-bold">Reason category:</span> {openReturn.categoryLabel}.{' '}
                    <span className="font-bold">Sent:</span>{' '}
                    {openReturn.returnedByName
                        ? `${openReturn.returnedByName} on ${formatSchoolMoment(openReturn.returnedAt)}`
                        : formatSchoolDay(openReturn.returnedAt)}
                    , from a record that read <span className="font-mono">{openReturn.previousStatus}</span>.
                </p>
            )}

            <p className="mt-1 leading-relaxed">
                <span className="font-bold">Note:</span> {openReturn ? openReturn.note : fallbackReason}
            </p>

            {openReturn?.expectedCorrection && (
                <p className="mt-1 leading-relaxed">
                    <span className="font-bold">Correction expected:</span> {openReturn.expectedCorrection}
                </p>
            )}

            {nextStep && <p className="mt-1.5 text-xs opacity-80">{nextStep}</p>}
        </div>
    );
}
