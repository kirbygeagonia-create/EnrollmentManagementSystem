import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * The one place a server flash becomes something a desk can read.
 *
 * Three rules this file exists to keep:
 *   - Every flash on the redirect is shown. A controller may send a success AND a warning about
 *     the same action (Blocking does, for a schedule that saved with a conflict), and dropping the
 *     second one loses the only notice the desk gets.
 *   - A message that needs an answer stays until it is dismissed. A timer that suits "Saved" will
 *     bury a forty-word instruction on what to do next.
 *   - An urgent message is announced, not whispered. role="status" is polite, and a screen reader
 *     can leave it unread while the desk moves on.
 *   - A rejected save is read even when the form has no room for it. Page-level field errors join the
 *     same queue, because most desk forms post directly and render no error list of their own.
 */
const variants = {
    success: {
        // Not "Saved": the desk's success flashes include "Admission rejected." and
        // "Assessment finalized.", which are completions rather than saves.
        label: 'Completed',
        accent: 'bg-success-500',
        iconWrap: 'bg-success-100 text-success-700',
        border: 'border-success-200',
        hold: 4500,
        urgent: false,
        icon: (
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
    },
    warning: {
        // "Heads up" and not something like "One thing to check", which would claim the action
        // finished: three of the four warnings in app/ are refusals ("Payment can only be
        // collected for assessed enrollments", "This account still has an outstanding balance"),
        // and only Blocking's schedule conflict is a save with a caveat. The key is overloaded,
        // so the label stays neutral and each message carries whether anything happened.
        label: 'Heads up',
        accent: 'bg-warning-500',
        iconWrap: 'bg-warning-100 text-warning-700',
        border: 'border-warning-200',
        hold: 14000,
        urgent: true,
        icon: (
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
        ),
    },
    info: {
        label: 'Good to know',
        accent: 'bg-info-500',
        iconWrap: 'bg-info-100 text-info-700',
        border: 'border-info-200',
        hold: 9000,
        urgent: false,
        icon: (
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
    },
    error: {
        // Deliberately not "Error". The desk needs to know which step failed and that nothing of
        // theirs has been lost, not that the system classifies this as an error condition.
        label: 'That step did not go through',
        accent: 'bg-danger-500',
        iconWrap: 'bg-danger-100 text-danger-700',
        border: 'border-danger-200',
        hold: null,
        urgent: true,
        icon: (
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
    },
};

// What needs an answer reads at the top of the stack.
const urgency = { error: 0, warning: 1, info: 2, success: 3 };

const textOf = (raw) => (Array.isArray(raw) ? raw.join(', ') : String(raw ?? '')).trim();

function ToastCard({ item, onDismiss }) {
    const variant = variants[item.type];

    useEffect(() => {
        if (variant.hold === null) {
            return undefined;
        }

        const timer = setTimeout(() => onDismiss(item.id), variant.hold);

        return () => clearTimeout(timer);
    }, [item.id, variant.hold, onDismiss]);

    return (
        <div
            role={variant.urgent ? 'alert' : 'status'}
            aria-live={variant.urgent ? 'assertive' : 'polite'}
            className={`card animate-in flex items-start gap-3 p-4 border-l-4 shadow-lg ${variant.accent} ${variant.border}`}
        >
            <span className={`inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${variant.iconWrap}`}>
                {variant.icon}
            </span>
            <div className="min-w-0 flex-1 pt-0.5">
                <p className="font-heading text-sm font-semibold text-brand-900">{variant.label}</p>
                <p className="mt-0.5 text-sm leading-snug break-words text-brand-600">{item.message}</p>
            </div>
            <button
                type="button"
                onClick={() => onDismiss(item.id)}
                className="shrink-0 rounded-lg p-1 text-brand-400 transition-colors hover:bg-navy-100 hover:text-brand-600"
                aria-label={variant.urgent ? 'Dismiss this message' : 'Dismiss notification'}
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    );
}

export default function Toast({ max = 4 }) {
    const { flash, errors } = usePage().props;
    const [queue, setQueue] = useState([]);
    const seen = useRef(new Set());
    const nextId = useRef(0);

    useEffect(() => {
        if (!flash) {
            return;
        }

        const incoming = Object.keys(variants)
            .filter((type) => flash[type])
            .map((type) => ({ type, message: textOf(flash[type]) }))
            .filter((item) => item.message !== '' && !seen.current.has(`${item.type}|${item.message}`))
            .map((item) => ({ ...item, id: (nextId.current += 1) }));

        if (incoming.length === 0) {
            return;
        }

        incoming.forEach((item) => seen.current.add(`${item.type}|${item.message}`));
        setQueue((current) => [...incoming, ...current].slice(0, max));
    }, [flash, max]);

    // Ruled 2026-10-10: a rejected save arrives as page-level field errors, and the forms that post
    // with router.post directly — the upload picker, the camera capture, twenty-some other desk
    // actions — render none of them. Without this the desk's only answer is a button that stops
    // spinning. Forms that already list their own errors show both; the field is the precise place
    // to fix it and this is the notice that something happened at all.
    useEffect(() => {
        if (!errors || typeof errors !== 'object') {
            return;
        }

        const message = Object.values(errors).map(textOf).filter((text) => text !== '').join(' ');

        if (message === '' || seen.current.has(`error|${message}`)) {
            return;
        }

        seen.current.add(`error|${message}`);
        setQueue((current) => [{ type: 'error', message, id: (nextId.current += 1) }, ...current].slice(0, max));
    }, [errors, max]);

    const dismiss = useCallback((id) => setQueue((current) => current.filter((item) => item.id !== id)), []);

    if (queue.length === 0) {
        return null;
    }

    const ordered = [...queue].sort((a, b) => urgency[a.type] - urgency[b.type]);

    return (
        <div className="fixed top-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-3">
            {ordered.map((item) => (
                <ToastCard key={item.id} item={item} onDismiss={dismiss} />
            ))}
        </div>
    );
}
