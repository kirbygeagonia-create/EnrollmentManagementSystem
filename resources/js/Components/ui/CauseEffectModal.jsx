import Modal from './Modal';
import { useState } from 'react';

const toneConfig = {
    danger: {
        iconBg: 'bg-rose-500/15 border-rose-500/30 text-rose-500',
        badgeBg: 'bg-rose-500/10 text-rose-700 border-rose-300 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
        confirmBtn: 'btn btn-danger',
        accentBorder: 'border-rose-500/20 bg-rose-50/40 dark:bg-rose-950/20',
        icon: (
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        ),
    },
    warning: {
        iconBg: 'bg-amber-500/15 border-amber-500/30 text-amber-500',
        badgeBg: 'bg-amber-500/10 text-amber-700 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
        confirmBtn: 'btn btn-accent',
        accentBorder: 'border-amber-500/20 bg-amber-50/40 dark:bg-amber-950/20',
        icon: (
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
    },
    info: {
        iconBg: 'bg-blue-500/15 border-blue-500/30 text-blue-500',
        badgeBg: 'bg-blue-500/10 text-blue-700 border-blue-300 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
        confirmBtn: 'btn btn-primary',
        accentBorder: 'border-blue-500/20 bg-blue-50/40 dark:bg-blue-950/20',
        icon: (
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
    },
    success: {
        iconBg: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-500',
        badgeBg: 'bg-emerald-500/10 text-emerald-700 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
        confirmBtn: 'btn btn-success',
        accentBorder: 'border-emerald-500/20 bg-emerald-50/40 dark:bg-emerald-950/20',
        icon: (
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
    },
};

export default function CauseEffectModal({
    show = false,
    onClose,
    onConfirm,
    title = 'Confirm Institutional Action',
    subtitle = null,
    entityContext = null, // { label: string, value: string, badge?: string }
    tone = 'warning', // 'danger' | 'warning' | 'info' | 'success'
    cause = null, // "When you perform this action..."
    effects = [], // ["Effect 1", "Effect 2", ...]
    requiresAcknowledgement = null, // auto-defaults to true for danger
    acknowledgementText = 'I understand and acknowledge the institutional consequences of this action.',
    confirmText = 'Confirm & Proceed',
    cancelText = 'Cancel, Keep Unchanged',
    loading = false,
}) {
    const shouldRequireAck = requiresAcknowledgement !== null ? requiresAcknowledgement : tone === 'danger';
    const [isAcknowledged, setIsAcknowledged] = useState(false);

    const handleClose = () => {
        setIsAcknowledged(false);
        if (onClose) onClose();
    };

    const handleConfirm = () => {
        setIsAcknowledged(false);
        if (onConfirm) onConfirm();
    };

    const cfg = toneConfig[tone] || toneConfig.warning;
    const isConfirmDisabled = loading || (shouldRequireAck && !isAcknowledged);

    return (
        <Modal
            show={show}
            onClose={handleClose}
            size="md"
            closeable={!loading}
        >
            <div className="space-y-4">
                {/* Header Strip with Severity Icon */}
                <div className="flex items-start gap-3.5 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div className={`h-11 w-11 rounded-2xl flex items-center justify-center border flex-shrink-0 ${cfg.iconBg}`}>
                        {cfg.icon}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2 flex-wrap mb-1">
                            <span className={`text-[10px] uppercase tracking-wider font-extrabold px-2 py-0.5 rounded-full border ${cfg.badgeBg}`}>
                                {tone.toUpperCase()} ACTION
                            </span>
                        </div>
                        <h3 className="text-base font-heading font-bold text-slate-900 dark:text-white leading-snug">
                            {title}
                        </h3>
                        {subtitle && (
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {subtitle}
                            </p>
                        )}
                    </div>
                </div>

                {/* Target Entity Banner (if provided) */}
                {entityContext && (
                    <div className="px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between text-xs">
                        <span className="text-slate-500 dark:text-slate-400 font-semibold">{entityContext.label}:</span>
                        <div className="flex items-center gap-2">
                            <span className="font-bold text-slate-900 dark:text-slate-100 font-mono">
                                {entityContext.value}
                            </span>
                            {entityContext.badge && (
                                <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                    {entityContext.badge}
                                </span>
                            )}
                        </div>
                    </div>
                )}

                {/* Cause Statement */}
                {cause && (
                    <p className="text-xs text-slate-700 dark:text-slate-300 leading-relaxed font-medium">
                        {cause}
                    </p>
                )}

                {/* Structured Cause & Effect Impact List */}
                {effects && effects.length > 0 && (
                    <div className={`p-4 rounded-xl border ${cfg.accentBorder}`}>
                        <h4 className="text-[11px] font-heading font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200 mb-2 flex items-center gap-1.5">
                            <span className="h-1.5 w-1.5 rounded-full bg-slate-500" />
                            System Impact & Consequences:
                        </h4>
                        <ul className="space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                            {effects.map((effect, idx) => (
                                <li key={idx} className="flex items-start gap-2 leading-relaxed">
                                    <span className="text-slate-400 mt-1 flex-shrink-0">•</span>
                                    <span>{effect}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Risk Acknowledgment Checkbox (for high-risk / irreversible actions) */}
                {shouldRequireAck && (
                    <div className="pt-2">
                        <label className="flex items-start gap-2.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 cursor-pointer select-none group">
                            <input
                                type="checkbox"
                                checked={isAcknowledged}
                                onChange={(e) => setIsAcknowledged(e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500 mt-0.5 flex-shrink-0"
                            />
                            <span className="text-xs text-slate-700 dark:text-slate-300 font-semibold group-hover:text-slate-900 dark:group-hover:text-white leading-snug">
                                {acknowledgementText}
                            </span>
                        </label>
                    </div>
                )}

                {/* Action Buttons */}
                <div className="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5">
                    <button
                        type="button"
                        onClick={handleClose}
                        disabled={loading}
                        className="btn btn-secondary w-full sm:w-auto text-xs"
                    >
                        {cancelText}
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={isConfirmDisabled}
                        className={`${cfg.confirmBtn} w-full sm:w-auto text-xs font-bold shadow-md transition-all flex items-center justify-center gap-2 disabled:opacity-50`}
                    >
                        {loading ? (
                            <>
                                <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                Executing...
                            </>
                        ) : (
                            confirmText
                        )}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
